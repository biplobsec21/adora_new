<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Customer_refunds extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load_global();
        $this->load->model('customer_refunds_model', 'customer_refunds');
    }

    public function index()
    {
        $this->permission_check('customer_refunds_view');
        $data = $this->data;
        $data['page_title'] = 'Customer Refunds';
        $data['refunds'] = $this->customer_refunds->get_refunds();
        $this->load->view('customer-refunds/index', $data);
    }

    public function mark_paid($refund_id)
    {
        $this->permission_check_with_msg('customer_refunds_pay');
        if ($this->input->method(true) !== 'POST') {
            show_error('Refund payments must be confirmed using POST.', 405);
        }

        $result = $this->customer_refunds->mark_paid((int) $refund_id, $this->data);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('customer_refunds'), 'refresh');
    }
}
