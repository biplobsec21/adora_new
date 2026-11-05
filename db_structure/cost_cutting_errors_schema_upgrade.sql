-- Store rejected Cost Cutting CSV rows separately from processable payment items.
-- Safe to run repeatedly.

CREATE TABLE IF NOT EXISTS `db_cost_cutting_batch_errors` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `batch_id` int(50) NOT NULL,
  `source_row_number` int(10) NOT NULL,
  `customer_code` varchar(50) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `cutting_amount` decimal(20,2) DEFAULT NULL,
  `error_type` varchar(50) NOT NULL,
  `error_message` varchar(500) NOT NULL,
  `blocks_processing` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cost_cutting_batch_error_row` (`batch_id`,`source_row_number`),
  KEY `idx_cost_cutting_batch_error_blocking` (`batch_id`,`blocks_processing`),
  CONSTRAINT `fk_cost_cutting_batch_error_batch`
    FOREIGN KEY (`batch_id`) REFERENCES `db_cost_cutting_batches` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `db_cost_cutting_batch_errors`
  (`batch_id`, `source_row_number`, `customer_code`, `customer_name`, `cutting_amount`, `error_type`, `error_message`, `blocks_processing`, `created_at`)
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
);
