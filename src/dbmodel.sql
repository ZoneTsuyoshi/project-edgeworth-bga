-- © だんじり協奏曲 (Danjiri Concerto)
-- https://x.com/danjiriconcerto
--
-- PROJECT EDGEWORTH のデータベーススキーマ（仕様書 010 §3）。
-- 動的なデータだけを持ち、静的な定義（コスト・名声・効果・区域の種類）は PHP の Material/ に置く。
-- コメントはコードと同じ行に書かない（BGA の規約）。

CREATE TABLE IF NOT EXISTS `project_card` (
  `card_id` SMALLINT UNSIGNED NOT NULL,
  `card_location` VARCHAR(12) NOT NULL,
  `card_location_arg` INT NOT NULL DEFAULT 0,
  `card_order` INT NOT NULL DEFAULT 0,
  `card_activation` TINYINT NOT NULL DEFAULT 0,
  `card_memo` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`card_id`),
  KEY `idx_card_loc` (`card_location`, `card_location_arg`, `card_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `satellite` (
  `sat_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sat_player` INT UNSIGNED NOT NULL,
  `sat_color` TINYINT UNSIGNED NOT NULL,
  `sat_shape` TINYINT UNSIGNED NOT NULL,
  `sat_plan` TINYINT UNSIGNED NOT NULL,
  `sat_card` VARCHAR(8) NOT NULL,
  `sat_cell` INT NULL DEFAULT NULL,
  `sat_dir` CHAR(2) NULL DEFAULT NULL,
  `sat_fuel_used` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `sat_grace` TINYINT(1) NOT NULL DEFAULT 0,
  `sat_activation` TINYINT NOT NULL DEFAULT 0,
  `sat_memo` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`sat_id`),
  UNIQUE KEY `uq_sat_owner_shape` (`sat_player`, `sat_shape`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `link` (
  `link_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `link_player` INT UNSIGNED NOT NULL,
  `link_cell` INT NOT NULL,
  `link_slot` TINYINT UNSIGNED NOT NULL,
  `link_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `link_commit` INT UNSIGNED NOT NULL,
  `link_round` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`link_id`),
  UNIQUE KEY `uq_link_owner_cell` (`link_player`, `link_cell`),
  UNIQUE KEY `uq_link_place` (`link_cell`, `link_slot`, `link_level`),
  KEY `idx_link_commit` (`link_commit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `staged_link` (
  `stage_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stage_player` INT UNSIGNED NOT NULL,
  `stage_cell` INT NOT NULL,
  `stage_sat` INT UNSIGNED NOT NULL,
  `stage_special` TINYINT(1) NOT NULL DEFAULT 0,
  `stage_source` VARCHAR(48) NOT NULL,
  `stage_area_cost` SMALLINT NOT NULL DEFAULT 0,
  `stage_special_cost` SMALLINT NOT NULL DEFAULT 0,
  `stage_pioneer` TEXT NULL DEFAULT NULL,
  `stage_reserved` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`stage_id`),
  KEY `idx_stage_player` (`stage_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `region` (
  `region_br` TINYINT UNSIGNED NOT NULL,
  `region_bc` TINYINT UNSIGNED NOT NULL,
  `region_board` CHAR(1) NOT NULL,
  `region_flip` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`region_br`, `region_bc`),
  UNIQUE KEY `uq_region_board` (`region_board`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `action_card` (
  `action_player` INT UNSIGNED NOT NULL,
  `action_no` TINYINT UNSIGNED NOT NULL,
  `action_state` TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`action_player`, `action_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mission_card` (
  `mission_no` TINYINT UNSIGNED NOT NULL,
  `mission_player` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`mission_no`),
  KEY `idx_mission_player` (`mission_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `choice` (
  `choice_player` INT UNSIGNED NOT NULL,
  `choice_state` SMALLINT UNSIGNED NOT NULL,
  `choice_data` TEXT NOT NULL,
  `choice_auto` TINYINT(1) NOT NULL DEFAULT 0,
  `choice_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`choice_player`, `choice_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pool` (
  `pool_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pool_player` INT UNSIGNED NOT NULL,
  `pool_window` VARCHAR(4) NOT NULL,
  `pool_kind` CHAR(1) NOT NULL,
  `pool_src` INT UNSIGNED NOT NULL,
  `pool_effect` VARCHAR(48) NOT NULL,
  `pool_event` VARCHAR(20) NOT NULL,
  `pool_context` TEXT NULL DEFAULT NULL,
  `pool_default_order` SMALLINT NOT NULL,
  `pool_chosen_order` SMALLINT NULL DEFAULT NULL,
  `pool_status` VARCHAR(8) NOT NULL DEFAULT 'pending',
  `pool_state` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`pool_id`),
  KEY `idx_pool_player` (`pool_player`, `pool_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `held_notif` (
  `held_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `held_origin` INT UNSIGNED NOT NULL,
  `held_type` VARCHAR(32) NOT NULL,
  `held_variant` VARCHAR(16) NOT NULL DEFAULT '',
  `held_args` TEXT NOT NULL,
  PRIMARY KEY (`held_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

ALTER TABLE `player` ADD `maya` INT NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `budget` INT NOT NULL DEFAULT 15;
ALTER TABLE `player` ADD `pad_cell` INT NULL DEFAULT NULL;
ALTER TABLE `player` ADD `fame_detail` TEXT NULL DEFAULT NULL;
