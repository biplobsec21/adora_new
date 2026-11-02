-- Upgrade existing DueFlow installations to store the loan component per customer.

ALTER TABLE `db_due_generation_items`
  ADD COLUMN IF NOT EXISTS `loan_due_amount` decimal(20,2) NOT NULL DEFAULT 0.00 AFTER `previous_outstanding_amount`;

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

DROP TEMPORARY TABLE IF EXISTS `tmp_due_generation_refund_backfill`;

START TRANSACTION;

CREATE TEMPORARY TABLE `tmp_due_generation_refund_backfill` ENGINE=InnoDB AS
SELECT
  ci.id AS cost_cutting_item_id,
  ci.customer_id,
  b.id AS cost_cutting_batch_id,
  ROUND(ci.cutting_amount - ci.new_paid_amount, 2) AS refund_amount,
  COALESCE(NULLIF(b.processed_by, ''), NULLIF(b.uploaded_by, ''), 'SYSTEM') AS generated_by,
  COALESCE(b.processed_at, b.updated_at) AS generated_at,
  b.system_ip,
  g.id AS generation_id,
  gi.id AS generation_item_id
FROM `db_cost_cutting_items` AS ci
INNER JOIN `db_cost_cutting_batches` AS b ON b.id = ci.batch_id
INNER JOIN `db_due_generations` AS g
  ON g.id = (
    SELECT matched_generation.id
    FROM `db_due_generations` AS matched_generation
    WHERE DATE_FORMAT(matched_generation.due_cycle_date, '%Y-%m') = DATE_FORMAT(b.payment_period, '%Y-%m')
    ORDER BY (matched_generation.generation_type = 'Monthly Cycle') DESC,
             matched_generation.due_cycle_date DESC
    LIMIT 1
  )
INNER JOIN `db_due_generation_items` AS gi
  ON gi.generation_id = g.id
 AND gi.customer_id = ci.customer_id
WHERE b.status = 'Completed'
  AND ci.cutting_amount > ci.new_paid_amount;

INSERT INTO `db_due_generation_refunds` (
  `customer_id`, `generation_id`, `generation_item_id`, `cost_cutting_batch_id`,
  `cost_cutting_item_id`, `refund_amount`, `status`, `generated_by`,
  `generated_at`, `system_ip`
)
SELECT
  `customer_id`, `generation_id`, `generation_item_id`, `cost_cutting_batch_id`,
  `cost_cutting_item_id`, `refund_amount`, 'Pending', `generated_by`,
  `generated_at`, `system_ip`
FROM `tmp_due_generation_refund_backfill` AS backfill
WHERE NOT EXISTS (
  SELECT 1
  FROM `db_due_generation_refunds` AS existing_refund
  WHERE existing_refund.cost_cutting_item_id = backfill.cost_cutting_item_id
);

UPDATE `db_due_generation_items` AS gi
INNER JOIN `tmp_due_generation_refund_backfill` AS backfill
  ON backfill.generation_item_id = gi.id
SET gi.actually_cut_amount = gi.total_due_amount,
    gi.remaining_due_amount = 0,
    gi.status = 'Fully Cut';

UPDATE `db_due_generations` AS g
INNER JOIN (
  SELECT
    gi.generation_id,
    COALESCE(SUM(gi.actually_cut_amount), 0) AS total_cut_amount,
    COALESCE(SUM(gi.remaining_due_amount), 0) AS total_remaining_due
  FROM `db_due_generation_items` AS gi
  INNER JOIN (
    SELECT DISTINCT generation_id
    FROM `tmp_due_generation_refund_backfill`
  ) AS affected ON affected.generation_id = gi.generation_id
  GROUP BY gi.generation_id
) AS totals ON totals.generation_id = g.id
SET g.total_cut_amount = totals.total_cut_amount,
    g.total_remaining_due = totals.total_remaining_due;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS `tmp_due_generation_refund_backfill`;