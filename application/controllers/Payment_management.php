<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Payment_management extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load_global();
        $this->load->model('payment_management_model', 'payments');
    }

    public function index()
    {
        $this->permission_check('payment_management_view');
        $data = $this->data;
        $data['page_title'] = 'Payment Management';
        $this->load->view('payment-management/list', $data);
    }

    public function ajax_list()
    {
        $this->permission_check_with_msg('payment_management_view');
        $list = $this->payments->get_datatables();
        $data = array();
        $number = (int) $this->input->post('start');
        foreach ($list as $payment) {
            $number++;
            $actions = '<div class="btn-group"><button class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Actions <span class="caret"></span></button><ul class="dropdown-menu">';
            if ($this->permissions('payment_management_record')) {
                $actions .= '<li><a href="#" class="record-payment" data-id="' . (int) $payment->customer_id . '" data-code="' . html_escape($payment->customer_id) . '" data-customer="' . html_escape($payment->customer_name) . '" data-due="' . html_escape($payment->remaining_amount) . '"><i class="fa fa-money"></i> Record Manual Payment</a></li>';
            }
            if ($this->permissions('payment_management_status')) {
                $actions .= '<li><a href="#" class="manual-status" data-id="' . (int) $payment->customer_id . '"><i class="fa fa-edit"></i> Change Status</a></li>';
            }
            $actions .= '</ul></div>';
            $data[] = array(
                '<input type="checkbox" class="payment-select" value="' . (int) $payment->customer_id . '">',
                html_escape($payment->customer_name),
                $payment->customer_id,
                html_escape($payment->address),
                app_number_format($payment->remaining_amount),
                html_escape($payment->payment_status),
                $actions,
            );
        }
        echo json_encode(array(
            'draw' => (int) $this->input->post('draw'),
            'recordsTotal' => $this->payments->count_all(),
            'recordsFiltered' => $this->payments->count_filtered(),
            'data' => $data,
        ));
    }

    public function record_payment()
    {
        $this->permission_check_with_msg('payment_management_record');
        $customer_id = (int) $this->input->post('customer_id');
        $amount = (float) $this->input->post('amount');
        $payment_date = system_fromatted_date($this->input->post('payment_date', true));
        $payment_type = $this->input->post('payment_type', true);
        $note = trim((string) $this->input->post('note', true));
        if (!$this->is_valid_date($payment_date) || $payment_type === '') {
            show_error('Invalid payment details.', 400);
        }
        $result = $this->payments->record_payment($customer_id, $amount, $payment_date, $payment_type, $note, $this->data);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('payment_management'), 'refresh');
    }

    public function bulk_payment()
    {
        $this->permission_check_with_msg('payment_management_record');
        $customer_ids = $this->input->post('customer_ids');
        $amount_mode = $this->input->post('amount_mode', true);
        $amount = (float) $this->input->post('amount');
        $payment_date = system_fromatted_date($this->input->post('payment_date', true));
        $payment_type = $this->input->post('payment_type', true);
        $note = trim((string) $this->input->post('note', true));
        $customer_ids = is_array($customer_ids) ? $customer_ids : array();
        if (!$this->is_valid_date($payment_date) || $payment_type === '' || ($amount_mode === 'fixed_amount' && $amount <= 0)) {
            show_error('Invalid bulk payment details.', 400);
        }
        $result = $this->payments->record_bulk_payments($customer_ids, $amount_mode, $amount, $payment_date, $payment_type, $note, $this->data);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('payment_management'), 'refresh');
    }

    private function is_valid_date($date)
    {
        $parsed_date = DateTime::createFromFormat('Y-m-d', (string) $date);
        return $parsed_date && $parsed_date->format('Y-m-d') === $date;
    }
}
