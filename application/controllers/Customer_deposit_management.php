<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Customer_deposit_management extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load_global();
        $this->load->model('customer_deposit_management_model', 'customer_deposits');
    }

    public function index()
    {
        $this->permission_check('customer_deposit_view');

        $data = $this->data;
        $data['page_title'] = 'Customer Deposit Management';
        $data['customers'] = $this->customer_deposits->get_customers();
        $data['selected_customer_id'] = (int) $this->input->get('customer_id', true);
        $data['selected_from_date'] = trim((string) $this->input->get('from_date', true)) ?: date('01-m-Y');
        $data['selected_to_date'] = trim((string) $this->input->get('to_date', true)) ?: date('d-m-Y');
        $from_date = system_fromatted_date($data['selected_from_date']);
        $to_date = system_fromatted_date($data['selected_to_date']);
        if (($from_date && !$this->is_valid_date($from_date)) || ($to_date && !$this->is_valid_date($to_date))) {
            $this->session->set_flashdata('error', 'Invalid deposit date filter.');
            redirect(base_url('customer_deposit_management'), 'refresh');
        }
        if ($from_date && $to_date && $from_date > $to_date) {
            $this->session->set_flashdata('error', 'The From date cannot be later than the To date.');
            redirect(base_url('customer_deposit_management'), 'refresh');
        }

        $data['transactions'] = $this->customer_deposits->get_transactions($data['selected_customer_id'], $from_date, $to_date);
        $data['totals'] = $this->customer_deposits->get_transaction_totals($data['selected_customer_id'], $from_date, $to_date);
        $data['audit_logs'] = $this->customer_deposits->get_audit_logs($data['selected_customer_id'], $from_date, $to_date);
        $data['selected_customer_balance'] = $data['selected_customer_id'] > 0
            ? $this->customer_deposits->get_customer_deposit_balance($data['selected_customer_id'])
            : null;

        $this->load->view('customer-deposit-management/list', $data);
    }

    public function deposit()
    {
        $this->permission_check('customer_deposit_add');
        $data = $this->data;
        $data['page_title'] = 'Record Customer Deposit';
        $data['customers'] = $this->customer_deposits->get_customers();
        $this->load->view('customer-deposit-management/transaction', $data);
    }

    public function withdraw()
    {
        $this->permission_check('customer_deposit_withdraw');
        $data = $this->data;
        $data['page_title'] = 'Withdraw Customer Deposit';
        $data['customers'] = $this->customer_deposits->get_customer_balances();
        $data['withdrawal'] = true;
        $this->load->view('customer-deposit-management/transaction', $data);
    }

    public function save_deposit()
    {
        $this->save_transaction('customer_deposit_add', 'DEPOSIT');
    }

    public function save_withdrawal()
    {
        $this->save_transaction('customer_deposit_withdraw', 'WITHDRAWAL');
    }

    private function save_transaction($permission, $type)
    {
        $this->permission_check_with_msg($permission);
        if ($this->input->method(true) !== 'POST') {
            show_error('Customer deposit transactions must be submitted with POST.', 405);
        }

        $customer_id = (int) $this->input->post('customer_id', true);
        $amount = $this->input->post('amount', true);
        $transaction_date = system_fromatted_date($this->input->post('transaction_date', true));
        $payment_method = trim((string) $this->input->post('payment_method', true));
        $note = trim((string) $this->input->post('note', true));
        if ($customer_id <= 0 || !is_numeric($amount) || (float) $amount <= 0 || !$this->is_valid_date($transaction_date)) {
            show_error('Invalid customer deposit transaction.', 400);
        }

        $result = $this->customer_deposits->record_transaction(
            $customer_id,
            $type,
            round((float) $amount, 2),
            $transaction_date,
            $payment_method,
            $note,
            $this->data
        );
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('customer_deposit_management'), 'refresh');
    }

    private function is_valid_date($date)
    {
        $parsed = DateTime::createFromFormat('Y-m-d', (string) $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }
}
