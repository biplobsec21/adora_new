<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Due_generation extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load_global();
        $this->load->model('due_generation_model', 'due_generation');
    }

    public function index()
    {
        $this->permission_check('payment_management_view');
        $data = $this->data;
        $data['page_title'] = 'Canteen Due Generation';
        $data['settings'] = $this->due_generation->get_settings();
        $opening = $this->due_generation->get_opening_generation();
        $data['opening_required'] = !$opening;
        $default_due_date = $opening
            ? $this->due_generation->get_next_generation_due_date($data['settings']->cost_cutting_day)
            : $this->data['CUR_DATE'];
        $data['default_generation_month'] = date('Y-m', strtotime($default_due_date));
        $data['available_generation_month'] = $data['default_generation_month'];
        $data['generations'] = $this->due_generation->get_generations();
        $this->load->model('sms_service_model', 'sms_service');
        $data['sms_settings'] = $this->sms_service->get_settings();
        $data['generated_cycle_dates'] = array();
        foreach ($data['generations'] as $generation) {
            $data['generated_cycle_dates'][] = $generation->due_cycle_date;
        }
        $this->load->view('due-generation/index', $data);
    }

    public function save_settings()
    {
        $this->permission_check_with_msg('payment_management_record');
        $result = $this->due_generation->save_settings($this->input->post('cost_cutting_day'), $this->data);
        $this->session->set_flashdata($result ? 'success' : 'error', $result ? 'DueFlow settings saved.' : 'Cost Cutting Date must be between 1 and 31.');
        redirect(base_url('due_generation'), 'refresh');
    }

    public function generate()
    {
        $this->permission_check_with_msg('payment_management_record');
        $month = trim((string) $this->input->post('generation_month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month) || !checkdate((int) substr($month, 5, 2), 1, (int) substr($month, 0, 4))) show_error('Select a valid generation month.', 400);
        $settings = $this->due_generation->get_settings();
        $opening = $this->due_generation->get_opening_generation();
        $available_month = $opening
            ? date('Y-m', strtotime($this->due_generation->get_next_generation_due_date($settings->cost_cutting_day)))
            : date('Y-m', strtotime($this->data['CUR_DATE']));
        if ($month !== $available_month) {
            $this->session->set_flashdata('error', 'Only ' . date('F Y', strtotime($available_month . '-01')) . ' is currently available for DueFlow generation.');
            redirect(base_url('due_generation'), 'refresh');
        }
        $result = $this->due_generation->create_generation($month . '-01', $this->data);
        if ($result['success']) {
            redirect(base_url('due_generation/details/' . $result['generation_id']) . '?generated=1', 'refresh');
        }
        $this->session->set_flashdata('error', $result['message']);
        redirect(base_url('due_generation'), 'refresh');
    }

    public function download($id)
    {
        $this->permission_check('payment_management_view');
        $generation = $this->due_generation->get_generation($id);
        if (!$generation) show_404();
        $this->due_generation->mark_sent($generation->id);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . basename($generation->file_name) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Customer Number', 'Customer Name', 'Previous Outstanding', 'New Due', 'Cutting Amount'));
        foreach ($this->due_generation->get_items($generation->id) as $item) {
            fputcsv($output, array($item->customer_number, $item->customer_name, $item->previous_outstanding_amount, $item->new_due_amount, $item->total_due_amount));
        }
        fclose($output);
        exit;
    }

    public function reconcile($id)
    {
        $this->permission_check_with_msg('payment_management_record');
        if (empty($_FILES['result_file']['name'])) show_error('Please select the successful-cutting CSV file.', 400);
        $config = array('upload_path' => './uploads/csv/due-generation/results/', 'allowed_types' => 'csv', 'max_size' => 10240, 'file_ext_tolower' => true, 'encrypt_name' => true);
        if (!is_dir(FCPATH . 'uploads/csv/due-generation/results/') && !@mkdir(FCPATH . 'uploads/csv/due-generation/results/', 0755, true)) show_error('The result CSV directory could not be created.', 500);
        $this->load->library('upload', $config);
        if (!$this->upload->do_upload('result_file')) show_error(strip_tags($this->upload->display_errors()), 400);
        $upload = $this->upload->data();
        $result = $this->due_generation->reconcile((int) $id, $upload['full_path'], $this->data);
        if (!$result['success']) @unlink($upload['full_path']);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('due_generation/details/' . (int) $id), 'refresh');
    }

    public function details($id)
    {
        $this->permission_check('payment_management_view');
        $data = $this->data;
        $data['page_title'] = 'Due Generation Details';
        $data['generation'] = $this->due_generation->get_generation($id);
        if (!$data['generation']) show_404();
        $data['generation_success_message'] = $this->input->get('generated', true) === '1'
            ? 'DueFlow generated successfully.'
            : '';
        $data['due_generation_title'] = $data['generation']->generation_type === 'Opening Balance'
            ? 'Initial Due Setup Details'
            : date('F Y', strtotime($data['generation']->due_cycle_date)) . ' Due Generation Details';
        $data['items'] = $this->due_generation->get_items($id);
        $this->load->view('due-generation/details', $data);
    }

    public function sms_preview($id)
    {
        $this->permission_check('send_sms');
        $this->load->model('sms_service_model', 'sms_service');
        echo json_encode($this->sms_service->preview_due_generation((int) $id));
    }

    public function send_sms($id)
    {
        $this->permission_check_with_msg('send_sms');
        $this->load->model('sms_service_model', 'sms_service');
        $result = $this->sms_service->send_due_generation_manual((int) $id, $this->data);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('due_generation'), 'refresh');
    }
}
