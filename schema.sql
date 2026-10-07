-- TGE Rynek Dnia Nastepnego — notowania godzinowe i 15-minutowe.
-- Uzycie: mysql tge < schema.sql
-- Kazdy wiersz = jeden okres dostawy; ts_* = POCZATEK okresu (np. 14:00 + period 60 = 14:00-15:00).
-- Ceny w PLN/MWh (jak na tge.pl), wolumeny w MW / MWh. Brak notowania = NULL.

CREATE TABLE IF NOT EXISTS `tge_rdn` (
  `ts_utc`            datetime NOT NULL,
  `period`            smallint NOT NULL,           -- 60 = produkt godzinowy, 15 = 15-minutowy
  `ts_local`          datetime NOT NULL,
  `fix1_price`        decimal(10,2) DEFAULT NULL,  -- Fixing I: kurs PLN/MWh
  `fix1_volume`       decimal(12,3) DEFAULT NULL,  -- Fixing I: wolumen MW
  `cont_price`        decimal(10,2) DEFAULT NULL,  -- notowania ciagle: kurs sredniowazony PLN/MWh
  `cont_volume`       decimal(12,3) DEFAULT NULL,  -- notowania ciagle: wolumen MW
  `fix2_price_eur`    decimal(10,2) DEFAULT NULL,  -- Fixing II: kurs jednolity EUR/MWh
  `fix2_price`        decimal(10,2) DEFAULT NULL,  -- Fixing II: kurs jednolity PLN/MWh
  `fix2_volume`       decimal(12,3) DEFAULT NULL,  -- Fixing II: wolumen MW
  `fix2_volume_buy`   decimal(12,3) DEFAULT NULL,
  `fix2_volume_sell`  decimal(12,3) DEFAULT NULL,
  `total_min`         decimal(10,2) DEFAULT NULL,  -- lacznie: kurs minimalny PLN/MWh
  `total_max`         decimal(10,2) DEFAULT NULL,  -- lacznie: kurs maksymalny PLN/MWh
  `total_avg`         decimal(10,2) DEFAULT NULL,  -- lacznie: kurs sredniowazony PLN/MWh
  `total_volume`      decimal(12,3) DEFAULT NULL,  -- lacznie: wolumen MWh
  `total_volume_buy`  decimal(12,3) DEFAULT NULL,
  `total_volume_sell` decimal(12,3) DEFAULT NULL,
  PRIMARY KEY (`ts_utc`, `period`),
  KEY `idx_local` (`ts_local`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Przyklady:
-- ceny godzinowe na jutro w zl/kWh:
--   SELECT ts_local, fix1_price / 1000 AS zl_kwh FROM tge_rdn WHERE period = 60 AND DATE(ts_local) = CURDATE() + INTERVAL 1 DAY ORDER BY ts_local;
-- 3 najtansze godziny jutro:
--   SELECT ts_local, fix1_price FROM tge_rdn WHERE period = 60 AND DATE(ts_local) = CURDATE() + INTERVAL 1 DAY ORDER BY fix1_price LIMIT 3;
