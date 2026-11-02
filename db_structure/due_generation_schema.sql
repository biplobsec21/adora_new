-- Canteen DueFlow generation and Back Office reconciliation.
-- Run after the base Pioneer schema and cost_cutting_schema.sql.

CREATE TABLE IF NOT EXISTS `db_due_generation_settings` (
  `id` tinyint(3) NOT NULL,
  `cost_cutting_day` tinyint(2) NOT NULL DEFAULT 25,
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `db_due_generation_settings` (`id`, `cost_cutting_day`)
VALUES (1, 25)
ON DUPLICATE KEY UPDATE `id` = `id`;

CREATE TABLE IF NOT EXISTS `db_due_generations` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `generation_number` varchar(40) NOT NULL,
  `due_cycle_date` date NOT NULL,
  `generation_type` enum('Opening Balance','Monthly Cycle') NOT NULL DEFAULT 'Monthly Cycle',
  `cost_cutting_day` tinyint(2) NOT NULL,
  `billing_cycle_start` datetime DEFAULT NULL,
  `billing_cycle_end` datetime DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `result_file_name` varchar(255) DEFAULT NULL,
  `result_file_path` varchar(500) DEFAULT NULL,
  `total_records` int(10) NOT NULL DEFAULT 0,
  `total_new_due` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_previous_outstanding` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_due_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_cut_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_remaining_due` decimal(20,2) NOT NULL DEFAULT 0.00,
  `status` enum('Generated','Sent to Back Office','Reconciled') NOT NULL DEFAULT 'Generated',
  `sms_status` enum('Not Sent','Sending','Sent','Partial','Failed') NOT NULL DEFAULT 'Not Sent',
  `sms_sent_at` datetime DEFAULT NULL,
  `generated_by` varchar(50) NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reconciled_by` varchar(50) DEFAULT NULL,
  `reconciled_at` datetime DEFAULT NULL,
  `system_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_due_generation_number` (`generation_number`),
  UNIQUE KEY `uq_due_generation_cycle` (`due_cycle_date`),
  KEY `idx_due_generation_status` (`status`),
  KEY `idx_due_generation_cycle` (`due_cycle_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration for installations where the DueFlow tables already exist.
ALTER TABLE `db_due_generations`
  ADD COLUMN IF NOT EXISTS `generation_type` enum('Opening Balance','Monthly Cycle') NOT NULL DEFAULT 'Monthly Cycle' AFTER `due_cycle_date`;
ALTER TABLE `db_due_generations`
  MODIFY `billing_cycle_start` datetime DEFAULT NULL,
  MODIFY `billing_cycle_end` datetime DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `db_due_generation_items` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `generation_id` int(50) NOT NULL,
  `customer_id` int(50) NOT NULL,
  `customer_number` varchar(50) NOT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `previous_outstanding_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `loan_due_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `new_due_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_due_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `actually_cut_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `remaining_due_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `status` enum('Generated','Fully Cut','Unpaid','Carried Forward') NOT NULL DEFAULT 'Generated',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_due_generation_customer` (`generation_id`,`customer_id`),
  KEY `idx_due_generation_item_customer` (`customer_id`),
  CONSTRAINT `fk_due_generation_item_generation`
    FOREIGN KEY (`generation_id`) REFERENCES `db_due_generations` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `db_due_generation_refunds` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `customer_id` int(50) NOT NULL,
  `generation_id` int(50) NOT NULL,
  `generation_item_id` int(50) NOT NULL,
  `cost_cutting_batch_id` int(50) NOT NULL,
  `cost_cutting_item_id` int(50) NOT NULL,
  `refund_amount` decimal(20,2) NOT NULL,
  `status` enum('Pending','Paid') NOT NULL DEFAULT 'Pending',
  `generated_by` varchar(50) NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `paid_by` varchar(50) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `system_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_due_generation_refund_cost_cutting_item` (`cost_cutting_item_id`),
  KEY `idx_due_generation_refund_customer` (`customer_id`),
  KEY `idx_due_generation_refund_status` (`status`),
  KEY `idx_due_generation_refund_generation` (`generation_id`),
  CONSTRAINT `fk_due_generation_refund_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `db_customers` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_due_generation_refund_generation`
    FOREIGN KEY (`generation_id`) REFERENCES `db_due_generations` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_due_generation_refund_item`
    FOREIGN KEY (`generation_item_id`) REFERENCES `db_due_generation_items` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_due_generation_refund_cost_cutting_batch`
    FOREIGN KEY (`cost_cutting_batch_id`) REFERENCES `db_cost_cutting_batches` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_due_generation_refund_cost_cutting_item`
    FOREIGN KEY (`cost_cutting_item_id`) REFERENCES `db_cost_cutting_items` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;