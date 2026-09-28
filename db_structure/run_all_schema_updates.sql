-- Combined schema setup and upgrade script for the application database.
-- Select the target database before running this file.
-- Includes every SQL file in db_structure except ramk_pioneer.sql.

-- Source: cost_cutting_schema.sql
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

-- Source: customer_number_migration.sql
-- Customer number migration support.
-- This is idempotent for databases where the column was already added manually.

SET @customer_number_column_exists := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'db_customers'
    AND column_name = 'customer_number'
);
SET @customer_number_column_sql := IF(
  @customer_number_column_exists = 0,
  'ALTER TABLE `db_customers` ADD COLUMN `customer_number` varchar(50) NOT NULL DEFAULT ''N/A'' AFTER `customer_code`',
  'SELECT 1'
);
PREPARE customer_number_column_stmt FROM @customer_number_column_sql;
EXECUTE customer_number_column_stmt;
DEALLOCATE PREPARE customer_number_column_stmt;

SET @customer_number_index_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'db_customers'
    AND index_name = 'idx_customers_customer_number'
);
SET @customer_number_index_sql := IF(
  @customer_number_index_exists = 0,
  'ALTER TABLE `db_customers` ADD KEY `idx_customers_customer_number` (`customer_number`)',
  'SELECT 1'
);
PREPARE customer_number_index_stmt FROM @customer_number_index_sql;
EXECUTE customer_number_index_stmt;
DEALLOCATE PREPARE customer_number_index_stmt;

-- Source: due_generation_schema.sql
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

-- Source: due_generation_schema_upgrade.sql
-- Upgrade existing DueFlow installations to store the loan component per customer.

ALTER TABLE `db_due_generation_items`
  ADD COLUMN IF NOT EXISTS `loan_due_amount` decimal(20,2) NOT NULL DEFAULT 0.00 AFTER `previous_outstanding_amount`;

-- Source: eod_schema.sql
-- End of Day closing schema for the pioneer application.
-- Run this against the database selected by DB_DATABASE.
-- This project uses a single company database, so no company_id is required.

