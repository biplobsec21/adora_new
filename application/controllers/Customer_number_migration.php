<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Customer_number_migration extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load_global();
        $this->load->model('customer_number_migration_model', 'migration');
    }

    public function index()
    {
        $this->permission_check('customers_edit');
        $data = $this->data;
        $data['page_title'] = 'Customer Number Migration';
        $data['customers'] = $this->build_preview($this->migration->get_customers());
        $this->load->view('customer-number-migration/index', $data);
    }

    public function apply()
    {
        $this->permission_check_with_msg('customers_edit');
        if ($this->input->post('confirm') !== '1') {
            show_error('Migration was not confirmed.', 400);
        }
        $updates = array();
        $changed_count = 0;
        foreach ($this->migration->get_customers() as $customer) {
            $customer_number = $this->extract_number($customer->customer_name);
            if ((string) $customer->customer_number !== $customer_number) {
                $changed_count++;
            }
            $updates[] = array(
                'id' => $customer->id,
                'customer_number' => $customer_number,
            );
        }
        if (!$this->migration->apply_numbers($updates)) {
            show_error('Customer number migration failed.', 500);
        }
        $this->session->set_flashdata('success', 'Customer number migration completed. ' . $changed_count . ' customer record(s) updated.');
        redirect(base_url('customer_number_migration'), 'refresh');
    }

    private function build_preview($customers)
    {
        foreach ($customers as $customer) {
            $customer->proposed_number = $this->extract_number($customer->customer_name);
            $customer->changed = $customer->customer_number !== $customer->proposed_number;
        }
        return $customers;
    }

    private function extract_number($customer_name)
    {
        $name = trim((string) $customer_name);
        if (preg_match('/^([0-9]+)\b/', $name, $matches)) {
            return $matches[1];
        }
        if (preg_match('/^([A-Za-z]+-[0-9]+)\b/i', $name, $matches)) {
            return strtoupper($matches[1]);
        }
        return 'N/A';
    }
}
