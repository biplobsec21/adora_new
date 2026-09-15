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
