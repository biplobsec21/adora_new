-- Initial schema for Customer Loan Management
-- This is the first SQL structure for customer loan/advance tracking.
-- Important design note:
-- The existing Customer Ledger remains for sales/inventory/customer financial history.
-- Customer loan data should be tracked separately through db_customer_loans and db_customer_loan_transactions,
-- and can be exposed through a dedicated Customer Loan Ledger / Customer Loan Statement.

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

-- Optional foreign key pattern for future use:
-- ALTER TABLE `db_customer_loan_transactions`
--   ADD CONSTRAINT `fk_customer_loan_transactions_loan`
--   FOREIGN KEY (`loan_id`) REFERENCES `db_customer_loans` (`id`)
--   ON UPDATE CASCADE ON DELETE CASCADE;

-- Optional future extension:
-- CREATE TABLE IF NOT EXISTS `db_customer_loan_schedules` (
--   `id` int(50) NOT NULL AUTO_INCREMENT,
--   `loan_id` int(50) NOT NULL,
--   `due_date` date NOT NULL,
--   `principal_due` decimal(20,2) NOT NULL,
--   `interest_due` decimal(20,2) NOT NULL DEFAULT 0.00,
--   `total_due` decimal(20,2) NOT NULL,
--   `paid_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
--   `status` enum('Pending','Paid','Overdue') NOT NULL DEFAULT 'Pending',
--   `created_at` datetime NOT NULL DEFAULT current_timestamp(),
--   PRIMARY KEY (`id`),
--   KEY `idx_customer_loan_schedules_loan` (`loan_id`),
--   KEY `idx_customer_loan_schedules_due_date` (`due_date`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
