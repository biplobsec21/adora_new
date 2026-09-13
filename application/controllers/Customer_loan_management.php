<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Customer_loan_management extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load_global();
        $this->load->model('customer_loan_management_model', 'customer_loans');
    }

    public function index()
    {
        $this->permission_check('customer_loan_view');

        $data = $this->data;
        $data['page_title'] = 'Customer Loan Management';
        $data['loans'] = $this->customer_loans->get_loans();

        $this->load->view('customer-loan-management/list', $data);
    }

    public function add()
    {
        $this->permission_check('customer_loan_add');

        $data = $this->data;
        $data['page_title'] = 'Add Customer Loan';
        $data['customers'] = $this->customer_loans->get_customers();

        $this->load->view('customer-loan-management/add', $data);
    }

    public function save()
    {
        $this->permission_check_with_msg('customer_loan_add');

        $customer_id = (int) $this->input->post('customer_id', true);
        $loan_amount = (float) $this->input->post('loan_amount', true);
        $loan_date = system_fromatted_date($this->input->post('loan_date', true));
        $note = trim((string) $this->input->post('note', true));

        if ($customer_id <= 0 || $loan_amount <= 0 || !$this->is_valid_date($loan_date)) {
            show_error('Invalid customer loan request.', 400);
        }

        $result = $this->customer_loans->create_loan($customer_id, $loan_amount, $loan_date, $note, $this->data);

        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('customer_loan_management'), 'refresh');
    }

    public function repayment()
    {
        $this->permission_check('customer_loan_repayment');

        $data = $this->data;
        $data['page_title'] = 'Record Customer Loan Repayment';
        $data['customers'] = $this->customer_loans->get_customers();
        $data['open_loans'] = $this->customer_loans->get_open_loans();

        $this->load->view('customer-loan-management/repayment', $data);
    }

    public function save_repayment()
    {
        $this->permission_check_with_msg('customer_loan_repayment');

        $loan_id = (int) $this->input->post('loan_id', true);
        $amount = (float) $this->input->post('amount', true);
        $transaction_date = system_fromatted_date($this->input->post('transaction_date', true));
        $payment_method = trim((string) $this->input->post('payment_method', true));
        $note = trim((string) $this->input->post('note', true));

        if ($loan_id <= 0 || $amount <= 0 || !$this->is_valid_date($transaction_date)) {
            show_error('Invalid repayment request.', 400);
        }

        $result = $this->customer_loans->record_repayment($loan_id, $amount, $transaction_date, $payment_method, $note, $this->data);

        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('customer_loan_management'), 'refresh');
    }

    public function statement($customer_id = 0)
    {
        $this->permission_check('customer_loan_view');

        $customer_id = (int) $customer_id;
        if ($customer_id <= 0) {
            $customer_id = (int) $this->input->get('customer_id', true);
        }

        $data = $this->data;
        $data['page_title'] = 'Customer Loan Statement';
        $data['customers'] = $this->customer_loans->get_customers();
        $data['selected_customer_id'] = $customer_id;

        if ($customer_id > 0) {
            $data['customer'] = $this->customer_loans->get_customer($customer_id);
            $data['statement'] = $this->customer_loans->get_customer_statement($customer_id);
            $data['summary'] = $this->customer_loans->get_customer_loan_summary($customer_id);
        }

        $this->load->view('customer-loan-management/statement', $data);
    }

    private function is_valid_date($date)
    {
        $parsed_date = DateTime::createFromFormat('Y-m-d', (string) $date);
        return $parsed_date && $parsed_date->format('Y-m-d') === $date;
    }
}
