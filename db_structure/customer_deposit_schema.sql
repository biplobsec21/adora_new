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
