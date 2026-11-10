-- Add customer deposit and withdrawal management and include both in EOD cash.
-- Safe to run repeatedly against an installation with EOD already configured.

CREATE TABLE IF NOT EXISTS `db_customer_deposit_transactions` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `customer_id` int(50) NOT NULL,
  `transaction_type` enum('DEPOSIT','WITHDRAWAL') NOT NULL,
  `amount` decimal(20,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_date` date DEFAULT NULL,
  `created_time` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_deposit_transactions_customer` (`customer_id`),
  KEY `idx_customer_deposit_transactions_date` (`transaction_date`),
  KEY `idx_customer_deposit_transactions_type_date` (`transaction_type`,`transaction_date`),
  KEY `idx_customer_deposit_transactions_customer_date` (`customer_id`,`transaction_date`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @deposit_eod_deposits_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_daily_closing' AND column_name = 'total_customer_deposits'
);
SET @sql := IF(@deposit_eod_deposits_exists = 0,
  'ALTER TABLE `db_daily_closing` ADD COLUMN `total_customer_deposits` decimal(20,2) NOT NULL DEFAULT 0.00 AFTER `total_loan_repayments_received`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @deposit_eod_withdrawals_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'db_daily_closing' AND column_name = 'total_customer_deposit_withdrawals'
);
SET @sql := IF(@deposit_eod_withdrawals_exists = 0,
  'ALTER TABLE `db_daily_closing` ADD COLUMN `total_customer_deposit_withdrawals` decimal(20,2) NOT NULL DEFAULT 0.00 AFTER `total_customer_deposits`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
