<?php
// Skopiuj do config.php i uzupelnij. config.php jest w .gitignore.

define('TGE_PERIODS', [60, 15]);   // 60 = produkty godzinowe, 15 = 15-minutowe; mozna zostawic jeden
define('TGE_PAUSE',   2);          // przerwa w sekundach miedzy zapytaniami do tge.pl

// Baza MySQL / MariaDB. DB_NAME = '' -> zamiast zapisu do bazy wypisuje CSV na ekran.
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', 'tge');
define('DB_PASS', 'password');
define('DB_NAME', 'tge');
