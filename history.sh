#!/bin/bash
# Pobiera notowania dzien po dniu.  Np.:  ./history.sh 2026-08-15   albo   ./history.sh 2026-08-15 2026-08-31
php "$(dirname "$0")/tge_history.php" "$@"
