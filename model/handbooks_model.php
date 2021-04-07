<?php

require_once 'base_model.php';

class Handbooks_model extends Base_model
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

    function create_handbook_from_data($data)
    {

        $lastid = 0;
        if ($this->db->insert('handbooks', $data) == '1') {
            $lastid = $this->db->insert_id();
        }
        return $lastid;
    }
    public function update_handbook_from_data($handbook_id, $data)
    {
        $this->db->set('updated_at', 'NOW()', FALSE);
        $this->db->where("id =", $handbook_id)->update('handbooks', $data);
    }
    function get_handbooks()
    {
        $drivers = $this->db->where('type', 'driver')->get('handbooks')->result_array();
        $vehicles = $this->db->where('type', 'vehicle')->get('handbooks')->result_array();
        $data = [
            'driver' => $drivers,
            'vehicle' => $vehicles,
        ];
        return $data;
    }
    function get_handbook_details($handbook_id)
    {
        $data['handbook_details'] = $this->db->where('id', $handbook_id)->limit(1)->get('handbooks')->row_array();
        $data['documents'] = $this->handbook_documents_model->get_documents($handbook_id);
        $data['change_logs'] = $this->handbooks_change_log_model->get_change_logs($handbook_id);

        return $data;
    }
    function delete_handbook($handbook_id)
    {
        $this->db->where('id', $handbook_id);
        $data = $this->db->delete('handbooks');
        return $data;
    }

    function get_all_handbook(){
        $this->db->select('id, name, type, description, updated_at');
        $query = $this->db->get('handbooks');
        $data['handbooks'] = $query->result_array();
        for ($i = 0; $i < count($data['handbooks']); $i++) {
            $data['handbooks'][$i]['documents'] = $this->db->select('id, name, file, description')->where('handbook_id', $data['handbooks'][$i]['id'])->get('handbooks_documents')->result_array();
            $data['handbooks'][$i]['change_logs'] = $this->db->get('handbooks_change_log')->result_array();
        }
        return $data;
    }
}
