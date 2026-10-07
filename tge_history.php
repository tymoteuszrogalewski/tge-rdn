#!/usr/bin/env php
<?php
/**
 * tge_history.php — pobiera notowania dzien po dniu (daty DOSTAWY).
 * Uzycie: php tge_history.php OD [DO]       np. php tge_history.php 2026-08-15
 * Strona TGE pokazuje tylko ok. 2 ostatnie miesiace — starsze dni zwracaja 0 wierszy.
 */

require __DIR__ . '/tge.inc.php';

if (!isset($argv[1])) {
    fwrite(STDERR, "Uzycie: php tge_history.php OD [DO]   (daty YYYY-MM-DD)\n");
    exit(1);
}

$tz   = new DateTimeZone(TGE_TZ);
$from = new DateTime($argv[1], $tz);
$to   = new DateTime($argv[2] ?? 'tomorrow', $tz);

$db    = tge_db();
$total = 0;
for ($d = clone $from; $d <= $to; $d->modify('+1 day')) {
    $n = tge_day($db, $d->format('Y-m-d'));
    if ($n === false) exit(1);
    $total += $n;
    if ($db) echo $d->format('Y-m-d') . ": $n wierszy\n";
}
if ($db) echo "Gotowe: $total wierszy\n";