CREATE TABLE IF NOT EXISTS `db_daily_closing` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `closing_date` date NOT NULL,
  `total_sales_due` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_cash_collected` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_purchase_paid` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_loan_given` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_loan_repayments_received` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_expenses` decimal(20,2) NOT NULL DEFAULT 0.00,
  `custom_cash_additions` decimal(20,2) NOT NULL DEFAULT 0.00,
  `custom_cash_deductions` decimal(20,2) NOT NULL DEFAULT 0.00,
  `final_cash_in_hand` decimal(20,2) NOT NULL DEFAULT 0.00,
  `late_entry_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `closing_type` enum('Regular','Retroactive') NOT NULL DEFAULT 'Regular',
  `created_by` varchar(50) NOT NULL,
  `system_ip` varchar(45) NOT NULL,
  `created_date` date DEFAULT NULL,
  `created_time` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_daily_closing_date` (`closing_date`),
  KEY `idx_daily_closing_date` (`closing_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `db_cash_adjustments` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `closing_date` date NOT NULL,
  `type` enum('Addition','Deduction') NOT NULL,
  `amount` decimal(20,2) NOT NULL,
  `note` text DEFAULT NULL,
  `created_by` varchar(50) NOT NULL,
  `system_ip` varchar(45) NOT NULL,
  `created_date` date DEFAULT NULL,
  `created_time` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_cash_adjustments_amount_positive` CHECK (`amount` > 0),
  KEY `idx_cash_adjustments_date` (`closing_date`),
  KEY `idx_cash_adjustments_date_type` (`closing_date`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `db_audit_logs` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(64) NOT NULL,
  `record_id` int(50) DEFAULT NULL,
  `affected_date` date DEFAULT NULL,
  `action` enum('INSERT','UPDATE','DELETE','LATE_ENTRY') NOT NULL,
  `old_value` longtext NOT NULL,
  `new_value` longtext DEFAULT NULL,
  `changed_by` varchar(50) NOT NULL,
  `system_ip` varchar(45) NOT NULL,
  `created_date` date DEFAULT NULL,
  `created_time` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_affected_date` (`affected_date`),
  KEY `idx_audit_table_record` (`table_name`,`record_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Source: eod_schema_upgrade.sql
-- Upgrade existing EOD installations to support late-entry audit tracking and cash-flow categories.
-- These changes are conditional so this script also runs after eod_schema.sql.
-- ALTER TABLE `db_audit_logs`
--   MODIFY `action` enum('INSERT','UPDATE','DELETE','LATE_ENTRY') NOT NULL;
-- ALTER TABLE `db_daily_closing`
--   ADD COLUMN `late_entry_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `final_cash_in_hand`;

ALTER TABLE `db_daily_closing`
  ADD COLUMN IF NOT EXISTS `total_purchase_paid` DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `total_cash_collected`,
  ADD COLUMN IF NOT EXISTS `total_loan_given` DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `total_purchase_paid`,
  ADD COLUMN IF NOT EXISTS `total_loan_repayments_received` DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `total_loan_given`;

ALTER TABLE `db_daily_closing`
  ADD COLUMN IF NOT EXISTS `created_date` DATE NULL AFTER `system_ip`,
  ADD COLUMN IF NOT EXISTS `created_time` VARCHAR(50) NULL AFTER `created_date`;

ALTER TABLE `db_cash_adjustments`
  ADD COLUMN IF NOT EXISTS `created_date` DATE NULL AFTER `system_ip`,
  ADD COLUMN IF NOT EXISTS `created_time` VARCHAR(50) NULL AFTER `created_date`;

ALTER TABLE `db_audit_logs`
  ADD COLUMN IF NOT EXISTS `created_date` DATE NULL AFTER `system_ip`,
  ADD COLUMN IF NOT EXISTS `created_time` VARCHAR(50) NULL AFTER `created_date`;

-- Source: customer_loan_management_upgrade.sql
-- Upgrade an existing installation for customer loan audit history.
-- Run this once against the active application database.

ALTER TABLE `db_audit_logs`
  ADD COLUMN IF NOT EXISTS `new_value` longtext DEFAULT NULL AFTER `old_value`;

-- Source: sms_schema.sql
-- SMS provider, templates, secure ledger links, and send audit logs.
-- Run after the base Pioneer schema.

CREATE TABLE IF NOT EXISTS `db_sms_settings` (
  `id` tinyint(3) NOT NULL,
  `provider_url` varchar(255) NOT NULL DEFAULT 'http://bulksmsbd.net/api/smsapi',
  `many_provider_url` varchar(255) NOT NULL DEFAULT 'http://bulksmsbd.net/api/smsapimany',
  `balance_url` varchar(255) NOT NULL DEFAULT 'http://bulksmsbd.net/api/getBalanceApi',
  `api_key` varchar(255) NOT NULL DEFAULT '',
  `sender_id` varchar(100) NOT NULL DEFAULT '',
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `sales_invoice_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `due_generation_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `cost_cutting_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `db_sms_settings` (`id`) VALUES (1)
ON DUPLICATE KEY UPDATE `id` = `id`;

CREATE TABLE IF NOT EXISTS `db_sms_templates` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `event_key` enum('sales_invoice','due_generation','cost_cutting') NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `message_body` text NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sms_template_event` (`event_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `db_sms_templates` (`event_key`, `template_name`, `message_body`)
VALUES
  ('sales_invoice', 'Sales Invoice', 'Hello {customer_name}, your invoice {sales_code} for {sales_amount} has been created. Paid: {paid_amount}. Invoice due: {invoice_due}. Thank you, {site_name}.'),
  ('due_generation', 'Customer Due Generation', 'Your {due_month} due is {total_due}. Ledger: {ledger_url}'),
  ('cost_cutting', 'Cost Cutting Processed', 'Your {due_month} payment of {cut_amount} was processed. Remaining due: {remaining_due}.')
ON DUPLICATE KEY UPDATE `event_key` = `event_key`;

-- Update only the original defaults; preserve templates customized by an administrator.
UPDATE `db_sms_templates` SET `message_body` = 'Your {due_month} due is {total_due}. Ledger: {ledger_url}' WHERE `event_key` = 'due_generation' AND `message_body` = 'Dear {customer_name}, your current due is {total_due}. View your ledger: {ledger_url}';
UPDATE `db_sms_templates` SET `message_body` = 'Your {due_month} payment of {cut_amount} was processed. Remaining due: {remaining_due}.' WHERE `event_key` = 'cost_cutting' AND `message_body` = 'Dear {customer_name}, your cost cutting payment of {cut_amount} was processed. Remaining due: {remaining_due}. View your ledger: {ledger_url}';

CREATE TABLE IF NOT EXISTS `db_sms_ledger_links` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `customer_id` int(50) NOT NULL,
  `token_value` char(64) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sms_ledger_value` (`token_value`),
  UNIQUE KEY `uq_sms_ledger_token` (`token_hash`),
  UNIQUE KEY `uq_sms_ledger_customer` (`customer_id`),
  KEY `idx_sms_ledger_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration for installations where the SMS tables already exist.
ALTER TABLE `db_sms_settings`
  ADD COLUMN IF NOT EXISTS `many_provider_url` varchar(255) NOT NULL DEFAULT 'http://bulksmsbd.net/api/smsapimany' AFTER `provider_url`;
ALTER TABLE `db_sms_ledger_links`
  ADD COLUMN IF NOT EXISTS `token_value` char(64) NOT NULL DEFAULT '' AFTER `customer_id`;
ALTER TABLE `db_due_generations`
  ADD COLUMN IF NOT EXISTS `sms_status` enum('Not Sent','Sending','Sent','Partial','Failed') NOT NULL DEFAULT 'Not Sent' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `sms_sent_at` datetime DEFAULT NULL AFTER `sms_status`;

CREATE TABLE IF NOT EXISTS `db_sms_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `customer_id` int(50) DEFAULT NULL,
  `event_key` varchar(40) NOT NULL,
  `template_id` int(50) DEFAULT NULL,
  `generation_id` int(50) DEFAULT NULL,
  `cost_cutting_batch_id` int(50) DEFAULT NULL,
  `recipient` varchar(30) DEFAULT NULL,
  `message_body` text NOT NULL,
  `provider_code` varchar(20) DEFAULT NULL,
  `provider_response` text DEFAULT NULL,
  `status` enum('Queued','Sent','Failed','Skipped') NOT NULL DEFAULT 'Queued',
  `error_message` varchar(500) DEFAULT NULL,
  `sent_by` varchar(50) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sms_log_event` (`event_key`),
  KEY `idx_sms_log_customer` (`customer_id`),
  KEY `idx_sms_log_created` (`created_at`),
  KEY `idx_sms_log_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Source: sms_schema_upgrade.sql
-- Upgrade existing SMS settings with the Sales Invoice trigger option.

ALTER TABLE `db_sms_settings`
  ADD COLUMN IF NOT EXISTS `sales_invoice_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `enabled`;

-- Source: sms_sales_invoice_upgrade.sql
-- Upgrade existing SMS installations for sales invoice notifications.

ALTER TABLE `db_sms_settings`
  ADD COLUMN IF NOT EXISTS `sales_invoice_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `enabled`;

ALTER TABLE `db_sms_templates`
  MODIFY `event_key` enum('sales_invoice','due_generation','cost_cutting') NOT NULL;

INSERT INTO `db_sms_templates` (`event_key`, `template_name`, `message_body`)
VALUES ('sales_invoice', 'Sales Invoice', 'Hello {customer_name}, your invoice {sales_code} for {sales_amount} has been created. Paid: {paid_amount}. Invoice due: {invoice_due}. Thank you, {site_name}.')
ON DUPLICATE KEY UPDATE `event_key` = `event_key`;

UPDATE `db_sms_templates`
SET `message_body` = 'Hello {customer_name}, your invoice {sales_code} for {sales_amount} has been created. Paid: {paid_amount}. Invoice due: {invoice_due}. Thank you, {site_name}.'
WHERE `event_key` = 'sales_invoice'
  AND `message_body` = 'Invoice {customer_number} for {total_due}. Current due: {new_due}. Ledger: {ledger_url}';


CREATE TABLE IF NOT EXISTS `db_customer_loans` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `customer_id` int(50) NOT NULL,
  `loan_amount` decimal(20,2) NOT NULL,
  `paid_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `loan_date` date NOT NULL,
  `status` enum('OPEN','CLOSED','CANCELLED') NOT NULL DEFAULT 'OPEN',
  `note` text DEFAULT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_date` date DEFAULT NULL,
  `created_time` varchar(50) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customer_loans_customer` (`customer_id`),
  KEY `idx_customer_loans_date` (`loan_date`),
  KEY `idx_customer_loans_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `db_customer_loan_transactions` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `loan_id` int(50) NOT NULL,
  `customer_id` int(50) NOT NULL,
  `transaction_type` enum('LOAN_ISSUE','REPAYMENT','ADJUSTMENT') NOT NULL,
  `amount` decimal(20,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_date` date DEFAULT NULL,
  `created_time` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_loan_transactions_loan` (`loan_id`),
  KEY `idx_customer_loan_transactions_customer` (`customer_id`),
  KEY `idx_customer_loan_transactions_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;