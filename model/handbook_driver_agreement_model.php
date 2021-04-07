<?php

require_once 'base_model.php';

class Handbook_driver_agreement_model extends Base_model
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

    function create_handbook_driver_agreement_from_data($data)
    {
        $lastid = 0;
        if ($this->db->insert('handbooks_driver_agreement', $data) == '1') {
            $lastid = $this->db->insert_id();
        }
        return $lastid;
    }
}
