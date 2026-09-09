<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Payment_management_model extends CI_Model
{
    public function get_customer_addresses()
    {
        return $this->db
            ->distinct()
            ->select('c.address')
            ->from('db_customers AS c')
            ->where('c.status', 1)
            ->where("TRIM(COALESCE(c.address, '')) <>", '')
            ->order_by('c.address', 'ASC')
            ->get()
            ->result();
    }

    public function get_datatables()
    {
        $this->build_list_query();
        $length = (int) $this->input->post('length');
        $start = (int) $this->input->post('start');
        if ($length !== -1) {
            $this->db->limit(max(1, $length), max(0, $start));
        }
        return $this->db->get()->result();
    }

    public function count_filtered()
    {
        $this->build_list_query();
        return $this->db->get()->num_rows();
    }

    public function count_all()
    {
        $this->build_list_query();
        return $this->db->get()->num_rows();
    }

    public function get_due_customers_for_export($address = '')
    {
        $this->build_list_query($address);
        return $this->db->get()->result();
    }

    private function build_list_query($address = '')
    {
        $this->db->select("c.id AS customer_id, c.customer_code, c.customer_number, c.customer_name, c.address, c.mobile,
                        COALESCE(c.opening_balance, 0) AS opening_balance,
                        COALESCE(ob.paid_amount, 0) AS opening_balance_paid,
                        COALESCE(sales.expected_amount, 0) AS expected_amount,
                        COALESCE(sales.paid_amount, 0) AS paid_amount,
                        (COALESCE(c.opening_balance, 0) - COALESCE(ob.paid_amount, 0)
                            + COALESCE(sales.expected_amount, 0) - COALESCE(sales.paid_amount, 0)) AS remaining_amount,
                        CASE
                            WHEN (COALESCE(c.opening_balance, 0) - COALESCE(ob.paid_amount, 0)
                                + COALESCE(sales.expected_amount, 0) - COALESCE(sales.paid_amount, 0)) <= 0 THEN 'Paid'
                            WHEN (COALESCE(ob.paid_amount, 0) + COALESCE(sales.paid_amount, 0)) > 0 THEN 'Partial'
                            ELSE 'Unpaid'
                        END AS payment_status")
            ->from('db_customers AS c')
            ->join('(SELECT customer_id, SUM(payment) AS paid_amount FROM db_cobpayments WHERE status = 1 GROUP BY customer_id) AS ob', 'ob.customer_id = c.id', 'left')
            ->join("(SELECT customer_id, SUM(grand_total) AS expected_amount, SUM(paid_amount) AS paid_amount
                                            FROM db_sales WHERE status = 1 AND sales_status = 'Final' GROUP BY customer_id) AS sales", 'sales.customer_id = c.id', 'left')
            ->where('c.status', 1)
            ->having('remaining_amount >', 0);

        if ($address !== '') {
            $this->db->where('c.address', $address);
        }

        $search_data = $this->input->post('search');
        $search = trim((string) (is_array($search_data) && isset($search_data['value']) ? $search_data['value'] : ''));
        if ($search !== '') {
            $this->db->group_start()
                ->like('c.customer_name', $search)
                ->or_like('c.address', $search)
                ->or_like('c.mobile', $search)
                ->or_like('c.id', $search)
                ->group_end();
        }

        $this->db->order_by('c.customer_name', 'ASC');
    }

    public function record_payment($customer_id, $amount, $payment_date, $payment_type, $note, $user_data)
    {
        $this->db->trans_begin();
        $result = $this->allocate_payment($customer_id, $amount, $payment_date, $payment_type, $note, $user_data);
        if (!$result['success'] || !$this->db->trans_status()) {
            $this->db->trans_rollback();
            return $result;
        }
        $this->db->trans_commit();
        return $result;
    }

    public function record_bulk_payments($customer_ids, $amount_mode, $amount, $payment_date, $payment_type, $note, $user_data)
    {
        if (empty($customer_ids) || !in_array($amount_mode, array('full_due', 'fixed_amount'), true)) {
            return array('success' => false, 'message' => 'Invalid bulk payment request.');
        }
        $this->db->trans_begin();
        foreach ($customer_ids as $customer_id) {
            $due = $this->get_customer_due($customer_id);
            $customer_amount = $amount_mode === 'full_due' ? $due : (float) $amount;
            $result = $this->allocate_payment((int) $customer_id, $customer_amount, $payment_date, $payment_type, $note, $user_data);
            if (!$result['success']) {
                $this->db->trans_rollback();
                return array('success' => false, 'message' => $result['message']);
            }
        }
        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'Bulk payments could not be recorded.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'message' => 'Bulk payments recorded successfully.');
    }

    private function allocate_payment($customer_id, $amount, $payment_date, $payment_type, $note, $user_data)
    {
        $customer = $this->db->get_where('db_customers', array('id' => $customer_id, 'status' => 1))->row();
        if (!$customer) {
            return array('success' => false, 'message' => 'Customer not found.');
        }
        $opening_paid = (float) $this->db->select_sum('payment')->where(array('customer_id' => $customer_id, 'status' => 1))->get('db_cobpayments')->row()->payment;
        $opening_due = max(0, (float) $customer->opening_balance - $opening_paid);
        $sales = $this->db->query("SELECT id, customer_id, grand_total, paid_amount,
                GREATEST(COALESCE(grand_total, 0) - COALESCE(paid_amount, 0), 0) AS sales_due
            FROM db_sales WHERE customer_id = ? AND status = 1 AND sales_status = 'Final'
              AND COALESCE(grand_total, 0) > COALESCE(paid_amount, 0)
            ORDER BY sales_date ASC, id ASC", array($customer_id))->result();
        $remaining = $opening_due;
        foreach ($sales as $sale) {
            $remaining += (float) $sale->sales_due;
        }
        if ($amount <= 0 || $amount > $remaining) {
            return array('success' => false, 'message' => 'Payment amount must be greater than zero and not exceed the remaining amount.');
        }

        if ($amount > 0 && $opening_due > 0) {
            $opening_payment = min($amount, $opening_due);
            $this->db->insert('db_cobpayments', array(
                'customer_id' => $customer_id,
                'payment_date' => $payment_date,
                'payment_type' => $payment_type,
                'payment' => $opening_payment,
                'payment_note' => $note,
                'created_date' => $user_data['CUR_DATE'],
                'created_time' => $user_data['CUR_TIME'],
                'created_by' => $user_data['CUR_USERNAME'],
                'system_ip' => $user_data['SYSTEM_IP'],
                'system_name' => $user_data['SYSTEM_NAME'],
                'status' => 1,
            ));
            $amount -= $opening_payment;
        }
        foreach ($sales as $sale) {
            if ($amount <= 0) break;
            $payment_amount = min($amount, (float) $sale->sales_due);
            $this->db->insert('db_salespayments', array(
                'sales_id' => $sale->id,
                'payment_date' => $payment_date,
                'payment_type' => $payment_type,
                'payment' => $payment_amount,
                'payment_note' => $note,
                'system_ip' => $user_data['SYSTEM_IP'],
                'system_name' => $user_data['SYSTEM_NAME'],
                'created_time' => $user_data['CUR_TIME'],
                'created_date' => $user_data['CUR_DATE'],
                'created_by' => $user_data['CUR_USERNAME'],
                'status' => 1,
            ));
            $payment_id = $this->db->insert_id();
            $new_paid = (float) $sale->paid_amount + $payment_amount;
            $this->db->where('id', $sale->id)->update('db_sales', array(
                'paid_amount' => $new_paid,
                'payment_status' => $new_paid >= (float) $sale->grand_total ? 'Paid' : 'Partial',
            ));
            $this->db->insert('db_customer_payments', array(
                'salespayment_id' => $payment_id,
                'customer_id' => $customer_id,
                'payment_date' => $payment_date,
                'payment_type' => $payment_type,
                'payment' => $payment_amount,
                'payment_note' => $note,
                'system_ip' => $user_data['SYSTEM_IP'],
                'system_name' => $user_data['SYSTEM_NAME'],
                'created_time' => $user_data['CUR_TIME'],
                'created_date' => $user_data['CUR_DATE'],
                'created_by' => $user_data['CUR_USERNAME'],
                'status' => 1,
            ));
            $amount -= $payment_amount;
        }

        return array('success' => true, 'message' => 'Payment recorded successfully.');
    }

    private function get_customer_due($customer_id)
    {
        $customer = $this->db->get_where('db_customers', array('id' => (int) $customer_id, 'status' => 1))->row();
        if (!$customer) return 0;
        $opening_paid = (float) $this->db->select_sum('payment')->where(array('customer_id' => $customer_id, 'status' => 1))->get('db_cobpayments')->row()->payment;
        $sales = $this->db->select('COALESCE(SUM(grand_total - paid_amount), 0) AS due', false)
            ->where(array('customer_id' => $customer_id, 'status' => 1, 'sales_status' => 'Final'))
            ->where('grand_total > paid_amount')->get('db_sales')->row();
        return max(0, (float) $customer->opening_balance - $opening_paid + (float) $sales->due);
    }

    public function update_statuses($customer_ids, $status, $user_data)
    {
        if (!in_array($status, array('Paid', 'Unpaid'), true) || empty($customer_ids)) {
            return array('success' => false, 'message' => 'Invalid bulk payment status request.');
        }
        $this->db->trans_begin();
        foreach ($customer_ids as $customer_id) {
            $sales = $this->db->where(array('customer_id' => (int) $customer_id, 'status' => 1, 'sales_status' => 'Final'))->get('db_sales')->result();
            foreach ($sales as $sale) {
                $this->db->where('id', $sale->id)->update('db_sales', array('payment_status' => $status));
                $this->db->insert('db_audit_logs', array(
                    'table_name' => 'db_sales',
                    'record_id' => $sale->id,
                    'affected_date' => $sale->sales_date,
                    'action' => 'UPDATE',
                    'old_value' => json_encode(array('payment_status' => $sale->payment_status, 'source' => 'Bulk Manual')),
                    'changed_by' => $user_data['CUR_USERNAME'],
                    'system_ip' => $user_data['SYSTEM_IP'],
                ));
            }
        }
        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'Bulk status update failed.');
        }
        $this->db->trans_commit();
        return array('success' => true, 'message' => 'Bulk status update completed.');
    }
}
