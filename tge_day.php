#!/usr/bin/env php
<?php
/**
 * tge_day.php — pobiera notowania na dzis i jutro (albo podany dzien DOSTAWY).
 * Uzycie: php tge_day.php [YYYY-MM-DD]
 * Cron:   20 8-16 * * *  /sciezka/go.sh
 */

require __DIR__ . '/tge.inc.php';

$tz   = new DateTimeZone(TGE_TZ);
$days = isset($argv[1])
      ? [$argv[1]]
      : [(new DateTime('today', $tz))->format('Y-m-d'), (new DateTime('tomorrow', $tz))->format('Y-m-d')];

$db   = tge_db();
$fail = 0;
foreach ($days as $day) {
    $n = tge_day($db, $day);
    if ($n === false) { $fail++; continue; }
    if ($db) echo "$day: $n wierszy\n";
}
exit($fail ? 1 : 0);
