-- End of Day closing schema for the pioneer application.
-- Run this against the database selected by DB_DATABASE.
-- This project uses a single company database, so no company_id is required.

CREATE TABLE IF NOT EXISTS `db_daily_closing` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `closing_date` date NOT NULL,
  `total_sales_due` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_cash_collected` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_expenses` decimal(20,2) NOT NULL DEFAULT 0.00,
  `custom_cash_additions` decimal(20,2) NOT NULL DEFAULT 0.00,
  `custom_cash_deductions` decimal(20,2) NOT NULL DEFAULT 0.00,
  `final_cash_in_hand` decimal(20,2) NOT NULL DEFAULT 0.00,
  `late_entry_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `closing_type` enum('Regular','Retroactive') NOT NULL DEFAULT 'Regular',
  `created_by` varchar(50) NOT NULL,
  `system_ip` varchar(45) NOT NULL,
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
  `changed_by` varchar(50) NOT NULL,
  `system_ip` varchar(45) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_affected_date` (`affected_date`),
  KEY `idx_audit_table_record` (`table_name`,`record_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
