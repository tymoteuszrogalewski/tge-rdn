<?php
/**
 * tge.inc.php — biblioteka: pobranie notowan Rynku Dnia Nastepnego (RDN) z tge.pl
 * (Fixing I, notowania ciagle, Fixing II, lacznie) dla produktow godzinowych i 15-minutowych,
 * zapis do MySQL/MariaDB lub CSV.
 */

$cfg = __DIR__ . '/config.php';
if (!file_exists($cfg)) {
    fwrite(STDERR, "Brak config.php — skopiuj config.example.php do config.php i uzupelnij.\n");
    exit(1);
}
require $cfg;

const TGE_URL = 'https://tge.pl/energia-elektryczna-rdn';
const TGE_TZ  = 'Europe/Warsaw';

// Kolumny tabeli TGE w kolejnosci, w jakiej sa na stronie (sekcje oznaczone komentarzami HTML)
const TGE_COLS = [
    'Fixing I'   => ['fix1_price', 'fix1_volume'],
    'Continuous' => ['cont_price', 'cont_volume'],
    'Fixing II'  => ['fix2_price_eur', 'fix2_price', 'fix2_volume', 'fix2_volume_buy', 'fix2_volume_sell'],
    'SUMA'       => ['total_min', 'total_max', 'total_avg', 'total_volume', 'total_volume_buy', 'total_volume_sell'],
];

function tge_http($url) {
    for ($try = 1; $try <= 3; $try++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'tge-rdn/1.0',  // TGE odrzuca polaczenia z "przegladarkowym" User-Agentem bez przegladarki
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200 && $html) return $html;
        fwrite(STDERR, "TGE: HTTP $code (proba $try/3)\n");
        if ($try < 3) sleep(5);
    }
    return false;
}

// "-1 300,50" -> -1300.5 ; "-" / puste -> null. TGE: separator tysiecy = spacja, dziesietny = przecinek.
function tge_num($cell) {
    $v = trim(preg_replace('/<!--.*?-->/s', '', strip_tags($cell, '<!---->')));
    $v = str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], $v);
    return is_numeric($v) ? (float)$v : null;
}

/**
 * Pobiera notowania dla dnia DOSTAWY (YYYY-MM-DD). Sesja na TGE odbywa sie dzien wczesniej.
 * $period: 60 = produkty godzinowe, 15 = 15-minutowe.
 * Zwraca [ts_utc => wiersz] (ts = poczatek okresu), [] gdy brak danych, false przy bledzie.
 */
function tge_fetch($delivery, $period = 60) {
    $tz      = new DateTimeZone(TGE_TZ);
    $session = (new DateTime($delivery, $tz))->modify('-1 day')->format('d-m-Y');
    $html    = tge_http(TGE_URL . '?dateShow=' . $session . '&type=' . ($period == 15 ? 2 : 1));
    if ($html === false) return false;

    $marker = $period == 15 ? '_Q\d{2}:\d{2}' : '_H\d{2}';
    preg_match_all('/<tr[^>]*>\s*<td[^>]*>\s*' . preg_quote($delivery, '/') . $marker . '.*?<\/tr>/s', $html, $m);

    // Kolejne wiersze = kolejne okresy od polnocy czasu polskiego. Liczenie od polnocy w UTC
    // daje poprawne godziny takze w dni zmiany czasu (23 lub 25 godzin).
    $t0   = (new DateTime($delivery, $tz))->getTimestamp();
    $rows = [];
    foreach ($m[0] as $i => $tr) {
        $row = [];
        foreach (TGE_COLS as $section => $cols) {
            preg_match('/' . $section . ' start -->(.*?)<!-- ' . $section . ' end/s', $tr, $sm);
            preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $sm[1] ?? '', $tds);
            foreach ($cols as $k => $col) $row[$col] = isset($tds[1][$k]) ? tge_num($tds[1][$k]) : null;
        }
        $rows[gmdate('Y-m-d H:i:s', $t0 + $i * $period * 60)] = $row;
    }
    return $rows;
}

function tge_db() {
    if (DB_NAME === '') return null;  // tryb CSV
    try {
        return new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    } catch (mysqli_sql_exception $e) {
        fwrite(STDERR, "DB: " . $e->getMessage() . "\n");
        exit(1);
    }
}

// Zapis do tabeli tge_rdn albo wypis CSV na stdout, gdy brak bazy.
function tge_save($db, $rows, $period) {
    $tz = new DateTimeZone(TGE_TZ);
    foreach ($rows as $ts => $r) {
        $local = (new DateTime($ts, new DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d H:i:s');
        if (!$db) {
            echo "$ts;$local;$period;" . implode(';', array_map(fn($v) => $v ?? '', $r)) . "\n";
            continue;
        }
        $cols = ['ts_utc' => "'$ts'", 'period' => (int)$period, 'ts_local' => "'$local'"];
        foreach ($r as $k => $v) $cols[$k] = $v === null ? 'NULL' : (float)$v;
        $upd = [];
        foreach (array_keys($cols) as $k) if ($k !== 'ts_utc' && $k !== 'period') $upd[] = "`$k` = VALUES(`$k`)";
        $db->query('INSERT INTO tge_rdn (`' . implode('`, `', array_keys($cols)) . '`) VALUES (' . implode(', ', $cols) . ')
                    ON DUPLICATE KEY UPDATE ' . implode(', ', $upd));
    }
}

// Pobiera i zapisuje jeden dzien dostawy dla wszystkich okresow z config.php. Zwraca liczbe wierszy albo false.
function tge_day($db, $delivery) {
    $n = 0;
    foreach (TGE_PERIODS as $period) {
        $rows = tge_fetch($delivery, $period);
        if ($rows === false) return false;
        tge_save($db, $rows, $period);
        $n += count($rows);
        sleep(TGE_PAUSE);
    }
    return $n;
}
