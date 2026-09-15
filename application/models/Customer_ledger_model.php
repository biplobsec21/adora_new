<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Customer_ledger_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // Get customer details
    public function get_customer_details($customer_id)
    {
        $this->db->select('*');
        $this->db->from('db_customers');
        $this->db->where('id', $customer_id);
        $this->db->where('status', 1);

        $query = $this->db->get();
        return $query ? $query->row() : null;
    }

    // Get comprehensive customer ledger
    public function get_customer_ledger($customer_id, $start_date, $end_date)
    {
        $ledger_data = array();

        // Convert dates to database format
        $db_start_date = date('Y-m-d', strtotime($start_date));
        $db_end_date = date('Y-m-d', strtotime($end_date));

        // 1. Opening Balance
        $opening_balance = $this->get_opening_balance($customer_id, $db_start_date);

        // Set running balance starting from opening balance
        $running_balance = $opening_balance;

        // Add opening balance entry only if it's not zero
        if ((float)$opening_balance !== 0.0) {
            $ledger_data[] = (object)[
                'date' => $db_start_date . ' 00:00:00',
                'reference_no' => '',
                'type' => 'Opening Balance',
                'location' => '',
                'payment_status' => '',
                'debit' => $opening_balance > 0 ? abs($opening_balance) : 0,
                'credit' => $opening_balance < 0 ? abs($opening_balance) : 0,
                'balance' => $running_balance,
                'payment_method' => '',
                'others' => '',
                'items' => array(),
                'is_opening' => true
            ];
        }

        // Collect all transactions first
        $all_transactions = array();

        // 2. Sales Invoices (INCREASE customer balance - customer owes more)
        $sales = $this->get_sales_transactions($customer_id, $db_start_date, $db_end_date);
        foreach ($sales as $sale) {
            $sale->type = 'Sell';
            $sale->is_opening = false;
            $all_transactions[] = $sale;
        }

        // 3. Sales Returns (DECREASE customer balance - customer owes less)
        $sales_returns = $this->get_sales_return_transactions($customer_id, $db_start_date, $db_end_date);
        foreach ($sales_returns as $return) {
            $return->type = 'Sales Return';
            $return->is_opening = false;
            $all_transactions[] = $return;
        }

        // 4. Customer Payments (DECREASE customer balance - customer pays)
        $payments = $this->get_customer_payments($customer_id, $db_start_date, $db_end_date);
        foreach ($payments as $payment) {
            $payment->type = 'Payment';
            $payment->is_opening = false;
            $all_transactions[] = $payment;
        }

        // 5. Sales Return Payments (INCREASE customer balance - we pay customer)
        $return_payments = $this->get_sales_return_payments($customer_id, $db_start_date, $db_end_date);
        foreach ($return_payments as $return_payment) {
            $return_payment->type = 'Return Payment';
            $return_payment->is_opening = false;
            $all_transactions[] = $return_payment;
        }

        // 6. COB Payments (DECREASE customer balance - customer pays opening balance)
        $cob_payments = $this->get_cob_payments($customer_id, $db_start_date, $db_end_date);
        foreach ($cob_payments as $cob_payment) {
            $cob_payment->type = 'Opening Balance Payment';
            $cob_payment->is_opening = false;
            $all_transactions[] = $cob_payment;
        }

        // 7. Customer loans and repayments
        $loan_transactions = $this->get_loan_transactions($customer_id, $db_start_date, $db_end_date);
        foreach ($loan_transactions as $loan_transaction) {
            $loan_transaction->type = $loan_transaction->transaction_type === 'REPAYMENT' ? 'Loan Repayment' : 'Customer Loan';
            $loan_transaction->is_opening = false;
            $all_transactions[] = $loan_transaction;
        }

        // Replace the usort function with this more precise version:
        usort($all_transactions, function ($a, $b) {
            $timeA = strtotime($a->date);
            $timeB = strtotime($b->date);

            // If dates are equal, sort by type to ensure consistent order
            if ($timeA === $timeB) {
                $typeOrder = [
                    'Opening Balance' => 0,
                    'Sell' => 1,
                    'Sales Return' => 2,
                    'Payment' => 3,
                    'Return Payment' => 4,
                    'Opening Balance Payment' => 5,
                    'Customer Loan' => 6,
                    'Loan Repayment' => 7
                ];
                return ($typeOrder[$a->type] ?? 999) - ($typeOrder[$b->type] ?? 999);
            }

            return $timeA - $timeB;
        });

        // Now calculate running balance in correct order
        foreach ($all_transactions as $transaction) {
            switch ($transaction->type) {
                case 'Sell':
                    $running_balance += $transaction->grand_total;
                    break;
                case 'Sales Return':
                    $running_balance -= $transaction->grand_total;
                    break;
                case 'Payment':
                    $running_balance -= $transaction->payment;
                    break;
                case 'Return Payment':
                    $running_balance += $transaction->payment;
                    break;
                case 'Opening Balance Payment':
                    $running_balance -= $transaction->payment;
                    break;
                case 'Customer Loan':
                    $running_balance += $transaction->amount;
                    break;
                case 'Loan Repayment':
                    $running_balance -= $transaction->amount;
                    break;
            }

            $transaction->balance = $running_balance;
            $ledger_data[] = $transaction;
        }

        return $ledger_data;
    }

    // Get opening balance before start date
    private function get_opening_balance($customer_id, $start_date)
    {
        $balance = 0;

        // Opening balance from customer record
        $this->db->select('opening_balance');
        $this->db->from('db_customers');
        $this->db->where('id', $customer_id);
        $customer = $this->db->get()->row();
        $balance += $customer ? $customer->opening_balance : 0;

        // Sales before start date (customer owes this amount)
        $this->db->select('COALESCE(SUM(grand_total), 0) as total_sales');
        $this->db->from('db_sales');
        $this->db->where('customer_id', $customer_id);
        $this->db->where('sales_date <', $start_date);
        $this->db->where('status', 1);
        $sales = $this->db->get()->row();
        $balance += $sales->total_sales;

        // Sales returns before start date (reduce what customer owes)
        $this->db->select('COALESCE(SUM(grand_total), 0) as total_returns');
        $this->db->from('db_salesreturn');
        $this->db->where('customer_id', $customer_id);
        $this->db->where('return_date <', $start_date);
        $this->db->where('status', 1);
        $returns = $this->db->get()->row();
        $balance -= $returns->total_returns;

        // Payments before start date (reduce what customer owes)
        $this->db->select('COALESCE(SUM(payment), 0) as total_payments');
        $this->db->from('db_salespayments');
        $this->db->where('sales_id IN (SELECT id FROM db_sales WHERE customer_id = ' . $customer_id . ')');
        $this->db->where('payment_date <', $start_date);
        $this->db->where('status', 1);
        $payments = $this->db->get()->row();
        $balance -= $payments->total_payments;

        // Return payments before start date (we owe customer)
        $this->db->select('COALESCE(SUM(payment), 0) as total_return_payments');
        $this->db->from('db_salespaymentsreturn');
        $this->db->where('return_id IN (SELECT id FROM db_salesreturn WHERE customer_id = ' . $customer_id . ')');
        $this->db->where('payment_date <', $start_date);
        $this->db->where('status', 1);
        $return_payments = $this->db->get()->row();
        $balance += $return_payments->total_return_payments;

        // COB Payments before start date (customer paid opening balance)
        $this->db->select('COALESCE(SUM(payment), 0) as total_cob_payments');
        $this->db->from('db_cobpayments');
        $this->db->where('customer_id', $customer_id);
        $this->db->where('payment_date <', $start_date);
        $this->db->where('status', 1);
        $cob_payments = $this->db->get()->row();
        $balance -= $cob_payments->total_cob_payments;

        // Customer loans increase the amount due; repayments reduce it.
        $this->db->select('COALESCE(SUM(CASE WHEN transaction_type = "LOAN_ISSUE" THEN amount ELSE 0 END), 0) AS total_loan_issues,
                   COALESCE(SUM(CASE WHEN transaction_type = "REPAYMENT" THEN amount ELSE 0 END), 0) AS total_loan_repayments');
        $this->db->from('db_customer_loan_transactions');
        $this->db->where('customer_id', $customer_id);
        $this->db->where('transaction_date <', $start_date);
        $loan_balance = $this->db->get()->row();
        $balance += (float) $loan_balance->total_loan_issues - (float) $loan_balance->total_loan_repayments;

        return $balance;
    }

    // Get sales transactions
    private function get_sales_transactions($customer_id, $start_date, $end_date)
    {
        $this->db->select('s.id, s.sales_code as reference_no, s.sales_date as date, 
                          s.grand_total, s.payment_status, w.warehouse_name as location,
                          si.item_id, i.item_name, si.sales_qty, si.price_per_unit, 
                          si.discount_amt, si.tax_amt, si.total_cost');
        $this->db->from('db_sales s');
        $this->db->join('db_warehouse w', 'w.id = s.warehouse_id', 'left');
        $this->db->join('db_salesitems si', 'si.sales_id = s.id', 'left');
        $this->db->join('db_items i', 'i.id = si.item_id', 'left');
        $this->db->where('s.customer_id', $customer_id);
        $this->db->where('s.sales_date >=', $start_date);
        $this->db->where('s.sales_date <=', $end_date);
        $this->db->where('s.status', 1);
        $this->db->order_by('s.sales_date', 'ASC');

        $query = $this->db->get();
        $sales = $query->result();

        // Group by sales invoice
        $grouped_sales = array();
        foreach ($sales as $sale) {
            if (!isset($grouped_sales[$sale->id])) {
                $grouped_sales[$sale->id] = (object)[
                    'date' => $sale->date,
                    'sales_id' => $sale->id,
                    'reference_no' => $sale->reference_no,
                    'grand_total' => $sale->grand_total,
                    'payment_status' => $sale->payment_status,
                    'location' => $sale->location,
                    'debit' => $sale->grand_total,
                    'credit' => 0,
                    'payment_method' => '',
                    'others' => '',
                    'items' => array()
                ];
            }

            if ($sale->item_id) {
                $grouped_sales[$sale->id]->items[] = (object)[
                    'item_name' => $sale->item_name,
                    'sales_qty' => $sale->sales_qty,
                    'price_per_unit' => $sale->price_per_unit,
                    'discount_amt' => $sale->discount_amt,
                    'tax_amt' => $sale->tax_amt,
                    'total_cost' => $sale->total_cost
                ];
            }
        }

        return array_values($grouped_sales);
    }

    // Get sales return transactions
    private function get_sales_return_transactions($customer_id, $start_date, $end_date)
    {
        $this->db->select('sr.id, sr.return_code as reference_no, sr.return_date as date, 
                          sr.grand_total, sr.payment_status, w.warehouse_name as location,
                          sri.item_id, i.item_name, sri.return_qty, sri.price_per_unit, 
                          sri.discount_amt, sri.tax_amt, sri.total_cost');
        $this->db->from('db_salesreturn sr');
        $this->db->join('db_warehouse w', 'w.id = sr.warehouse_id', 'left');
        $this->db->join('db_salesitemsreturn sri', 'sri.return_id = sr.id', 'left');
        $this->db->join('db_items i', 'i.id = sri.item_id', 'left');
        $this->db->where('sr.customer_id', $customer_id);
        $this->db->where('sr.return_date >=', $start_date);
        $this->db->where('sr.return_date <=', $end_date);
        $this->db->where('sr.status', 1);
        $this->db->order_by('sr.return_date', 'ASC');

        $query = $this->db->get();
        $returns = $query->result();

        // Group by return invoice
        $grouped_returns = array();
        foreach ($returns as $return) {
            if (!isset($grouped_returns[$return->id])) {
                $grouped_returns[$return->id] = (object)[
                    'date' => $return->date,
                    "return_id" => $return->id,
                    'reference_no' => $return->reference_no,
                    'grand_total' => $return->grand_total,
                    'payment_status' => $return->payment_status,
                    'location' => $return->location,
                    'debit' => 0,
                    'credit' => $return->grand_total,
                    'payment_method' => '',
                    'others' => '',
                    'items' => array()
                ];
            }

            if ($return->item_id) {
                $grouped_returns[$return->id]->items[] = (object)[
                    'item_name' => $return->item_name,
                    'return_qty' => $return->return_qty,
                    'price_per_unit' => $return->price_per_unit,
                    'discount_amt' => $return->discount_amt,
                    'tax_amt' => $return->tax_amt,
                    'total_cost' => $return->total_cost
                ];
            }
        }

        return array_values($grouped_returns);
    }

    // Get customer payments
    private function get_customer_payments($customer_id, $start_date, $end_date)
    {
        $this->db->select('sp.id, sp.payment_date as date, sp.payment_type as payment_method, 
                      sp.payment_note as others, sp.payment,
                      CONCAT("SP", YEAR(sp.payment_date), "/", sp.id) as reference_no,
                      s.sales_code as sales_reference');
        $this->db->from('db_salespayments sp');
        $this->db->join('db_sales s', 's.id = sp.sales_id');
        $this->db->where('s.customer_id', $customer_id);
        $this->db->where('sp.payment_date >=', $start_date);
        $this->db->where('sp.payment_date <=', $end_date);
        $this->db->where('sp.status', 1);
        $this->db->where('sp.payment >', 0);

        $this->db->order_by('sp.payment_date', 'ASC');

        $query = $this->db->get();
        $payments = $query->result();

        foreach ($payments as $payment) {
            $payment->debit = 0;
            $payment->credit = $payment->payment;
            $payment->location = '';
            $payment->payment_status = 'Paid';
            $payment->items = array();
            if (empty($payment->others)) {
                $payment->others = "Payment for " . $payment->sales_reference;
            }
        }

        return $payments;
    }

    // Get sales return payments
    private function get_sales_return_payments($customer_id, $start_date, $end_date)
    {
        $this->db->select('spr.id, spr.payment_date as date, spr.payment_type as payment_method, 
                      spr.payment_note as others, spr.payment,
                      CONCAT("RSP", YEAR(spr.payment_date), "/", spr.id) as reference_no,
                      sr.return_code as return_reference');
        $this->db->from('db_salespaymentsreturn spr');
        $this->db->join('db_salesreturn sr', 'sr.id = spr.return_id');
        $this->db->where('sr.customer_id', $customer_id);
        $this->db->where('spr.payment_date >=', $start_date);
        $this->db->where('spr.payment_date <=', $end_date);
        $this->db->where('spr.payment >', 0);

        $this->db->where('spr.status', 1);
        $this->db->order_by('spr.payment_date', 'ASC');

        $query = $this->db->get();
        $return_payments = $query->result();

        foreach ($return_payments as $payment) {
            $payment->debit = $payment->payment;
            $payment->credit = 0;
            $payment->location = '';
            $payment->payment_status = 'Paid';
            $payment->items = array();
            if (empty($payment->others)) {
                $payment->others = "Return payment for " . $payment->return_reference;
            }
        }

        return $return_payments;
    }

    // Get COB Payments (Customer Opening Balance Payments)
    private function get_cob_payments($customer_id, $start_date, $end_date)
    {
        $this->db->select('id, payment_date as date, payment_type as payment_method, 
                      payment_note as others, payment,
                      CONCAT("COB", YEAR(payment_date), "/", id) as reference_no');
        $this->db->from('db_cobpayments');
        $this->db->where('customer_id', $customer_id);
        $this->db->where('payment_date >=', $start_date);
        $this->db->where('payment_date <=', $end_date);
        $this->db->where('status', 1);
        $this->db->order_by('payment_date', 'ASC');

        $query = $this->db->get();
        $cob_payments = $query->result();

        foreach ($cob_payments as $payment) {
            $payment->debit = 0;
            $payment->credit = $payment->payment;
            $payment->location = '';
            $payment->payment_status = 'Paid';
            $payment->items = array();
            if (empty($payment->others)) {
                $payment->others = "Opening Balance Payment";
            }
        }

        return $cob_payments;
    }

    // Get customer loan issues and repayments
    private function get_loan_transactions($customer_id, $start_date, $end_date)
    {
        $this->db->select('id, loan_id, transaction_type, transaction_date AS date, amount,
                           payment_method, note AS others,
                           CONCAT("CL", YEAR(transaction_date), "/", id) AS reference_no');
        $this->db->from('db_customer_loan_transactions');
        $this->db->where('customer_id', $customer_id);
        $this->db->where('transaction_date >=', $start_date);
        $this->db->where('transaction_date <=', $end_date);
        $this->db->where_in('transaction_type', ['LOAN_ISSUE', 'REPAYMENT']);
        $this->db->where('amount >', 0);
        $this->db->order_by('transaction_date', 'ASC');

        $transactions = $this->db->get()->result();
        foreach ($transactions as $transaction) {
            $transaction->debit = $transaction->transaction_type === 'LOAN_ISSUE' ? $transaction->amount : 0;
            $transaction->credit = $transaction->transaction_type === 'REPAYMENT' ? $transaction->amount : 0;
            $transaction->location = '';
            $transaction->payment_status = $transaction->transaction_type === 'REPAYMENT' ? 'Paid' : 'Issued';
            $transaction->items = array();
            if (empty($transaction->others)) {
                $transaction->others = $transaction->transaction_type === 'REPAYMENT' ? 'Customer loan repayment' : 'Customer loan issued';
            }
        }

        return $transactions;
    }

    // Get account summary
    public function get_account_summary($customer_id, $start_date, $end_date)
    {
        $db_start_date = date('Y-m-d', strtotime($start_date));
        $db_end_date = date('Y-m-d', strtotime($end_date));

        $summary = array(
            'opening_balance' => 0,
            'total_invoice' => 0,
            'total_paid' => 0,
            'total_cob_payments' => 0,
            'total_loan_issued' => 0,
            'total_loan_repaid' => 0,
            'advance_balance' => 0,
            'balance_due' => 0
        );

        // Opening balance
        $summary['opening_balance'] = $this->get_opening_balance($customer_id, $db_start_date);

        // Total invoice (sales within date range)
        $this->db->select('COALESCE(SUM(grand_total), 0) as total_invoice');
        $this->db->from('db_sales');
        $this->db->where('customer_id', $customer_id);
        $this->db->where('sales_date >=', $db_start_date);
        $this->db->where('sales_date <=', $db_end_date);
        $this->db->where('status', 1);
        $invoice_result = $this->db->get()->row();
        $summary['total_invoice'] = $invoice_result->total_invoice;

        // Total paid (payments within date range)
        $this->db->select('COALESCE(SUM(sp.payment), 0) as total_paid');
        $this->db->from('db_salespayments sp');
        $this->db->join('db_sales s', 's.id = sp.sales_id');
        $this->db->where('s.customer_id', $customer_id);
        $this->db->where('sp.payment_date >=', $db_start_date);
        $this->db->where('sp.payment_date <=', $db_end_date);
        $this->db->where('sp.status', 1);
        $paid_result = $this->db->get()->row();
        $summary['total_paid'] = $paid_result->total_paid;

        // Total COB payments within date range
        $this->db->select('COALESCE(SUM(payment), 0) as total_cob_payments');
        $this->db->from('db_cobpayments');
        $this->db->where('customer_id', $customer_id);
        $this->db->where('payment_date >=', $db_start_date);
        $this->db->where('payment_date <=', $db_end_date);
        $this->db->where('status', 1);
        $cob_result = $this->db->get()->row();
        $summary['total_cob_payments'] = $cob_result->total_cob_payments;

        $this->db->select('COALESCE(SUM(CASE WHEN transaction_type = "LOAN_ISSUE" THEN amount ELSE 0 END), 0) AS total_loan_issued,
                   COALESCE(SUM(CASE WHEN transaction_type = "REPAYMENT" THEN amount ELSE 0 END), 0) AS total_loan_repaid');
        $this->db->from('db_customer_loan_transactions');
        $this->db->where('customer_id', $customer_id);
        $this->db->where('transaction_date >=', $db_start_date);
        $this->db->where('transaction_date <=', $db_end_date);
        $loan_result = $this->db->get()->row();
        $summary['total_loan_issued'] = (float) $loan_result->total_loan_issued;
        $summary['total_loan_repaid'] = (float) $loan_result->total_loan_repaid;

        // Balance due (including COB payments)
        $summary['balance_due'] = $summary['opening_balance'] + $summary['total_invoice'] + $summary['total_loan_issued'] - $summary['total_paid'] - $summary['total_cob_payments'] - $summary['total_loan_repaid'];

        return $summary;
    }
}
