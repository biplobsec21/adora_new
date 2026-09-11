<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Eod extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load_global();
        $this->load->model('eod_model', 'eod');
    }

    public function index()
    {
        $this->permission_check('eod_view');
        // die($this->eod_start_date());
        $closing_date = $this->input->get('date', true);
        if ($closing_date === null || $closing_date === '') {
            $closing_date = $this->data['CUR_DATE'];
        }
        if (!$this->is_valid_date($closing_date) || $closing_date < $this->eod_start_date()) {
            $closing_date = $this->eod_start_date();
        }

        $data = $this->data;
        $data['page_title'] = 'End of Day';
        $data['summary'] = $this->eod->get_summary($closing_date);
        $data['adjustments'] = $this->permissions('eod_adjustment_view')
            ? $this->eod->get_adjustments($closing_date)
            : array();
        $data['audit_logs'] = $this->permissions('eod_audit_view')
            ? $this->eod->get_audit_logs($closing_date)
            : array();
        $data['pending_date'] = $this->eod->get_pending_date($this->data['CUR_DATE'], $this->eod_start_date());
        $data['late_collected_cash'] = $this->eod->get_late_collected_cash($closing_date);
        $this->load->view('eod/dashboard', $data);
    }

    public function report()
    {
        $this->permission_check('eod_report');
        $data = $this->data;
        $data['page_title'] = 'EOD Report';
        $this->load->view('eod/report', $data);
    }

    public function report_data()
    {
        $this->permission_check('eod_report');

        $from_date_input = $this->input->get('from_date', true);
        $to_date_input = $this->input->get('to_date', true);
        if ($from_date_input === null || $to_date_input === null) {
            $from_date_input = $this->input->post('from_date', true);
            $to_date_input = $this->input->post('to_date', true);
        }
        $from_date = system_fromatted_date($from_date_input);
        $to_date = system_fromatted_date($to_date_input);
        if (!$this->is_valid_date($from_date) || !$this->is_valid_date($to_date) || $from_date > $to_date) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(array('error' => 'Invalid date range.')));
        }

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array('data' => $this->eod->get_report($from_date, $to_date))));
    }

    public function details()
    {
        $this->permission_check('eod_view');

        $type = $this->input->get('type', true);
        $closing_date = $this->input->get('date', true);
        $page = (int) $this->input->get('page', true);
        if (!$this->is_valid_date($closing_date) || $closing_date < $this->eod_start_date()) {
            $closing_date = $this->eod_start_date();
        }
        if ($page < 1) {
            $page = 1;
        }

        $result = $this->eod->get_detail_page($type, $closing_date, $page, 50);
        if ($result === false) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(array('error' => 'Invalid detail type.')));
        }

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function add_adjustment()
    {
        $this->permission_check_with_msg('eod_adjustment_add');

        $closing_date = $this->input->post('closing_date', true);
        $type = $this->input->post('type', true);
        $amount = $this->input->post('amount', true);
        $note = trim((string) $this->input->post('note', true));

        if (!$this->can_modify_date($closing_date) || !in_array($type, array('Addition', 'Deduction'), true) || !is_numeric($amount) || (float) $amount <= 0) {
            show_error('Invalid EOD adjustment request.', 400);
        }
        $is_override = $this->is_closed($closing_date);
        if ($is_override && !$this->permissions('eod_edit_closed_data')) {
            show_error('This date is already closed. An audited override workflow is required.', 403);
        }

        if ($this->eod->add_adjustment($closing_date, $type, (float) $amount, $note, $this->data['CUR_USERNAME'], $this->data['SYSTEM_IP'], $is_override)) {
            $this->session->set_flashdata('success', 'Cash adjustment added successfully.');
        } else {
            $this->session->set_flashdata('error', 'Cash adjustment could not be added.');
        }
        redirect(base_url('eod?date=' . rawurlencode($closing_date)), 'refresh');
    }

    public function close_day()
    {
        $this->permission_check_with_msg('eod_close');

        $closing_date = $this->input->post('closing_date', true);
        if (!$this->can_modify_date($closing_date)) {
            show_error('You do not have permission to close this date.', 403);
        }

        $closing_type = ($closing_date === $this->data['CUR_DATE']) ? 'Regular' : 'Retroactive';
        $result = $this->eod->close_day($closing_date, $closing_type, $this->data['CUR_USERNAME'], $this->data['SYSTEM_IP']);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('eod?date=' . rawurlencode($closing_date)), 'refresh');
    }

    public function update_adjustment()
    {
        $this->permission_check_with_msg('eod_edit_closed_data');
        $id = (int) $this->input->post('id');
        $closing_date = $this->input->post('closing_date', true);
        $type = $this->input->post('type', true);
        $amount = $this->input->post('amount', true);
        $note = trim((string) $this->input->post('note', true));
        if (!in_array($type, array('Addition', 'Deduction'), true) || !is_numeric($amount) || (float) $amount <= 0) {
            show_error('Invalid EOD adjustment request.', 400);
        }
        $result = $this->eod->update_adjustment($id, $type, (float) $amount, $note, $this->data['CUR_USERNAME'], $this->data['SYSTEM_IP']);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('eod?date=' . rawurlencode($closing_date)), 'refresh');
    }

    public function delete_adjustment()
    {
        $this->permission_check_with_msg('eod_edit_closed_data');
        $id = (int) $this->input->post('id');
        $closing_date = $this->input->post('closing_date', true);
        $result = $this->eod->delete_adjustment($id, $this->data['CUR_USERNAME'], $this->data['SYSTEM_IP']);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('eod?date=' . rawurlencode($closing_date)), 'refresh');
    }

    private function can_modify_date($closing_date)
    {
        if (!$this->is_valid_date($closing_date) || $closing_date < $this->eod_start_date()) {
            return false;
        }
        return $closing_date === $this->data['CUR_DATE'] || $this->permissions('eod_retroactive_close');
    }

    private function eod_start_date()
    {
        return config_item('eod_start_date') ?: $this->data['CUR_DATE'];
    }

    private function is_closed($closing_date)
    {
        return $this->db->where('closing_date', $closing_date)->count_all_results('db_daily_closing') > 0;
    }

    private function is_valid_date($date)
    {
        $parsed_date = DateTime::createFromFormat('Y-m-d', (string) $date);
        return $parsed_date && $parsed_date->format('Y-m-d') === $date;
    }
}
