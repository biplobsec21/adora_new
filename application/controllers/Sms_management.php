<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sms_management extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load_global();
        $this->load->model('sms_service_model', 'sms_service');
    }
    public function index()
    {
        $this->permission_check('sms_api_view');
        $data = $this->data;
        $data['page_title'] = 'SMS Management';
        $data['settings'] = $this->sms_service->get_settings();
        $data['sms_balance'] = $this->sms_service->get_balance();
        $data['templates'] = $this->sms_service->get_templates();
        $data['logs'] = $this->sms_service->get_logs(date('Y-m'));
        $this->load->view('sms-management/index', $data);
    }
    public function save_settings()
    {
        $this->permission_check_with_msg('sms_api_edit');
        $result = $this->sms_service->save_settings($this->input->post(null, true), $this->data);
        $this->session->set_flashdata($result ? 'success' : 'error', $result ? 'SMS settings saved.' : 'SMS settings could not be saved.');
        redirect(base_url('sms_management'));
    }
    public function save_template($id)
    {
        $this->permission_check_with_msg('sms_template_edit');
        $result = $this->sms_service->save_template($id, $this->input->post('message_body', true), $this->input->post('enabled'), $this->data);
        $this->session->set_flashdata($result ? 'success' : 'error', $result ? 'SMS template saved.' : 'SMS template could not be saved.');
        redirect(base_url('sms_management'));
    }
    public function logs()
    {
        $this->permission_check('sms_template_view');
        $selected_date = trim((string) $this->input->get('date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) $selected_date = date('Y-m-d');
        $data = $this->data;
        $data['page_title'] = 'SMS Logs';
        $data['selected_date'] = $selected_date;
        $data['logs'] = $this->sms_service->get_logs($selected_date);
        $this->load->view('sms-management/logs', $data);
    }
}
