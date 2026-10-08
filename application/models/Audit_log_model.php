<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Audit_log_model
 *
 * Global audit trail engine. Call from any other model:
 *
 *   // Before update — cache the current row
 *   $this->audit_log->snapshot('sales', $sales_id, 'db_sales');
 *
 *   // After insert / update / delete
 *   $this->audit_log->log('sales', 'create', $id, $sales_code, $new_data);
 *   $this->audit_log->log('sales', 'update', $id, $sales_code, $new_data);
 *   $this->audit_log->log('sales', 'delete', $id, $sales_code, null);
 */
class Audit_log_model extends CI_Model
{
    // Fields that must never be stored in old/new values
    private $excluded_fields = [
        'password', 'password_hash', 'token', 'remember_token',
        'csrf_token', 'secret', 'api_key',
    ];

    // In-memory snapshot cache: ['module:id' => [...row data...]]
    private $snapshots = [];

    public function __construct()
    {
        parent::__construct();
    }

    // ---------------------------------------------------------------
    // PUBLIC: snapshot()
    // Call BEFORE an update or delete to cache the current row state.
    // $table is the primary table for that module (e.g. 'db_sales').
    // ---------------------------------------------------------------
    public function snapshot($module, $record_id, $table)
    {
        $row = $this->db->select('*')
                        ->from($table)
                        ->where('id', (int) $record_id)
                        ->get()->row_array();

        if (!empty($row)) {
            $row = $this->_strip_excluded($row);
            $this->snapshots[$module . ':' . $record_id] = $row;
        }
    }

    // ---------------------------------------------------------------
    // PUBLIC: log()
    // Call AFTER the insert / update / delete completes.
    //
    // $module      — 'sales', 'purchase', 'damage', 'items', etc.
    // $action      — 'create' | 'update' | 'delete'
    // $record_id   — PK of the affected row
    // $record_code — human-readable code (sales_code, damage_code, …)
    // $new_data    — array of new values (null for delete)
    // ---------------------------------------------------------------
    public function log($module, $action, $record_id, $record_code, $new_data)
    {
        try {
            $old_data       = null;
            $changed_fields = null;

            $snapshot_key = $module . ':' . $record_id;

            if (isset($this->snapshots[$snapshot_key])) {
                $old_data = $this->snapshots[$snapshot_key];
                unset($this->snapshots[$snapshot_key]);
            }

            if ($new_data !== null) {
                $new_data = $this->_strip_excluded((array) $new_data);
            }

            // Compute changed fields for update events
            if ($action === 'update' && $old_data !== null && $new_data !== null) {
                $changed = [];
                foreach ($new_data as $key => $val) {
                    if (array_key_exists($key, $old_data) && (string)$old_data[$key] !== (string)$val) {
                        $changed[] = $key;
                    }
                }
                // Also catch fields in old but not in new (rare but possible)
                foreach ($old_data as $key => $val) {
                    if (!array_key_exists($key, $new_data)) {
                        $changed[] = $key;
                    }
                }
                $changed_fields = !empty($changed) ? json_encode(array_values(array_unique($changed))) : null;

                // Nothing actually changed — skip the log entry
                if (empty($changed)) {
                    return true;
                }
            }

            $entry = [
                'module'         => $module,
                'action'         => $action,
                'record_id'      => (int) $record_id,
                'record_code'    => $record_code,
                'old_values'     => ($old_data  !== null) ? json_encode($old_data)  : null,
                'new_values'     => ($new_data  !== null) ? json_encode($new_data)  : null,
                'changed_fields' => $changed_fields,
                'user_id'        => (int) $this->session->userdata('inv_userid'),
                'username'       => $this->session->userdata('inv_username'),
                'ip_address'     => $this->input->ip_address(),
                'user_agent'     => substr($this->input->user_agent(), 0, 500),
                'created_at'     => date('Y-m-d H:i:s'),
            ];

            return (bool) $this->db->insert('db_audit_log', $entry);

        } catch (Exception $e) {
            // Report the failure so the calling operation can decide whether to roll back.
            log_message('error', 'Audit log failed: ' . $e->getMessage());
            return false;
        }
    }

    // ---------------------------------------------------------------
    // DATATABLE QUERY
    // ---------------------------------------------------------------
    public function get_datatables()
    {
        $this->_build_query();
        if (isset($_POST['length']) && (int) $_POST['length'] !== -1) {
            $this->db->limit((int) $_POST['length'], (int) $_POST['start']);
        }
        return $this->db->get()->result();
    }

