<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Send_sms_customer extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load_global();
        $this->load->model('sms_service_model', 'sms_service');
    }

    public function index()
    {
        $this->permission_check('send_sms');
        $data = $this->data;
        $data['page_title'] = 'Send SMS to Customer';
        $data['customers'] = $this->db
            ->select('id, customer_name, customer_number, mobile')
            ->where('status', 1)
            ->where('mobile IS NOT NULL', null, false)
            ->where('mobile !=', '')
            ->order_by('customer_name', 'ASC')
            ->get('db_customers')
            ->result();
        $this->load->view('send-sms-customer', $data);
    }

    public function send()
    {
        $this->permission_check_with_msg('send_sms');
        $customer_id = (int) $this->input->post('customer_id', true);
        $message = trim((string) $this->input->post('message', true));
        $result = $this->sms_service->send_manual_customer_sms($customer_id, $message, $this->data);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('send_sms_customer'), 'refresh');
    }
}
