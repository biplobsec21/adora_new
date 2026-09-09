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