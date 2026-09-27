<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Stock_history_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // Get comprehensive stock summary
    public function get_stock_summary($item_id)
    {
        $summary = array(
            'total_purchase' => 0,
            'opening_stock' => 0,
            'total_sell_return' => 0,
            'stock_transfers_in' => 0,
            'total_sold' => 0,
            'total_stock_adjustment' => 0,
            'total_purchase_return' => 0,
            'stock_transfers_out' => 0,
            'current_stock' => 0,
            'production_output' => 0,
            'production_consumption' => 0,
            'total_damaged' => 0,
        );

        // Derive current stock from the transaction ledger so the page matches
        // the same balance shown in the stock-history table.
        $transactions = $this->get_transaction_history($item_id, 0, 1000);
        if (!empty($transactions)) {
            $last_transaction = end($transactions);
            if (isset($last_transaction->new_quantity)) {
                $summary['current_stock'] = (float) $last_transaction->new_quantity;
            }
        }

        // Fall back to the stored item snapshot only if no transaction history exists.
        if (empty($transactions)) {
            $item = $this->db->select('stock as current_stock')->from('db_items')->where('id', $item_id)->get()->row();
            if ($item) {
                $summary['current_stock'] = (float) $item->current_stock;
            }
        }

        // Total Purchase
        $query = $this->db->query("
        SELECT COALESCE(SUM(pi.purchase_qty), 0) as total 
        FROM db_purchaseitems pi 
        JOIN db_purchase p ON p.id = pi.purchase_id 
        WHERE pi.item_id = $item_id AND p.status = 1
    ");
        if ($query) $summary['total_purchase'] = $query->row()->total;

        // Total Sold
        $query = $this->db->query("
        SELECT COALESCE(SUM(si.sales_qty), 0) as total 
        FROM db_salesitems si 
        JOIN db_sales s ON s.id = si.sales_id 
        WHERE si.item_id = $item_id AND s.status = 1
    ");
        if ($query) $summary['total_sold'] = $query->row()->total;

        // Opening Stock (from stock entry - only non-production entries)
        $query = $this->db->query("
        SELECT COALESCE(SUM(qty), 0) as total 
        FROM db_stockentry 
        WHERE item_id = $item_id 
        AND status = 1 
        AND (note NOT LIKE '%Production%' OR note IS NULL)
    ");
        if ($query) $summary['opening_stock'] = $query->row()->total;

        // Sales Returns
        $query = $this->db->query("
        SELECT COALESCE(SUM(sr.return_qty), 0) as total 
        FROM db_salesitemsreturn sr 
        JOIN db_salesreturn s ON s.id = sr.return_id 
        WHERE sr.item_id = $item_id AND s.status = 1
    ");
        if ($query) $summary['total_sell_return'] = $query->row()->total;

        // Purchase Returns
        $query = $this->db->query("
        SELECT COALESCE(SUM(pr.return_qty), 0) as total 
        FROM db_purchaseitemsreturn pr 
        JOIN db_purchasereturn p ON p.id = pr.return_id 
        WHERE pr.item_id = $item_id AND p.status = 1
    ");
        if ($query) $summary['total_purchase_return'] = $query->row()->total;

        // Production Output (from stock entries - including Production Output and Production Revert)
        $query = $this->db->query("
        SELECT COALESCE(SUM(se.qty), 0) as total 
        FROM db_stockentry se 
        WHERE se.item_id = $item_id 
        AND se.status = 1 
        AND (se.note LIKE '%Production Output%' OR se.note LIKE '%Production Revert%')
    ");
        if ($query) $summary['production_output'] = $query->row()->total;

        // Production Consumption (from approved productions only - cancelled/rejected are excluded)
        $query = $this->db->query("
        SELECT COALESCE(SUM(ABS(im.qty)), 0) as total 
        FROM inventory_movements im 
        JOIN production_batches pb ON pb.id = im.reference_id
        WHERE im.item_id = $item_id 
        AND im.type = 'PRODUCTION_CONSUME'
        AND pb.status = 'Approved'
    ");
        if ($query) $summary['production_consumption'] = $query->row()->total;

        // Stock Adjustment (other adjustments excluding production)
        $query = $this->db->query("
        SELECT COALESCE(SUM(qty), 0) as total 
        FROM db_stockentry 
        WHERE item_id = $item_id 
        AND status = 1 
        AND qty <> 0
        AND (note NOT LIKE '%Production%' OR note IS NULL)
    ");
        if ($query) $summary['total_stock_adjustment'] = $query->row()->total;

        // Total Damaged (approved damage entries only)
        $query = $this->db->query("
        SELECT COALESCE(SUM(di.damage_qty), 0) as total
        FROM db_damageitems di
        JOIN db_damage d ON d.id = di.damage_id
        WHERE di.item_id = $item_id
        AND d.status = 'approved'
    ");
        if ($query) $summary['total_damaged'] = $query->row()->total;

        return $summary;
    }

    // Get transaction history with proper running balance
    public function get_transaction_history($item_id, $start = 0, $length = 1000)
    {
        $transactions = array();

        // 1. Sales Transactions
        $this->db->select("
        'Sell' as type,
        -si.sales_qty as quantity_change,
        si.sales_qty as absolute_quantity,
        s.sales_date as transaction_date,
        s.sales_code as reference_no,
        CONCAT(COALESCE(c.customer_name, 'Walk-in Customer'), ' (', COALESCE(c.mobile, 'N/A'), ')') as customer_supplier_info,
        s.id as source_id,
        'sales' as source_table,
        si.id as detail_id,
        CONCAT(s.sales_date, ' ', COALESCE(s.created_time, '00:00:00')) as sort_date
    ", false);
        $this->db->from('db_salesitems si');
        $this->db->join('db_sales s', 's.id = si.sales_id', 'left');
        $this->db->join('db_customers c', 'c.id = s.customer_id', 'left');
        $this->db->where('si.item_id', $item_id);
        $this->db->where('s.status', 1);
        $sales = $this->db->get();
        if ($sales) $transactions = array_merge($transactions, $sales->result());

        // 2. Purchase Transactions
        $this->db->select("
        'Purchase' as type,
        pi.purchase_qty as quantity_change,
        pi.purchase_qty as absolute_quantity,
        p.purchase_date as transaction_date,
        p.purchase_code as reference_no,
        CONCAT(COALESCE(sp.supplier_name, 'Unknown Supplier'), ' (', COALESCE(sp.mobile, 'N/A'), ')') as customer_supplier_info,
        p.id as source_id,
        'purchase' as source_table,
        pi.id as detail_id,
        CONCAT(p.purchase_date, ' ', COALESCE(p.created_time, '00:00:00')) as sort_date
    ", false);
        $this->db->from('db_purchaseitems pi');
        $this->db->join('db_purchase p', 'p.id = pi.purchase_id', 'left');
        $this->db->join('db_suppliers sp', 'sp.id = p.supplier_id', 'left');
        $this->db->where('pi.item_id', $item_id);
        $this->db->where('p.status', 1);
        $purchases = $this->db->get();
        if ($purchases) $transactions = array_merge($transactions, $purchases->result());

        // 3. Sales Returns
        $this->db->select("
        'Sell Return' as type,
        sr.return_qty as quantity_change,
        sr.return_qty as absolute_quantity,
        s.return_date as transaction_date,
        s.return_code as reference_no,
        CONCAT(COALESCE(c.customer_name, 'Walk-in Customer'), ' (', COALESCE(c.mobile, 'N/A'), ')') as customer_supplier_info,
        s.id as source_id,
        'sales_return' as source_table,
        sr.id as detail_id,
        CONCAT(s.return_date, ' ', COALESCE(s.created_time, '00:00:00')) as sort_date
    ", false);
        $this->db->from('db_salesitemsreturn sr');
        $this->db->join('db_salesreturn s', 's.id = sr.return_id', 'left');
        $this->db->join('db_customers c', 'c.id = s.customer_id', 'left');
        $this->db->where('sr.item_id', $item_id);
        $this->db->where('s.status', 1);
        $sales_returns = $this->db->get();
        if ($sales_returns) $transactions = array_merge($transactions, $sales_returns->result());

        // 4. Purchase Returns
        $this->db->select("
        'Purchase Return' as type,
        -pr.return_qty as quantity_change,
        pr.return_qty as absolute_quantity,
        p.return_date as transaction_date,
        p.return_code as reference_no,
        CONCAT(COALESCE(sp.supplier_name, 'Unknown Supplier'), ' (', COALESCE(sp.mobile, 'N/A'), ')') as customer_supplier_info,
        p.id as source_id,
        'purchase_return' as source_table,
        pr.id as detail_id,
        CONCAT(p.return_date, ' ', COALESCE(p.created_time, '00:00:00')) as sort_date
    ", false);
        $this->db->from('db_purchaseitemsreturn pr');
        $this->db->join('db_purchasereturn p', 'p.id = pr.return_id', 'left');
        $this->db->join('db_suppliers sp', 'sp.id = p.supplier_id', 'left');
        $this->db->where('pr.item_id', $item_id);
        $this->db->where('p.status', 1);
        $purchase_returns = $this->db->get();
        if ($purchase_returns) $transactions = array_merge($transactions, $purchase_returns->result());

        // 5. Stock Adjustments (excluding production consumption duplicates)
        $this->db->select("
        CASE 
            WHEN se.note LIKE '%Opening Stock%' OR se.note = '' OR se.note IS NULL THEN 'Opening Stock'
            WHEN se.qty > 0 THEN 'Stock In'
            ELSE 'Stock Out'
        END as type,
        se.qty as quantity_change,
        ABS(se.qty) as absolute_quantity,
        se.entry_date as transaction_date,
        CONCAT('STK-', se.id) as reference_no,
        CASE 
            WHEN se.note = '' OR se.note IS NULL THEN 'Opening Stock'
            ELSE se.note
        END as customer_supplier_info,
        se.id as source_id,
        'stock_entry' as source_table,
        se.id as detail_id,
        se.entry_date as sort_date
    ", false);
        $this->db->from('db_stockentry se');
        $this->db->where('se.item_id', $item_id);
        $this->db->where('se.status', 1);
        $this->db->where('se.qty <>', 0);
        // Include opening stock rows even when note is null/empty, but exclude production-consumption duplicates.
        $this->db->where("(se.note IS NULL OR se.note = '' OR se.note NOT LIKE '%Production Consumption - Batch:%')");
        $stock_entries = $this->db->get();
        if ($stock_entries) $transactions = array_merge($transactions, $stock_entries->result());

        // 6. Production Consumption ONLY (no duplicates)
        $this->db->select("
        'Production Consume' as type,
        -ABS(im.qty) as quantity_change,
        ABS(im.qty) as absolute_quantity,
        im.created_at as transaction_date,
        CONCAT('PROD-', COALESCE(pb.batch_code, im.reference_id)) as reference_no,
        CONCAT('Batch: ', COALESCE(pb.batch_code, 'N/A')) as customer_supplier_info,
        im.id as source_id,
        'production_consume' as source_table,
        im.id as detail_id,
        im.created_at as sort_date,
        im.reference_id as production_batch_id
    ", false);
        $this->db->from('inventory_movements im');
        $this->db->join('production_batches pb', 'pb.id = im.reference_id', 'left');
        $this->db->where('im.item_id', $item_id);
        $this->db->where('im.type', 'PRODUCTION_CONSUME');
        $production_consume = $this->db->get();
        if ($production_consume) $transactions = array_merge($transactions, $production_consume->result());

        // 7. Damage Transactions (approved only — stock was actually deducted)
        $this->db->select("
        CONCAT('Damage (', d.damage_type, ')') as type,
        -di.damage_qty as quantity_change,
        di.damage_qty as absolute_quantity,
        d.damage_date as transaction_date,
        d.damage_code as reference_no,
        CONCAT(
            'Added by: ', COALESCE(u.username, 'Unknown'),
            ' | Type: ', d.damage_type,
            CASE WHEN d.reason IS NOT NULL AND d.reason != '' THEN CONCAT(' | ', d.reason) ELSE '' END
        ) as customer_supplier_info,
        d.id as source_id,
        'damage' as source_table,
        di.id as detail_id,
        CONCAT(d.damage_date, ' ', COALESCE(d.created_time, '00:00:00')) as sort_date
    ", false);
        $this->db->from('db_damageitems di');
        $this->db->join('db_damage d',  'd.id = di.damage_id',  'left');
        $this->db->join('db_users u',   'u.id = d.created_by',  'left');
        $this->db->where('di.item_id', $item_id);
        $this->db->where('d.status', 'approved');
        $damage_transactions = $this->db->get();
        if ($damage_transactions) $transactions = array_merge($transactions, $damage_transactions->result());

        // Sort all transactions by date (oldest first for proper balance calculation)
        usort($transactions, function ($a, $b) {
            $time_a = strtotime($a->sort_date);
            $time_b = strtotime($b->sort_date);
            if ($time_a == $time_b) {
                return $a->detail_id - $b->detail_id;
            }
            return $time_a - $time_b;
        });

        // Calculate running balance and keep the ledger in chronological order so
        // the opening balance appears first and each next row reflects the updated balance.
        $transactions_with_balance = $this->calculate_running_balance_from_current($transactions, $item_id);

        // Apply pagination AFTER calculating balance
        return array_slice($transactions_with_balance, $start, $length);
    }

    // Calculate running balance working backward from current stock
    private function calculate_running_balance_from_current($transactions, $item_id)
    {
        if (empty($transactions)) {
            return array();
        }

        // Get current stock from database
        $this->db->select('stock');
        $this->db->from('db_items');
        $this->db->where('id', $item_id);
        $current_stock_result = $this->db->get()->row();
        $current_stock = $current_stock_result ? $current_stock_result->stock : 0;

        // Build a straight running balance from the transaction stream.
        // This keeps the opening stock row at its own value instead of shifting
        // every earlier row to fit the current stock from the items table.
        $running_balance = 0;

        foreach ($transactions as $transaction) {
            $running_balance += (float) $transaction->quantity_change;
            $transaction->new_quantity = $running_balance;
        }

        return $transactions;
    }

    // Count total transactions (excluding duplicate production entries)
    public function count_total_transactions($item_id)
    {
        $counts = [
            // Sales
            $this->db->from('db_salesitems si')
                ->join('db_sales s', 's.id = si.sales_id')
                ->where('si.item_id', $item_id)
                ->where('s.status', 1)
                ->count_all_results(),

            // Purchases
            $this->db->from('db_purchaseitems pi')
                ->join('db_purchase p', 'p.id = pi.purchase_id')
                ->where('pi.item_id', $item_id)
                ->where('p.status', 1)
                ->count_all_results(),

            // Sales Returns
            $this->db->from('db_salesitemsreturn sr')
                ->join('db_salesreturn s', 's.id = sr.return_id')
                ->where('sr.item_id', $item_id)
                ->where('s.status', 1)
                ->count_all_results(),

            // Purchase Returns
            $this->db->from('db_purchaseitemsreturn pr')
                ->join('db_purchasereturn p', 'p.id = pr.return_id')
                ->where('pr.item_id', $item_id)
                ->where('p.status', 1)
                ->count_all_results(),

            // Stock Entries (excluding duplicate production consumption)
            $this->db->from('db_stockentry')
                ->where('item_id', $item_id)
                ->where('status', 1)
                ->where('qty <>', 0)
                ->where("(note NOT LIKE '%Production Consumption - Batch:%' OR note IS NULL)")
                ->count_all_results(),

            // Production Consumption only
            $this->db->from('inventory_movements')
                ->where('item_id', $item_id)
                ->where('type', 'PRODUCTION_CONSUME')
                ->count_all_results(),

            // Damage (approved only)
            $this->db->from('db_damageitems di')
                ->join('db_damage d', 'd.id = di.damage_id')
                ->where('di.item_id', $item_id)
                ->where('d.status', 'approved')
                ->count_all_results(),
        ];

        return array_sum($counts);
    }

    // Get item details with additional info
    public function get_item_details($item_id)
    {
        $this->db->select('i.*, c.category_name, u.unit_name, b.brand_name');
        $this->db->from('db_items i');
        $this->db->join('db_category c', 'c.id = i.category_id', 'left');
        $this->db->join('db_units u', 'u.id = i.unit_id', 'left');
        $this->db->join('db_brands b', 'b.id = i.brand_id', 'left');
        $this->db->where('i.id', $item_id);

        $query = $this->db->get();
        return $query ? $query->row() : null;
    }
}




















