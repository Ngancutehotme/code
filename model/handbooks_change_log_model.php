<?php

require_once 'base_model.php';

class Handbooks_change_log_model extends Base_model
{
    protected $session_login;
    protected $account_info_id;
    public function __construct()
    {
        parent::__construct();
        $this->load->model('custom_model');

        $this->load->helper(array('form', 'url'));
        $session_data = $this->session->userdata('logged_in');
        $this->account_info_id = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : '';
        $this->session_login = $session_data;
        $this->sort_ticket_ability = isset($session_data['sort_ticket_ability']) ? $session_data['sort_ticket_ability'] : 0;
    }

    function create_handbooks_change_log_from_data($data)
    {
        $lastid = 0;
        if ($this->db->insert('handbooks_change_log', $data) == '1') {
            $lastid = $this->db->insert_id();
        }
        return $lastid;
    }

    function check_driver_read($driver_id, $bus_id)
    {
        if (!(int) $driver_id) {
            return 0;
        }
        $query = "	
        select * from (select MAX(id) as latest_change_log_id, handbook_id from handbooks_change_log group by handbook_id) as new_change_log inner join handbooks on new_change_log.handbook_id = handbooks.id
        where latest_change_log_id not in (select change_log_id from handbooks_driver_agreement where driver_id = " . $driver_id . ")";


        if ($bus_id != null) {
            if (!(int) $bus_id) {
                return 0;
            }
            $query .= " and (handbooks.buses like '%\"" . (string) $bus_id . "\"%' or type = 'driver' )";
        }

        $handbooks_not_read = $this->db->query($query)->result_array();
        for ($i = 0; $i < count($handbooks_not_read); $i++) {
            $handbooks_not_read[$i]['document'] = $this->db->where('handbook_id', $handbooks_not_read[$i]['handbook_id'])->get('handbooks_documents')->result_array();
        }
        return $handbooks_not_read;
    }

    function get_change_logs($handbook_id)
    {
        $data = $this->db->where('handbook_id', $handbook_id)->get('handbooks_change_log')->result_array();
        return $data;
    }
    public function update_change_logs($id, $data)
    {
        $this->db->set('updated_at', 'NOW()', FALSE);
        $this->db->where("id =", $id);
        return $this->db->update('handbooks_change_log', $data);
    }
    function delete_change_logs($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete('handbooks_change_log');
    }
    function delete_change_logs_handbook($handbook_id)
    {
        $this->db->where('handbook_id', $handbook_id);
        return $this->db->delete('handbooks_change_log');
    }
    function get_changelog_detail($id)
    {
        $data = $this->db->where('id', $id)->limit(1)->get('handbooks_change_log')->result_array();
        return $data;
    }
    function get_change_logs_id($handbook_id)
    {
        $this->db->select("id");
        $this->db->where('handbook_id', $handbook_id);
        $this->db->from('handbooks_change_log');
        return $this->db->get()->result_array();
    }
    function delete_change_logs_handbook_driver_agreement($change_logs_id)
    {
        $array_id = [];
        foreach($change_logs_id as $change_log_id){
            array_push($array_id, $change_log_id['id']);
        };
        $this->db->where_in('change_log_id', $array_id);
        return $this->db->delete('handbooks_driver_agreement');
    }
}
