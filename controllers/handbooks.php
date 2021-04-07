<?php
if (!defined('BASEPATH'))
    exit('No direct script access allowed');
session_start(); //we need to call PHP's session object to access it through CI
class Handbooks extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->lang->load('smartcard', 'english');
        $this->load->helper('language');
        $this->load->library('session');
        $this->load->model('smartcard_config_model');
        $this->load->helper('url');
        $this->load->helper('form');
        $this->load->model('organisation_model');
        $this->load->model('config_model');
        $this->load->model('handbooks_model');
        $this->load->model('handbook_documents_model');
        $this->load->model('handbooks_change_log_model');
        // check operator permission
        $this->transportlib->check_operator_permission();
        $da = $this->session->userdata('logged_in');
        $this->account_info_id = isset($da['account_info_id']) ? $da['account_info_id'] : IS_DEACTIVE;
    }
    function create_driver_handbook()
    {

        $da = $this->session->userdata('logged_in');
        $data['current_user'] = $da;
        $data['config_data'] = json_encode($this->smartcard_config_model->get_smartcard_travel_timeframe_configs());
        $data['free_travel_config_data'] = json_encode($this->smartcard_config_model->get_smartcard_free_travel_configs());
        $organisations = $this->organisation_model->get_organisation();
        $data['organisations'] = json_encode($organisations ?: []);
        $data['is_show_organisation'] = json_encode(count($organisations) > 0 && !isset($da['organisation_id']));
        $this->load->view('admin/add_driver_handbook', $data);
    }
    function index()
    {
        $da = $this->session->userdata('logged_in');
        $data['handbooks'] = json_encode($this->handbooks_model->get_handbooks());
        $this->load->view('admin/handbook', $data);
    }
    function get_handbook_driver()
    {
        $da = $this->session->userdata('logged_in');
        echo json_encode($this->handbooks_model->get_handbooks());
    }
    function get_change_log()
    {
        $handbook_id = json_decode(file_get_contents('php://input'), true)['handbook_id'];
        try {
            $this->handbooks_change_log_model->get_change_logs($handbook_id);
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }

        echo json_encode($result);
    }
    function get_changelog_detail()
    {
        $id = json_decode(file_get_contents('php://input'), true)['id'];
        try {
            $result['log'] = $this->handbooks_change_log_model->get_changelog_detail($id);
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }

        echo json_encode($result);
    }
    function delete_handbook()
    {
        $this->db->trans_begin();
        try {
            $handbook_id = json_decode(file_get_contents('php://input'), true)['handbook_id'];
            $change_logs_id = $this->handbooks_change_log_model->get_change_logs_id($handbook_id);
            if (!empty($change_logs_id)) {
                $this->handbooks_change_log_model->delete_change_logs_handbook_driver_agreement($change_logs_id);
            }
            $this->handbooks_change_log_model->delete_change_logs_handbook($handbook_id);
            $this->handbook_documents_model->delete_document_handbook($handbook_id);
            $this->handbooks_model->delete_handbook($handbook_id);
            $result['type'] = "success";
            $result['status'] = "success";
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
            echo json_encode($result);
            throw new Exception($e->getMessage());
            $this->db->trans_rollback();
        }
        echo json_encode($result);
    }
    public function create_handbook()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $id = $this->handbooks_model->create_handbook_from_data($data);
            $result['id'] = $id;
            $result['type'] = "success";
            $result['status'] = "success";
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }
        echo json_encode($result);
    }
    public function create_handbook_document()
    {
        $data_doc = $this->input->post();
        try {
            $data = array();
            if ($_FILES['file']['error'] == UPLOAD_ERR_OK) {
                $targetName = time() . $_FILES['file']['name'];
                $uploadDir = FCPATH . 'upload';
                $tmpName = $_FILES['file']['tmp_name'];
                $filePath = $uploadDir . '\\' . $targetName;
                $data['targetName'] = $targetName;

                if (move_uploaded_file($tmpName, $filePath)) {
                    $data_insert = [
                        'name' => $targetName,
                        'handbook_id' => $data_doc['handbook_id'],
                        'description' => $data_doc['description'],
                        'file' => "upload/" . $targetName,
                    ];

                    $this->handbook_documents_model->create_handbook_documents_from_data($data_insert);
                    $result['documents'] =  $this->handbook_documents_model->get_documents($data_doc['handbook_id']);
                    $result['type'] = "success";
                    $result['status'] = "success";
                }
            }
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }
        echo json_encode($result);
    }
    public function get_handbook_details()
    {
        $handbook_id = json_decode(file_get_contents('php://input'), true);
        $id = $handbook_id['handbook_id'];
        try {
            $result['handbook'] = $this->handbooks_model->get_handbook_details($id);
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }
        echo json_encode($result);
    }
    public function view_edit_handbook($handbook_id)
    {
        $data['handbook_id'] = $handbook_id;
        $this->load->view('admin/edit_driver_handbook', $data);
    }
    function update_handbook()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'];
        try {
            $this->handbooks_model->update_handbook_from_data($id, $data);
            $result['type'] = "success";
            $result['status'] = "success";
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }
        echo json_encode($result);
    }
    function update_log()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'];
        try {
            $this->handbooks_change_log_model->update_change_logs($id, $data);
            $result['logs'] = $this->handbooks_change_log_model->get_change_logs($data['handbook_id']);
            $result['type'] = "success";
            $result['status'] = "success";
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }
        echo json_encode($result);
    }
    function update_document()
    {
        $data_doc = $this->input->post();
        try {
            $data = array();
            if (isset($_FILES['file']) && $_FILES['file'] && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
                $result = [];
                $targetName = time() . $_FILES['file']['name'];
                $uploadDir = FCPATH . 'upload';
                $tmpName = $_FILES['file']['tmp_name'];
                $filePath = $uploadDir . '\\' . $targetName;
                $data['targetName'] = $targetName;

                if (move_uploaded_file($tmpName, $filePath)) {
                    $data_update = [
                        'updated_at' => date("Y/m/d"),
                        'name' => $targetName,
                        'handbook_id' => $data_doc['handbook_id'],
                        'description' => $data_doc['description'],
                        'file' => "upload/" . $targetName,
                        'id' => $data_doc['id'],
                    ];
                    $this->handbook_documents_model->update_document($data_update);
                    $result['handbook'] = $this->handbook_documents_model->get_file_details($data_doc['handbook_id']);
                    $result['type'] = "success";
                    $result['status'] = "success";
                }
            } else {
                $data_update = [
                    'updated_at' => date("Y/m/d"),
                    'handbook_id' => $data_doc['handbook_id'],
                    'description' => $data_doc['description'],
                    'id' => $data_doc['id'],
                ];
                $this->handbook_documents_model->update_document($data_update);
                $result['handbook'] = $this->handbook_documents_model->get_file_details($data_doc['handbook_id']);
                $result['type'] = "success";
                $result['status'] = "success";
            }
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }
        echo json_encode($result);
    }
    function delete_log()
    {
        $log_id = json_decode(file_get_contents('php://input'), true)['handbook_id'];
        try {
            $this->handbooks_model->delete_handbook($log_id);
            $result['type'] = "success";
            $result['status'] = "success";
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }
        echo json_encode($result);
    }
    function delete_document()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $this->handbook_documents_model->delete_document($data['id']);
            $result['handbook'] = $this->handbook_documents_model->get_file_details($data['handbook_id']);
            $result['type'] = "success";
            $result['status'] = "success";
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }
        echo json_encode($result);
    }
    function viewFile()
    {
        if ($this->uri->segment(4)) {
            $file = FCPATH . 'upload\\' . $this->uri->segment(4);
            header('Content-type: application/pdf');
            header('Content-Disposition: inline');
            header('Content-Transfer-Encoding: binary');
            header('Accept-Ranges: bytes');
            @readfile($file);
        } else {
            echo "Can't open file";
        }
    }
    public function get_file_details()
    {
        $file = json_decode(file_get_contents('php://input'), true);
        $id = $file['file_id'];
        try {
            $result['handbook'] = $this->handbook_documents_model->get_file_detail($id);
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }
        echo json_encode($result);
    }
    public function create_log()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $this->handbooks_change_log_model->create_handbooks_change_log_from_data($data);
            $result['logs'] = $this->handbooks_change_log_model->get_change_logs($data['handbook_id']);
            $result['type'] = "success";
            $result['status'] = "success";
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }
        echo json_encode($result);
    }
}
