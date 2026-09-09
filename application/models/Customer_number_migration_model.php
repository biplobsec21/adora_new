<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Customer_number_migration_model extends CI_Model
{
    public function get_customers()
    {
        return $this->db->select('id, customer_code, customer_name, customer_number')
            ->from('db_customers')
            ->order_by('id', 'ASC')
            ->get()
            ->result();
    }

    public function apply_numbers($updates)
    {
        $this->db->trans_begin();
        foreach ($updates as $update) {
            $this->db->where('id', (int) $update['id'])->update('db_customers', array(
                'customer_number' => $update['customer_number'],
            ));
        }
        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return true;
    }
}
