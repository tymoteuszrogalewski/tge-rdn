#!/bin/bash
# Pobiera notowania na dzis i jutro. Do crona, np. co godzine 8-16:  20 8-16 * * *  /sciezka/tge-rdn/go.sh
php "$(dirname "$0")/tge_day.php" "$@"
