<?php

require_once 'base_model.php';

class Handbook_documents_model extends Base_model
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

    function create_handbook_documents_from_data($data)
    {
        $lastid = 0;
        if ($this->db->insert('handbooks_documents', $data) == '1') {
            $lastid = $this->db->insert_id();
        }
        return $lastid;
    }
    function get_documents($handbook_id)
    {
        $data = $this->db->where('handbook_id', $handbook_id)->get('handbooks_documents')->result_array();
        return $data;
    }
    function get_file_detail($id)
    {
        $data = $this->db->where('id', $id)->limit(1)->get('handbooks_documents')->result_array();
        return $data;
    }
    function get_file_details($id)
    {
        $data = $this->db->where('handbook_id', $id)->get('handbooks_documents')->result_array();
        return $data;
    }
    public function update_document($data)
    {
        $this->db->set($data, FALSE);
        $this->db->where("id =", $data['id']);
        $data = $this->db->update('handbooks_documents', $data);
        return $data;
    }
    function delete_document($id)
    {
        $this->db->where('id', $id);
        $data = $this->db->delete('handbooks_documents');
        return $data;
    }
    function delete_document_handbook($handbook_id)
    {
        $this->db->where('handbook_id', $handbook_id);
        $data = $this->db->delete('handbooks_documents');
        return $data;
    }
}
