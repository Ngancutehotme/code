<?php
require_once 'base_model.php';
class Admin_model extends Base_model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database(MASTER_DB);
        $this->load->model('custom_model');
        $this->load->helper('url');
        $this->load->helper('form');

        $this->load->library('db_manager');
    }

    function login($username, $password)
    {
        $db2 = $this->db_manager->get_connection(MASTER_DB);

        $db2->where('email', $username);
        $db2->where('password', MD5($password));

        $db2->limit(1);
        $query = $db2->get('company_master_table');


        if ($query->num_rows() == 1) {
            return $query->result();
        } else {
            /**
             * JIRA TSM-495
             * nghi.doan
             */
            return $this->check_user_login($username, $password);
        }
    }



    /**
     * @JIRA TSM-495
     * check_user_login check if user login
     */
    public function check_user_login($email, $pass, $account_info_id = null)
    {
        $this->load->library('feature_url');
        $db2 = $this->db_manager->get_connection(MASTER_DB);
        $db2->select('email as email_sub_admin, contact_person as name, company_id, password, organisation_id, id as id_sub_admin, LOWER(role) as type_sub_admin, security_2fa_activated');
        if ($account_info_id) {
            $db2->where('id', $account_info_id);
        } else {
            $db2->where('email', $email);
            $db2->where('password', md5($pass));
        }
        $db2->limit(1);
        $query = $db2->get('master_db.permission_account_info');
        $result = $query->row_array();

        if (!empty($result)) {
            $arr_results = [];
            $arr_parent_id = [];
            $db2->where('company_id', $result['company_id']);
            $query_master = $db2->get('company_master_table');
            $company_info = $query_master->result();
            $company_info[0]->security_2fa_activated = $result['security_2fa_activated'];
            // get list role
            $this->db = $this->db_manager->get_connection($company_info[0]->db_name);
            $this->db->select('pf.id, account_info_id, feature_id, LOWER(feature_name) as feature_name, action, feature_special, parent_id');
            $this->db->from('permission_role pr');
            $this->db->join('master_db.permission_feature pf', 'pf.id = pr.feature_id', 'LEFT');
            $this->db->where('pr.account_info_id', $result['id_sub_admin']);
            $this->db->where('pr.status', 1);
            $role_info = $this->db->get()->result_array();

            foreach ($role_info as $key => $value) {
                if (!in_array($value['parent_id'], $arr_parent_id)) {
                    $arr_parent_id[] = (($value['parent_id'] == 0) ? $value['id'] : $value['parent_id']);
                }
                $parent_key  = ($value['parent_id'] == 0) ? $value['id'] : $value['parent_id'];
                if ($value['parent_id'] != 0) {
                    if (!array_key_exists($parent_key, $arr_results)) {
                        $arr_results[$parent_key] = [
                            str_replace(' ', '', $value['feature_name']) => $value['action']
                        ];
                    } else {
                        $arr_results[$parent_key] =  array_merge($arr_results[$parent_key], [str_replace(' ', '', $value['feature_name']) => $value['action']]);
                    }
                } else {
                    //Please check before you have any changes here.
                    //Here, we used feature_name in the permission_feature table with the Zone with id 7 to compare PERMISSION_ZONE in base_constants to check and add the action value to the Zone.
                    //The check command may be wrong when the name 'Zone' is changed in the permission_feature table.
                    //Therefore, it is best not to change its feature_name.
                    if (str_replace(' ', '', $value['feature_name']) == PERMISSION_ZONE) {
                        $arr_results[$parent_key] = [str_replace(' ', '', $value['feature_name']) => $value['action']];
                    } else {
                        $arr_results[$parent_key] = [];
                    }
                }
            }
            $company_info[0]->account_info_id = $result['id_sub_admin'];
            $company_info[0]->contact_person = $result['name'];
            $company_info[0]->organisation_id = $result['organisation_id'];
            $company_info[0]->url_redirect = '';
            if (!empty($arr_parent_id)) {
                $arr_role = [];
                $this->db->select('pf.id, LOWER(pf.feature_name) as feature_name');
                $this->db->where_in('id', $arr_parent_id);
                $list_permission = $this->db->get('master_db.permission_feature pf')->result_array();
                foreach ($list_permission as $key => $value) {
                    $arr_role = array_merge($arr_role, [str_replace(' ', '', $value['feature_name']) => $arr_results[$value['id']]]);
                }
                $arr_url_redirect = feature_url::$arr_feature_url;
                if (array_key_exists(array_keys($arr_role)[0], $arr_url_redirect)) {
                    $el_first = array_values($arr_role)[0];
                    $company_info[0]->url_redirect = $arr_url_redirect[array_keys($arr_role)[0]][array_keys($el_first)[0]];
                }
                $arr_role = array_merge(
                    $arr_role,
                    [
                        'sub_operator_info' => [
                            'account_info_id' => $result['id_sub_admin'],
                            'account_name' => $result['name'],
                            'role' => strtolower($result['type_sub_admin'])
                        ]
                    ]
                );
            }
            if (!is_dir(FCPATH . 'application/cache/user')) {
                mkdir(FCPATH . 'application/cache/user', 0777, true);
            }
            // file cache exist
            if ($this->cache->file->get('user/' . $result['id_sub_admin'])) {
                // delete file cache
                $this->cache->file->delete('user/' . $result['id_sub_admin']);
            }
            // save file with new content and auto delete after 24 hours
            $this->cache->file->save('user/' . $result['id_sub_admin'], json_encode($arr_role), 86400);
            return $company_info;
        }
        return false;
    }

    public function set_subadmin()
    {
        $this->custom_model->check_session();

        $this->load->helper('url');

        $fname = trim(str_replace("%20", " ", $this->input->post('fname')));
        $lname = trim(str_replace("%20", " ", $this->input->post('lname')));
        $email =  trim(str_replace("%20", " ", $this->input->post('email')));
        $password = trim(str_replace("%20", " ", $this->input->post('password')));

        $flag = 0;

        $query = $this->db->get_where('admin', array('email' => $email));
        if ($query->num_rows() > 0) {
            echo "<script> alert('Email Already Exists'); </script>";
            redirect('admin/subadmin', 'refresh');
        } else {
            $data = array(
                'fname' => $fname,
                'lname' => $lname,
                'password' => md5($password),
                'email' => $email,
                'type' => 'superadmin'
            );

            $this->db->set('created_date', 'NOW()', FALSE);
            $this->db->set('updated_date', 'NOW()', FALSE);
            if ($this->db->insert('admin', $data) == '1') {
                $flag = 1;
            }
        }

        if ($flag == '1') {
            // E-Mail Classes///
            $this->load->model('mail_function');
            $this->load->model('maildesign_model');

            ///Calling E-mail Template.////
            $body =  $this->maildesign_model->send_register_admin($email, $password);
            $chk = $this->mail_function->sendmail($email, "Transport me Admin User Detail", $body);
            if ($chk == '1') {
                echo "<script> alert('Admin user created'); </script>";
                redirect('admin/subadmin', 'refresh');
            }
        }
    }

    public function set_license()
    {
        $this->custom_model->check_session();
        $this->load->helper('url');

        $company_id = trim(str_replace("%20", " ", $this->input->post('company_name')));
        $company_email =  $this->custom_model->getvalues("company_master_table", "company_id", $company_id, "email");
        //$token_key = trim(str_replace("%20", " ",$this->input->post('token_key')));

        $token_key = uniqid() . $company_id;
        $encryped_key = md5($token_key);
        $caps_key = strtoupper($encryped_key);
        $flag = 0;


        /*	$data = array(
            'company_id' => $company_id,
            'keys' => $encryped_key,
            'status'=>'active'
            );

            $this->db->set('created_date', 'NOW()', FALSE);
            $this->db->set('updated_date', 'NOW()', FALSE);
            if($this->db->insert('token_key', $data)=='1')
            {
                $flag = 1;
            }

            if($flag == 1)
            {
                echo "<script> alert('token key Created'); </script>";
                redirect('admin/license', 'refresh');
            }
            else {
                echo "<script> alert('key not Created'); </script>";
                redirect('admin/license', 'refresh');
            }
         */


        //$this->db->select("`token_key`.`keys`,token_key.company_id,token_key.keys,token_key.status");
        //$this->db->from('token_key');
        //$query = $this->db->get();

        //if($query->num_rows() >0)
        //{

        //echo "<script> alert('company Already Exist'); </script>";
        //redirect('admin/subadmin', 'refresh');
        //}
        //else
        //{
        $data = array(
            'company_id' => $company_id,
            'keys' => $caps_key,
            'status' => 'active'
        );



        $this->db->set('created_date', 'NOW()', FALSE);
        $this->db->set('updated_date', 'NOW()', FALSE);
        if ($this->db->insert('token_key', $data) == '1') {
            $flag = 1;
        }
        //	}

        if ($flag == 1) {
            // E-Mail Classes///


            $this->load->model('mail_function');
            $this->load->model('maildesign_model');

            ///Calling E-mail Template.////


            $body =  $this->maildesign_model->send_token_key($company_email, $caps_key);
            $chk = $this->mail_function->sendmail($company_email, "Transport me Token Key Detail", $body);
            if ($chk == '1') {
                echo "<script> alert('token key Created'); </script>";
                redirect('admin/license', 'refresh');
            }
        }


        /*if($flag == '1')
        {
            // E-Mail Classes///


                $this->load->model('mail_function');
                $this->load->model('maildesign_model');

            ///Calling E-mail Template.////


                $body =  $this->maildesign_model->send_register_admin($email,$password);
                $chk = $this->mail_function->sendmail($email,"Transport me Admin User Detail",$body);
                if($chk == '1')
                {
                    echo "<script> alert('Admin user created'); </script>";
                            redirect('admin/subadmin', 'refresh');
                }
        }*/ else {
            echo "<script> alert('key not Created '); </script>";
            redirect('admin/license', 'refresh');
        }
    }


    public function get_license()
    {
        $this->custom_model->check_session();
        $array = $this->uri->uri_to_assoc();

        $this->db->order_by("created_date", "desc");
        $query = $this->db->get('token_key');

        return $query->result_array();
    }

    public function license()
    {
        $query = "SELECT t.id, t.keys,t.status,t.created_date,c.name FROM `token_key` t,company_master_table c
                 where t.company_id=c.company_id";

        $result = $this->db->query($query);
        return $result->result_array();
    }

    public function update_myprofile()
    {
        $this->custom_model->check_session();
        $this->load->helper('url');


        $fname = trim(str_replace("%20", " ", $this->input->post('fname')));
        $lname = trim(str_replace("%20", " ", $this->input->post('lname')));
        $email =  trim(str_replace("%20", " ", $this->input->post('email')));
        $password = trim(str_replace("%20", " ", $this->input->post('password')));
        $subadmin_id = trim(str_replace("%20", " ", $this->input->post('subadmin_id')));


        $flag = 0;

        $query = $this->db->get_where('admin', array('id' => $subadmin_id));
        if ($query->num_rows() > 0) {
            $old_pass = $this->custom_model->getvalues("admin", "id", $subadmin_id, "password");

            if ($password == $old_pass) {
                $data = array(
                    'fname' => $fname,
                    'lname' => $lname,
                    'email' => $email
                );

                $newpass = 0;
            } else {
                $data = array(
                    'fname' => $fname,
                    'lname' => $lname,
                    'password' => md5($password),
                    'email' => $email
                );
                $newpass = 1;
            }

            foreach ($query->result() as $row) {
                if ($row->email == $email) {

                    $this->db->set('updated_date', 'NOW()', FALSE);
                    $this->db->where('id', $subadmin_id);
                    if ($this->db->update('admin', $data) == '1') {

                        $flag = 1;
                    }
                } else {

                    $query = $this->db->get_where('admin', array('email' => $email));
                    if ($query->num_rows() > 0) {
                        echo "<script> alert('Email already exist'); </script>";
                        redirect('admin/myprofile', 'refresh');
                    } else {

                        $this->db->set('updated_date', 'NOW()', FALSE);
                        $this->db->where('id', $subadmin_id);
                        if ($this->db->update('admin', $data) == '1') {

                            $flag = 1;
                        }
                    }
                }
            }
        }


        if ($flag == 1) {
            echo "<script> alert('User Updated'); </script>";
            redirect('admin/myprofile', 'refresh');
        }
    }

    public function get_subadmin()
    {
        $this->custom_model->check_session();
        $array = $this->uri->uri_to_assoc();

        $this->db->order_by("created_date", "desc");
        $query = $this->db->get('admin');
        return $query->result_array();
    }

    public function get_superuser()
    {
        $this->custom_model->check_session();
        $array = $this->uri->uri_to_assoc();

        $this->db->order_by("created_date", "desc");
        $query = $this->db->get('user_master');
        return $query->result_array();
    }



    public function get_myprofile($id)
    {
        $this->custom_model->check_session();
        $this->db->where('id =', $id);
        $query = $this->db->get('admin');
        return $query->result_array();
    }

    public function get_companyprofile()
    {
        $this->custom_model->check_session();
        $this->db->order_by("company_id", "desc");
        $this->db->where("login_type <>", "superadmin");
        $this->db->where("isActive =", "1");
        $query = $this->db->get('company_master_table');
        return $query->result_array();
    }



    public function delete_subadmin($id)
    {
        $this->custom_model->check_session();

        $session_data = $this->session->userdata('logged_in');
        $data['username'] = $session_data['name'];
        $data['type'] = $session_data['type'];
        $type =  $session_data['type'];

        if ($type == 'admin') {
            if ($this->db->delete('admin', array('id' => $id)) == '1') {

                echo "<script> alert('Sub admin deleted successfully'); </script>";
                redirect('admin/subadmin', 'refresh');
            }
        } else {
            echo "<script> alert('You Don not have a permission to delete this post'); </script>";
        }
    }

    // this function get Login
    public function forget_pass()
    {
        $this->load->helper('url');
        if ($this->input->post('email')) {
            $email =  $this->input->post('email');
            $status = 1;
        } elseif (json_decode(file_get_contents('php://input'), true)) {
            $email =  json_decode(file_get_contents('php://input'), true)['email'];
            $status = 2;
        }
        if ($email) {
            /// Testing the user exist or not//

            $this->db->where('email =', $email);
            $query = $this->db->get('admin');
            //return $query->row_array();
            if ($query->num_rows() > 0) {
                $password = $this->custom_model->randomPrefix(10);
                $data = array(
                    'password' => md5($password)
                );
                $this->db->where('email', $email);
                if ($this->db->update('admin', $data) == '1') {
                    //// Sending mail for  authenticaiton  /////
                    $this->load->model('maildesign_model');
                    $this->load->model('mail_function');
                    $body =  $this->maildesign_model->send_repass($email, $password);
                    $chk = $this->mail_function->sendmail($email, "Transportme New Password Detail", $body);
                    if ($chk == '1') {
                        if ($status == 1) {
                            echo "<script> alert('An email has been sent to your respective email address.'); </script>";
                            redirect('admin', 'refresh');
                        } elseif ($status == 2) {
                            $result['message'] = "An email has been sent to your respective email address.";
                            $result['status'] = "success";
                            return $result;
                        }
                    }
                }
            } else {
                if ($status == 1) {
                    echo "<script> alert('Email address does not exists.'); </script>";
                    redirect('admin', 'refresh');
                } elseif ($status == 2) {
                    $result['message'] = "Email address does not exists.";
                    $result['status'] = "error";
                    return $result;
                }
            }
        } else {
            if ($status == 1) {
                echo "<script> alert('Please enter email address.'); </script>";
                redirect('admin', 'refresh');
            } elseif ($status == 2) {
                $result['message'] = "Please enter email address.";
                $result['status'] = "error";
                return $result;
            }
        }
    }


    public function delete_license($id)
    {
        $data = array(
            'status' => "inactive"
        );
        $this->db->where("id =", $id);
        if ($this->db->update('token_key', $data) == '1') {
            echo "<script> alert('Token Key Deactivated'); </script>";
            redirect('admin/license', 'refresh');
        }
    }

    /**
     *
     * This method is used to get data of all the companies created
     * @author Shyam
     */
    public function getCompanyList()
    {

        $this->db->order_by("company_id", "desc");
        $this->db->where("isActive =", "1");
        $this->db->where("login_type <>", "superadmin");
        $query = $this->db->get('company_master_table');

        return $query->result_array();
    }

    public function single_operator($company_id)
    {
        $db2 = $this->db_manager->get_connection(MASTER_DB);
        $db2->select('name');
        $db2->where('company_id =', $company_id);
        $query = $db2->get('company_master_table');
        $result = $query->result_array();
        $company_name = $result['0']['name'];

        return $company_name;
    }

    public function is_exists_company($company_name)
    {

        $flag = FALSE;
        $db2 = $this->db_manager->get_connection(MASTER_DB);
        $db2->where('name =', $company_name);
        $query = $db2->get('company_master_table');


        if ($query->num_rows() > 0) {
            $flag = TRUE;
        }
        return $flag;
    }

    function disable_license($id)
    {
        $data = array(
            'status' => "deleted"
        );
        $this->db->set('updated_date', 'NOW()', FALSE);
        $this->db->where("id =", $id);
        if ($this->db->update('token_key', $data) == '1') {
            echo "<script> alert('Token Key Deleted'); </script>";
            redirect('admin/license', 'refresh');
        }
    }

    function check_account($email, $table)
    {
        $this->load->library('feature_url');
        $db2 = $this->db_manager->get_connection(MASTER_DB);
        $db2->where('email', $email);

        $db2->limit(1);
        $query = $db2->get($table);

        if ($query->num_rows() == 1) {
            return $query->result_array();
        }
    }

    function _2fa_activated($id, $table)
    {
        $this->load->library('feature_url');
        $db2 = $this->db_manager->get_connection(MASTER_DB);
        $db2->set('security_2fa_activated', IS_ACTIVE);
        if ($table == 'permission_account_info') {
            $db2->where('id', $id);
        } else {
            $db2->where('company_id', $id);
        }
        $db2->update($table);
    }

    function save_qr_code_secret($secret, $id, $table)
    {
        $this->load->library('feature_url');
        $db2 = $this->db_manager->get_connection(MASTER_DB);
        $data = array(
            'qr_code_secret' => $secret
        );
        if ($table == 'permission_account_info') {
            $db2->where('id', $id);
        } else {
            $db2->where('company_id', $id);
        }
        $db2->update($table, $data);
    }
    
    function get_qr_code_secret($id, $table)
    {
        $this->load->library('feature_url');
        $db2 = $this->db_manager->get_connection(MASTER_DB);
        $db2->select('qr_code_secret');
        if ($table == 'permission_account_info') {
            $db2->where('id', $id);
        } else {
            $db2->where('company_id', $id);
        }
        return $db2->get($table)->result_array();
    }
}
