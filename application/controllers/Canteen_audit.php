<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Canteen_audit extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load_global();
        $this->load->model('canteen_audit_model', 'audit');
    }

    public function index()
    {
        $this->permission_check('payment_management_view');
        $month = $this->valid_month($this->input->get('month', true)) ?: date('Y-m');
        $data = $this->data;
        $data['page_title'] = 'Canteen Audit History';
        $data['selected_month'] = $month;
        $data['month_options'] = $this->audit->get_month_options();
        $data['history'] = $this->audit->get_history($month);
        $this->load->view('canteen-audit/index', $data);
    }

    public function download()
    {
        $this->permission_check('payment_management_view');
        $month = $this->valid_month($this->input->get('month', true)) ?: date('Y-m');
        $history = $this->audit->get_history($month);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="canteen-audit-' . $month . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Event Type', 'Period', 'Reference', 'Event Date', 'Operator', 'Records', 'Total Due', 'Total Cut', 'Remaining', 'Status'));
        foreach ($history as $row) fputcsv($output, array($row['event_type'], $row['period'], $row['reference'], $row['event_at'], $row['operator'], $row['records'], $row['total_due'], $row['total_cut'], $row['remaining'], $row['status']));
        fclose($output);
        exit;
    }

    public function download_due_generation($id)
    {
        $this->permission_check('payment_management_view');
        $this->load->model('due_generation_model', 'due_generation');
        $generation = $this->due_generation->get_generation((int) $id);
        if (!$generation) show_404();
        $this->due_generation->mark_sent($generation->id);
        $filename = date('F', strtotime($generation->due_cycle_date)) . '-audit-due-generation.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
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

    public function download_cost_cutting($id)
    {
        $this->permission_check('payment_management_view');
        $this->load->model('cost_cutting_model', 'cost_cutting');
        $batch = $this->cost_cutting->get_batch((int) $id);
        if (!$batch || empty($batch->file_path)) show_404();
        $file_path = FCPATH . ltrim($batch->file_path, '/\\');
        $uploads_path = realpath(FCPATH . 'uploads') . DIRECTORY_SEPARATOR;
        $resolved_path = realpath($file_path);
        if (!$resolved_path || strpos($resolved_path, $uploads_path) !== 0 || !is_file($resolved_path)) show_404();
        $filename = date('F', strtotime($batch->payment_period)) . '-audit-cost-cutting.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($resolved_path);
        exit;
    }

    private function valid_month($month)
    {
        return preg_match('/^\d{4}-\d{2}$/', (string) $month) && checkdate((int) substr($month, 5, 2), 1, (int) substr($month, 0, 4)) ? $month : false;
    }
}
