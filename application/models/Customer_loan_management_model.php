<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Customer_loan_management_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function get_loans()
    {
        $this->db->select('cl.*, c.customer_name, c.mobile as customer_phone');
        $this->db->from('db_customer_loans cl');
        $this->db->join('db_customers c', 'c.id = cl.customer_id', 'left');
        $this->db->order_by('cl.loan_date', 'DESC');

        return $this->db->get()->result_array();
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

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return ['success' => false, 'message' => 'Failed to create customer loan.'];
        }

        return ['success' => true, 'message' => 'Customer loan created successfully.'];
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
}
