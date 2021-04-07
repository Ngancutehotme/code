<?php

class Admin extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
        $this->load->model('admin_model');
        $this->load->helper('url');
        $this->load->helper('form');
        $this->load->helper('cookie');
        // @JIRA TSM-459
        $this->load->driver('cache');
    }

    public function index()
    {
        $this->load->helper('url');
        $this->load->helper('form');
        $this->load->library('form_validation');

        $this->load->view('admin/index');
    }

    public function login()
    {
        //This method will have the credentials validation
        $this->load->library('form_validation');
        $this->form_validation->set_rules('username', 'Username', 'trim|required|xss_clean');
        $this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');
        if ($this->form_validation->run() == FALSE) {
            //Field validation failed.  User redirected to login page
            $this->load->view('admin/index');
            return;
        }
        $username = $this->input->post('username');
        $password = $this->input->post('password');
        $result = $this->admin_model->login($username, $password);
        if ($result == false) {
            $data['error'] = 'Invalid username or password';
            $this->load->view('admin/index', $data);
            return;
        }
        // save result into session to reuse in 2FA page if need.
        $this->set_session($result, false);

        //check security 2fa activated
        $this->load->model('config_model');
        $organisation_id = null;
        if (isset($result[0]->account_info_id)) {
            // org user is login
            $organisation_id = $result[0]->organisation_id;
        }

        $data['remember_me'] = 30;
        $data['status'] = $result[0]->security_2fa_activated;
        // we need check if company is required 2FA or not.
        if ($result[0]->login_type != "superadmin") {
            $check_company_security_2fa = $this->config_model->get_security_2fa($organisation_id, $result[0]->db_name);
            if ($check_company_security_2fa) {
                $data['remember_me'] = $check_company_security_2fa[0]['security_remember_day'];
            }
        }

        // company require 2FA, we will show QR code then user can scan.
        if ($result[0]->security_2fa_activated != true) {
            // this is the first time this company account scan QR code then we need to show.
            $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
            if (isset($result[0]->account_info_id)) {
                $secret = $this->admin_model->get_qr_code_secret($result[0]->account_info_id, 'permission_account_info')[0]['qr_code_secret'];
                $qr_secret = $this->xorIt(base64_decode($secret), ENCRYPT_2FA_COOKIE_KEY, 1);
                if (!$secret) {
                    $secret = base64_encode($this->xorIt($g->generateSecret(), ENCRYPT_2FA_COOKIE_KEY));
                    $qr_secret = $this->xorIt(base64_decode($secret), ENCRYPT_2FA_COOKIE_KEY, 1);
                    $this->admin_model->save_qr_code_secret($secret, $result[0]->account_info_id, 'permission_account_info');
                }
            } else {
                $secret = $this->admin_model->get_qr_code_secret($result[0]->company_id, 'company_master_table')[0]['qr_code_secret'];
                $qr_secret = $this->xorIt(base64_decode($secret), ENCRYPT_2FA_COOKIE_KEY, 1);
                if (!$secret) {
                    $secret = base64_encode($this->xorIt($g->generateSecret(), ENCRYPT_2FA_COOKIE_KEY));
                    $qr_secret = $this->xorIt(base64_decode($secret), ENCRYPT_2FA_COOKIE_KEY, 1);
                    $this->admin_model->save_qr_code_secret($secret, $result[0]->company_id, 'company_master_table');
                }
            }
            $data['url'] = \Sonata\GoogleAuthenticator\GoogleQrUrl::generate($username, $qr_secret, QR_CODE_ISSUER);
            $data['secret'] = $secret;
            $this->session->unset_userdata('logged_in');
            return $this->load->view('admin/check_secutity_2fa', $data);
        } else {
            // company account scanned QR code before. So we need to check if cookie is expired or not.
            $security_2fa_expired = $this->check_2fa_expired($result[0]->company_id);
            if ($security_2fa_expired == true) {
                // we need to show a text field to enter code. We will not show image again.
                if (isset($result[0]->account_info_id)) {
                    $secret = $this->admin_model->get_qr_code_secret($result[0]->account_info_id, 'permission_account_info')[0]['qr_code_secret'];
                } else {
                    $secret = $this->admin_model->get_qr_code_secret($result[0]->company_id, 'company_master_table')[0]['qr_code_secret'];
                }
                $data['secret'] = $secret;
                $data['url'] = '';
                $this->session->unset_userdata('logged_in');

                return $this->load->view('admin/check_secutity_2fa', $data);
            }
        }

        // company does not require 2FA or 2FA is not expired. We can set session now.
        $this->set_session($result, true);

        $session_data = $this->session->userdata('logged_in');
        if ($session_data['type'] == "superadmin") {
            redirect('company', 'refresh');
        } else {
            // @JIRA TSM-495
            if (isset($session_data['account_info_id'])) {
                if (isset($session_data['url_redirect']) && !empty($session_data['url_redirect'])) {
                    redirect($session_data['url_redirect'], 'refresh');
                } else {
                    echo "<script>alert('You do not have permission access page')</script>";
                    redirect('admin', 'refresh');
                }
                // end @JIRA TSM-495
            }
            redirect('gpstracking', 'refresh');
        }
    }
    public function switch_session($sess_array)
    {
        // mark this user has scan 2FA.
        $this->db->set('security_2fa_activated', TRUE);

        $this->db->set('last_login', 'NOW()', FALSE);
        $this->db->where('company_id', $sess_array['id']);
        $this->db->update('company_master_table');
        $this->session->unset_userdata('qrcode');
        $this->session->set_userdata('logged_in', $sess_array);
        return $sess_array;
    }

    public function set_session($result, $need_login = true)
    {
        $session_name = 'qrcode';
        if ($result) {
            $sess_array = array();
            foreach ($result as $row) {
                if ($need_login == true) {
                    $this->db->set('last_login', 'NOW()', FALSE);
                    $this->db->where('company_id', $row->company_id);
                    $this->db->update('company_master_table');
                    $this->session->unset_userdata($session_name);
                    $session_name = 'logged_in';
                }
                $sess_array = array(
                    'id' => $row->company_id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'db_name' => $row->db_name,
                    'latitude' => $row->latitude,
                    'longitude' => $row->longitude,
                    'type' => $row->login_type,
                    'zone_ticket_ability' => $row->zone_ticket_ability,
                    'pax_count_ability' => $row->pax_count_ability,
                    'buslink_ability' => $row->buslink_ability,
                    'pdc_check' => $row->pdc_check,
                    'eos_check' => $row->eos_check,
                    'on_time_running_check' => $row->on_time_running_check,
                    'hyor4_check' => $row->hyor4_check,
                    'hyor4_check_dec18' => $row->hyor4_check_dec18, // @JIRA TSM-150
                    'hyor8_check' => $row->hyor8_check,
                    'passenger_crowding_check' => $row->passenger_crowding_check,
                    'cancel_incomplete_check' => $row->cancel_incomplete_check,
                    'accessible_bus_services_check' => $row->accessible_bus_services_check,
                    'hyor2_check' => $row->hyor2_check,
                    'hyor3_check' => $row->hyor3_check,
                    'hyor1_check' => $row->hyor1_check,
                    'hyor1_check_dec18' => $row->hyor1_check_dec18, // @JIRA TSM-150
                    'bus_roll_check' => $row->bus_roll_check,
                    'fuel_fill_check' => $row->fuel_fill_check,
                    'bus_damage_check' => $row->bus_damage_check,   //@Jira Task: TSM-654
                    'pax_count_fix' => $row->pax_count_fix,   //@Jira Task: TSM-710
                    'ewd_check' => $row->ewd_check,   //@Jira Task: TSM-744
                    'sort_ticket_ability' => $row->sort_ticket_ability,
                    'auto_end_odometer_ability' => $row->auto_end_odometer_ability,
                    'fit4work_statement_ability' => $row->fit4work_statement_ability,
                    'pdc_eos_defect_ability' => $row->pdc_eos_defect_ability,
                    'duress_check' => $row->duress_check,
                    'bus_loading_check' => $row->bus_loading_check,
                    'historical_map_check' => $row->historical_map_check,
                    'late_sign_on_alert_check' => $row->late_sign_on_alert_check,
                    'smartcard_check' => $row->smartcard_check,
                    'route_guidance_map_check' => $row->route_guidance_map_check,
                    'transaction_screen_setting' => $row->transaction_screen_setting,
                    'timezone' => $row->timezone,
                    'route_revenue_report' => $row->route_revenue_report,
                    'split_trip_check' => $row->split_trip_check,
                    'esm_passenger' => $row->esm_passenger,
                    'under_over_report' => $row->under_over_report,
                    'currency' => $row->currency,
                    'gps_bundle' => (int) $row->gps_bundle,
                    'manual_ticket' => $row->manual_ticket,
                    'on_demand_service' => $row->on_demand_service,
                    'change_driver' => $row->change_driver,
                    'clear_of_bus' => $row->clear_of_bus,
                    //@JIRA TSM-317
                    'passenger_manifest' => $row->passenger_manifest,
                    //END @JIRA TSM-317
                    //JIRA TSM-70
                    'worksheet_declaration_report' => $row->worksheet_declaration_report,
                    // @JIRA TSM-476
                    'permission_user' => $row->permission_user,
                    // @JIRA TSM-781
                    'ptb_report_check' => $row->ptb_report_check
                    //@JIRA TSM-783
                    , 'passenger_id_check' => $row->passenger_id_check,
                    'cashless_ticket' => $row->cashless_ticket,
                    'public_tracking' => $row->public_tracking,
                    'push_to_talk' => $row->push_to_talk,
                    'use_zone_to_zone_ticket' => $row->use_zone_to_zone_ticket,
                    'organisation_id' => isset($row->organisation_id) ? (int)$row->organisation_id : null,
                    'security_2fa_activated' => isset($row->security_2fa_activated) ? (int)$row->security_2fa_activated : null,
                );
                /**
                 * JIRA TSM-495
                 * nghi.doan
                 */
                if (isset($row->account_info_id) && !empty($row->account_info_id)) {
                    $sess_array['account_info_id'] = $row->account_info_id;
                    $sess_array['is_sub_operator'] = true;
                    // @JIRA TSM-521
                    $sess_array['account_info_name'] = $row->contact_person;
                    // END @JIRA TSM-521
                    $sess_array['url_redirect'] = $row->url_redirect;
                }
                /**
                 * end JIRA TSM-495
                 */
                $this->session->set_userdata($session_name, $sess_array);
            }
        }
    }

    public function check_2fa_expired($company_id)
    {
        $cookie_2fa_encrypt = get_cookie(SECURITY_2FA_COOKIE_NAME);
        if ($cookie_2fa_encrypt == false) {
            return true;
        }
        $cookie_2fa = $this->cookie_convert(false, $cookie_2fa_encrypt);
        list($cookie_company_id, $expired_date) = explode("#", $cookie_2fa);
        $today = date("Y-m-d H:i:s");
        if ($expired_date < $today || $cookie_company_id != $company_id) {
            return true;
        }
        return false;
    }

    function xorIt($string, $key, $type = 0)
    {
        $sLength = strlen($string);
        $xLength = strlen($key);
        for ($i = 0; $i < $sLength; $i++) {
            for ($j = 0; $j < $xLength; $j++) {
                if ($type == 1) {
                    //decrypt
                    $string[$i] = $key[$j] ^ $string[$i];
                } else {
                    //crypt
                    $string[$i] = $string[$i] ^ $key[$j];
                }
            }
        }
        return $string;
    }

    public function cookie_convert($is_encrypt, $text)
    {
        if ($is_encrypt == true) {
            return base64_encode($this->xorIt($text, ENCRYPT_2FA_COOKIE_KEY));
        } else {
            return $this->xorIt(base64_decode($text), ENCRYPT_2FA_COOKIE_KEY, 1);
        }
    }

    public function check_google_authenticator()
    {
        $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
        $secret = $this->input->post('secret');
        $qr_secret = $this->xorIt(base64_decode($secret), ENCRYPT_2FA_COOKIE_KEY, 1);
        $pre_session_data = $this->session->userdata('qrcode');
        if ($pre_session_data == null) {
            redirect('admin', 'refresh');
        }
        $code = $this->input->post('code');
        $qr_code_url = $this->input->post('qr_code_url');
        if (!$code) {
            $data['message'] = 'Please enter code';
            $data['status'] = $pre_session_data["security_2fa_activated"];
            $data['code'] = $code;
            $data['url'] = $qr_code_url;
            $data['secret'] = $secret;
            return $this->load->view('admin/check_secutity_2fa', $data);
        }
        if ($g->checkCode($qr_secret, $code)) {
            // switch session.
            $session_data = $this->switch_session($pre_session_data);

            // save cookie.
            $remember_me = $this->input->post('remember_me');
            $expired_at = date('Y-m-d H:i:s', strtotime('+' . $remember_me . ' days'));
            $cookie_value = $session_data['id'] . '#' . $expired_at;
            $cookie_value_encrypt = $this->cookie_convert(true, $cookie_value);
            set_cookie(SECURITY_2FA_COOKIE_NAME, $cookie_value_encrypt, $remember_me * 24 * 60 * 60);

            if ($session_data['type'] == "superadmin") {
                $this->admin_model->_2fa_activated($session_data['id'], 'company_master_table');
                redirect('company', 'refresh');
            } else {
                // @JIRA TSM-495
                if (isset($session_data['account_info_id'])) {
                    $this->admin_model->_2fa_activated($session_data['account_info_id'], 'permission_account_info');
                    if (isset($session_data['url_redirect']) && !empty($session_data['url_redirect'])) {
                        redirect($session_data['url_redirect'], 'refresh');
                    } else {
                        echo "<script>alert('You do not have permission access page')</script>";
                        redirect('admin', 'refresh');
                    }
                    // end @JIRA TSM-495
                }
                // $this->admin_model->_2fa_activated($session_data['id'], 'company_master_table');
                redirect('gpstracking', 'refresh');
            }
        } else {
            $data['message'] = 'Invalid login';
            $data['status'] = $pre_session_data["security_2fa_activated"];
            $data['code'] = $code;
            $data['url'] = $qr_code_url;
            $data['secret'] = $secret;
            return $this->load->view('admin/check_secutity_2fa', $data);
        }
    }

    public function license()
    {

        $da = $this->session->userdata('logged_in');
        if ($da['id'] != "" && $da['id'] != "0") {

            $this->load->helper('url');
            $this->load->helper('form');
            $this->load->library('form_validation');

            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "superadmin") {
                $data['user_name'] = $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['id'] = $session_data['id'];

                $data['license_detail'] = $this->admin_model->license(); //$this->admin_model->get_license();
                $this->load->view('admin/license', $data);
            } else {
                //	echo "<script> alert ('You Do not have a permition to view this page');</script>";
                redirect('company', 'refresh');
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function subadmin()
    {

        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {

            $this->load->helper('url');
            $this->load->helper('form');
            $this->load->library('form_validation');

            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "admin") {
                $data['username'] = $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['id'] = $session_data['id'];
                $data['subadmin_detail'] = $this->admin_model->get_subadmin();

                $this->load->view('admin/subadmin', $data);
            } else {
                //	echo "<script> alert ('You Do not have a permition to view this page');</script>";
                redirect('admin/myprofile', 'refresh');
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function superuser()
    {

        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {

            $this->load->helper('url');
            $this->load->helper('form');
            $this->load->library('form_validation');

            $session_data = $this->session->userdata('logged_in');

            if ($session_data['type'] == "superadmin") {
                $data['user_name'] = $session_data['user_name'];
                $data['type'] = $session_data['type'];
                $data['id'] = $session_data['id'];
                $data['superuser_detail'] = $this->admin_model->get_superuser();
                $data['company_list'] = $this->admin_model->getCompanyList();
                $this->load->view('admin/superuser', $data);
            } else {
                //	echo "<script> alert ('You Do not have a permition to view this page');</script>";
                redirect('admin/myprofile', 'refresh');
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function myprofile()
    {

        $this->load->helper('url');
        $this->load->helper('form');


        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "admin") {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['id'] = $session_data['id'];
                $data['subadmin_detail'] = $this->admin_model->get_myprofile($session_data['id']);
                $this->load->view('admin/admin_profile', $data);
            } else {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['id'] = $session_data['id'];
                $data['subadmin_detail'] = $this->admin_model->get_myprofile($session_data['id']);
                $this->load->view('admin/admin_profile', $data);
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function add_license()
    {
        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {

            $session_data = $this->session->userdata('logged_in');

            if ($session_data['type'] == "superadmin") {
                $session_data = $this->session->userdata('logged_in');
                $data['user_name'] = $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = $session_data['id'];
                $data['company_detail'] = $this->admin_model->get_companyprofile();


                $this->load->view('admin/license_add', $data);
            } else {
                echo "<script> alert ('You Do not have a permition to view this page');</script>";
                redirect('admin', 'refresh');
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function add_subadmin()
    {
        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {

            $session_data = $this->session->userdata('logged_in');

            if ($session_data['type'] == "admin") {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = $session_data['name'];
                $data['type'] = $session_data['type'];

                $data['user_id'] = $session_data['id'];


                $this->load->view('admin/subadmin_add', $data);
            } else {
                echo "<script> alert ('You Do not have a permition to view this page');</script>";
                redirect('user', 'refresh');
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function create_subadmin()
    {
        $this->load->helper('form');
        $this->admin_model->set_subadmin();
    }

    public function create_license()
    {
        $this->load->helper('form');
        $this->admin_model->set_license();
    }

    public function update_profile()
    {
        $array = $this->uri->uri_to_assoc();
        $id = $array['id'];
        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $session_data = $this->session->userdata('logged_in');

            $session_data = $this->session->userdata('logged_in');
            $data['username'] = $session_data['name'];
            $data['type'] = $session_data['type'];
            $data['user_id'] = $session_data['id'];
            $data['subadmin_id'] = $id;

            $this->load->view('admin/subadmin_edit', $data);
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function delete_license($id)
    {
        $this->admin_model->delete_license($id);
    }

    public function update_myprofile()
    {
        $this->load->helper('form');
        $this->admin_model->update_myprofile();
    }

    public function delete_subadmin($id)
    {
        $this->load->helper('form');
        $this->admin_model->delete_subadmin($id);
    }

    public function forgetpass()
    {
        $this->load->helper('form');
        $this->admin_model->forget_pass();
    }

    public function logout()
    {
        $da = $this->session->userdata('logged_in');
        if ($da) {
            /**
             * JIRA TSM-495
             * nghi.doan
             */
            if (isset($da['account_info_id']) && $this->cache->file->get('user/' . $da['account_info_id'])) {
                $this->cache->file->delete('user/' . $da['account_info_id']);
            }
            /**
             * end JIRA TSM-495
             */
            $this->session->unset_userdata('logged_in');
            /**
             * JIRA TASK TSM-459
             */
            if ($this->session->userdata('users_login')) {
                $this->session->unset_userdata('users_login');
            }
        }
        // fix session timeout or delete
        redirect('admin', 'refresh');
    }

    public function disable_license($id)
    {
        $this->admin_model->disable_license($id);
    }
}