    public function count_filtered()
    {
        $this->_build_query();
        return $this->db->get()->num_rows();
    }

    public function count_all()
    {
        return (int) $this->db->select('COUNT(*) as total')->from('db_audit_log')->get()->row()->total;
    }

    private function _build_query()
    {
        $this->db->select('id, module, action, record_id, record_code, changed_fields, username, ip_address, created_at');
        $this->db->from('db_audit_log');

        // Filters
        $module     = $this->input->post('filter_module');
        $action     = $this->input->post('filter_action');
        $user       = $this->input->post('filter_user');
        $from_date  = $this->input->post('filter_from_date');
        $to_date    = $this->input->post('filter_to_date');
        $rec_code   = $this->input->post('filter_record_code');

        if (!empty($module))    { $this->db->where('module', $module); }
        if (!empty($action))    { $this->db->where('action', $action); }
        if (!empty($user))      { $this->db->where('username', $user); }
        if (!empty($rec_code))  { $this->db->like('record_code', $rec_code); }

        if (!empty($from_date)) {
            $this->db->where('DATE(created_at) >=', date('Y-m-d', strtotime($from_date)));
        }
        if (!empty($to_date)) {
            $this->db->where('DATE(created_at) <=', date('Y-m-d', strtotime($to_date)));
        }

        // Global search box
        if (!empty($_POST['search']['value'])) {
            $s = $_POST['search']['value'];
            $this->db->group_start();
            $this->db->like('module',      $s);
            $this->db->or_like('action',      $s);
            $this->db->or_like('record_code', $s);
            $this->db->or_like('username',    $s);
            $this->db->or_like('ip_address',  $s);
            $this->db->group_end();
        }

        // Order
        $col_map = ['id','module','action','record_id','record_code','changed_fields','username','ip_address','created_at'];
        if (isset($_POST['order'])) {
            $col_idx = (int) $_POST['order']['0']['column'];
            $col     = isset($col_map[$col_idx]) ? $col_map[$col_idx] : 'id';
            $dir     = strtoupper($_POST['order']['0']['dir']) === 'ASC' ? 'ASC' : 'DESC';
            $this->db->order_by($col, $dir);
        } else {
            $this->db->order_by('id', 'DESC');
        }
    }

    // ---------------------------------------------------------------
    // SINGLE RECORD FOR DETAIL VIEW
    // ---------------------------------------------------------------
    public function get_by_id($id)
    {
        return $this->db->select('*')->from('db_audit_log')->where('id', (int) $id)->get()->row();
    }

    // ---------------------------------------------------------------
    // RECORD-SPECIFIC HISTORY (all audit entries for one record)
    // ---------------------------------------------------------------
    public function get_record_history($module, $record_id)
    {
        return $this->db->select('id, action, changed_fields, username, ip_address, created_at')
                        ->from('db_audit_log')
                        ->where('module', $module)
                        ->where('record_id', (int) $record_id)
                        ->order_by('id', 'DESC')
                        ->get()->result();
    }

    // ---------------------------------------------------------------
    // EXPORT DATA (for CSV/Excel)
    // ---------------------------------------------------------------
    public function get_export_data($filters = [])
    {
        $this->db->select('module, action, record_id, record_code, changed_fields, old_values, new_values, username, ip_address, user_agent, created_at');
        $this->db->from('db_audit_log');

        if (!empty($filters['module']))    { $this->db->where('module', $filters['module']); }
        if (!empty($filters['action']))    { $this->db->where('action', $filters['action']); }
        if (!empty($filters['username']))  { $this->db->where('username', $filters['username']); }
        if (!empty($filters['from_date'])) { $this->db->where('DATE(created_at) >=', $filters['from_date']); }
        if (!empty($filters['to_date']))   { $this->db->where('DATE(created_at) <=', $filters['to_date']); }

        $this->db->order_by('id', 'DESC');
        return $this->db->get()->result();
    }

    // ---------------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------------
    private function _strip_excluded(array $data)
    {
        foreach ($this->excluded_fields as $field) {
            unset($data[$field]);
        }
        return $data;
    }

