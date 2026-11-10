<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Customer_deposit_management_model extends CI_Model
{
    public function get_customers()
    {
        return $this->db->select('id, customer_name, mobile AS customer_phone')
            ->where('status', 1)
            ->order_by('customer_name', 'ASC')
            ->get('db_customers')
            ->result_array();
    }

    public function get_customer_deposit_balance($customer_id, $through_date = '')
    {
        $this->db->select('COALESCE(SUM(CASE WHEN transaction_type = "DEPOSIT" THEN amount WHEN transaction_type = "WITHDRAWAL" THEN -amount ELSE 0 END), 0) AS balance', false)
            ->where('customer_id', (int) $customer_id);
        if ($through_date !== '') {
            $this->db->where('transaction_date <=', $through_date);
        }
        $row = $this->db->get('db_customer_deposit_transactions')->row();

        return max(0, round((float) $row->balance, 2));
    }

    public function get_customer_balances()
    {
        return $this->db->select('c.id, c.customer_name, c.mobile AS customer_phone, GREATEST(COALESCE(SUM(CASE WHEN d.transaction_type = "DEPOSIT" THEN d.amount WHEN d.transaction_type = "WITHDRAWAL" THEN -d.amount ELSE 0 END), 0), 0) AS deposit_balance', false)
            ->from('db_customers c')
            ->join('db_customer_deposit_transactions d', 'd.customer_id = c.id', 'left')
            ->where('c.status', 1)
            ->group_by(array('c.id', 'c.customer_name', 'c.mobile'))
            ->order_by('c.customer_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function get_transactions($customer_id = 0, $from_date = '', $to_date = '')
    {
        $this->db->select('d.*, c.customer_name, c.mobile AS customer_phone,
            (SELECT COALESCE(SUM(CASE WHEN prior.transaction_type = "DEPOSIT" THEN prior.amount WHEN prior.transaction_type = "WITHDRAWAL" THEN -prior.amount ELSE 0 END), 0)
             FROM db_customer_deposit_transactions prior
             WHERE prior.customer_id = d.customer_id
               AND (prior.transaction_date < d.transaction_date OR (prior.transaction_date = d.transaction_date AND prior.id <= d.id))
            ) AS balance_after', false)
            ->from('db_customer_deposit_transactions d')
            ->join('db_customers c', 'c.id = d.customer_id', 'left');
        if ((int) $customer_id > 0) {
            $this->db->where('d.customer_id', (int) $customer_id);
        }
        if ($from_date) {
            $this->db->where('d.transaction_date >=', $from_date);
        }
        if ($to_date) {
            $this->db->where('d.transaction_date <=', $to_date);
        }

        return $this->db->order_by('d.transaction_date', 'DESC')
            ->order_by('d.id', 'DESC')
            ->get()
            ->result_array();
    }

    public function get_audit_logs($customer_id = 0, $from_date = '', $to_date = '')
    {
        if ((int) $customer_id > 0) {
            $transaction_ids = $this->db->select('id')
                ->where('customer_id', (int) $customer_id)
                ->get('db_customer_deposit_transactions')
                ->result_array();
            if (!$transaction_ids) {
                return array();
            }
            $this->db->where_in('record_id', array_column($transaction_ids, 'id'));
        }
        if ($from_date) {
            $this->db->where('affected_date >=', $from_date);
        }
        if ($to_date) {
            $this->db->where('affected_date <=', $to_date);
        }
        $this->db->select('id, table_name, record_id, action, old_value, changed_by, system_ip, created_at');
        if ($this->db->field_exists('new_value', 'db_audit_logs')) {
            $this->db->select('new_value');
        } else {
            $this->db->select('NULL AS new_value', false);
        }

        return $this->db->where('table_name', 'db_customer_deposit_transactions')
            ->order_by('id', 'DESC')
            ->get('db_audit_logs')
            ->result_array();
    }

    public function get_transaction_totals($customer_id = 0, $from_date = '', $to_date = '')
    {
        $this->db->select('COALESCE(SUM(CASE WHEN transaction_type = "DEPOSIT" THEN amount ELSE 0 END), 0) AS total_deposits,
            COALESCE(SUM(CASE WHEN transaction_type = "WITHDRAWAL" THEN amount ELSE 0 END), 0) AS total_withdrawals', false);
        if ((int) $customer_id > 0) {
            $this->db->where('customer_id', (int) $customer_id);
        }
        if ($from_date) {
            $this->db->where('transaction_date >=', $from_date);
        }
        if ($to_date) {
            $this->db->where('transaction_date <=', $to_date);
        }
        $row = $this->db->get('db_customer_deposit_transactions')->row_array();

        return array(
            'total_deposits' => (float) $row['total_deposits'],
            'total_withdrawals' => (float) $row['total_withdrawals'],
        );
    }

    public function record_transaction($customer_id, $type, $amount, $transaction_date, $payment_method, $note, $user_data)
    {
        if (!in_array($type, array('DEPOSIT', 'WITHDRAWAL'), true) || $customer_id <= 0 || $amount <= 0) {
            return array('success' => false, 'message' => 'Invalid customer deposit transaction.');
        }

        $this->db->trans_begin();
        $customer = $this->db->query(
            'SELECT id FROM db_customers WHERE id = ? AND status = 1 FOR UPDATE',
            array((int) $customer_id)
        )->row();
        if (!$customer) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The selected customer is not active.');
        }

        $balance = $this->get_customer_deposit_balance($customer_id, $transaction_date);
        if ($type === 'WITHDRAWAL' && $amount > $balance) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'Withdrawal cannot exceed the available customer deposit balance of ' . number_format($balance, 2) . '.');
        }

        $record = array(
            'customer_id' => (int) $customer_id,
            'transaction_type' => $type,
            'amount' => round((float) $amount, 2),
            'transaction_date' => $transaction_date,
            'payment_method' => $payment_method ?: 'CASH',
            'note' => $note,
            'created_by' => (int) ($user_data['userid'] ?? 0),
            'created_date' => $user_data['CUR_DATE'],
            'created_time' => $user_data['CUR_TIME'],
            'created_at' => date('Y-m-d H:i:s'),
        );
        if (!$this->db->insert('db_customer_deposit_transactions', $record)) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'Customer deposit transaction could not be saved.');
        }

        $transaction_id = (int) $this->db->insert_id();
        $audit = array(
            'table_name' => 'db_customer_deposit_transactions',
            'record_id' => $transaction_id,
            'affected_date' => $transaction_date,
            'action' => 'INSERT',
            'old_value' => json_encode(null),
            'changed_by' => $user_data['CUR_USERNAME'] ?? ($user_data['userid'] ?? 'SYSTEM'),
            'system_ip' => $user_data['SYSTEM_IP'] ?? $this->input->ip_address(),
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        );
        if ($this->db->field_exists('new_value', 'db_audit_logs')) {
            $audit['new_value'] = json_encode($record);
        } else {
            $audit['old_value'] = json_encode(array('previous' => null, 'new' => $record));
        }
        if (!$this->db->insert('db_audit_logs', $audit) || !$this->db->trans_status()) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'Customer deposit transaction audit could not be saved.');
        }

        $this->db->trans_commit();
        return array(
            'success' => true,
            'message' => $type === 'DEPOSIT' ? 'Customer deposit recorded successfully.' : 'Customer deposit withdrawal recorded successfully.',
        );
    }
}
