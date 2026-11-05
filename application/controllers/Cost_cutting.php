<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Cost_cutting extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load_global();
        $this->load->model('cost_cutting_model', 'cost_cutting');
    }

    public function index()
    {
        $this->permission_check('payment_management_view');
        $data = $this->data;
        $data['page_title'] = 'Canteen Cost Cutting';
        $data['batches'] = $this->cost_cutting->get_batches();
        foreach ($data['batches'] as $batch) {
            $batch->payment_period_label = $this->format_payment_period($batch->payment_period);
        }
        $this->load->model('sms_service_model', 'sms_service');
        $data['sms_settings'] = $this->sms_service->get_settings();
        $this->load->view('cost-cutting/index', $data);
    }

    public function download_example()
    {
        $this->permission_check('payment_management_view');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="cost_cutting_example.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('Customer Number', 'Customer Name', 'Cutting Amount'));
        fputcsv($output, array('BA-8938', 'Maj Tahsin Abdullah, Sigs', '4000'));
        fclose($output);
        exit;
    }

    public function download_uploaded($batch_id)
    {
        $this->permission_check('payment_management_view');
        $batch = $this->cost_cutting->get_batch((int) $batch_id);
        if (!$batch || empty($batch->file_path)) show_404();
        $file_path = FCPATH . ltrim($batch->file_path, '/\\');
        $uploads_path = realpath(FCPATH . 'uploads') . DIRECTORY_SEPARATOR;
        $resolved_path = realpath($file_path);
        if (!$resolved_path || strpos($resolved_path, $uploads_path) !== 0 || !is_file($resolved_path)) show_404();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . basename($batch->file_name) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($resolved_path);
        exit;
    }

    public function upload()
    {
        $this->permission_check_with_msg('payment_management_record');
        $period = date('Y-m', strtotime('first day of last month'));
        if (empty($_FILES['cutting_file']['name'])) {
            show_error('Please select a CSV file.', 400);
        }

        $config = array(
            'upload_path' => './uploads/csv/',
            'allowed_types' => 'csv',
            'max_size' => 10240,
            'file_ext_tolower' => true,
            'encrypt_name' => true,
        );
        $this->load->library('upload', $config);
        if (!$this->upload->do_upload('cutting_file')) {
            show_error(strip_tags($this->upload->display_errors()), 400);
        }
        $upload = $this->upload->data();
        $file_path = $upload['full_path'];
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            show_error('The uploaded CSV file could not be opened.', 400);
        }

        $header = fgetcsv($handle);
        $columns = $this->map_columns($header);
        if (($columns['customer'] === false && $columns['name'] === false) || $columns['cutting'] === false) {
            fclose($handle);
            @unlink($file_path);
            show_error('CSV must contain Customer Name or Customer Number and a Cutting Amount column.', 400);
        }

        $this->db->trans_begin();
        $batch_id = $this->cost_cutting->create_batch($period . '-01', $upload['client_name'], 'uploads/csv/' . $upload['file_name'], $this->data);
        $seen = array();
        $summary = array('total_records' => 0, 'valid_records' => 0, 'error_records' => 0, 'total_due_amount' => 0, 'total_cutting_amount' => 0, 'paid_count' => 0, 'partial_count' => 0, 'unpaid_count' => 0);
        $row_number = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            if (count(array_filter($row, 'strlen')) === 0) {
                continue;
            }
            $summary['total_records']++;
            $customer_number = trim((string) ($row[$columns['customer']] ?? ''));
            $customer_name = $columns['name'] === false ? null : trim((string) ($row[$columns['name']] ?? ''));
            $raw_cutting = trim((string) ($row[$columns['cutting']] ?? ''));
            $name_match_count = null;
            if ($columns['name'] !== false) {
                $name_match = $this->cost_cutting->find_customer_snapshot_by_name($customer_name);
                $snapshot = $name_match['snapshot'];
                $name_match_count = $name_match['match_count'];
            } else {
                $snapshot = $this->cost_cutting->get_customer_snapshot($customer_number);
            }
            if ($snapshot) {
                $customer_number = $snapshot['customer_number'];
            }
            $normalized_number = strtoupper($customer_number);
            $errors = array();
            $validation_message = null;
            $cutting_amount = null;

            if ($columns['name'] !== false) {
                if ($customer_name === '') {
                    $errors[] = 'Customer Name is required.';
                } elseif ($name_match_count === 0) {
                    $errors[] = 'No active customer matched this name.';
                } elseif ($name_match_count > 1) {
                    $errors[] = 'Customer name matched more than one active customer.';
                } elseif (!$snapshot) {
                    $errors[] = 'Matched customer is no longer active.';
                }
            } elseif ($customer_number === '') {
                $errors[] = 'Customer Number is required.';
            }
            if ($snapshot && $normalized_number === 'N/A') {
                $errors[] = 'N/A customers must be processed manually from the customer panel.';
            }
            if (($snapshot || $columns['name'] === false) && isset($seen[$normalized_number])) {
                $errors[] = 'Duplicate Customer Number.';
            } elseif ($snapshot || ($columns['name'] === false && $customer_number !== '')) {
                $seen[$normalized_number] = true;
            }
            if ($raw_cutting === '' || !is_numeric(str_replace(',', '', $raw_cutting))) {
                $errors[] = 'Cutting Amount must be numeric.';
            } else {
                $cutting_amount = (float) str_replace(',', '', $raw_cutting);
                if ($cutting_amount < 0) {
                    $errors[] = 'Cutting Amount cannot be negative.';
                }
            }

            if ($columns['name'] === false && !$snapshot && $customer_number !== '') {
                $errors[] = 'Customer Number Not Found.';
            }
            if (!$errors && ($processed_batch = $this->cost_cutting->get_completed_batch_for_customer($period . '-01', $customer_number)) !== null) {
                $errors[] = 'Customer already processed in batch ' . $processed_batch->batch_number . ' for this payment period.';
            }
            $error = implode(' ', $errors);
            $new_status = null;
            $due_amount = $snapshot ? $snapshot['due_amount'] : 0;
            if ($error === '') {
                $new_status = $cutting_amount >= $due_amount ? 'Paid' : ($cutting_amount > 0 ? 'Partial Paid' : 'Unpaid');
                if ($cutting_amount > $due_amount) {
                    $validation_message = 'Overpaid by ' . number_format($cutting_amount - $due_amount, 2) . '; processing will cap the applied amount at the current due.';
                }
                $summary['valid_records']++;
                $summary['total_due_amount'] += $due_amount;
                $summary['total_cutting_amount'] += $cutting_amount;
                $summary[$new_status === 'Partial Paid' ? 'partial_count' : strtolower($new_status) . '_count']++;
            } else {
                $summary['error_records']++;
            }

            if ($error !== '') {
                $unmatched_name = $columns['name'] !== false
                    && $customer_name !== ''
                    && $name_match_count !== 1
                    && count($errors) === 1;
                $error_type = 'Validation error';
                if ($unmatched_name) {
                    $error_type = $name_match_count > 1 ? 'Ambiguous customer name' : 'Unmatched customer name';
                }
                $this->cost_cutting->add_batch_error($batch_id, array(
                    'source_row_number' => $row_number,
                    'customer_number' => $customer_number !== '' ? $customer_number : null,
                    'customer_name' => $customer_name,
                    'cutting_amount' => $cutting_amount,
                    'error_type' => $error_type,
                    'error_message' => $error,
                    'blocks_processing' => $unmatched_name ? 0 : 1,
                ));
                continue;
            }

            $this->cost_cutting->add_item($batch_id, array(
                'source_row_number' => $row_number,
                'customer_id' => $snapshot['customer_id'],
                'customer_code' => $customer_number,
                'customer_name' => $customer_name,
                'due_amount' => $due_amount,
                'cutting_amount' => $cutting_amount,
                'previous_status' => $snapshot['status'],
                'previous_paid_amount' => $snapshot['paid_amount'],
                'new_status' => $new_status,
                'new_paid_amount' => $cutting_amount,
                'validation_status' => 'Valid',
                'validation_error' => $validation_message,
            ));
        }
        fclose($handle);
        $this->cost_cutting->update_batch_summary($batch_id, $summary);
        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            @unlink($file_path);
            show_error('Cost Cutting upload could not be saved.', 500);
        }
        $this->db->trans_commit();
        redirect(base_url('cost_cutting/review/' . $batch_id));
    }

    public function review($batch_id)
    {
        $this->permission_check('payment_management_view');
        $data = $this->data;
        $data['page_title'] = 'Review Cost Cutting';
        $data['batch'] = $this->cost_cutting->get_batch($batch_id);
        $data['items'] = $this->cost_cutting->get_items($batch_id);
        $data['batch_errors'] = $this->cost_cutting->get_batch_errors($batch_id);
        $data['blocking_error_count'] = $this->cost_cutting->get_blocking_batch_error_count($batch_id);
        if (!$data['batch']) {
            show_404();
        }
        $data['batch']->payment_period_label = $this->format_payment_period($data['batch']->payment_period);
        $this->load->model('sms_service_model', 'sms_service');
        $data['sms_settings'] = $this->sms_service->get_settings();
        $this->load->view('cost-cutting/review', $data);
    }

    public function error_customer_candidates($batch_id, $error_id)
    {
        $this->permission_check_with_msg('payment_management_record');
        $search = trim((string) $this->input->get('q', true));
        $candidates = $this->cost_cutting->get_error_customer_candidates(
            (int) $batch_id,
            (int) $error_id,
            $search
        );
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array('customers' => $candidates)));
    }

    public function error_customer_preview($batch_id, $error_id)
    {
        $this->permission_check_with_msg('payment_management_record');
        $customer_id = (int) $this->input->get('customer_id');
        $preview = $this->cost_cutting->preview_batch_error_customer(
            (int) $batch_id,
            (int) $error_id,
            $customer_id
        );
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array(
                'success' => $preview !== null,
                'preview' => $preview,
            )));
    }

    public function apply_error_customer($batch_id, $error_id)
    {
        $this->permission_check_with_msg('payment_management_record');
        if ($this->input->method(true) !== 'POST') {
            show_error('Manual Cost Cutting must be submitted with POST.', 405);
        }
        $result = $this->cost_cutting->apply_batch_error(
            (int) $batch_id,
            (int) $error_id,
            (int) $this->input->post('customer_id'),
            $this->data
        );
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('cost_cutting/review/' . (int) $batch_id), 'refresh');
    }

    public function sms_preview($batch_id)
    {
        $this->permission_check('send_sms');
        $this->load->model('sms_service_model', 'sms_service');
        echo json_encode($this->sms_service->preview_cost_cutting((int) $batch_id));
    }

    public function send_sms($batch_id)
    {
        $this->permission_check_with_msg('send_sms');
        $this->load->model('sms_service_model', 'sms_service');
        $result = $this->sms_service->send_cost_cutting_manual((int) $batch_id, $this->data);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('cost_cutting'), 'refresh');
    }

    public function process($batch_id)
    {
        $this->permission_check_with_msg('payment_management_record');
        if ($this->input->post('confirm') !== '1') {
            show_error('Cost Cutting processing was not confirmed.', 400);
        }
        $result = $this->cost_cutting->process_batch((int) $batch_id, $this->data);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('cost_cutting/review/' . (int) $batch_id), 'refresh');
    }

    private function map_columns($header)
    {
        $columns = array('customer' => false, 'cutting' => false, 'name' => false);
        foreach ((array) $header as $index => $value) {
            $key = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)));
            $key = preg_replace('/[^a-z0-9]/', '', $key);
            if (in_array($key, array('customernumber', 'customerno', 'number'), true)) $columns['customer'] = $index;
            if (in_array($key, array('cuttingamount', 'cutting', 'amount'), true)) $columns['cutting'] = $index;
            if (in_array($key, array('customername', 'name'), true)) $columns['name'] = $index;
        }
        return $columns;
    }

    private function format_payment_period($payment_period)
    {
        $months = array(
            1 => 'JAN',
            2 => 'FEB',
            3 => 'MAR',
            4 => 'APR',
            5 => 'MAY',
            6 => 'JUN',
            7 => 'JUL',
            8 => 'AUG',
            9 => 'SEPT',
            10 => 'OCT',
            11 => 'NOV',
            12 => 'DEC',
        );
        $timestamp = strtotime($payment_period);
        return $months[(int) date('n', $timestamp)] . ',' . date('Y', $timestamp);
    }
}
