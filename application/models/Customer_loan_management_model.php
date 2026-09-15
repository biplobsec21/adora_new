<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Customer_loan_management_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function get_loans($customer_id = 0, $from_date = '', $to_date = '')
    {
        $this->db->select('cl.*, c.customer_name, c.mobile as customer_phone');
        $this->db->from('db_customer_loans cl');
        $this->db->join('db_customers c', 'c.id = cl.customer_id', 'left');
        if ((int) $customer_id > 0) {
            $this->db->where('cl.customer_id', (int) $customer_id);
        }
        if ($from_date) {
            $this->db->where('cl.loan_date >=', $from_date);
        }
        if ($to_date) {
            $this->db->where('cl.loan_date <=', $to_date);
        }
        $this->db->order_by('cl.loan_date', 'DESC');

        return $this->db->get()->result_array();
    }

    public function get_loan($loan_id)
    {
        return $this->db->get_where('db_customer_loans', ['id' => $loan_id])->row_array();
    }

    public function get_audit_logs($customer_id = 0, $from_date = '', $to_date = '')
    {
        if ((int) $customer_id > 0) {
            $loan_ids = $this->db->select('id')->where('customer_id', (int) $customer_id)->get('db_customer_loans')->result_array();
            if (empty($loan_ids)) {
                return [];
            }

            $loan_ids = array_column($loan_ids, 'id');
            $transaction_ids = $this->db->select('id')->where_in('loan_id', $loan_ids)->get('db_customer_loan_transactions')->result_array();
            $transaction_ids = array_column($transaction_ids, 'id');

            $this->db->group_start()
                ->where('table_name', 'db_customer_loans')
                ->where_in('record_id', $loan_ids)
                ->group_end();
            if (!empty($transaction_ids)) {
                $this->db->or_group_start()
                    ->where('table_name', 'db_customer_loan_transactions')
                    ->where_in('record_id', $transaction_ids)
                    ->group_end();
            }
        } else {
            $this->db->where_in('table_name', ['db_customer_loans', 'db_customer_loan_transactions']);
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

        return $this->db
            ->where_in('table_name', ['db_customer_loans', 'db_customer_loan_transactions'])
            ->order_by('id', 'DESC')
            ->get('db_audit_logs')
            ->result_array();
    }

    public function get_customers()
    {
        $this->db->select('id, customer_name, mobile as customer_phone');
        $this->db->from('db_customers');
        $this->db->where('status', 1);
        $this->db->order_by('customer_name', 'ASC');

        return $this->db->get()->result_array();
    }

    public function get_customer($customer_id)
    {
        $this->db->select('id, customer_name, mobile as customer_phone');
        $this->db->from('db_customers');
        $this->db->where('id', $customer_id);

        return $this->db->get()->row_array();
    }

    public function create_loan($customer_id, $loan_amount, $loan_date, $note, $user_data)
    {
        $this->db->trans_start();

        $this->db->insert('db_customer_loans', [
            'customer_id' => $customer_id,
            'loan_amount' => $loan_amount,
            'paid_amount' => 0,
            'balance_amount' => $loan_amount,
            'loan_date' => $loan_date,
            'status' => 'OPEN',
            'created_by' => $user_data['userid'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'note' => $note,
        ]);

        $loan_id = $this->db->insert_id();

        $this->db->insert('db_customer_loan_transactions', [
            'loan_id' => $loan_id,
            'customer_id' => $customer_id,
            'transaction_type' => 'LOAN_ISSUE',
            'amount' => $loan_amount,
            'transaction_date' => $loan_date,
            'payment_method' => 'SYSTEM',
            'note' => $note,
            'created_by' => $user_data['userid'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $transaction_id = $this->db->insert_id();
        $loan = $this->get_loan($loan_id);
        $this->write_audit('db_customer_loans', $loan_id, 'INSERT', null, $loan, $loan_date, $user_data);
        $this->write_audit('db_customer_loan_transactions', $transaction_id, 'INSERT', null, [
            'loan_id' => $loan_id,
            'customer_id' => $customer_id,
            'transaction_type' => 'LOAN_ISSUE',
            'amount' => $loan_amount,
            'transaction_date' => $loan_date,
            'note' => $note,
        ], $loan_date, $user_data);

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return ['success' => false, 'message' => 'Failed to create customer loan.'];
        }

        return ['success' => true, 'message' => 'Customer loan created successfully.'];
    }

    public function update_loan($loan_id, $customer_id, $loan_amount, $loan_date, $note, $user_data)
    {
        $loan = $this->get_loan($loan_id);
        if (!$loan) {
            return ['success' => false, 'message' => 'Loan not found.'];
        }

        $paid_amount = (float) $loan['paid_amount'];
        if ($loan_amount < $paid_amount) {
            return ['success' => false, 'message' => 'Loan amount cannot be less than the amount already repaid.'];
        }

        $transactions_before = $this->db
            ->where('loan_id', $loan_id)
            ->order_by('id', 'ASC')
            ->get('db_customer_loan_transactions')
            ->result_array();

        $this->db->trans_start();

        $this->db->where('id', $loan_id);
        $this->db->update('db_customer_loans', [
            'customer_id' => $customer_id,
            'loan_amount' => $loan_amount,
            'balance_amount' => $loan_amount - $paid_amount,
            'status' => ($loan_amount - $paid_amount) <= 0 ? 'CLOSED' : 'OPEN',
            'loan_date' => $loan_date,
            'updated_by' => $user_data['userid'] ?? 0,
            'updated_at' => date('Y-m-d H:i:s'),
            'note' => $note,
        ]);

        $this->db->where('loan_id', $loan_id);
        $this->db->update('db_customer_loan_transactions', [
            'customer_id' => $customer_id,
        ]);

        $this->db->where('loan_id', $loan_id);
        $this->db->where('transaction_type', 'LOAN_ISSUE');
        $this->db->update('db_customer_loan_transactions', [
            'amount' => $loan_amount,
            'transaction_date' => $loan_date,
            'note' => $note,
        ]);

        $updated_loan = $this->get_loan($loan_id);
        $this->write_audit('db_customer_loans', $loan_id, 'UPDATE', $loan, $updated_loan, $loan_date, $user_data);

        $transactions_after = $this->db
            ->where('loan_id', $loan_id)
            ->order_by('id', 'ASC')
            ->get('db_customer_loan_transactions')
            ->result_array();
        foreach ($transactions_before as $index => $transaction_before) {
            $transaction_after = $transactions_after[$index] ?? null;
            if ($transaction_after && $transaction_before != $transaction_after) {
                $this->write_audit('db_customer_loan_transactions', $transaction_before['id'], 'UPDATE', $transaction_before, $transaction_after, $loan_date, $user_data);
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return ['success' => false, 'message' => 'Failed to update customer loan.'];
        }

        return ['success' => true, 'message' => 'Customer loan updated successfully.'];
    }

    public function get_open_loans()
    {
        $this->db->select('cl.id, cl.customer_id, c.customer_name, cl.loan_amount, cl.paid_amount, cl.balance_amount');
        $this->db->from('db_customer_loans cl');
        $this->db->join('db_customers c', 'c.id = cl.customer_id', 'left');
        $this->db->where('cl.status', 'OPEN');
        $this->db->order_by('cl.loan_date', 'DESC');

        return $this->db->get()->result_array();
    }

    public function record_repayment($loan_id, $amount, $transaction_date, $payment_method, $note, $user_data)
    {
        $loan = $this->db->get_where('db_customer_loans', ['id' => $loan_id])->row_array();

        if (!$loan) {
            return ['success' => false, 'message' => 'Loan not found.'];
        }

        if ($amount > $loan['balance_amount']) {
            return ['success' => false, 'message' => 'Repayment amount cannot exceed outstanding balance.'];
        }

        $this->db->trans_start();

        $new_paid_amount = $loan['paid_amount'] + $amount;
        $new_balance_amount = $loan['balance_amount'] - $amount;
        $status = ($new_balance_amount <= 0) ? 'CLOSED' : 'OPEN';

        $this->db->where('id', $loan_id);
        $this->db->update('db_customer_loans', [
            'paid_amount' => $new_paid_amount,
            'balance_amount' => $new_balance_amount,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $user_data['userid'] ?? 0,
        ]);

        $this->db->insert('db_customer_loan_transactions', [
            'loan_id' => $loan_id,
            'customer_id' => $loan['customer_id'],
            'transaction_type' => 'REPAYMENT',
            'amount' => $amount,
            'transaction_date' => $transaction_date,
            'payment_method' => $payment_method ?: 'CASH',
            'note' => $note,
            'created_by' => $user_data['userid'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $transaction_id = $this->db->insert_id();
        $updated_loan = $this->get_loan($loan_id);
        $this->write_audit('db_customer_loans', $loan_id, 'UPDATE', $loan, $updated_loan, $transaction_date, $user_data);
        $this->write_audit('db_customer_loan_transactions', $transaction_id, 'INSERT', null, [
            'loan_id' => $loan_id,
            'customer_id' => $loan['customer_id'],
            'transaction_type' => 'REPAYMENT',
            'amount' => $amount,
            'transaction_date' => $transaction_date,
            'payment_method' => $payment_method ?: 'CASH',
            'note' => $note,
        ], $transaction_date, $user_data);

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return ['success' => false, 'message' => 'Failed to record repayment.'];
        }

        return ['success' => true, 'message' => 'Loan repayment recorded successfully.'];
    }

    public function get_customer_statement($customer_id)
    {
        $this->db->select('clt.*, cl.loan_amount, cl.balance_amount');
        $this->db->from('db_customer_loan_transactions clt');
        $this->db->join('db_customer_loans cl', 'cl.id = clt.loan_id', 'left');
        $this->db->where('cl.customer_id', $customer_id);
        $this->db->order_by('clt.transaction_date', 'ASC');
        $this->db->order_by('clt.id', 'ASC');

        return $this->db->get()->result_array();
    }

    public function get_customer_loan_summary($customer_id)
    {
        $this->db->select('SUM(loan_amount) AS total_loan_amount, SUM(paid_amount) AS total_paid_amount, SUM(balance_amount) AS total_balance_amount');
        $this->db->from('db_customer_loans');
        $this->db->where('customer_id', $customer_id);

        $summary = $this->db->get()->row_array();

        return [
            'total_loan_amount' => (float) ($summary['total_loan_amount'] ?? 0),
            'total_paid_amount' => (float) ($summary['total_paid_amount'] ?? 0),
            'total_balance_amount' => (float) ($summary['total_balance_amount'] ?? 0),
        ];
    }

    private function write_audit($table_name, $record_id, $action, $old_value, $new_value, $affected_date, $user_data)
    {
        $audit = [
            'table_name' => $table_name,
            'record_id' => $record_id,
            'affected_date' => $affected_date,
            'action' => $action,
            'old_value' => json_encode($old_value),
            'changed_by' => $user_data['CUR_USERNAME'] ?? ($user_data['userid'] ?? 'SYSTEM'),
            'system_ip' => $user_data['SYSTEM_IP'] ?? $this->input->ip_address(),
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($this->db->field_exists('new_value', 'db_audit_logs')) {
            $audit['new_value'] = json_encode($new_value);
        } else {
            $audit['old_value'] = json_encode([
                'previous' => $old_value,
                'new' => $new_value,
            ]);
        }

        return $this->db->insert('db_audit_logs', $audit);
    }
}
