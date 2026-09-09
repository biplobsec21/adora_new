-- Canteen Cost Cutting schema for the pioneer application.
-- Run this after the base database schema has been created.
-- payment_period stores the first day of the selected month.

CREATE TABLE IF NOT EXISTS `db_cost_cutting_batches` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `batch_number` varchar(32) NOT NULL,
  `payment_period` date NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `total_records` int(10) NOT NULL DEFAULT 0,
  `valid_records` int(10) NOT NULL DEFAULT 0,
  `error_records` int(10) NOT NULL DEFAULT 0,
  `total_due_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_cutting_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `paid_count` int(10) NOT NULL DEFAULT 0,
  `partial_count` int(10) NOT NULL DEFAULT 0,
  `unpaid_count` int(10) NOT NULL DEFAULT 0,
  `status` enum('Uploaded','Reviewed','Completed','Reverted','Failed') NOT NULL DEFAULT 'Uploaded',
  `uploaded_by` varchar(50) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_by` varchar(50) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `reverted_by` varchar(50) DEFAULT NULL,
  `reverted_at` datetime DEFAULT NULL,
  `system_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cost_cutting_batch_number` (`batch_number`),
  KEY `idx_cost_cutting_period` (`payment_period`),
  KEY `idx_cost_cutting_status` (`status`),
  KEY `idx_cost_cutting_uploaded_at` (`uploaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `db_cost_cutting_items` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `batch_id` int(50) NOT NULL,
  `source_row_number` int(10) NOT NULL,
  `customer_id` int(50) DEFAULT NULL,
  `customer_code` varchar(20) DEFAULT NULL,
  `customer_name` varchar(50) DEFAULT NULL,
  `due_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `cutting_amount` decimal(20,2) DEFAULT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `previous_paid_amount` decimal(20,2) DEFAULT NULL,
  `new_status` enum('Paid','Partial Paid','Unpaid') DEFAULT NULL,
  `new_paid_amount` decimal(20,2) DEFAULT NULL,
  `validation_status` enum('Valid','Invalid') NOT NULL DEFAULT 'Valid',
  `validation_error` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cost_cutting_batch_customer` (`batch_id`,`customer_code`),
  KEY `idx_cost_cutting_item_customer` (`customer_id`),
  KEY `idx_cost_cutting_item_status` (`validation_status`),
  CONSTRAINT `fk_cost_cutting_item_batch`
    FOREIGN KEY (`batch_id`) REFERENCES `db_cost_cutting_batches` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
