-- Store rejected Cost Cutting CSV rows separately from processable payment items.
-- Safe to run repeatedly.

CREATE TABLE IF NOT EXISTS `db_cost_cutting_batch_errors` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `batch_id` int(50) NOT NULL,
  `source_row_number` int(10) NOT NULL,
  `customer_number` varchar(50) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `cutting_amount` decimal(20,2) DEFAULT NULL,
  `error_type` varchar(50) NOT NULL,
  `error_message` varchar(500) NOT NULL,
  `blocks_processing` tinyint(1) NOT NULL DEFAULT 1,
  `due_at_upload` decimal(20,2) DEFAULT NULL,
  `resolved_customer_id` int(50) DEFAULT NULL,
  `resolved_customer_number` varchar(50) DEFAULT NULL,
  `applied_amount` decimal(20,2) DEFAULT NULL,
  `payment_item_id` int(50) DEFAULT NULL,
  `resolved_by` varchar(50) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cost_cutting_batch_error_row` (`batch_id`,`source_row_number`),
  KEY `idx_cost_cutting_batch_error_blocking` (`batch_id`,`blocks_processing`),
  KEY `idx_cost_cutting_batch_error_customer` (`resolved_customer_id`),
  CONSTRAINT `fk_cost_cutting_batch_error_batch`
    FOREIGN KEY (`batch_id`) REFERENCES `db_cost_cutting_batches` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @error_customer_number_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'customer_number'
);
SET @error_customer_code_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'customer_code'
);
SET @sql := IF(@error_customer_number_exists = 0 AND @error_customer_code_exists = 1,
  'ALTER TABLE `db_cost_cutting_batch_errors` CHANGE COLUMN `customer_code` `customer_number` varchar(50) DEFAULT NULL',
  IF(@error_customer_number_exists = 0,
    'ALTER TABLE `db_cost_cutting_batch_errors` ADD COLUMN `customer_number` varchar(50) DEFAULT NULL AFTER `source_row_number`',
    'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @error_customer_number_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'customer_number'
);
SET @error_customer_code_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'customer_code'
);
SET @sql := IF(@error_customer_number_exists = 1 AND @error_customer_code_exists = 1,
  'UPDATE `db_cost_cutting_batch_errors` SET `customer_number` = COALESCE(`customer_number`, `customer_code`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @error_customer_code_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'customer_code'
);
SET @sql := IF(@error_customer_code_exists = 1,
  'ALTER TABLE `db_cost_cutting_batch_errors` DROP COLUMN `customer_code`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @resolved_customer_id_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'resolved_customer_id'
);
SET @sql := IF(@resolved_customer_id_exists = 0,
  'ALTER TABLE `db_cost_cutting_batch_errors` ADD COLUMN `resolved_customer_id` int(50) DEFAULT NULL AFTER `blocks_processing`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @due_at_upload_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'due_at_upload'
);
SET @sql := IF(@due_at_upload_exists = 0,
  'ALTER TABLE `db_cost_cutting_batch_errors` ADD COLUMN `due_at_upload` decimal(20,2) DEFAULT NULL AFTER `blocks_processing`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @resolved_customer_number_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'resolved_customer_number'
);
SET @sql := IF(@resolved_customer_number_exists = 0,
  'ALTER TABLE `db_cost_cutting_batch_errors` ADD COLUMN `resolved_customer_number` varchar(50) DEFAULT NULL AFTER `resolved_customer_id`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @resolved_customer_name_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'resolved_customer_name'
);
SET @sql := IF(@resolved_customer_name_exists = 0,
  'ALTER TABLE `db_cost_cutting_batch_errors` ADD COLUMN `resolved_customer_name` varchar(255) DEFAULT NULL AFTER `resolved_customer_number`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @applied_amount_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'applied_amount'
);
SET @sql := IF(@applied_amount_exists = 0,
  'ALTER TABLE `db_cost_cutting_batch_errors` ADD COLUMN `applied_amount` decimal(20,2) DEFAULT NULL AFTER `resolved_customer_number`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @payment_item_id_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'payment_item_id'
);
SET @sql := IF(@payment_item_id_exists = 0,
  'ALTER TABLE `db_cost_cutting_batch_errors` ADD COLUMN `payment_item_id` int(50) DEFAULT NULL AFTER `applied_amount`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @resolved_by_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'resolved_by'
);
SET @sql := IF(@resolved_by_exists = 0,
  'ALTER TABLE `db_cost_cutting_batch_errors` ADD COLUMN `resolved_by` varchar(50) DEFAULT NULL AFTER `payment_item_id`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @resolved_at_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND column_name = 'resolved_at'
);
SET @sql := IF(@resolved_at_exists = 0,
  'ALTER TABLE `db_cost_cutting_batch_errors` ADD COLUMN `resolved_at` datetime DEFAULT NULL AFTER `resolved_by`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @resolved_customer_index_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'db_cost_cutting_batch_errors' AND index_name = 'idx_cost_cutting_batch_error_customer'
);
SET @sql := IF(@resolved_customer_index_exists = 0,
  'ALTER TABLE `db_cost_cutting_batch_errors` ADD KEY `idx_cost_cutting_batch_error_customer` (`resolved_customer_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `db_cost_cutting_batch_errors`
  (`batch_id`, `source_row_number`, `customer_number`, `customer_name`, `cutting_amount`, `error_type`, `error_message`, `blocks_processing`, `created_at`)
SELECT
  `batch_id`,
  `source_row_number`,
  `customer_code`,
  `customer_name`,
  `cutting_amount`,
  CASE
    WHEN `validation_error` LIKE '%customer name did not match exactly one active customer%' THEN 'Unmatched customer name'
    WHEN `validation_error` LIKE '%No active customer matched this name%' THEN 'Unmatched customer name'
    WHEN `validation_error` LIKE '%Customer name matched more than one active customer%' THEN 'Ambiguous customer name'
    ELSE 'Validation error'
  END,
  COALESCE(NULLIF(`validation_error`, ''), 'Invalid Cost Cutting row'),
  CASE
    WHEN `validation_error` LIKE '%customer name did not match exactly one active customer%' THEN 0
    WHEN `validation_error` LIKE '%No active customer matched this name%' THEN 0
    WHEN `validation_error` LIKE '%Customer name matched more than one active customer%' THEN 0
    ELSE 1
  END,
  `created_at`
FROM `db_cost_cutting_items`
WHERE `validation_status` = 'Invalid';

DELETE `items`
FROM `db_cost_cutting_items` AS `items`
INNER JOIN `db_cost_cutting_batch_errors` AS `errors`
  ON `errors`.`batch_id` = `items`.`batch_id`
  AND `errors`.`source_row_number` = `items`.`source_row_number`
WHERE `items`.`validation_status` = 'Invalid';

UPDATE `db_cost_cutting_batches` AS `batches`
SET `batches`.`error_records` = (
  SELECT COUNT(*)
  FROM `db_cost_cutting_batch_errors` AS `errors`
  WHERE `errors`.`batch_id` = `batches`.`id`
    AND `errors`.`resolved_at` IS NULL
);