    // Convenience: compute a human-readable diff array for display.
    // Handles both flat rows and document format {'header':{...},'items':[...]}
    public function build_diff($old_json, $new_json)
    {
        $old = $old_json ? json_decode($old_json, true) : [];
        $new = $new_json ? json_decode($new_json, true) : [];

        // Detect document format
        $is_document = isset($old['header']) || isset($new['header']);

        if ($is_document) {
            return $this->_build_document_diff($old, $new);
        }

        // Flat row diff (simple modules: customers, suppliers, roles, etc.)
        return $this->_build_flat_diff($old, $new);
    }

    private function _build_flat_diff(array $old, array $new)
    {
        $all_keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($all_keys);

        $diff = [];
        foreach ($all_keys as $key) {
            $old_val = array_key_exists($key, $old) ? $old[$key] : null;
            $new_val = array_key_exists($key, $new) ? $new[$key] : null;
            $diff[]  = [
                'field'   => $key,
                'old'     => $old_val,
                'new'     => $new_val,
                'changed' => ((string)$old_val !== (string)$new_val),
                'type'    => 'field',
            ];
        }
        return $diff;
    }

    private function _build_document_diff(array $old_doc, array $new_doc)
    {
        $diff = [];

        // --- Header field diff ---
        $old_h = $old_doc['header'] ?? [];
        $new_h = $new_doc['header'] ?? [];
        $all_keys = array_unique(array_merge(array_keys($old_h), array_keys($new_h)));
        sort($all_keys);

        foreach ($all_keys as $key) {
            $old_val = array_key_exists($key, $old_h) ? $old_h[$key] : null;
            $new_val = array_key_exists($key, $new_h) ? $new_h[$key] : null;
            $diff[]  = [
                'field'   => $key,
                'old'     => $old_val,
                'new'     => $new_val,
                'changed' => ((string)$old_val !== (string)$new_val),
                'type'    => 'field',
            ];
        }

        // --- Items diff ---
        $old_items = $old_doc['items'] ?? [];
        $new_items = $new_doc['items'] ?? [];

        $items_json_old = json_encode($old_items);
        $items_json_new = json_encode($new_items);
        $items_changed  = ($items_json_old !== $items_json_new);

        if ($items_changed || !empty($old_items) || !empty($new_items)) {
            // Index new and old items by item_id for side-by-side comparison
            $old_by_item = [];
            foreach ($old_items as $row) {
                $key = $row['item_id'] ?? null;
                if ($key) { $old_by_item[$key] = $row; }
            }
            $new_by_item = [];
            foreach ($new_items as $row) {
                $key = $row['item_id'] ?? null;
                if ($key) { $new_by_item[$key] = $row; }
            }

            $all_item_ids = array_unique(array_merge(array_keys($old_by_item), array_keys($new_by_item)));

            $item_rows = [];
            foreach ($all_item_ids as $item_id) {
                $old_row = $old_by_item[$item_id] ?? null;
                $new_row = $new_by_item[$item_id] ?? null;

                // Format a readable summary for each item row
                $old_summary = $old_row ? $this->_format_item_summary($old_row) : null;
                $new_summary = $new_row ? $this->_format_item_summary($new_row) : null;

                $status = 'unchanged';
                if (!$old_row)                         { $status = 'added'; }
                elseif (!$new_row)                     { $status = 'removed'; }
                elseif ($old_summary !== $new_summary) { $status = 'changed'; }

                $item_rows[] = [
                    'item_id' => $item_id,
                    'old'     => $old_summary,
                    'new'     => $new_summary,
                    'status'  => $status,
                ];
            }

            $diff[] = [
                'field'      => 'items',
                'old'        => null,  // displayed via item_rows
                'new'        => null,
                'changed'    => $items_changed,
                'type'       => 'items',
                'item_rows'  => $item_rows,
            ];
        }

        return $diff;
    }

    private function _format_item_summary(array $row)
    {
        // Pick the most meaningful columns available
        $name  = $row['item_name']     ?? ('Item #' . ($row['item_id'] ?? '?'));
        $qty   = $row['sales_qty']     ?? $row['purchase_qty']  ?? $row['damage_qty']  ?? $row['return_qty']  ?? '?';
        $price = $row['price_per_unit'] ?? $row['unit_cost']    ?? $row['unit_total_cost'] ?? '?';
        $total = $row['total_cost']    ?? $row['total_value']   ?? '?';
        return "Qty:{$qty} | Price:{$price} | Total:{$total} | {$name}";
    }
}
