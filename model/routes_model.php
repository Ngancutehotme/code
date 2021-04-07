<?php

require_once 'base_model.php';

class Routes_model extends Base_model
{

    protected $sort_ticket_ability;
    protected $session_login;
    protected $account_info_id;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('custom_model');

        $this->load->helper(array('form', 'url'));
        $session_data = $this->session->userdata('logged_in');
        // @JIRA TSM-521
        $this->account_info_id = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : '';
        $this->session_login = $session_data;
        // END @JIRA TSM-521
        $this->sort_ticket_ability = isset($session_data['sort_ticket_ability']) ? $session_data['sort_ticket_ability'] : 0;
        // TME-299
        // $this->load->library('app_firestore');
    }

    public function get_route_category()
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $array = $this->uri->uri_to_assoc();
        // @JIRA TSM-521  Get data category by data permission
        $select = ['rc.*'];
        if (!empty($this->account_info_id)) {
            $select = array_merge($select, ['pcs.action']);
            $this->db->join('permission_category_sub pcs', 'rc.id = pcs.category_id');
            $this->db->where('pcs.account_info_id', $this->account_info_id);
            $this->db->where('pcs.status', IS_ACTIVE);
        }
        // END @JIRA TSM-521
        $this->db->select($select);
        $this->db->where("rc.status =", "1");
        $this->db->order_by("rc.id", "desc");
        $results = $this->db->get('route_category rc')->result_array();
        return $results;
    }

    public function create_route_category()
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->load->helper('url');
        $created_by = isset($this->session_login['account_info_id']) ? $this->session_login['account_info_id'] : $this->session_login['id'];

        $cat_name = $this->input->post('cat_name');

        $data = array(
            'name' => $cat_name,
            'created_by' => $created_by,
            'updated_by' => $created_by
        );

        $this->db->trans_start();
        $this->db->set('created_date', 'NOW()', FALSE);
        $this->db->set('updated_date', 'NOW()', FALSE);
        $this->db->insert('route_category', $data);
        // @JIRA TSM-533
        if (!empty($this->account_info_id)) {
            $cat_id = $this->db->insert_id();
            $category_role = [
                'account_info_id' => $created_by,
                'category_id' => $cat_id,
                'action' => SUB_OPERATOR_EXECUTE_CONTROL,
                'status' => 1
            ];
            $this->db->insert('permission_category_sub', $category_role);
        }
        // @JIRA TSM-533
        $this->db->trans_complete();
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
            echo "<script> alert('Route Category Inserted'); </script>";
            redirect('routes', 'refresh');
        }
    }

    public function update_route_category()
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $this->load->helper('url');
        $created_by = $this->input->post('created_by');

        $cat_name = $this->input->post('cat_name');
        $id = $this->input->post('id');

        $data = array(
            'name' => $cat_name,
            'updated_by' => $created_by
        );
        $this->db->set('updated_date', 'NOW()', FALSE);
        $this->db->where("id =", $id);
        if ($this->db->update('route_category', $data) == '1') {
            echo "<script> alert('Route Category Updated'); </script>";
            redirect('routes', 'refresh');
        }
    }



    public function delete_route_category($id)
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->db->where("route_category_id =", $id);
        $this->db->where("status =", "1");
        $query1 = $this->db->get('route');

        $total_record = $query1->num_rows();

        if ($total_record < 1) {
            $data = array(
                'status' => "0"
            );
            $this->db->trans_begin();
            $this->db->where("id =", $id);
            $this->db->update('route_category', $data);
            // @JIRA TSM-533
            $this->db->where(['category_id' => $id]);
            $this->db->update('permission_category_sub', $data);
            // END TSM-533
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
                echo "<script> alert('Route category deleted'); </script>";
                redirect('routes', 'refresh');
            }
        } else {

            echo "<script> alert('Can\'t Deleted, there is " . $total_record . " route/s in this category.'); </script>";
            redirect('routes', 'refresh');
        }
    }

    public function get_last_route_stops($route_id)
    {
        $route_stops = $this->db->query("SELECT * FROM route_stops where route_id = '$route_id' ORDER BY position DESC")->result_array();
        return $route_stops[0];
    }

    public function find_route_stop($route_id, $stop_id)
    {
        $route_stops = $this->db->query("SELECT * FROM route_stops where route_id = '$route_id' and stop_id= $stop_id limit 1")->row_array();
        return $route_stops;
    }

    public function copy_pm_route()
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $this->load->helper('url');
        $created_by = $this->input->post('created_by');
        //$route_category_id =  $this->input->post('old_category');
        $old_route_id = $this->input->post('route_id');
        $new_route_name = $this->input->post('new_route_name');
        $last_old_route_stop = $this->get_last_route_stops($old_route_id)->id;
        try {
            $this->db->trans_begin();
            //@JIRA TSM-396 update add col line_color_kml
            $sql = "INSERT INTO `route`(`name`,`route_description`,`route_category_id`, `number_of_section`, `departing_stop`, `ticket_type_id`, `status`, `is_manual`, `speed_limit`, `created_by`, `created_date`, `updated_by`, `updated_date`, `use_zone_ability`, `pax_count_ability`, `departing_zone_id`, `on_time_running_check`, `midpoint`, `planned_trip_per_day`, `route_number`, `print_out_end_of_route_report`, `loop_service`, `number_of_route_loop`, `window_time`, `planned_kms`, `route_type`, `dead_running_kms`, `timetabled_service_check`, `special_route_type` ,`m_f_term`, `m_f_hol`, `sat`, `sun`, `ph`, `activation_time`, `late_sign_on`, status_gps_navigation_line, link_kml_file, `line_color_kml`) (SELECT '$new_route_name',`route_description` , '$new_route_category_id', `number_of_section`, '$last_old_route_stop', `ticket_type_id`, `status`, `is_manual`, `speed_limit`, `created_by`, NOW(), `updated_by`, NOW(), `use_zone_ability`, `pax_count_ability`, `departing_zone_id`, `on_time_running_check`, `midpoint`, `planned_trip_per_day`, `route_number`, `print_out_end_of_route_report`, `loop_service`, `number_of_route_loop`, `window_time`, `planned_kms`, `route_type`, `dead_running_kms`, `timetabled_service_check`, `special_route_type` , `m_f_term`, `m_f_hol`, `sat`, `sun`, `ph`, `activation_time`, `late_sign_on`, status_gps_navigation_line, link_kml_file, `line_color_kml` FROM `route` WHERE `id`= '$old_route_id')";
            //$query = $this->db->query($sql);
            $flag = 0;
            if ($this->db->query($sql) === TRUE) {
                $flag = 1;
                $last_inserted_route_id = $this->db->insert_id();


                $sql = "INSERT INTO `route_stops`( `route_id`, `stop_id`, `section_number`, `previous_stop_id`, `position`, `is_popular`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`, `zone_id`, `midpoint`) (SELECT  '$last_inserted_route_id'
                , `stop_id`, `section_number`, `previous_stop_id`, @i:=@i+1, `is_popular`, `status`, `created_by`, NOW(), `updated_by`, NOW(), `zone_id`,  `midpoint` FROM `route_stops`,(SELECT @i:=0) AS foo WHERE `route_id`='$old_route_id' ORDER BY `position` DESC)";
                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }

                // loop route
                $sql = "INSERT INTO `route_stop_departure_time`( `route_id`, `stop_id`, `departure_time`, `loop`, `created_date`)"
                    . " (SELECT  '$last_inserted_route_id', `stop_id`, `departure_time`, `loop`, NOW() FROM `route_stop_departure_time` WHERE `route_id`='$old_route_id' ORDER BY `id` DESC)";
                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }


                $sql = "INSERT INTO `route_ticket_type`(`route_id`, `ticket_type_id`) (SELECT '$last_inserted_route_id',`ticket_type_id`  FROM `route_ticket_type` WHERE `route_id`= '$old_route_id')";
                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }


                $sql = "INSERT INTO `ticket_price_stops`( `route_ticket_type_id`, `route_id`, `stop_from`, `stop_to`, `section_travel`, `amount`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`) (SELECT  `route_ticket_type_id`, '$last_inserted_route_id',
            tps.stop_to as stop_from, tps.stop_from as stop_to, `section_travel`, `amount`, `status`, `created_by`, NOW(), `updated_by`, NOW() FROM `ticket_price_stops` AS `tps` WHERE `route_id`='$old_route_id' ORDER BY `id` DESC)";

                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }


                $sql = "INSERT INTO `route_section`( `route_id`, `section_no`, `is_loop`) (SELECT  '$last_inserted_route_id', `section_no`, `is_loop` FROM `route_section` WHERE `route_id`='$old_route_id')";

                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }



                $sql = "INSERT INTO `route_departure_time`( `route_id`, `departure_time`, `created_by`, `created_date`, `updated_by`, `updated_date`) (SELECT  '$last_inserted_route_id', `departure_time`, `created_by`, 'NOW()', `updated_by`, NOW() FROM `route_departure_time` WHERE `route_id`= '$old_route_id' ORDER BY `id` DESC)";
                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }

                // always empty??
                $sql = "INSERT INTO `route_stop_to_stop_price`( `route_id`, `tickent_type_name`, `display_name`, `is_printable`, `is_weekly`, `valid_days`, `status`, `created_date`, `updated_date`, `created_by`, `updated_by`) (SELECT  '$last_inserted_route_id',
            `tickent_type_name`, `display_name`, `is_printable`, `is_weekly`, `valid_days`, `status`, NOW(), NOW(), `created_by`, `updated_by` FROM `route_stop_to_stop_price` WHERE `route_id`='$old_route_id')";
                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }
                // always empty??
                $sql = "INSERT INTO `operation_date` (`route_id`, `date`, `status`) SELECT '$last_inserted_route_id', `date`, `status` FROM `operation_date` WHERE `route_id`='$old_route_id'";
                if ($this->db->query($sql)) {
                    $flag = 1;
                }

                $created_by = isset($this->session_login['account_info_id']) ? $this->session_login['account_info_id'] : $this->session_login['id'];
                $sql = "INSERT INTO `route_line` (`route_id`, `lat`, `lng`, `created_by`, `created_date`) SELECT '$last_inserted_route_id', `lat`, `lng`, '$created_by', NOW() FROM `route_line` where route_id = '$old_route_id' order by `id` desc";
                if ($this->db->query($sql)) {
                    $flag = 1;
                }

                $sql = "INSERT INTO smartcard_manifest_route_stop(smartcard_type_detail_id, cate_id, route_id, stop_id, is_all_day, default_deboarding_stop_id)
                (SELECT smartcard_type_detail_id, cate_id, '$last_inserted_route_id', smrs.default_deboarding_stop_id as stop_id, is_all_day, smrs.stop_id as default_deboarding_stop_id 
                FROM smartcard_manifest_route_stop as smrs
                WHERE route_id = '$old_route_id' AND is_all_day = 1)";
                if ($this->db->query($sql)) {
                    $flag = 1;
                }

                $sql = "select smrsd.deboarding_stop_id, smrsd.day_of_week,smrs.stop_id as boarding_stop_id, smrs.smartcard_type_detail_id
                from smartcard_manifest_route_stop as smrs
                join smartcard_manifest_route_stop_detail as smrsd on smrs.id = smrsd.smartcard_manifest_route_stop_id
                where smrs.is_all_day = 0
                and smrs.route_id = '$old_route_id'";
                $manifestQuery = $this->db->query($sql);
                $smartcardManifestRes = $manifestQuery->result_array();
                if (is_array($smartcardManifestRes)) {
                    $groupBySmarcard = array();
                    foreach ($smartcardManifestRes as $smartcard_infor) {
                        if (!isset($groupBySmarcard[$smartcard_infor['smartcard_type_detail_id']])) {
                            $groupBySmarcard[$smartcard_infor['smartcard_type_detail_id']] = array();
                        }

                        if (!isset($groupBySmarcard[$smartcard_infor['smartcard_type_detail_id']][$smartcard_infor['deboarding_stop_id']])) {
                            $groupBySmarcard[$smartcard_infor['smartcard_type_detail_id']][$smartcard_infor['deboarding_stop_id']] = array();
                        }
                        $groupBySmarcard[$smartcard_infor['smartcard_type_detail_id']][$smartcard_infor['deboarding_stop_id']][] = $smartcard_infor;
                    };

                    foreach ($groupBySmarcard as $smartcard_type_detail_id => $group_deboarding_stop) {
                        foreach ($group_deboarding_stop as $deboarding_stop_id => $smartcard_manifest) {
                            $data_smart_route_stop = array(
                                'smartcard_type_detail_id' => $smartcard_type_detail_id,
                                'route_stop_id' => $this->find_route_stop($last_inserted_route_id, $deboarding_stop_id)['id'],
                                'route_id' => $last_inserted_route_id,
                                'stop_id' => $deboarding_stop_id,
                                'is_all_day' => 0,
                                'default_deboarding_stop_id' => null
                            );
                            $this->db->insert('smartcard_manifest_route_stop', $data_smart_route_stop);
                            $smartcard_manifest_id_installed = $this->db->insert_id();
                            foreach ($smartcard_manifest as $infor) {
                                $data_smart_route_stop_detail[] = array(
                                    'day_of_week' => $infor['day_of_week'],
                                    'deboarding_stop_id' =>  $infor['boarding_stop_id'],
                                    'smartcard_manifest_route_stop_id' => $smartcard_manifest_id_installed,
                                );
                            }
                            $this->db->insert_batch('smartcard_manifest_route_stop_detail', $data_smart_route_stop_detail);
                            unset($data_smart_route_stop_detail);
                        }
                    }
                }

                if ($this->db->trans_status() === FALSE) {

                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();
                }
            }
        } catch (Exception $ex) {

            $this->db->trans_rollback();
        }

        if ($flag == 1) {
            echo "<script> alert('Route Inserted'); </script>";
            redirect('routes/routes_list', 'refresh');
        } else {
            echo "<script> alert('Route not inserted'); </script>";
            redirect('routes/routes_list', 'refresh');
        }
    }

    public function generate_loop_stop($route_id, $total_loop)
    {
        $this->custom_model->check_session();
        $this->db->where('route_id', $route_id);
        $this->db->where('status', 1);
        $this->db->order_by('position');
        $query = $this->db->get('route_stops');
        $stops_result = $query->result_array();
        $data_route_stops = array();
        $max_stop_position = 0;
        foreach ($stops_result as $stop) {
            if ((int)$stop['position'] > $max_stop_position) {
                $max_stop_position = (int)$stop['position'];
            }
        }
        $last_stop = end($stops_result);
        for ($i = 0; $i < $total_loop - 1; $i++) {

            foreach ($stops_result as $key => $stop) {
                $max_stop_position = $max_stop_position + 1;
                $previous_stop_id = $key == 0 ? $last_stop['stop_id'] : $stops_result[$key - 1]['stop_id'];
                $data_route_stops[] = array(
                    'route_id' => $route_id,
                    'stop_id' => $stop['stop_id'],
                    'section_number' => $stop['section_number'],
                    'previous_stop_id' =>  $previous_stop_id,
                    'position' => $max_stop_position,
                    'is_popular' => $stop['is_popular'],
                    'status' => $stop['status'],
                    'created_by' => $this->session_data['id'],
                    'created_date' => date('Y-m-d H:i:s'),
                    'updated_by' => $this->session_data['id'],
                    'updated_date' =>  date('Y-m-d H:i:s'),
                    'zone_id' => $stop['zone_id'],
                    'midpoint' => $stop['midpoint'] || '',
                );
            }
        }
        if ($this->db->insert_batch('route_stops', $data_route_stops) == 1) {
            redirect('routes/edit_route/id/' . $route_id . '?tab=stop', 'refresh');
        }
    }

    public function copy_route()
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $this->load->helper('url');
        $created_by = $this->input->post('created_by');
        //$route_category_id =  $this->input->post('old_category');
        $new_route_category_id = $this->input->post('new_category');
        $old_route_id = $this->input->post('route_id');
        $new_route_name = $this->input->post('new_route_name');
        $is_create_pm_route = $this->input->post('create_manifest_pm_route');
        if ($is_create_pm_route) {
            $this->copy_pm_route();
        }

        try {
            $this->db->trans_begin();
            //@JIRA TSM-396 update add col line_color_kml
            $sql = "INSERT INTO `route`(`name`,`route_description`,`route_category_id`, `number_of_section`, `departing_stop`, `ticket_type_id`, `status`, `is_manual`, `speed_limit`, `created_by`, `created_date`, `updated_by`, `updated_date`, `use_zone_ability`, `pax_count_ability`, `departing_zone_id`, `on_time_running_check`, `midpoint`, `planned_trip_per_day`, `route_number`, `print_out_end_of_route_report`, `loop_service`, `number_of_route_loop`, `window_time`, `planned_kms`, `route_type`, `dead_running_kms`, `timetabled_service_check`, `special_route_type` ,`m_f_term`, `m_f_hol`, `sat`, `sun`, `ph`, `activation_time`, `late_sign_on`, status_gps_navigation_line, link_kml_file, `line_color_kml`) (SELECT '$new_route_name',`route_description` , '$new_route_category_id', `number_of_section`, `departing_stop`, `ticket_type_id`, `status`, `is_manual`, `speed_limit`, `created_by`, NOW(), `updated_by`, NOW(), `use_zone_ability`, `pax_count_ability`, `departing_zone_id`, `on_time_running_check`, `midpoint`, `planned_trip_per_day`, `route_number`, `print_out_end_of_route_report`, `loop_service`, `number_of_route_loop`, `window_time`, `planned_kms`, `route_type`, `dead_running_kms`, `timetabled_service_check`, `special_route_type` , `m_f_term`, `m_f_hol`, `sat`, `sun`, `ph`, `activation_time`, `late_sign_on`, status_gps_navigation_line, link_kml_file, `line_color_kml` FROM `route` WHERE `id`= '$old_route_id')";
            //$query = $this->db->query($sql);
            $flag = 0;
            if ($this->db->query($sql) === TRUE) {
                $flag = 1;
                $last_inserted_route_id = $this->db->insert_id();


                $sql = "INSERT INTO `route_stops`( `route_id`, `stop_id`, `section_number`, `previous_stop_id`, `position`, `is_popular`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`, `zone_id`, `midpoint`) (SELECT  '$last_inserted_route_id'
                , `stop_id`, `section_number`, `previous_stop_id`, `position`, `is_popular`, `status`, `created_by`, NOW(), `updated_by`, NOW(), `zone_id`,  `midpoint` FROM `route_stops` WHERE `route_id`='$old_route_id')";
                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }

                // loop route
                $sql = "INSERT INTO `route_stop_departure_time`( `route_id`, `stop_id`, `departure_time`, `loop`, `created_date`)"
                    . " (SELECT  '$last_inserted_route_id', `stop_id`, `departure_time`, `loop`, NOW() FROM `route_stop_departure_time` WHERE `route_id`='$old_route_id')";
                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }


                $sql = "INSERT INTO `route_ticket_type`(`route_id`, `ticket_type_id`) (SELECT '$last_inserted_route_id',`ticket_type_id`  FROM `route_ticket_type` WHERE `route_id`= '$old_route_id')";
                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }


                $sql = "INSERT INTO `ticket_price_stops`( `route_ticket_type_id`, `route_id`, `stop_from`, `stop_to`, `section_travel`, `amount`, `status`, `created_by`, `created_date`, `updated_by`, `updated_date`) (SELECT  `route_ticket_type_id`, '$last_inserted_route_id',
            `stop_from`, `stop_to`, `section_travel`, `amount`, `status`, `created_by`, NOW(), `updated_by`, NOW() FROM `ticket_price_stops` WHERE `route_id`='$old_route_id')";

                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }



                $sql = "INSERT INTO `route_section`( `route_id`, `section_no`, `is_loop`) (SELECT  '$last_inserted_route_id', `section_no`, `is_loop` FROM `route_section` WHERE `route_id`='$old_route_id')";

                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }



                $sql = "INSERT INTO `route_departure_time`( `route_id`, `departure_time`, `created_by`, `created_date`, `updated_by`, `updated_date`) (SELECT  '$last_inserted_route_id', `departure_time`, `created_by`, 'NOW()', `updated_by`, NOW() FROM `route_departure_time` WHERE `route_id`= '$old_route_id')";
                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }


                $sql = "INSERT INTO `route_stop_to_stop_price`( `route_id`, `tickent_type_name`, `display_name`, `is_printable`, `is_weekly`, `valid_days`, `status`, `created_date`, `updated_date`, `created_by`, `updated_by`) (SELECT  '$last_inserted_route_id',
            `tickent_type_name`, `display_name`, `is_printable`, `is_weekly`, `valid_days`, `status`, NOW(), NOW(), `created_by`, `updated_by` FROM `route_stop_to_stop_price` WHERE `route_id`='$old_route_id')";
                if ($this->db->query($sql) === TRUE) {
                    $flag = 1;
                }

                $sql = "INSERT INTO `operation_date` (`route_id`, `date`, `status`) SELECT '$last_inserted_route_id', `date`, `status` FROM `operation_date` WHERE `route_id`='$old_route_id'";
                if ($this->db->query($sql)) {
                    $flag = 1;
                }

                $created_by = isset($this->session_login['account_info_id']) ? $this->session_login['account_info_id'] : $this->session_login['id'];
                $sql = "INSERT INTO `route_line` (`route_id`, `lat`, `lng`, `created_by`, `created_date`) SELECT '$last_inserted_route_id', `lat`, `lng`, '$created_by', NOW() FROM `route_line` where route_id = '$old_route_id'";
                if ($this->db->query($sql)) {
                    $flag = 1;
                }

                if ($this->db->trans_status() === FALSE) {

                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();
                }
            }
        } catch (Exception $ex) {

            $this->db->trans_rollback();
        }

        if ($flag == 1) {
            echo "<script> alert('Route Inserted'); </script>";
            redirect('routes/routes_list', 'refresh');
        } else {
            echo "<script> alert('Route not inserted'); </script>";
            redirect('routes/routes_list', 'refresh');
        }
    }

    public function copy_section_matrix()
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $from_route_id = $this->input->post('from_route_id');
        $to_route_ids = $this->input->post('to_route_ids');
        $sections = $this->db->query("SELECT stop_from, stop_to, section_travel FROM ticket_price_stops where route_id = " . $from_route_id)->result_array();

        if (count($to_route_ids) > 0) {
            // Make sure all missing section matrix created
            foreach ($to_route_ids as $route_id) {
                $this->update_section_travel_by_route_id($route_id);
            }

            // Copy over all section matrix
            $in_route_ids = implode(',', $to_route_ids);
            foreach ($sections as $stop_to_stop) {
                $section_data = array('section_travel' => $stop_to_stop['section_travel']);
                $this->db->set('updated_date', 'NOW()', FALSE);
                $this->db->where("stop_from =", $stop_to_stop['stop_from']);
                $this->db->where("stop_to =", $stop_to_stop['stop_to']);
                $this->db->where("route_id in ({$in_route_ids})");
                $this->db->update('ticket_price_stops', $section_data);
            }
        }

        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => true
        ));
    }

    ///////////////////////////////////    //////////////////////////////////

    public function get_route()
    {
        /// Check User Session///
        $this->custom_model->check_session();


        $array = $this->uri->uri_to_assoc();
        // @JIRA TSM-521
        if (!empty($this->account_info_id)) {
            $this->db->where('account_info_id', $this->account_info_id);
            $this->db->select('r.*, pcs.action');
            $this->db->join('route r', 'r.route_category_id = pcs.category_id');
            $this->db->where('pcs.status', 1);
            $this->db->where('r.status', 1);
            $this->db->order_by('id', 'ESC');
            $results = $this->db->get('permission_category_sub pcs')->result_array();
            // END @JIRA TSM-521
        } else {
            $this->db->order_by("id", "desc");
            $this->db->where("status =", "1");
            $query = $this->db->get('route');
            $results = $query->result_array();
        }
        return $results;
    }

    public function get_pending_route()
    {
        /// Check User Session///
        $this->custom_model->check_session();


        $array = $this->uri->uri_to_assoc();
        // @JIRA TSM-521
        if (!empty($this->account_info_id)) {
            $this->db->where('account_info_id', $this->account_info_id);
            $this->db->select('r.*, pcs.action');
            $this->db->join('route r', 'r.route_category_id = pcs.category_id');
            $this->db->where('pcs.status', 2);
            $this->db->where('r.status', 2);
            $this->db->order_by('id', 'ESC');
            $results = $this->db->get('permission_category_sub pcs')->result_array();
            // END @JIRA TSM-521
        } else {
            $this->db->order_by("id", "desc");
            $this->db->where("status =", "2");
            $query = $this->db->get('route');
            $results = $query->result_array();
        }
        return $results;
    }

    /**
     * This method is used to get data from route table
     * @author Shyam Sundar Pandey
     * @param unknown_type $id
     */
    public function get_route_data($id)
    {
        $this->custom_model->check_session();
        $this->db->where("id =", $id);
        $query = $this->db->get('route');
        return $query->result_array();
    }

    /**
     *
     * This method is used to get ticket types selected for the specified $route_id
     * @param unknown_type $route_id
     * @author Shyam Sundar Pandey
     */
    public function get_route_ticket_selected($route_id)
    {
        $this->db->select('ticket_type.id');
        $this->db->from('ticket_type');
        $this->db->join('route_ticket_type', 'ticket_type.id=route_ticket_type.ticket_type_id');
        $this->db->where('route_ticket_type.route_id', $route_id);
        $this->db->where('ticket_type.status', '1');
        $query = $this->db->get();
        $result = $query->result();
        $ticket_arr = array();
        foreach ($result as $row) {
            $ticket_arr[] = $row->id;
        }
        return $ticket_arr;
    }

    public function update_route_from_data($route_id, $data)
    {
        return $this->db->where('id', $route_id)->update('route', $data);
    }

    public function create_stops_from_data($datastop)
    {
        $stop_id = 0;
        if ($this->db->insert('stops', $datastop) == '1') {
            $stop_id = $this->db->insert_id();
        }
        return $stop_id;
    }

    public function create_route_line_from_batch($data)
    {
        $result = 0;
        if ($this->db->insert_batch('route_line', $data) == '1') {
            $result = 1;
        }
        return $result;
    }

    public function create_route_stops_from_data($data)
    {
        $route_stops_id = 0;
        if ($this->db->insert('route_stops', $data) == '1') {
            $route_stops_id = $this->db->insert_id();
        }
        return $route_stops_id;
    }

    public function create_route_from_data($data)
    {
        $lastid = 0;
        if ($this->db->insert('route', $data) == '1') {
            $lastid = $this->db->insert_id();
        }
        return $lastid;
    }

    public function create_route()
    {

        /// Check User Session///
        $this->custom_model->check_session();

        $this->load->helper('url');
        $created_by = $this->input->post('created_by');
        $route_category_id = $this->input->post('route_category');
        $special_route_type = $this->input->post('special_route_type');

        $route_name = $this->input->post('route_name');
        $route_description = !empty($this->input->post('route_description')) ? $this->input->post('route_description') : null;
        $section_no = $this->input->post('section_no');
        $departing_id = $this->input->post('departing_id');
        $stop_is_popular = $this->input->post('stop_is_popular') ? 1 : 0;
        $speed_limit = $this->input->post('speed_limit');
        $is_manual = $this->input->post('is_manual');
        $ticket_type = $this->input->post('ticket_type');
        $is_ticket_stop_to_stop = $this->input->post('is_ticket_stop_to_stop');
        $use_zone_ability = $this->input->post('use_zone_ability');
        $can_be_tracked = $this->input->post('can_be_tracked');
        $pax_count_ability = $this->input->post('pax_count_ability');
        $departing_zone_id = $this->input->post('departing_zone_id');
        $on_time_running_check = $this->input->post('on_time_running_check');
        $timetabled_service_check = $this->input->post('timetabled_service_check');
        $departure_time = $this->input->post('departure_time');
        $contract_number = $this->input->post('contract_number');
        $deboarding_alert = $this->input->post('deboarding_alert');
        if (empty($departure_time)) {
            $departure_time = null;
        }
        $planned_trip_per_day = $this->input->post('planned_trip_per_day');
        $route_number = $this->input->post('route_number');
        $midpoint = $this->input->post('midpoint');
        $print_out_end_of_route_report = $this->input->post('print_out_end_of_route_report');

        $planned_kms = $this->input->post('planned_kms');
        $route_type = $this->input->post('route_type');
        $dead_running_kms = $this->input->post('dead_running_kms');

        $activation_time = $this->input->post('activation_time');
        if (empty($activation_time)) {
            $activation_time = null;
        }

        $lateSignOn = $this->input->post('late_sign_on');
        $clear_of_bus = $this->input->post('clear_of_bus');
        $second_code_check = isset($clear_of_bus) ? $this->input->post('second_code_check') : null;
        $end_route_time = isset($clear_of_bus) ? $this->input->post('end_route_time') : null;


        $smartcardCheck = $this->input->post('smartcard_check');

        // for OR2 reporting
        $m_f_term = $this->input->post('m_f_term');
        $m_f_hol = $this->input->post('m_f_hol');
        $sat = $this->input->post('sat');
        $sun = $this->input->post('sun');
        $ph = $this->input->post('ph');

        // loop route
        $loop_service = $this->input->post('loop_service');
        $window_time = null;
        $number_of_route_loop = 0;
        if ($loop_service == 1) {
            $window_time = $this->input->post('window_time');
            if (empty($window_time)) {
                $window_time = null;
            }
            $number_of_route_loop = $this->input->post('number_of_route_loop');
        }
        $flag = 0;
        $on_demand_service = $this->input->post('on_demand_service_route');

        if ($this->check_tycket_type_with_route_create($ticket_type, $section_no) == 1) {

            $data = array(
                'name' => $route_name,
                'route_description' => $route_description,
                'route_category_id' => $route_category_id,
                'number_of_section' => $section_no,
                'departing_stop' => $departing_id,
                'speed_limit' => $speed_limit,
                'is_manual' => $is_manual,
                'use_zone_ability' => $use_zone_ability,
                'pax_count_ability' => $pax_count_ability,
                'can_be_tracked' => $can_be_tracked,
                'departing_zone_id' => $departing_zone_id,
                'on_time_running_check' => $on_time_running_check,
                'planned_trip_per_day' => $planned_trip_per_day,
                'route_number' => $route_number,
                'midpoint' => $midpoint,
                'print_out_end_of_route_report' => $print_out_end_of_route_report,
                'loop_service' => $loop_service,
                'window_time' => $window_time,
                'timetabled_service_check' => $timetabled_service_check,
                'number_of_route_loop' => $number_of_route_loop,
                'planned_kms' => $planned_kms,
                'route_type' => $route_type,
                'dead_running_kms' => $dead_running_kms,
                'm_f_term' => $m_f_term,
                'm_f_hol' => $m_f_hol,
                'sat' => $sat,
                'sun' => $sun,
                'ph' => $ph,
                'deboarding_alert' => $deboarding_alert,
                'clear_of_bus' => $clear_of_bus,
                'second_code_check' => $second_code_check,
                'end_route_time' => $end_route_time,
                'created_by' => $created_by,
                'updated_by' => $created_by,
                'special_route_type' => $special_route_type,
                'activation_time' => $activation_time,
                'late_sign_on' => $lateSignOn,
                'smartcard_check' => $smartcardCheck,
                'on_demand_service' => $on_demand_service,
                'contract_number_id' => $contract_number
            );

            $this->db->set('created_date', 'NOW()', FALSE);
            $this->db->set('updated_date', 'NOW()', FALSE);
            if ($this->db->insert('route', $data) == '1') {
                $lastid = $this->db->insert_id();

                if ($ticket_type) {
                    // sort ticket
                    if ($this->sort_ticket_ability) {
                        $data = array();
                        $sort_ticket_type = $this->_sort_ticket($ticket_type);
                        foreach ($sort_ticket_type as $key => $value) {
                            $data[] = array(
                                'route_id' => $lastid,
                                'ticket_type_id' => $value,
                                'order' => $key + 1
                            );
                        }
                        if (sizeof($data) > 0) {
                            if ($this->db->insert_batch('route_ticket_type', $data)) {
                                $flag = 1;
                            }
                        }
                    } else {
                        $data = array();
                        foreach ($ticket_type as $value) {
                            $data[] = array(
                                'route_id' => $lastid,
                                'ticket_type_id' => $value
                            );
                        }
                        if (sizeof($data) > 0) {
                            if ($this->db->insert_batch('route_ticket_type', $data)) {
                                $flag = 1;
                            }
                        }
                    }
                } else {
                    $flag = 1;
                }

                // VNS - loop route start
                if ($loop_service == 1) {
                    $value = array();
                    for ($i = 1; $i <= $number_of_route_loop; $i++) {
                        $stop_departure_time = $this->input->post('departure_time' . $i);
                        if (empty($stop_departure_time)) {
                            $stop_departure_time = null;
                        }
                        $value[] = array(
                            'route_id' => $lastid,
                            'stop_id' => $departing_id,
                            'departure_time' => $stop_departure_time,
                            'loop' => $i,
                            'created_date' => date('Y-m-d H:i:s')
                        );
                    }
                    if (count($value) > 0) {
                        $this->db->insert_batch('route_stop_departure_time', $value);
                    }
                } else {
                    $value = array(
                        'route_id' => $lastid,
                        'stop_id' => $departing_id,
                        'departure_time' => $departure_time,
                        'loop' => 1,
                        'created_date' => date('Y-m-d H:i:s')
                    );
                    $this->db->insert('route_stop_departure_time', $value);
                }
                // VNS -- loop route end

                // is popular stop function
                $value = [
                    'route_id' => $lastid,
                    'stop_id' => $departing_id,
                    'section_number' => 1,
                    'previous_stop_id' => 0,
                    'position' => 0,
                    'is_popular' => $stop_is_popular,
                    'status' => 1,
                    'created_by' => $created_by,
                    'updated_by' => $created_by,
                    // 'created_date' => ,
                    // 'updated_date' => ,
                    'zone_id' => null,
                    'departure_time' => $departure_time,
                    'midpoint' => $midpoint,
                    // 'stop_order'=>1, //@JIRA TSM-68
                ];

                $this->db->insert('route_stops', $value);


                if ($flag == 1) {
                    echo "<script> alert('Route Inserted'); </script>";
                    redirect('routes/routes_list', 'refresh');
                } else {
                    echo "<script> alert('Route ticket not inserted'); </script>";
                    redirect('routes/routes_list', 'refresh');
                }
            }
        } else {
            echo "<script> alert('Selected Ticket type section number is less then selected section number'); </script>";
            redirect('routes/routes_list', 'refresh');
        }
    }

    public function update_pending_route($route_stops, $route_id)
    {

        foreach ($route_stops as $key => $row) {

            if ($row['status'] == PENDING_ROUTE) {
                $stop = $this->db->where('id', $row['stop_id'])->get('stops')->row_array();
                $departing_stop =  $stop['near_by_stop'] != null ? $stop['near_by_stop'] : $stop['id'];
                // update route departing_stop
                if ($key == 0) {
                    $data_route = array(
                        'departing_stop' =>  $departing_stop,
                        'status'  =>  ACTIVE_ROUTE,
                    );

                    $this->db->where('id', $route_id)->update('route', $data_route);
                }

                // update route_stops
                if (isset($stop['near_by_stop'])) {

                    $data_route_stops = array(
                        'stop_id' =>  $stop['near_by_stop'],
                        'status'  =>  ACTIVE_ROUTE,
                    );

                    $this->db->where('route_id', $route_id)->where('stop_id', $row['stop_id'])->update('route_stops', $data_route_stops);
                } else {
                    $data_route_stops = array(
                        'status'  =>  ACTIVE_ROUTE,
                    );

                    $this->db->where('route_id', $route_id)->where('stop_id', $row['stop_id'])->update('route_stops', $data_route_stops);
                }

                // update stops
                if ($stop['status'] == IS_PENDING) {
                    $this->db->where('id', $stop['id'])->update('stops', array(
                        'status'  =>  IS_ACTIVE,
                    ));

                    $fb_data = array(
                        'id' => $stop['id'],
                        'name' => $stop['name'],
                        'lat' => $stop['lat'],
                        'lng' => $stop['lng'],
                        'is_popular' => isset($stop['is_popular']) ? $stop['is_popular'] : 0,
                    );

                    if (isset($stop['zone_id'])) {
                        $fb_data['zone_id'] = $stop['zone_id'];
                    }
                    $this->app_firestore->update_stop($fb_data);
                }
            }
        }
    }

    public function check_tycket_type_with_route_create($ticket_type, $section_no)
    {
        /// Check User Session///
        $this->custom_model->check_session();


        $this->db->where_in('id', $ticket_type);
        $query = $this->db->get('ticket_type');

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                if ($row->ticket_price_type_id == "2") {

                    if ($row->no_section < $section_no) {
                        return 0;
                        exit();
                    }
                }
            }
        }

        return 1;
    }
    //@Jira task: TSM-389
    public function get_departing_stop($route_id)
    {
        $this->db->where('id =', $route_id)->where('status =', '1');
        $route = $this->db->get('route')->row();
        return $route->departing_stop;
    }

    ///////

    /**
     *
     * This method is used to edit routes
     * @author Shyam Sundar Pandey (modified for complete route editing)
     */

    public function update_route()
    { /// Check User Session///
        $this->custom_model->check_session();

        $this->load->helper('url');
        $created_by = $this->input->post('created_by');
        $route_name = $this->input->post('route_name');
        $route_description = !empty($this->input->post('route_description')) ? $this->input->post('route_description') : null;
        $speed_limit = $this->input->post('speed_limit');
        $category = $this->input->post('category');
        $id = $this->input->post('id');
        $section_no = $this->input->post('section_no');
        $departing_stop = $this->input->post('departing_id');
        $is_manual = $this->input->post('is_manual');
        $ticket_type = $this->input->post('ticket_type');
        $use_zone_ability = $this->input->post('use_zone_ability');
        $can_be_tracked = $this->input->post('can_be_tracked');
        $pax_count_ability = $this->input->post('pax_count_ability');
        $departing_zone_id = $this->input->post('departing_zone_id');
        $departure_time = $this->input->post('departure_time');
        //@Jira task: TSM-389
        $departing_stop_old = $this->get_departing_stop($id);

        if (empty($departure_time)) {
            $departure_time = null;
        }
        $on_time_running_check = $this->input->post('on_time_running_check');
        $timetabled_service_check = $this->input->post('timetabled_service_check');

        $planned_trip_per_day = $this->input->post('planned_trip_per_day');
        $route_number = $this->input->post('route_number');
        $print_out_end_of_route_report = $this->input->post('print_out_end_of_route_report');

        $planned_kms = $this->input->post('planned_kms');
        $route_type = $this->input->post('route_type');
        $dead_running_kms = $this->input->post('dead_running_kms');

        $activation_time = $this->input->post('activation_time');
        if (empty($activation_time)) {
            $activation_time = null;
        }

        $lateSignOn = $this->input->post('late_sign_on');
        $smartcardCheck = $this->input->post('smartcard_check');

        // for OR2 reporting
        $m_f_term = $this->input->post('m_f_term');
        $m_f_hol = $this->input->post('m_f_hol');
        $sat = $this->input->post('sat');
        $sun = $this->input->post('sun');
        $ph = $this->input->post('ph');

        // loop route
        $loop_service = $this->input->post('loop_service');
        $window_time = null;
        $number_of_route_loop = 0;
        if ($loop_service == 1) {
            $window_time = $this->input->post('window_time');
            if (empty($window_time)) {
                $window_time = null;
            }
            $number_of_route_loop = $this->input->post('number_of_route_loop');
        }
        $current_number_of_route_loop = $this->input->post('current_number_of_route_loop');
        $special_route_type = $this->input->post('special_route_type');

        $current_number_of_section = $this->input->post('current_number_of_section');
        $current_cate_id = $this->input->post('current_cate_id');
        $on_demand_service = $this->input->post('on_demand_service_route');
        if ($this->check_tycket_type_with_route_create($ticket_type, $section_no) == 1) {
            $data = array(
                'name' => $route_name,
                'route_description' => $route_description,
                'speed_limit' => $speed_limit,
                'route_category_id' => $category,
                'number_of_section' => $section_no,
                'departing_stop' => $departing_stop,
                'is_manual' => $is_manual,
                'use_zone_ability' => $use_zone_ability,
                'pax_count_ability' => $pax_count_ability,
                'can_be_tracked' => $can_be_tracked,
                'departing_zone_id' => $departing_zone_id,
                'on_time_running_check' => $on_time_running_check,
                'planned_trip_per_day' => $planned_trip_per_day,
                'route_number' => $route_number,
                'print_out_end_of_route_report' => $print_out_end_of_route_report,
                'loop_service' => $loop_service,
                'window_time' => $window_time,
                'timetabled_service_check' => $timetabled_service_check,
                'number_of_route_loop' => $number_of_route_loop,
                'planned_kms' => $planned_kms,
                'dead_running_kms' => $dead_running_kms,
                'm_f_term' => $m_f_term,
                'm_f_hol' => $m_f_hol,
                'sat' => $sat,
                'sun' => $sun,
                'ph' => $ph,
                'route_type' => $route_type,
                'updated_by' => $created_by,
                'special_route_type' => $special_route_type,
                'activation_time' => $activation_time,
                'late_sign_on' => $lateSignOn,
                'smartcard_check' => $smartcardCheck,
                'on_demand_service' => $on_demand_service
            );
            $this->db->set('updated_date', 'NOW()', FALSE);
            $this->db->where("id =", $id);
            if ($this->db->update('route', $data) == '1') {

                //@Jira task: TSM-389
                $previous_stop_id_of_departing_stop_old =  $this->custom_model->getvalues_two("route_stops", "route_id", $id, "previous_stop_id", $departing_stop_old, "previous_stop_id");
                $stop_id_of_departing_stop_old =  $this->custom_model->getvalues_two("route_stops", "route_id", $id, "stop_id", $departing_stop_old, "stop_id");

                if (trim($previous_stop_id_of_departing_stop_old) != "") {
                    $data_previous_stop = array(
                        'previous_stop_id' => $departing_stop
                    );
                    $this->db->set('updated_date', 'NOW()', FALSE);
                    $this->db->where("route_id =", $id)->where("previous_stop_id =", $departing_stop_old);
                    $this->db->update('route_stops', $data_previous_stop);

                    if (trim($stop_id_of_departing_stop_old) != "") {
                        $data_stop_id = array(
                            'stop_id' => $departing_stop
                        );
                        $this->db->set('updated_date', 'NOW()', FALSE);
                        $this->db->where("route_id =", $id)->where("stop_id =", $departing_stop_old);
                        $this->db->update('route_stops', $data_stop_id);
                    } else {
                        $value = [
                            'route_id' => $id,
                            'stop_id' => $departing_stop,
                            'section_number' => 1,
                            'previous_stop_id' => 0,
                            'position' => 0,
                            'status' => 1,
                            'created_by' => $created_by,
                            'updated_by' => $created_by,
                            'zone_id' => null,
                            'departure_time' => $departure_time,
                        ];
                        $this->db->insert('route_stops', $value);
                    }
                }

                $this->db->delete('route_ticket_type', array('route_id' => $id));
                if ($ticket_type) {

                    if ($this->sort_ticket_ability) {
                        $data = array();
                        $sort_ticket_type = $this->_sort_ticket($ticket_type);
                        foreach ($sort_ticket_type as $key => $value) {
                            $data[] = array(
                                'route_id' => $id,
                                'ticket_type_id' => $value,
                                'order' => $key + 1
                            );
                        }
                        if (sizeof($data) > 0) {
                            if ($this->db->insert_batch('route_ticket_type', $data)) {
                                $flag = 1;
                            }
                        }
                    } else {
                        $data = array();
                        foreach ($ticket_type as $value) {
                            $data[] = array(
                                'route_id' => $id,
                                'ticket_type_id' => $value
                            );
                        }
                        if (sizeof($data) > 0) {
                            if ($this->db->insert_batch('route_ticket_type', $data)) {
                                $flag = 1;
                            }
                        }
                    }
                } else {
                    $flag = 1;
                }

                // VNS - loop route start
                // delete old data
                $this->db->delete('route_stop_departure_time', array('route_id' => $id, 'stop_id' => $departing_stop));

                if ($number_of_route_loop < $current_number_of_route_loop) {
                    $this->db->delete('route_stop_departure_time', "route_id = $id and stop_id != $departing_stop and `loop`  > $number_of_route_loop");
                }
                if ($loop_service == 1) {
                    $value = array();
                    for ($i = 1; $i <= $number_of_route_loop; $i++) {
                        $stop_departure_time = $this->input->post('departure_time' . $i);
                        if (empty($stop_departure_time)) {
                            $stop_departure_time = null;
                        }
                        $value[] = array(
                            'route_id' => $id,
                            'stop_id' => $departing_stop,
                            'departure_time' => $stop_departure_time,
                            'loop' => $i,
                            'created_date' => date('Y-m-d H:i:s')
                        );
                    }
                    if (count($value) > 0) {
                        $this->db->insert_batch('route_stop_departure_time', $value);
                    }
                } else {
                    $value = array(
                        'route_id' => $id,
                        'stop_id' => $departing_stop,
                        'departure_time' => $departure_time,
                        'loop' => 1,
                        'created_date' => date('Y-m-d H:i:s')
                    );
                    $this->db->insert('route_stop_departure_time', $value);
                }
                // VNS -- loop route end
                // BUS-769
                if ($section_no < $current_number_of_section) {
                    $this->db->update('route_stops', array('status' => 0), "route_id = $id and `section_number`  > $section_no");
                }

                if ($flag == 1) {

                    //update data of routes and stops assigned to smartcard
                    $this->load->model('smart_card_model');
                    $data_update = array(
                        'route_id' => $id,
                        'current_cate_id' => $current_cate_id,
                        'cate_id' => $category
                    );
                    $this->smart_card_model->update_smartcard_detail_route_stop_by_route_cate_id($data_update);

                    $update_msg = array('root_category_id' => $category);
                    $this->db->where("root_id =", $id);
                    if ($this->db->update('message', $update_msg) == '1') {
                        $update_trans = array('route_cat_id' => $category);
                        $this->db->where("route_id =", $id);
                        if ($this->db->update('transaction', $update_trans) == '1') {
                            $update_driver_bus_route = array('route_cat_id' => $category);
                            $this->db->where("route_id =", $id);
                            if ($this->db->update('driver_bus_route', $update_driver_bus_route) == '1') {
                                $status_gps_bundle = $this->input->post('gps_bundle');
                                if ($status_gps_bundle == 1) {
                                    //@JIRA TSM-347 Add code for Insert or Update line into route_line table
                                    $status_navigation_line = $this->input->post('status_gps_line');
                                    $data_color = $this->input->post('data_color'); //@JIRA TSM-414
                                    //Check condition navigation line for this route
                                    // to update status_navigation_line_navigation_line in route table
                                    switch ($status_navigation_line) {
                                        case $status_navigation_line == 'NON_NAV_LINE':
                                            $this->update_status_nav_line_for_route($id, '0', '', $created_by); //@JIRA TSM-414 add parameter $data_color to set color line
                                            break;
                                        case $status_navigation_line == 'NAV_LINE_KML':
                                            $this->update_status_nav_line_for_route($id, '1', $data_color, $created_by);
                                            break;
                                        case $status_navigation_line == 'NAV_LINE_GOOGLE_MAP_API':
                                            $this->update_status_nav_line_for_route($id, '2', '', $created_by);
                                            break;
                                            //@JIRA TSM-389 V.2
                                        case $status_navigation_line == 'NON_UPDATE':
                                            break;
                                    }
                                    $option = isset($_POST['guidance_method']) ? $_POST['guidance_method'] : false;
                                    //If option == KML_FILE then will be painting navigation line
                                    if ($option === 'KML_FILE') {
                                        $res_ins_or_up_line = '';
                                        $data_line = json_decode($this->input->post('data_line'));
                                        $existed = $this->input->post('existed_line');
                                        //Check condition EXISTED to update or insert route_line
                                        switch ($existed) {
                                            case $existed == 'EXISTED':
                                                //Update line (lat,lng) into route_line
                                                $res_ins_or_up_line = $this->update_route_line_by_kml($id, $created_by, $data_line);
                                                break;
                                            case $existed == 'NOT_EXISTED':
                                                //Insert line (lat,lng) into route_line
                                                $res_ins_or_up_line = $this->set_route_line_by_kml($id, $created_by, $data_line);
                                                break;
                                            case $existed == 'NOT_SAME':
                                                $res_ins_or_up_line = 0;
                                                break;
                                        }
                                        //@JIRA TSM-388 Upload navigation kml file
                                        if (isset($_FILES['file']) && $_FILES['file']['size'] > 0) {
                                            $this->load->model('transportme_api_model_v2');
                                            $upload = $this->transportme_api_model_v2->do_upload('file', 'kml');
                                            if ($upload && !$upload['error']) {
                                                //update linkfile
                                                $filename = $upload['filename'];
                                                $this->update_link_kml_for_route($id, $filename);
                                            }
                                        }

                                        $strMsg = 'Route Updated';
                                        switch ($res_ins_or_up_line) {
                                            case $res_ins_or_up_line === 1:
                                                $strMsg = 'Route and Line Updated. Navigation line with kml file data is setup.';
                                                break;
                                            case $res_ins_or_up_line === 0:
                                                $strMsg = 'Route Updated and Line UnUpdated. Navigation line with kml file data is not setup.';
                                                break;
                                        }
                                        echo "<script> alert('" . $strMsg . "'); </script>";
                                        redirect('routes/routes_list', 'refresh');
                                    } else {
                                        //This is the state that users choose to draw lines with google api
                                        echo "<script> alert('Route updated and Navigation line with google api is setup.'); </script>";
                                        redirect('routes/routes_list', 'refresh');
                                    }
                                } else {
                                    //Status gps_bundle off
                                    echo "<script> alert('Route Updated'); </script>";
                                    redirect('routes/routes_list', 'refresh');
                                    //@JIRA TSM-347 END
                                }
                            }
                        }
                    }
                } else {
                    echo "<script> alert('Route ticket not inserted'); </script>";
                    redirect('routes/routes_list', 'refresh');
                }
            }
        } else {

            echo "<script> alert('Selected Ticket type section number is less then selected section number'); </script>";
            redirect('routes/routes_list', 'refresh');
        }
    }
    public function update_route_status($status_id)
    {
        $data = ['status' => 1];
        $this->db->where('id',  $status_id)->update('route', $data);
    }
    public function delete_route_full($id)
    {
        /*
         * Delete Message
         */
        $this->db->where("root_id =", $id);
        $query = $this->db->get("message");
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $this->db->delete('message_driver', array('msg_id' => $row->id));
            }
        }

        $this->db->delete('message', array('root_id' => $id));

        /*
         * Delete route from all table
         *
         */
        $tables = array('transaction', 'ticket_price_stops', 'route_stops', 'driver_bus_route', 'route_stop_to_stop_price', 'route_section', 'route_ticket_type');
        $this->db->where('route_id =', $id);
        $this->db->delete($tables);





        if ($this->db->delete('route', array('id' => $id))) {
            echo "<script> alert('Route deleted'); </script>";
            redirect('routes/routes_list', 'refresh');
        }
    }

    public function delete_route_soft($id)
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $data = array(
            'status' => "0"
        );

        $this->db->where("id =", $id);
        if ($this->db->update('route', $data) == '1') {

            // update passenger list
            $this->db->update('passenger', array('cate_id' => null, 'route_id' => null, 'stop_id' => null), array('route_id' => $id));
            echo "<script> alert('Route deleted'); </script>";
            redirect('routes/routes_list', 'refresh');
        }
    }

    public function get_route_selected($id)
    {
        /// Check User Session///
        $this->custom_model->check_session();
        // @JIRA TMS-521
        if (!empty($this->account_info_id)) {
            $list_category = $this->get_route_category();
            $list_category_id = array_column($list_category, 'id');
            $this->db->where_in('route_category_id', $list_category_id);
        }
        // END @JIRA TSM-521
        $this->db->order_by("id", "desc");
        $this->db->where("status =", "1");
        $this->db->where("id =", $id);
        $query = $this->db->get('route');
        return $query->result_array();
    }

    public function save_popular_stops_by_section()
    {

        $is_popular = $this->input->post('is_popular');
        $stop_id = $this->input->post('stop_id');

        $data = array(
            'is_popular' => $is_popular,
        );

        $this->db->set('updated_date', 'NOW()', FALSE);
        // $this->db->where("is_popular",$is_popular);
        $this->db->where("id", $stop_id);
        if ($this->db->update('route_stops', $data) == '1') {
        }
    }

    // vinasource add 2016-03-24-- start
    function update_zone_stops()
    {
        $id = $this->input->post('id');
        $zone_id = $this->input->post('zone_id');
        $data = array(
            'zone_id' => $zone_id,
        );
        $this->db->set('updated_date', 'NOW()', FALSE);
        $this->db->where("id", $id);
        return $this->db->update('route_stops', $data);
    }
    //@Jira task: TSM-389
    public function update_position_by_route_id($preview_stop_middle, $route_stop, $route_id, $departing_stop)
    {

        $this->db->where('status =', '1')->where('stop_id =', $departing_stop);
        $query = $this->db->where('route_id =', $route_id)->get('route_stops');

        if ($query->num_rows() == 0) return;   //@Jira Task: TSM-389 when value in database not conrrect

        $row = $query->row();
        $previous_id = $departing_stop;
        $pre_pos = 0;

        do {

            $this->db->where('status =', '1')->where('previous_stop_id =', $previous_id)->where('section_number >', 0);
            $query = $this->db->where('route_id =', $route_id)->get('route_stops');

            if ($query->num_rows() == 0) return;    //@Jira Task: TSM-389 when value in database not conrrect

            $row = $query->row();

            $previous_id = $row->stop_id;
            $data = array('position' => $pre_pos += 1);
            $this->db->where('id', $row->id)->update('route_stops', $data);
        } while ($previous_id != $departing_stop);
    }
    //@Jira task: TSM-389
    public function get_list_position($previous_route_stop_position_middle, $route_id)
    {
        $this->db->select("id, position");
        $this->db->where("status =", "1");
        $this->db->where("route_id =", $route_id);
        $this->db->where("position >", $previous_route_stop_position_middle);
        $query = $this->db->get('route_stops');
        return $query->result_array();
    }
    //@Jira task: TSM-389
    public function get_info_preview_stop_middle($previous_id, $route_id)
    {
        $this->db->select("id, position");
        $this->db->where("status =", "1");
        $this->db->where("route_id =", $route_id);
        $this->db->where("previous_stop_id =", $previous_id);
        $this->db->where("section_number >", 0);
        $query = $this->db->get('route_stops');
        return $query->row();
    }
    // vinasource add 2016-03-24-- end
    public function create_route_stops_old()
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->load->helper('url');
        $created_by = $this->input->post('created_by');
        $route_id = $this->input->post('route_id');
        $route_stop = $this->input->post('route_stop');
        $section_no = $this->input->post('section_no');
        $previous_id = $this->input->post('previous_id');
        $ispopular = $this->input->post('ispopular') == 1 ? 1 : 0;
        $zone_id = $this->input->post('zone_id');
        $midpoint = $this->input->post('midpoint');
        $c_stop = $this->check_route_stops($route_id, $section_no, $route_stop, $previous_id);

        if ($c_stop == '1') {

            $stop_position = $this->check_route_stops_position($route_id, $section_no, $route_stop, $previous_id);
            $stop_position = $stop_position + 1;

            $this->db->where("status =", "0");
            $this->db->where("route_id", $route_id);
            $this->db->where("stop_id", $route_stop);
            $query1 = $this->db->get('route_stops');
            if ($query1->num_rows() > 0) {
                $data = array(
                    'section_number' => $section_no,
                    'previous_stop_id' => $previous_id,
                    'is_popular' => $ispopular,
                    'position' => $stop_position,
                    'status' => "1",
                    'zone_id' => $zone_id,
                    'midpoint' => $midpoint,
                    'updated_by' => $created_by
                );

                $this->db->set('updated_date', 'NOW()', FALSE);
                $this->db->where("status =", "0");
                $this->db->where("route_id", $route_id);
                $this->db->where("stop_id", $route_stop);
                if ($this->db->update('route_stops', $data) == '1') {
                    $this->update_stop_position($route_id, $previous_id);
                    // loop route
                    $this->_save_departure_time();

                    echo "<script> alert('Route stops inserted'); </script>";
                    redirect('routes/routes_list/route_stops/' . $route_id, 'refresh');
                }
            } else {

                $this->db->where("status =", "1");
                $this->db->where("route_id", $route_id);
                $this->db->where("stop_id", $route_stop);
                $this->db->where("previous_stop_id", $previous_id);
                $query1 = $this->db->get('route_stops');
                if ($query1->num_rows() > 0) {
                    echo "<script> alert('Route stops already exist'); </script>";
                    echo "<script> window.opener.location.reload(); window.self.close();</script>";

                    redirect('routes/routes_list/route_stops/' . $route_id, 'refresh');
                } else {

                    $data = array(
                        'route_id' => $route_id,
                        'stop_id' => $route_stop,
                        'section_number' => $section_no,
                        'previous_stop_id' => $previous_id,
                        'is_popular' => $ispopular,
                        'zone_id' => $zone_id,
                        'midpoint' => $midpoint,
                        'position' => $stop_position,
                        'created_by' => $created_by,
                        'updated_by' => $created_by
                    );


                    $this->db->set('created_date', 'NOW()', FALSE);
                    $this->db->set('updated_date', 'NOW()', FALSE);

                    if ($this->db->insert('route_stops', $data) == '1') {
                        // loop route
                        $this->_save_departure_time();

                        //update section travel chart
                        $this->update_section_travel_by_route_id($route_id);

                        //echo "<script> alert('hi'); </script>";
                        //echo "<script> alert('Route stops inserted'); </script>";
                        echo "<script> alert('Route stops inserted'); </script>";
                        echo "<script> window.opener.location.reload(); window.self.close();</script>";
                        redirect('routes/routes_list/route_stops/' . $route_id, 'refresh');
                    }
                }
            }
        } else {

            $stop_id_previous = $this->custom_model->getvalues_three("route_stops", "route_id", $route_id, "previous_stop_id", $previous_id, "status", "1", "stop_id");
            $stop_id_previous_position = $this->custom_model->getvalues_three("route_stops", "route_id", $route_id, "previous_stop_id", $previous_id, "status", "1", "position");
            $route_stop_id = $this->custom_model->getvalues_three("route_stops", "route_id", $route_id, "previous_stop_id", $previous_id, "status", "1", "id");
            $stop_position = $stop_id_previous_position;

            $this->db->where("status =", "0");
            $this->db->where("route_id", $route_id);
            $this->db->where("stop_id", $route_stop);
            $query1 = $this->db->get('route_stops');
            if ($query1->num_rows() > 0) {
                $data = array(
                    'section_number' => $section_no,
                    'previous_stop_id' => $previous_id,
                    'is_popular' => $ispopular,
                    'position' => $stop_position,
                    'status' => "1",
                    'zone_id' => $zone_id,
                    'midpoint' => $midpoint,
                    'updated_by' => $created_by
                );

                $this->db->set('updated_date', 'NOW()', FALSE);
                $this->db->where("status =", "0");
                $this->db->where("route_id", $route_id);
                $this->db->where("stop_id", $route_stop);
                if ($this->db->update('route_stops', $data) == '1') {
                    $data = array(
                        'previous_stop_id' => $route_stop,
                    );

                    $this->db->where("id =", $route_stop_id);
                    if ($this->db->update("route_stops", $data) == '1') {
                        $this->update_stop_position($route_id, $previous_id);
                        // loop route
                        $this->_save_departure_time();

                        echo "<script> alert('Route stops inserted'); </script>";
                        echo "<script> window.opener.location.reload(); window.self.close();</script>";
                        //  echo "<script> alert('Route stops inserted'); </script>";
                        redirect('routes/routes_list/route_stops/' . $route_id, 'refresh');
                    }
                }
            } else {

                $this->db->where("status =", "1");
                $this->db->where("route_id", $route_id);
                $this->db->where("stop_id", $route_stop);
                $this->db->where("previous_stop_id", $previous_id);
                $query1 = $this->db->get('route_stops');

                if ($query1->num_rows() > 0) {
                    echo "<script> alert('Route stops already exist'); </script>";
                    redirect('routes/routes_list/route_stops/' . $route_id, 'refresh');
                } else {

                    $data = array(
                        'route_id' => $route_id,
                        'stop_id' => $route_stop,
                        'section_number' => $section_no,
                        'previous_stop_id' => $previous_id,
                        'is_popular' => $ispopular,
                        'zone_id' => $zone_id,
                        'midpoint' => $midpoint,
                        'position' => $stop_position,
                        'created_by' => $created_by,
                        'updated_by' => $created_by
                    );

                    $this->db->set('created_date', 'NOW()', FALSE);
                    $this->db->set('updated_date', 'NOW()', FALSE);

                    if ($this->db->insert('route_stops', $data) == '1') {

                        $data = array(
                            'previous_stop_id' => $route_stop,
                        );

                        $this->db->where("id =", $route_stop_id);
                        if ($this->db->update("route_stops", $data) == '1') {
                            $this->update_stop_position($route_id, $previous_id);
                            // loop route
                            $this->_save_departure_time();

                            //update section travel chart
                            $this->update_section_travel_by_route_id($route_id);

                            //echo "<script> alert('bye'); </script>";
                            echo "<script> alert('Route stops inserted'); </script>";
                            echo "<script> window.opener.location.reload(); window.self.close();</script>";
                            redirect('routes/routes_list/route_stops/' . $route_id, 'refresh');
                        }
                    }
                }
            }
        }
    }

    /**
     * @description: check whether previous top is not active in requesting route
     */
    private function check_route_stops($route_id, $section_no, $stop_id, $previous_stop_id)
    {

        $this->db->where("route_id =", $route_id)->where("status =", "1");
        $this->db->where("previous_stop_id =", $previous_stop_id);

        return $this->db->get('route_stops')->num_rows() > 0 ? 0 : 1;
    }

    //////// This function check route sotps position ///////////////////
    private function check_route_stops_position($route_id, $section_no, $stop_id, $previous_stop_id)
    {
        $d_id = $this->custom_model->getvalues("route", "id", $route_id, "departing_stop");


        $this->db->where("route_id =", $route_id);
        //$this->db->where("section_number =",$section_no);

        $this->db->where("status =", "1");
        $this->db->where("stop_id =", $previous_stop_id);
        //$this->db->where("previous_stop_id !=",$d_id);
        $query = $this->db->get('route_stops');

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                return $row->position;
            }
        } else {
            //  return $this->db->last_query();
            return 0;
        }
    }

    public function get_stops_ticket_from_route($route_id)
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $array = $this->uri->uri_to_assoc();

        $this->db->order_by("id", "desc");
        $this->db->where("status =", "1");
        $this->db->where("route_id =", $route_id);
        $query = $this->db->get('route_stop_to_stop_price');
        return $query->result_array();
    }

    public function get_route_ticket_detail($route_id)
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $array = $this->uri->uri_to_assoc();


        $this->db->where("status =", "1");
        $this->db->where("route_id =", $route_id);
        $query = $this->db->get('ticket_price_stops');
        return $query->result_array();
    }

    public function logdata()
    {

        $data = 'Request URI is ' . $_SERVER['REQUEST_URI'] . ' data is ' . print_r($_REQUEST, true);
        $content = date('Y-m-d l H:i:s ') . $data; //Write the log
        $file = "transportmelog/" . date('mdy') . 'log.txt'; //log file
        if (file_exists($file)) {
            $handle = fopen($file, 'a');
        } else {
            $handle = fopen($file, 'w');
        }
        fputs($handle, "$content");
        fputs($handle, "\n");
        fclose($handle);
    }

    public function logText($textInput)
    {

        $file = "transportmelog/" . date('mdy') . 'deep-log.txt'; //log file
        if (file_exists($file)) {
            $handle = fopen($file, 'a');
        } else {
            $handle = fopen($file, 'w');
        }
        fputs($handle, $textInput);
        fputs($handle, "\n");
        fclose($handle);
    }

    public function create_route_stops_to_stops()
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->load->helper('url');
        $created_by = $this->input->post('created_by');
        $route_id = $this->input->post('route_id');

        //$this->logdata();
        //$this->logText("Step1");


        $flag = 0;



        $route_stops = $this->route_stops($route_id);
        //$this->logText("Step2");

        $i = 1;
        foreach ($route_stops as $stops) {
            $j = 1;
            foreach ($route_stops as $nstops) {
                if ($stops['stop_id'] == $nstops['stop_id']) {
                } else {
                    if ($i < $j) {

                        $data = array(
                            'route_id' => $route_id,
                            'stop_from' => $stops['stop_id'],
                            'stop_to' => $nstops['stop_id'],
                            'section_travel' => $this->input->post('select:' . $stops['stop_id'] . "_" . $nstops['stop_id']),
                            'amount' => $this->input->post($stops['stop_id'] . "_" . $nstops['stop_id']),
                            'created_by' => $created_by,
                            'updated_by' => $created_by
                        );
                        $this->db->set('created_date', 'NOW()', FALSE);
                        $this->db->set('updated_date', 'NOW()', FALSE);
                        if ($this->db->insert('ticket_price_stops', $data) == '1') {
                            $flag = 1;
                        }
                    } else {
                    }
                }
                $j = $j + 1;
            }
            $i = $i + 1;
        }


        //$this->logText("Step3");

        if ($flag == 1) {
            echo "<script> alert('Data inserted'); </script>";
            //@JIRA TSM-681
            redirect('routes/edit_route/id/' . $route_id, 'refresh');
        } else {
            echo "<script> alert('Data not inserted'); </script>";
            //@JIRA TSM-681
            redirect('routes/edit_route/id/' . $route_id, 'refresh');
        }
    }

    public function update_route_stops_to_stops()
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->load->helper('url');
        $created_by = $this->input->post('created_by');
        $route_id = $this->input->post('route_id');

        $this->logText("step1" . (microtime() * 1000));
        $this->logText(print_r($_REQUEST, true));

        $flag = 0;


        $this->logText("step1.5" . $route_id);

        $route_stops = $this->route_stops($route_id);
        //  $this->logdata();
        $this->logText("step2" . (microtime() * 1000));

        $i = 1;
        foreach ($route_stops as $stops) {
            $j = 1;
            foreach ($route_stops as $nstops) {
                if ($stops['stop_id'] == $nstops['stop_id']) {
                } else {
                    if ($i < $j) {
                        $query3 = $this->db->get_where('ticket_price_stops', array('route_id' => $route_id, 'stop_from' => $stops['stop_id'], 'stop_to' => $nstops['stop_id']));
                        if ($query3->num_rows() > 0) {

                            $data1 = array(
                                'route_id' => $route_id,
                                'stop_from' => $stops['stop_id'],
                                'stop_to' => $nstops['stop_id'],
                                'section_travel' => $this->input->post('select:' . $stops['stop_id'] . "_" . $nstops['stop_id']),
                                'amount' => $this->input->post($stops['stop_id'] . "_" . $nstops['stop_id']),
                                'status' => "1",
                                'updated_by' => $created_by
                            );
                            $this->db->where('route_id =', $route_id);
                            $this->db->where('stop_from =', $stops['stop_id']);
                            $this->db->where('stop_to =', $nstops['stop_id']);
                            if ($this->db->update('ticket_price_stops', $data1) == '1') {
                                $flag = 1;
                            }
                        } else {
                            $data1 = array(
                                'route_id' => $route_id,
                                'stop_from' => $stops['stop_id'],
                                'stop_to' => $nstops['stop_id'],
                                'section_travel' => $this->input->post('select:' . $stops['stop_id'] . "_" . $nstops['stop_id']),
                                'amount' => $this->input->post($stops['stop_id'] . "_" . $nstops['stop_id']),
                                'created_by' => $created_by,
                                'updated_by' => $created_by
                            );

                            if ($this->db->insert('ticket_price_stops', $data1) == '1') {
                                $flag = 1;
                            }
                        }
                    } else {
                    }
                }
                $j = $j + 1;
            }
            $i = $i + 1;
        }

        $flag = 1;

        // $this->logdata();
        $this->logText("step3" . (microtime() * 1000));



        if ($flag == 1) {
            echo "<script> alert('Data updated'); </script>";
            redirect('routes/routes_list/route_stops/' . $route_id, 'refresh');
        } else {
            echo "<script> alert('Data not Updated'); </script>";
            redirect('routes/routes_list/route_stops/' . $route_id, 'refresh');
        }
    }

    public function get_route_ticket_details($route_id, $stop_from)
    {
        $this->custom_model->check_session();

        $array = $this->uri->uri_to_assoc();


        $this->db->where("status =", "1");

        $this->db->where("stop_from =", $stop_from);
        $this->db->where("route_id =", $route_id);
        $query = $this->db->get('ticket_price_stops');
        if ($query->num_rows() > 0) {

            $data = array();
            foreach ($query->result() as $row) {
                $data[$row->stop_to] = $row->section_travel;
            }


            return $data;
        } else {
            return 0;
        }
    }

    public function update_route_stops_to_stops_single()
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->load->helper('url');
        $created_by = $this->input->post('created_by');
        $route_id = $this->input->post('route_id');
        $stop_from = $this->input->post('stop_from');
        $stop_to = $this->input->post('stop_to');
        $section_data = $this->input->post('section_data');

        try {
            $this->db->trans_start();
            $query_existing_entry = $this->db->get_where('ticket_price_stops', array('route_id' => $route_id, 'stop_from' => $stop_from, 'stop_to' => $stop_to));
            if ($query_existing_entry->num_rows() > 0) {

                $data1 = array(
                    'route_id' => $route_id,
                    'stop_from' => $stop_from,
                    'stop_to' => $stop_to,
                    'section_travel' => $section_data,
                    'amount' => "0",
                    'status' => "1",
                    'updated_by' => $created_by
                );
                $this->db->where('route_id =', $route_id);
                $this->db->where('stop_from =', $stop_from);
                $this->db->where('stop_to =', $stop_to);
                $this->db->update('ticket_price_stops', $data1);
            } else {
                $data1 = array(
                    'route_id' => $route_id,
                    'stop_from' => $stop_from,
                    'stop_to' => $stop_to,
                    'section_travel' => $section_data,
                    'amount' => "0",
                    'created_by' => $created_by,
                    'updated_by' => $created_by
                );

                $this->db->insert('ticket_price_stops', $data1);
            }

            // Delete the reversed entry
            $this->db->where('stop_from', $stop_to);
            $this->db->where('stop_to', $stop_from);
            $this->db->where('route_id', $route_id);
            $this->db->delete('ticket_price_stops');
            echo "true";
        } catch (Exception $exception) {
            echo "false";
        } finally {
            $this->db->trans_commit();
        }
    }

    /**
     * @description update old stop positions after insert / delete a stop
     * @revision @author duy.ton
     * @revision @JIRA TSM-208 optimize & convert from recursive call to do...while... loop
     * @revision @JIRA TSM-210 update stops' position starting with departing stop
     */
    public function update_stop_position($route_id, $previous_id)
    {

        $this->db->where('id =', $route_id)->where('status =', '1');
        $route = $this->db->get('route')->row();
        if (!isset($route->departing_stop)) return;
        // gracefully replace $previous_id with current route's departing stop
        $previous_id = $route->departing_stop;
        //==== status == 1 , session != 0
        // get record having $previous_id as stop_id field
        $this->db->where('status =', '1')->where('stop_id =', $previous_id);
        $query = $this->db->where('route_id =', $route_id)->get('route_stops');
        if ($query->num_rows() == 0) return;

        $row = $query->row(); // data has $previous_id as stop_id
        $pre_pos = ($row->position == ' ' || !isset($row->position)) ? 0 : $row->position;

        do { // re-update position values of other stops

            $this->db->where('status =', '1')->where('previous_stop_id =', $previous_id);
            $query = $this->db->where('route_id =', $route_id)->get('route_stops');
            if ($query->num_rows() == 0) return; // (when there isn't a stops loop ...)

            $row = $query->row();
            $previous_id = $row->stop_id; // condition for next stop record
            // update new position value to that next stop
            $data = array('position' => $pre_pos += 1);
            $this->db->where('id', $row->id)->update('route_stops', $data);
        } while ($previous_id != $route->departing_stop); // end of loop when get back to the beginning

    }

    public function get_stops_from_dbr($driver_bus_route_id)
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->db->select('route_id');
        $this->db->where('id', $driver_bus_route_id);

        $query = $this->db->get('driver_bus_route');
        return  $this->get_stops_from_route($query->row()->route_id);
    }

    public function get_stops_from_route($route_id)
    {
        /// Check User Session///
        $this->custom_model->check_session();


        $this->db->select('stop_id');
        $this->db->where('status', "1");
        $this->db->where('route_id', $route_id);

        $query = $this->db->get('route_stops');



        $stop_id = [];
        foreach ($query->result_array() as $id) {
            $stop_id[] = $id['stop_id'];
        }

        $stop_id[] = $this->custom_model->getvalues("route", "id", $route_id, "departing_stop");

        $today = date("Y-m-d");
        $this->db->where("status", "1");
        $this->db->order_by("id", "desc");
        $this->db->where_in('id', $stop_id);
        $query = $this->db->get('stops');
        return $query->result_array();
    }

    public function get_stops_from_route_new($route_id)
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->db->select('stop_id');
        $this->db->where('status', "1");
        $this->db->where('route_id', $route_id);
        $query = $this->db->get('route_stops');
        $stop_id = [];
        foreach ($query->result_array() as $id) {
            $stop_id[] = $id['stop_id'];
        }

        $stop_id[] = $this->custom_model->getvalues("route", "id", $route_id, "departing_stop");

        $today = date("Y-m-d");
        $this->db->where("status", "1");
        $this->db->order_by("name");
        // $this->db->where_not_in('id', $stop_id);
        $query = $this->db->get('stops');
        return $query->result_array();
    }
    public function get_zone_names()
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $this->db->where("status", "1");
        $this->db->order_by("id");
        $query = $this->db->get('zone');
        return $query->result_array();
    }

    public function get_all_stops_from_route()
    {
        $this->custom_model->check_session();
        $this->db->where("status", "1");
        $this->db->order_by("name");
        $query = $this->db->get('stops');
        return $query->result_array();
    }
    public function get_all_zone_from_route()
    {
        $this->custom_model->check_session();
        $this->db->where("status", "1");
        $this->db->order_by("name");
        $query = $this->db->get('zone');
        return $query->result_array();
    }

    public function get_stops_from_route_by_id($route_id)
    {
        $this->custom_model->check_session();
        $this->db->select('stop_id');
        $this->db->where("status", "1");
        $this->db->where("route_id", $route_id);
        $this->db->order_by("id", "desc");
        $query = $this->db->get('route_stops');
        return $query->result_array();
    }

    public function get_driver_bus_from_dbr($id)
    {
        $this->db->select('b.bus_num as bus_num, dd.first_name as first_name, dd.last_name as last_name');
        $this->db->from('driver_bus_route as dbr');
        $this->db->join('driver_detail as dd', 'dd.id = dbr.driver_id');
        $this->db->join('buses as b', 'b.id = dbr.bus_id');
        $this->db->where('dbr.id', $id);
        $query = $this->db->get();
        return $query->row();
    }

    public function route_stops_previous($route_id)
    {

        /// Check User Session///
        $this->custom_model->check_session();

        $this->db->where('status =', "1");
        $this->db->where('route_id', $route_id);
        $this->db->order_by('position', 'asc');
        $query = $this->db->get('route_stops');



        return $query->result_array();
    }

    public function route_stops($route_id, $for_section_matrix = false)
    {

        /// Check User Session///
        $this->custom_model->check_session();
        //$this->logdata();
        // $this->logText("step1");
        $query1 = $this->db->query('SELECT departing_stop as stop_id FROM `route` WHERE id = ' . $route_id);
        //$this->logdata();
        //  $this->logText("step2");

        //TSM 325 ADD CONDITON  section_number !=0 (FIX BUG departing stop don't view again when it looks the way back from end stop back departing_stop)
        $section_order = $for_section_matrix ? 'section_number asc, ' : '';
        $query = $this->db->query("select stop_id from route_stops where section_number !=0 and route_id = ${route_id} and status = 1 ORDER BY ${section_order} position asc");
        //END TSM 325
        $q_array = $query->result_array();
        return array_merge((array) $query1->result_array(), (array) $q_array);
    }

    public function route_stops_full($route_id)
    {
        /// Check User Session///
        // $this->custom_model->check_session();
        // $query = $this->db->query('SELECT * from route_stops rs where rs.route_id = ' . $route_id
        //                         )
        $this->db->select('s.name, s.lat, s.lng, rs.section_number, rs.position');
        $this->db->from('route_stops rs');
        $this->db->join('stops s', 'rs.stop_id = s.id', 'left');
        $this->db->where('rs.route_id', $route_id);
        $this->db->order_by('rs.position', 'asc');
        $query = $this->db->get();
        return $query->result();
    }

    public function route_stops_full_firebase($route_id)
    {
        /// Check User Session///
        // $this->custom_model->check_session();
        // $query = $this->db->query('SELECT * from route_stops rs where rs.route_id = ' . $route_id
        //                         )
        $this->db->select('s.name, s.lat, s.lng, rs.section_number, rs.position');
        $this->db->from('route_stops rs');
        $this->db->join('stops s', 'rs.stop_id = s.id', 'left');
        $this->db->where('rs.route_id', $route_id);
        $this->db->order_by('rs.position', 'asc');
        $query = $this->db->get();
        return $query->row();
    }

    public function get_stops_from_route_by_section_no($route_id, $section_no)
    {
        /// Check User Session///
        $this->custom_model->check_session();

        if ($section_no > 1) {
            $section = $section_no - 1;

            $this->db->order_by("position", "desc");
            $this->db->where('status =', "1");
            $this->db->where('route_id', $route_id);
            $this->db->where('section_number', $section);

            $query = $this->db->get('route_stops', "1", "0");
            if ($query->num_rows() > 0) {

                return $query->result_array();
            } else {
                return $this->get_stops_from_route_by_section_no($route_id, $section);
            }
        } else {
            return array(array('stop_id' => $this->custom_model->getvalues("route", "id", $route_id, "departing_stop")));
        }
    }

    public function get_stops_from_route_by_section_no_present($route_id, $section_no)
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->db->order_by("position", "asc");
        $this->db->where('status =', "1");
        $this->db->where('route_id', $route_id);
        $this->db->where('section_number', $section_no);

        $query = $this->db->get('route_stops');

        return $query->result_array();
    }

    public function get_ticket_type()
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->db->order_by("id", "desc");
        $this->db->where("status =", "1");
        $query = $this->db->get('ticket_type');
        return $query->result_array();
    }

    public function section_isloop()
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->load->helper('url');
        $route_id = $this->input->post('route_id');
        $section_no = $this->input->post('section_no');


        $this->db->where("route_id =", $route_id);
        $this->db->where("section_no =", $section_no);
        $query = $this->db->get('route_section');


        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                if ($row->is_loop == 1) {
                    $loop = 0;
                } else {
                    $loop = 1;
                }


                $data = array(
                    'is_loop' => $loop
                );

                $this->db->where("route_id =", $route_id);
                $this->db->where("section_no =", $section_no);

                if ($this->db->update('route_section', $data) == '1') {
                }
            }
        } else {
            $data = array(
                'route_id' => $route_id,
                'section_no' => $section_no,
                'is_loop' => "1"
            );
            if ($this->db->insert('route_section', $data) == '1') {
            }
        }
    }

    public function delete_route_stop()
    {
        $this->db->trans_start();
        try {
            $this->custom_model->check_session();

            $route_id = $this->input->post('route_id');
            $route_stop_ids = $this->input->post('route_stop_ids');

            if (!is_numeric($route_id)) {
                throw new Exception("Invalid route_id");
            }

            // Update all status to 0;
            $data = array(
                'status' => "0",
                'position' => "0"
            );
            $this->db->where_in("id", $route_stop_ids);
            $this->db->update('route_stops', $data);

            // Update position number and previous_id;
            $this->db->query("update route_stops, (SELECT @row_number:=@row_number+1 AS position_new, id FROM route_stops,
                (SELECT @row_number:=-1) AS t
                where route_id = $route_id and status = 1
                ORDER BY position) as tblNewPos
                set route_stops.position = tblNewPos.position_new
                where route_stops.id = tblNewPos.id and route_stops.status = 1 and route_stops.route_id = $route_id");

            $this->db->query("update route_stops rs 
                set previous_stop_id = IFNULL((select stop_id
                from (select * from route_stops) rsi 
                where rsi.position < rs.position 
                and rsi.route_id = $route_id and rsi.status = 1
                order by rsi.position desc
                limit 1), 0)
                where rs.route_id = $route_id and rs.status = 1;");

            $this->db->query("delete from ticket_price_stops
                where route_id = $route_id 
                and (stop_from not in (select stop_id from route_stops rs where rs.route_id = $route_id and rs.status = 1)
                or stop_to not in (select stop_id from route_stops rs where rs.route_id = $route_id and rs.status = 1));");

            $this->db->query("update route 
                set departing_stop = (select stop_id from route_stops rs where rs.route_id = $route_id and rs.status = 1 and rs.position = 0 limit 1)
                where route.id = $route_id");
        } finally {
            $this->db->trans_complete();
        }
    }

    //VINASOURCE add 04March2016 -- start
    function get_route_info($id)
    {
        if (!is_numeric($id)) {
            return false;
        }
        $this->db->where('id', $id);
        $query = $this->db->get('route');
        return $query->row();
    }

    //VINASOURCE add 04March2016 -- end

    function getStopListBySection($route_id, $section_no)
    {
        $this->db->select('rs.*, s.name');
        $this->db->from('route_stops rs');
        $this->db->join('stops s', 'rs.stop_id = s.id', 'left');
        $this->db->where('rs.status', 1);
        $this->db->where('rs.route_id', $route_id);
        $this->db->where('rs.section_number', $section_no);
        $this->db->where('s.status', 1);
        $this->db->order_by("position", "asc");
        $query = $this->db->get();
        return $query->result();
    }

    function update_departure_time()
    {
        $route_id = $this->input->post('route_id');
        $stop_id = $this->input->post('stop_id');
        $route_number = $this->input->post('route_number');
        $departure_time = $this->input->post('departure_time');
        if (empty($departure_time)) {
            $departure_time = null;
        }

        $this->db->where('route_id', $route_id);
        $this->db->where('stop_id', $stop_id);
        $this->db->where('loop', $route_number);
        $query = $this->db->get('route_stop_departure_time');
        $row = $query->row();
        if ($row) {
            $this->db->set('created_date', 'NOW()', FALSE);
            $this->db->where("id", $row->id);
            $re = $this->db->update('route_stop_departure_time', array('departure_time' => $departure_time));
        } else {
            $data = array(
                'route_id' => $route_id,
                'stop_id' => $stop_id,
                'loop' => $route_number,
                'departure_time' => $departure_time,
                'created_date' => date('Y-m-d H:i:s')
            );
            $this->db->insert('route_stop_departure_time', $data);
            $re = $this->db->insert_id();
        }

        $status = $re ? 1 : 0;
        echo $this->transportlib->json_model(array('status' => $status));
    }

    function getDepartureStopInfo($route_id)
    {
        $this->db->select('s.name as stop_name, r.midpoint, r.departing_stop');
        $this->db->from('route r');
        $this->db->join('stops s', 'r.departing_stop = s.id');
        $this->db->where('r.id', $route_id);
        $query = $this->db->get();
        return $query->row();
    }

    function validateMidpoint()
    {
        $msg = '';
        $route_id = $this->input->post('route_id');
        $departure_midpoint = $this->input->post('departure_midpoint');
        $stop_id = $this->input->post('stop_id');

        // departure midpoint
        if ($departure_midpoint == 1) {
            $this->db->select('s.name');
            $this->db->from('route_stops r');
            $this->db->join('stops s', 'r.stop_id = s.id');
            $this->db->where('r.route_id', $route_id);
            $this->db->where('r.midpoint', 1);
            $query = $this->db->get();
            $row = $query->row();
            if ($row) {
                $msg = "You have already selected midpoint at " . $row->name;
            }
        } else { // route stop midpoint
            // first, checking if departing stop is selected as midpoint
            $this->db->select('s.name');
            $this->db->from('route r');
            $this->db->join('stops s', 'r.departing_stop = s.id');
            $this->db->where('r.id', $route_id);
            $this->db->where('r.midpoint', 1);
            $query = $this->db->get();
            $row = $query->row();
            if ($row) {
                $msg = "You have already selected midpoint at " . $row->name;
            }

            if (empty($msg)) {
                // if not, checking other stop is selected as midpoint
                $this->db->select('s.name');
                $this->db->from('route_stops r');
                $this->db->join('stops s', 'r.stop_id = s.id');
                $this->db->where('r.route_id', $route_id);
                $this->db->where('r.midpoint', 1);
                $query = $this->db->get();
                $row = $query->row();
                if ($row) {
                    $msg = "You have already selected midpoint at " . $row->name;
                }
            }
        }
        echo $msg;
        exit();
    }

    function updateStopMidpoint()
    {
        $id = $this->input->post('id');
        $midpoint = $this->input->post('midpoint');
        $data = array(
            'midpoint' => $midpoint,
        );
        $this->db->set('updated_date', 'NOW()', FALSE);
        $this->db->where("id", $id);
        $re = $this->db->update('route_stops', $data);
        $status = $re ? 1 : 0;
        echo $this->transportlib->json_model(array('status' => $status));
    }

    function get_departure_time_loop($route_id, $stop_id)
    {
        $this->db->select('departure_time, loop');
        $this->db->from('route_stop_departure_time');
        $this->db->where('route_id', $route_id);
        $this->db->where('stop_id', $stop_id);
        $this->db->order_by('loop', 'asc');
        $query = $this->db->get();
        $result = $query->result();
        $data = array();
        if ($result) {
            foreach ($result as $row) {
                $data[$row->loop] = $row->departure_time;
            }
        }
        return $data;
    }

    function _save_departure_time()
    {
        $route_id = $this->input->post('route_id');
        $stop_id = $this->input->post('route_stop');
        $loop_service = $this->input->post('loop_service');
        $number_of_route_loop = $this->input->post('number_of_route_loop');
        $departure_time = $this->input->post('departure_time');
        $this->db->delete('route_stop_departure_time', array('route_id' => $route_id, 'stop_id' => $stop_id));
        if ($loop_service) {
            $value = array();
            for ($i = 1; $i <= $number_of_route_loop; $i++) {
                $stop_departure_time = $this->input->post('departure_time' . $i);
                if (empty($stop_departure_time)) {
                    $stop_departure_time = null;
                }
                $value[] = array(
                    'route_id' => $route_id,
                    'stop_id' => $stop_id,
                    'departure_time' => $stop_departure_time,
                    'loop' => $i,
                    'created_date' => date('Y-m-d H:i:s')
                );
            }
            if (count($value) > 0) {
                $this->db->insert_batch('route_stop_departure_time', $value);
            }
        } else {
            if (empty($departure_time)) {
                $departure_time = null;
            }
            $value = array(
                'route_id' => $route_id,
                'stop_id' => $stop_id,
                'departure_time' => $departure_time,
                'loop' => 1,
                'created_date' => date('Y-m-d H:i:s')
            );
            $this->db->insert('route_stop_departure_time', $value);
        }
    }

    function _sort_ticket($ticket_type)
    {
        $sort_value = $this->input->post('sort_value');
        $data = $ticket_type;
        if (!empty($sort_value)) {
            $arr_sort = explode(',', $sort_value);
            if ($arr_sort) {
                $arr_diff = array_diff($ticket_type, $arr_sort);
                if ($arr_diff) {
                    $data = array_merge($arr_sort, $arr_diff);
                } else {
                    $data = $arr_sort;
                }
            }
        }
        return $data;
    }



    function get_ticket_of_route($route_id)
    {
        $this->db->select('ticket_type.id, ticket_type.name');
        $this->db->from('ticket_type');
        $this->db->join('route_ticket_type', 'ticket_type.id=route_ticket_type.ticket_type_id');
        $this->db->where('route_ticket_type.route_id', $route_id);
        $this->db->where('ticket_type.status', '1');
        $this->db->order_by('route_ticket_type.order', 'asc');
        $query = $this->db->get();
        return $query->result();
    }

    function get_sort_ticket()
    {
        $ticket_type = $this->input->post('ticket_type');
        $sort_id = $this->input->post('sort_id');
        $this->db->select('id, name');
        $this->db->from('ticket_type');
        $this->db->where_in('id', $ticket_type);
        if (!empty($sort_id)) {
            $this->db->_protect_identifiers = FALSE;
            $this->db->order_by('field(id, ' . $sort_id . ')', false);
            $this->db->_protect_identifiers = TRUE;
        } else {
            $this->db->order_by('name', 'asc');
        }
        $query = $this->db->get();
        return $query->result();
    }

    function check_valid_route_by_ticket_type()
    {
        $route_id = $this->input->post('route_id');
        $ticket_type_id = $this->input->post('ticket_type_id');
        $this->db->where('ticket_type_id', $ticket_type_id);
        $this->db->where('route_id', $route_id);
        $query = $this->db->get('route_ticket_type');
        if ($query->num_rows() > 0) {
            return 1;
        } else {
            return 0;
        }
    }

    public function check_route_linked_to_split_trip_rule($route_id)
    {
        $this->db->where('st_rule_details.route_id', $route_id);
        $this->db->where('st_rule.status', 1);
        $this->db->join('st_rule', 'st_rule.id = st_rule_details.st_rule_id');
        return ($this->db->count_all_results('st_rule_details') > 0) ? 1 : 0;
    }

    function check_valid_all_route_by_ticket_type()
    {
        $this->db->where('status', 1);
        $query = $this->db->get('route');
        if ($query->num_rows() > 0) {
            $ticket_type_id = $this->input->post('ticket_type_id');
            foreach ($query->result() as $k => $route) {
                $this->db->where('ticket_type_id', $ticket_type_id);
                $this->db->where('route_id', $route->id);
                $query = $this->db->get('route_ticket_type');
                if ($query->num_rows() <= 0) {
                    return 0;
                }
            }

            return 1;
        } else {
            return 0;
        }
    }

    function check_valid_multi_route_category_by_ticket_type()
    {
        $ticket_type_id = $this->input->post('ticket_type_id');
        $multi_route_cats = $this->input->post('multi_route_cats');

        $this->db->join('route_ticket_type', 'route_ticket_type.route_id = route.id');
        $this->db->where('route_ticket_type.ticket_type_id', $ticket_type_id);
        $this->db->where_in('route.route_category_id', $multi_route_cats);
        $this->db->where('route.status', 1);
        $query = $this->db->get('route');

        if ($query->num_rows() > 0) {
            return 1;
        }
        return 0;
    }

    /**
     * Get route by id
     *
     * @param       int  $id    cate id
     * @return      array
     */
    function getRouteById($id)
    {
        $result = array();
        $id = (int) $id;
        if ($id > 0) {
            $this->db->where('id', $id);
            $this->db->where('status', IS_ACTIVE);
            $this->db->limit(IS_ACTIVE);
            $query = $this->db->get('route');
            $result = $query->row();
        }

        return $result;
    }

    /**
     * Get all routes
     *
     * @param       array   $includeList    Include list
     * @param       array   $excludeList    Exclude list
     * @param       string  $orderBy        Order by
     * @return      array
     */
    function getAllRoutes($includeList = array(), $excludeList = array(), $orderBy = 'ASC')
    {
        if (!empty($includeList)) {
            $this->db->where_in('id', $includeList);
        }
        if (!empty($excludeList)) {
            $this->db->where_not_in('id', $excludeList);
        }
        $this->db->where('status', IS_ACTIVE);
        $this->db->order_by('id', $orderBy);
        $query = $this->db->get('route');
        $result = $query->result_array();

        return $result;
    }

    public function update_section_travel_by_route_id($route_id)
    {
        //get departing_stop 
        $sql = "select departing_stop from route where id = {$route_id} limit 1 ";
        $query = $this->db->query($sql);
        $tmp = $query->result_array();

        $sql1 = "select stop_id from route_stops where route_id = {$route_id} and status=1 order by position ASC ";
        $query1 = $this->db->query($sql1);

        $tmp1 = $query1->result_array();
        $route_ids[] = $tmp[0]['departing_stop'];
        foreach ($tmp1 as $k => $v) {
            // There is no duplicate stops in 1 route.
            if (array_search($v['stop_id'], $route_ids) === false) {
                $route_ids[] = $v['stop_id'];
            }
        }

        $i = 0;
        $stop_from_stop_to = array();
        for ($i; $i <= count($route_ids); $i++) {
            for ($j = $i + 1; $j <= count($route_ids); $j++) {
                if (isset($route_ids[$j])) {
                    $stop_from_stop_to[] = $route_ids[$i] . "_" . $route_ids[$j];
                }
            }
        }


        $sql1 = "select concat(stop_from,'_',stop_to) stop_from_stop_to, section_travel, id from ticket_price_stops where route_id = {$route_id} and status = 1";
        $query1 = $this->db->query($sql1);
        $existing_section_matrix = $query1->result_array();
        $stop_from_stop_to_saved = array_map(function ($item) {
            return $item['stop_from_stop_to'];
        }, $existing_section_matrix);
        $stop_from_stop_to_saved_s = array_unique($stop_from_stop_to_saved);
        $result = array_diff($stop_from_stop_to, $stop_from_stop_to_saved_s);
        $results = array_unique($result);
        $session_data = $this->session->userdata('logged_in');
        if (count($results) > 0) {
            $section_matrix_items_to_delete = [];

            foreach ($results as $k1 => $item) {
                $stop_info = explode('_', $item);
                // exclude stop from = stop to
                if ($stop_info[0] != $stop_info[1]) {
                    $section_travel = 1;

                    // find existing section matrix with reversed direction
                    foreach ($existing_section_matrix as $section_matrix_item) {
                        if ($section_matrix_item['stop_from_stop_to'] == ($stop_info[1] . '_' . $stop_info[0])) {
                            $section_travel = $section_matrix_item['section_travel'];
                            $section_matrix_items_to_delete[] = $section_matrix_item['id'];
                            break;
                        }
                    }

                    $data = array(
                        'route_id' => $route_id,
                        'stop_from' => $stop_info[0],
                        'stop_to' => $stop_info[1],
                        'section_travel' => $section_travel,
                        'amount' => 0,
                        'route_ticket_type_id' => 0,
                        'created_by' => $session_data['id'],
                        'updated_by' => $session_data['id'],
                    );
                    $this->db->set('created_date', 'NOW()', FALSE);
                    $this->db->set('updated_date', 'NOW()', FALSE);
                    $this->db->insert('ticket_price_stops', $data);
                }
            }

            if (count($section_matrix_items_to_delete) > 0) {
                $this->db->where_in('id', $section_matrix_items_to_delete);
                $this->db->delete('ticket_price_stops');
            }
        }
    }

    public function get_departing_stop_by_route_id($route_id)
    {
        $query = $this->db->query("select s.name stop_name, s.id stop_id, r.id as route_id, r.name route_name, r.departing_stop
                                    from route as r
                                    join stops as s on s.id = r.departing_stop
                                    where r.id = $route_id");
        return $query->row_array();
    }

    public function get_route_by_multi_route_category_id($catIds, $oldData, $isOndemandService = false)
    {
        if ($oldData == 0) {
            $this->db->where('status', 1);
        }
        if ($isOndemandService) {
            $this->db->where('on_demand_service', 1);
        }
        $query = $this->db->where_in('route_category_id', $catIds)->get('route');
        return $query->result_array();
    }

    public function get_passenger_list($session_data)
    {
        if (isset($session_data['bus_roll_check']) && $session_data['bus_roll_check'] == '1') {
            return unserialize(ROUTE_TYPE_LIST);
        } elseif (isset($session_data['esm_passenger']) && $session_data['esm_passenger'] == '1') {
            return unserialize(ROUTE_EMS_TYPE_LIST);
        } else {
            return unserialize(ROUTE_TYPE_DEFAULT_LIST);
        }
    }

    /**
     * Get route categories that have esm feature enabled
     *
     * @return array
     */
    public function get_esm_route_category($session_data, $route_category_status = 0)
    {
        $result = [];
        if (isset($session_data['esm_passenger']) && $session_data['esm_passenger'] == '1') {
            $route_category_status = intval($route_category_status);
            $esm_route_type = [ROUTE_TYPE_ESM_AM, ROUTE_TYPE_ESM_PM];
            $this->db->distinct();
            $select_fields = [
                'rc.id',
                'rc.name'
            ];
            $this->db->select($select_fields);
            $this->db->from('route_category rc');
            $this->db->join('route r', 'rc.id = r.route_category_id');
            if ($route_category_status != IS_ACTIVE) {
                $this->db->where('rc.status', IS_ACTIVE);
                $this->db->where('r.status', IS_ACTIVE);
            }
            // @JIRA TSM-542
            if (isset($session_data['account_info_id']) &&  !empty($session_data['account_info_id'])) {
                $this->db->join('permission_category_sub pcs', 'rc.id = pcs.category_id');
                $this->db->where('account_info_id', $session_data['account_info_id']);
            }
            // END TSM-542
            $this->db->where_in('r.special_route_type', $esm_route_type);
            $this->db->order_by('id', 'desc');
            $result = $this->db->get()->result_array();
        }

        return $result;
    }

    /**
     * @revision @JIRA TSM-210 change data query for departing stop's is-popular attribute
     * @revision @author duy.ton
     */
    public function departing_is_popular_stop($route_id)
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->db->select('rs.is_popular')->from('route_stops rs');
        $this->db->join('route r', 'r.id = rs.route_id');
        $this->db->where('r.status =', '1')->where('rs.status =', '1');
        $this->db->where('rs.route_id', $route_id);
        $this->db->where('rs.stop_id = r.departing_stop');

        $data = $this->db->get()->result_array();
        $itis = count($data) > 0 && $data[0]['is_popular'] == 1;

        return $itis ? 1 : 0;
    }

    /**
     * @JIRA TSM-246
     * @author: kiet.nguyen
     * @description: Set data query in route_category, route, stops and route_stops table for function import kml file
     */
    public function set_route_stops_routestops_by_kml()
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $dataStops = $_POST['dataStops'];
        $flag = 0;
        $glrouteid = 0;          //Global Route ID insert stop table
        try {
            $this->db->trans_begin();
            $id_stop_list = [];  //Save list data stop insert into route-stop table
            $tmp_stopid = 0;     //Temporary Stop ID
            $inserted = 0;       //State insert first stop
            $departing_stop = 0; //Departing stop in route table
            $flag = 1;
            $idcat = $dataStops['cate_id'];
            foreach ($dataStops['dataStops'] as $key => $stop) {

                $datastop = array(
                    'name' => $stop['stopname'],
                    'address' => $stop['address'],
                    'lat' => $stop['lat'],
                    'lng' => $stop['lng'],
                    'is_school' => '0',
                    'bell_time_am' => null,
                    'bell_time_pm' => null,
                    'created_date' => date('Y-m-d H:i:s')
                );

                if (!(isset($stop['id_data'])) && ($inserted == 0)) {
                    //CHECK $stop FOR INSERT FIRST HAS NOT STOP IN STOPS TABLE
                    if ($this->db->insert('stops', $datastop) == '1') {
                        $stop_id = $this->db->insert_id();
                        $datastop['id'] = $stop_id;
                        $this->app_firestore->set_stop($datastop);
                        if ($key == 0) {
                            $tmp_stopid = $stop_id;
                            $departing_stop = $tmp_stopid;
                            //Create row first for col ipopular
                            array_push($id_stop_list, [
                                'stop_id' => $tmp_stopid,
                                'previous_stop_id' => $tmp_stopid
                            ]);
                        } else {
                            array_push($id_stop_list, [
                                'stop_id' => $stop_id,
                                'previous_stop_id' => $tmp_stopid
                            ]);
                            $tmp_stopid = $stop_id;
                        }

                        $flag = 1;
                        $inserted = 1;
                        //CREATE NEW ROUTE AND NEW STOP NOT SAME
                        $dataroute = array(
                            'name' => $dataStops['route_name'],
                            'route_description' =>  $dataStops['route_address'],
                            'route_category_id' => $idcat,
                            'number_of_section' => '1',
                            'departing_stop' => $departing_stop,
                            'speed_limit' => '50',
                            'is_manual' => '1',
                            'use_zone_ability' => '0',
                            'pax_count_ability' => '0',
                            'can_be_tracked' => '0',
                            'departing_zone_id' => '0',
                            'on_time_running_check' => '0',
                            'planned_trip_per_day' => '0',
                            'route_number' => '1',
                            'midpoint' => '0',
                            'print_out_end_of_route_report' => '1',
                            'loop_service' => '1',
                            'window_time' => null,
                            'timetabled_service_check' => '0',
                            'number_of_route_loop' => '1',
                            'planned_kms' => '0',
                            'route_type' => '1',
                            'dead_running_kms' => '0',
                            'm_f_term' => '1',
                            'm_f_hol' => '1',
                            'sat' => '1',
                            'sun' => '1',
                            'ph' => '1',
                            'created_by' => $dataStops['created_by'],
                            'updated_by' => $dataStops['created_by'],
                            'special_route_type' => '0',
                            'activation_time' => null,
                            'late_sign_on' => '0',
                            'smartcard_check' => '0',
                            'on_demand_service' => '0',
                            'line_color_kml' => $dataStops['lineColor'] //@JIRA TSM-396

                        );
                        $this->db->set('created_date', 'NOW()', FALSE);
                        $this->db->set('updated_date', 'NOW()', FALSE);
                        if ($this->db->insert('route', $dataroute) == '1') {
                            $glrouteid = $this->db->insert_id();
                            $flag = $this->set_route_line_by_kml($glrouteid, $dataStops['created_by'], $dataStops['dataLine']);
                        }
                    }
                } else {
                    if (isset($stop['id_data'])) {
                        //HAS STOP IN STOPS TABLE
                        $flag = 1;
                        if ($departing_stop == 0) {
                            $departing_stop = $stop['id_data'];
                            //Create row first for col ipopular
                            array_push($id_stop_list, [
                                'stop_id' => $stop['id_data'],
                                'previous_stop_id' => $stop['id_data']
                            ]);
                            $tmp_stopid = $stop['id_data'];
                        } else {
                            //Create row after first for col
                            array_push($id_stop_list, [
                                'stop_id' => $stop['id_data'],
                                'previous_stop_id' => $tmp_stopid
                            ]);
                            $tmp_stopid = $stop['id_data'];
                        }
                    } else {
                        //HASN'T IN STOPS TABLE TO DO INSERT STOP
                        if ($this->db->insert('stops', $datastop) == '1') {
                            $stop_id = $this->db->insert_id();
                            $datastop['id'] = $stop_id;
                            $this->app_firestore->set_stop($datastop);
                            $flag = 1;
                            if ($departing_stop == 0) {
                                $departing_stop = $stop_id;
                                array_push($id_stop_list, [
                                    'stop_id' => $stop_id,
                                    'previous_stop_id' => $stop_id
                                ]);
                            }
                            if ($key !== 0) {
                                array_push($id_stop_list, [
                                    'stop_id' => $stop_id,
                                    'previous_stop_id' => $tmp_stopid
                                ]);
                                $tmp_stopid = $stop_id;
                            }
                        }
                    }
                }
            }

            //RUN LOOP ADD NEW RECORD IN ROUTE-STOP TABLE
            $count = 0;
            foreach ($id_stop_list as $key => $stoplist) {
                $dataroute_stop[] = array(
                    'route_id' => $glrouteid,
                    'stop_id' => ($key == 0) ? $stoplist['previous_stop_id'] : $stoplist['stop_id'],
                    'section_number' => ($key == 0) ? 0 : 1,
                    'previous_stop_id' => $stoplist['previous_stop_id'],
                    'position' => $key,
                    'is_popular' => ($key == 0) ? 1 : 0,
                    'status' => 1,
                    'created_by' => $dataStops['created_by'],
                    'created_date' => date('Y-m-d H:i:s'),
                    'updated_by' => $dataStops['created_by'],
                    'updated_date' => date('Y-m-d H:i:s'),
                    'zone_id' => null,
                    'departure_time' => null,
                    'midpoint' => '0',
                );
                $count = $key;
            }
            $dataroute_stop[0]['previous_stop_id'] = $dataroute_stop[$count]['stop_id'];
            if ($this->db->insert_batch('route_stops', $dataroute_stop) == '1') {
                $flag = 1;
            }
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $ex) {

            $this->db->trans_rollback();
        }
        $result['routeid'] = $glrouteid;
        $result['flag'] = $flag;
        return $result;
    }

    /**
     * @JIRA TSM-341
     * @author: kiet.nguyen
     * @description: set_routestops_by_kml() :Insert new rows in route_stops table
     *               provided that there was stop in the stops table.
     */
    public function set_routestops_by_kml()
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $dataStops = $_POST['dataStops'];
        $flag = 0;
        $glrouteid = 0;          //Global Route ID insert stop table
        try {
            $this->db->trans_begin();
            $id_stop_list = [];  //Save list data stop insert into route-stop table
            $tmp_stopid = 0;     //Temporary Stop ID
            $departing_stop = 0; //Departing stop in route table

            $flag = 1;
            $idcat = $dataStops['cate_id'];
            //@JIRA TSM-341 CONDITION. IF ALL STOP IMPORTED HAVE ON STOP TABLE
            foreach ($dataStops['dataStops'] as $key => $stop) {

                //Create array $id_stop_list previous stop & stop insert route-stop DB
                if ($key == 0) {
                    $departing_stop = $stop['id_data'];
                    $tmp_stopid = $stop['id_data'];
                    array_push($id_stop_list, [
                        'stop_id' => $stop['id_data'],
                        'previous_stop_id' => $stop['id_data']
                    ]);
                } else {
                    array_push($id_stop_list, [
                        'stop_id' => $stop['id_data'],
                        'previous_stop_id' => $tmp_stopid
                    ]);
                    $tmp_stopid = $stop['id_data'];
                }
            }
            //CREATE ROUTE IN TABLE ROUTE. BUT DON'T CREATE NEW STOP
            $dataroute = array(
                'name' => $dataStops['route_name'],
                'route_description' =>  $dataStops['route_address'],
                'route_category_id' => $idcat,
                'number_of_section' => '1',
                'departing_stop' => $departing_stop,
                'speed_limit' => '50',
                'is_manual' => '1',
                'use_zone_ability' => '0',
                'pax_count_ability' => '0',
                'can_be_tracked' => '0',
                'departing_zone_id' => '0',
                'on_time_running_check' => '0',
                'planned_trip_per_day' => '0',
                'route_number' => '1',
                'midpoint' => '0',
                'print_out_end_of_route_report' => '1',
                'loop_service' => '1',
                'window_time' => null,
                'timetabled_service_check' => '0',
                'number_of_route_loop' => '1',
                'planned_kms' => '0',
                'route_type' => '1',
                'dead_running_kms' => '0',
                'm_f_term' => '1',
                'm_f_hol' => '1',
                'sat' => '1',
                'sun' => '1',
                'ph' => '1',
                'created_by' => $dataStops['created_by'],
                'updated_by' => $dataStops['created_by'],
                'special_route_type' => '0',
                'activation_time' => null,
                'late_sign_on' => '0',
                'smartcard_check' => '0',
                'on_demand_service' => '0',
                'line_color_kml' => $dataStops['lineColor'] //@JIRA TSM-396

            );
            $this->db->set('created_date', 'NOW()', FALSE);
            $this->db->set('updated_date', 'NOW()', FALSE);
            if ($this->db->insert('route', $dataroute) == '1') {
                $glrouteid = $this->db->insert_id();
                $flag = $this->set_route_line_by_kml($glrouteid, $dataStops['created_by'], $dataStops['dataLine']);
            }
            //RUN LOOP ADD NEW RECORD IN ROUTE-STOP TABLE
            $count = 0;
            foreach ($id_stop_list as $key => $stoplist) {
                $dataroute_stop[] = array(
                    'route_id' => $glrouteid,
                    'stop_id' => ($key == 0) ? $stoplist['previous_stop_id'] : $stoplist['stop_id'],
                    'section_number' => ($key == 0) ? 0 : 1,
                    'previous_stop_id' => $stoplist['previous_stop_id'],
                    'position' => $key,
                    'is_popular' => ($key == 0) ? 1 : 0,
                    'status' => 1,
                    'created_by' => $dataStops['created_by'],
                    'created_date' => date('Y-m-d H:i:s'),
                    'updated_by' => $dataStops['created_by'],
                    'updated_date' => date('Y-m-d H:i:s'),
                    'zone_id' => null,
                    'departure_time' => null,
                    'midpoint' => '0',
                );
                $count = $key;
            }
            $dataroute_stop[0]['previous_stop_id'] = $dataroute_stop[$count]['stop_id'];
            if ($this->db->insert_batch('route_stops', $dataroute_stop) == '1') {
                $flag = 1;
            }
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $ex) {

            $this->db->trans_rollback();
        }
        $result['routeid'] = $glrouteid;
        $result['flag'] = $flag;
        return $result;
    }
    /**
     * @JIRA TSM-246
     * @author: kiet.nguyen
     * @description: Update data query in route table for function import kml file
     */
    public function set_update_route_by_kml()
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $dataStops = $_POST['dataStops'];

        try {
            $this->db->trans_begin();
            $flag = 0;
            foreach ($dataStops as $stop) {
                $data = array(
                    'id' => $stop['id_data'],
                    'name' => $stop['stopname'],
                    'address' => $stop['address'],
                    'created_date' => date('Y-m-d H:i:s')
                );
                $this->db->where("id", $data['id']);
                if ($this->db->update('stops', $data) == '1') {
                    $this->app_firestore->update_stop($data);
                    $flag = 1;
                }
            }
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $ex) {

            $this->db->trans_rollback();
        }
        return ($flag == 1) ? 1 : 0;
    }

    /**
     * @JIRA TSM-347
     * @author: kiet.nguyen
     * @description: Get data in route_line table for func compare stop in kml file and stop data of the route
     */
    public function get_stops_by_route_id_for_kml()
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $route_id = $_GET['route_id'];
        $cat_id = $this->input->get('cat_id');
        $action_route = SUB_OPERATOR_EXECUTE_CONTROL;
        //Continue generating Map XML
        //Create a new DOMDocument object
        $dom = new DOMDocument("1.0");
        $node = $dom->createElement("markers"); //Create new element node
        $parnode = $dom->appendChild($node); //make the node show up

        $this->db->select('rs.id route_stop_id, s.name stop_name, s.id stop_id, s.lat lat, s.lng lng , r.departing_stop departing_stop');
        $this->db->join('route_stops rs', 'rs.stop_id = s.id');
        $this->db->join('route r', 'rs.route_id = r.id');
        $this->db->where('rs.route_id', $route_id);
        $this->db->where('rs.status', 1);
        $this->db->order_by('rs.position', 'ASC');
        $query = $this->db->get('stops s');
        // @JIRA TSM-533
        if (!empty($this->account_info_id)) {
            $action_route = $this->get_allowed_category($cat_id);
        }
        // END @JIRA TSM-533
        $results = $query->result();

        if (!$results) {
            header('HTTP/1.1 500 Error: Could not get markers!');
            exit();
        }

        //set document header to text/xml
        header("Content-type: text/xml");

        // Iterate through the rows, adding XML nodes for each
        foreach ($results as $obj) {
            $node = $dom->createElement("marker");
            $newnode = $parnode->appendChild($node);
            $newnode->setAttribute("route_stop_id", $obj->route_stop_id);
            $newnode->setAttribute("stop_id", $obj->stop_id);
            $newnode->setAttribute("stop_name", $obj->stop_name);
            $newnode->setAttribute("lat", $obj->lat);
            $newnode->setAttribute("lng", $obj->lng);
            //@JIRA TSM-317
            $newnode->setAttribute("departing_stop", $obj->departing_stop);
            // @JIRA TSM-533
            $newnode->setAttribute("action_route", $action_route);
        }

        echo $dom->saveXML();
    }

    /**
     * @JIRA TSM-347
     * @author: kiet.nguyen
     * @description: Get data in route_line table for check route has line data
     */

    public function get_route_line($route_id)
    {
        /// Check User Session///
        $this->custom_model->check_session();

        $this->db->select('route_id')->from('route_line');
        $this->db->where("route_id =", $route_id);
        $data = $this->db->get()->result_array();
        $existed = (count($data) > 0) ? 1 : 0;
        return $existed;
    }

    /**
     * @JIRA TSM-347
     * @author: kiet.nguyen
     * @description: Insert data in route_line table for function import line kml file
     */
    public function set_route_line_by_kml($route_id, $created_by, $arrLine)
    {

        try {

            $this->db->trans_begin();
            $flag = 0;
            //Insert line into route_line table
            foreach ($arrLine as $coord) {
                $data[] = array(
                    'route_id' => $route_id,
                    'lat' => (isset($coord->lat)) ? $coord->lat : $coord['lat'],
                    'lng' => (isset($coord->lng)) ? $coord->lng : $coord['lng'],
                    'created_by' => $created_by,
                    'created_date' => date('Y-m-d H:i:s')
                );
            }
            if ($this->db->insert_batch('route_line', $data) == '1') {
                $flag = 1;
            }
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $ex) {

            $this->db->trans_rollback();
        }
        return ($flag == 1) ? 1 : 0;
    }

    /**
     * @JIRA TSM-347
     * @author: kiet.nguyen
     * @description: Update data in route_line table for function import line kml file
     */
    public function update_route_line_by_kml($route_id, $created_by, $arrLine)
    {
        /// Check User Session///
        $this->custom_model->check_session();
        try {
            $this->db->trans_begin();
            $flag = 0;
            if ($this->db->delete('route_line', array('route_id' => $route_id)) == '1') {
                $flag = $this->set_route_line_by_kml($route_id, $created_by, $arrLine);
            }
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $ex) {

            $this->db->trans_rollback();
        }
        return ($flag == 1) ? 1 : 0;
    }

    /**
     * @JIRA TSM-347
     * @author: kiet.nguyen
     * @description: Update data status_gps_navigation_line in route table for function import line kml file
     *               IF value = 1: GPS view will be have the navigation line (Guidance) based on the KML;
     *                          2: GPS view will be have the navigation line (Guidance) based on the Google Map navigation API
     */
    public function update_status_nav_line_for_route($route_id, $status, $linecolor, $created_by)
    {
        /// Check User Session///
        $this->custom_model->check_session();
        //@JIRA TSM-414 add parameter $linecolor to set color line
        if (!empty($linecolor) && $status == 1) {
            $data = array(
                'line_color_kml' => $linecolor,
                'created_by' => $created_by,
                'status_gps_navigation_line' => $status
            );
        } else {
            $data = array(
                'created_by' => $created_by,
                'status_gps_navigation_line' => $status
            );
        }
        //END TSM-414

        try {
            $this->db->trans_begin();
            $flag = 0;
            $this->db->set('updated_date', 'NOW()', FALSE);
            $this->db->where('id', $route_id);
            if ($this->db->update('route', $data) == '1') {
                $flag = 1;
            }
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $ex) {

            $this->db->trans_rollback();
        }
        return ($flag == 1) ? 1 : 0;
    }

    public function get_window_time($id)
    {
        $this->db->select('r.window_time as window_time');
        $this->db->from('route as r');
        $this->db->where('id', $id);
        $query = $this->db->get();
        return $query->row();
    }

    /**
     * @JIRA TSM-388
     * @author: kiet.nguyen
     * @description: update link kml of route
     */
    function update_link_kml_for_route($route_id, $filename)
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $name  = substr(json_encode($filename['file_name']), 1, -1);;
        $link_file = 'kmpfilelog/' . $name;
        $flag = 0;
        try {
            $this->db->trans_begin();
            $data = array(
                'link_kml_file' => $link_file

            );
            $this->db->set('updated_date', 'NOW()', FALSE);
            $this->db->where('id', $route_id);
            if ($this->db->update('route', $data) == '1') {
                $flag = 1;
            }
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $ex) {
            $this->db->trans_rollback();
        }
        return $flag;
    }
    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @description: get smartcard detail for form view route_passenger_manifest_smartcard_add. Function SEARCH SMART CARD
     */
    public function get_smartcard_detail_for_manifest()
    {
        /// Check User Session///
        // add data permission
        if (!empty($this->account_info_id)) {
            $results = [];
            $this->load->model('exporteddoc_model');
            $data_perm = $this->exporteddoc_model->get_smart_card_type_detail_id_permission(0, 1);
            array_map(function ($item) use (&$results) {
                return $results[] = (object)[
                    'card_id' => $item->smartcard_type_detail_id,
                    'card_number' => $item->card_number,
                    'cardholder_name' => $item->cardholder_name
                ];
            }, $data_perm);
            return $results;
        }
        $this->custom_model->check_session();
        $this->db->select('smd.id as card_id, smd.card_number, c.cardholder_name');
        $this->db->join('consumer c', 'c.card_number = smd.card_number');
        $this->db->where('c.status', 1);
        $query = $this->db->get('smartcard_type_detail smd');
        return $query->result();
    }

    public function get_smartcard_detail_for_manifest_with_id($card_id)
    {
        /// Check User Session///
        // add data permission
        if (!empty($this->account_info_id)) {
            $results = [];
            $this->load->model('exporteddoc_model');
            $data_perm = $this->exporteddoc_model->get_smart_card_type_detail_id_permission(0, 1);
            array_map(function ($item) use (&$results) {
                return $results[] = (object)[
                    'card_id' => $item->smartcard_type_detail_id,
                    'card_number' => $item->card_number,
                    'cardholder_name' => $item->cardholder_name,
                ];
            }, $data_perm);
            return $results;
        }
        $this->custom_model->check_session();
        $this->db->select('smd.id as card_id, smd.card_number, c.cardholder_name, smd.digits_code');
        $this->db->join('consumer c', 'c.card_number = smd.card_number');
        $this->db->where('c.status', 1);
        $this->db->where('smd.id', $card_id);
        $query = $this->db->get('smartcard_type_detail smd');
        return $query->result();
    }

    // public function get_smartcard_manifest_route_stop($smart_manifest_id)
    // {
    //     $this->custom_model->check_session();

    //     $this->db->select('smrs.id,smrs.stop_id, smrs.cat_id, smrs.route_id, smrs.smartcard_type_detail_id, smrs.cate_id, smrs.route_id, smrs.stop_id, smrs.skip_day, smrs.deboarding_stop_id as deboarding_stop_id, smrsd.smartcard_manifest_route_stop_id, smrsd.day_of_week, smrsd.deboarding_stop_id as deboarding_stop_id_detail');
    //     $this->db->from('smartcard_manifest_route_stop smrs');
    //     $this->db->join('smartcard_manifest_route_stop_detail smrsd', 'smrsd.smartcard_manifest_route_stop_id = smrs.id');
    //     $this->db->where('smrs.id', $smart_manifest_id);

    //     $query = $this->db->get();
    //     return $query->result();
    // }

    public function get_smartcard_manifest_route_stop($smart_manifest_id)
    {
        $this->custom_model->check_session();

        $this->db->select('*');
        $this->db->from('smartcard_manifest_route_stop smrs');
        $this->db->where('smrs.id', $smart_manifest_id);
        $query = $this->db->get();
        return $query->row();
    }

    public function get_smartcard_manifest_route_stop_detail($smart_manifest_id)
    {
        $this->db->select('*');
        $this->db->from('smartcard_manifest_route_stop_detail smrsd');
        $this->db->where('smrsd.smartcard_manifest_route_stop_id', $smart_manifest_id);
        $query = $this->db->get();
        return $query->result();
    }

    public function get_all_smartcard_manifest_route_stop($route_stop_id)
    {
        $this->db->select('smrs.*, smd.card_number, c.cardholder_name, smd.digits_code');
        $this->db->from('smartcard_manifest_route_stop smrs');
        $this->db->join('smartcard_type_detail smd', 'smd.id = smrs.smartcard_type_detail_id');
        $this->db->join('consumer c', 'c.card_number = smd.card_number');
        $this->db->where('smrs.route_stop_id', $route_stop_id);
        $query = $this->db->get();
        return $query->result_array();
    }

    public function get_all_smartcard_manifest_route_stop_detail($smart_card_manifests)
    {
        if (empty($smart_card_manifests)) {
            return [];
        }
        foreach ($smart_card_manifests as &$smartcard) {
            $smartcard_id[] = $smartcard['id'];
        };

        $this->db->select('*');
        $this->db->from('smartcard_manifest_route_stop_detail smrsd');
        $this->db->where_in('smartcard_manifest_route_stop_id', $smartcard_id);
        $query = $this->db->get();
        return $query->result_array();
    }



    public function select_deboarding_stop_all_day()
    {
        $this->custom_model->check_session();
        $is_select_all_day = json_decode($this->input->post('is_all_day'));
        $card_id = $this->input->post('smartcard_id');
        if ($is_select_all_day) {
            $this->db->select('smrsd.id');
            $this->db->from('smartcard_manifest_route_stop as smrs');
            $this->db->join('smartcard_manifest_route_stop_detail smrsd', 'smrsd.smartcard_manifest_route_stop_id = smrs.id');
            $this->db->where('smrs.smartcard_type_detail_id', $card_id);
            $smrsd_query = $this->db->get();
            $smrsd_result = $smrsd_query->result_array();
            if (is_array($smrsd_result)) {
                for ($i = 0; $i < count($smrsd_result); $i++) {
                    $this->db->delete('smartcard_manifest_route_stop_detail', array('id' => $smrsd_result[$i]));
                }
            }
        } else {
            $this->db->update('smartcard_manifest_route_stop', array('default_deboarding_stop_id' => null));
        }
        return $this->db->update('smartcard_manifest_route_stop', array('smartcard_type_detail_id' => $is_select_all_day));
    }

    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @description: Function get smart card detail of stops for manifest view form route passenger manifest OR check existed card in stop
     */
    public function get_smartcard_detail_of_stops_on_route_for_manifest_view($route_id, $stop_id)
    {
        /// Check User Session///
        $this->custom_model->check_session();
        //Continue generating Map XML
        //Create a new DOMDocument object
        $dom = new DOMDocument("1.0");
        $node = $dom->createElement("cardInfo"); //Create new element node
        $parnode = $dom->appendChild($node); //make the node show up
        $this->db->select('smrs.smartcard_type_detail_id card_id, smrs.route_stop_id, smrs.stop_id, smrs.id id, smd.card_number, c.cardholder_name'); //@Jira Task: TSM-750
        $this->db->join('smartcard_type_detail smd', 'smrs.smartcard_type_detail_id = smd.id');
        $this->db->join('consumer c', 'c.card_number = smd.card_number');
        $this->db->where('smrs.route_id', $route_id);
        if (!empty($stop_id)) {
            //Get smart card detail of stops
            $this->db->where('smrs.stop_id', $stop_id);
        }
        // add permission smartcard manifest
        if (!empty($this->account_info_id)) {
            $this->db->join('permission_category_sub pcs', 'pcs.category_id = smrs.cate_id');
            $this->db->where('account_info_id', $this->account_info_id);
            $this->db->where('pcs.status', IS_ACTIVE);
        }
        $query = $this->db->get('smartcard_manifest_route_stop smrs');
        $results = $query->result();
        if (!$results) {
            header('HTTP/1.1 500 Error: Could not get markers!');
            exit();
        }

        //set document header to text/xml
        header("Content-type: text/xml");

        // Iterate through the rows, adding XML nodes for each
        foreach ($results as $obj) {
            $node = $dom->createElement("cardInfo");
            $newnode = $parnode->appendChild($node);
            $newnode->setAttribute("card_id", $obj->card_id);
            $newnode->setAttribute("route_stop_id", $obj->route_stop_id);
            $newnode->setAttribute("stop_id", $obj->stop_id);
            $newnode->setAttribute("card_number", $obj->card_number);
            $newnode->setAttribute("route_id", $route_id);
            $newnode->setAttribute("cardholder_name", $obj->cardholder_name);
            $newnode->setAttribute("id", $obj->id);
        }

        echo $dom->saveXML();
    }

    public function get_smartcard_with_string()
    {
        $this->custom_model->check_session();
        $string = $this->input->post('search_text');

        $org_condition = '';
        $organisation_id = $this->session_login['organisation_id'];
        if ($organisation_id) {
            $org_condition = "AND c.organisation_id = $organisation_id";
        }

        $sql = "SELECT smd.id as card_id, smd.card_number, c.cardholder_name, smd.digits_code
        FROM smartcard_type_detail as smd
        JOIN consumer c ON c.id = smd.consumer_id
        WHERE c.status =  1 and smd.status is null
            $org_condition
            AND  (c.cardholder_name  LIKE '%" . $this->db->escape_like_str($string) . "%' ESCAPE '!'
            OR  smd.card_number  LIKE '%" . $this->db->escape_like_str($string) . "%' ESCAPE '!'
            OR  smd.digits_code  LIKE '%" . $this->db->escape_like_str($string) . "%' ESCAPE '!')
            ORDER BY cardholder_name
            LIMIT 20";


        $query = $this->db->query($sql);
        $results = $query->result_array();
        return  json_encode($results);
    }




    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @description: Function set new smart cards passenger manifest from search_card_add to stop in smartcard_detail_route_stop table
     */
    public function set_new_smartcards_manifest_to_stop()
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $flag = 0;
        try {
            $this->db->trans_begin();

            $cards = $this->input->post('cards');
            $smartcard_manifest_id_installed = array();
            //Analysis array $card_id $category_id $route_id $stop_id and insert smartcard_detail_route_stop table
            for ($i = 0; $i < count($cards); $i++) {
                $smartcard = $cards[$i];
                switch ($smartcard['state']) {
                    case "EDIT":
                        $this->update_smartcards_manifest_to_stop($smartcard);
                        break;
                    case "ADD":
                        $this->add_smartcard_manifest_to_stop($smartcard);
                        break;
                    case "DEL":
                        $this->del_data_smartcard_manifest($smartcard['route_id'], $smartcard['stop_id'], $smartcard['card_id']);
                }
            }
            $flag = 1;
            if ($this->db->trans_status() === FALSE || $flag == 0) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $ex) {
            $this->db->trans_rollback();
        }
        return $flag;
    }

    public function add_smartcard_manifest_to_stop($smartcard)
    {
        try {
            $this->db->trans_begin();
            $is_selected_all_deboarding_stop = json_decode($smartcard['is_select_all_day']);
            $data_smart_route_stop = array(
                'smartcard_type_detail_id' =>  $smartcard['card_id'],
                'route_stop_id' => $smartcard['route_stop_id'],
                'route_id' => $smartcard['route_id'],
                'stop_id' => $smartcard['stop_id'],
                'is_all_day' => $is_selected_all_deboarding_stop,
                'default_deboarding_stop_id' =>  $smartcard['deboarding_stop_id']
            );
            $this->db->insert('smartcard_manifest_route_stop', $data_smart_route_stop);
            $smartcard_manifest_id_installed = $this->db->insert_id();

            if (!$is_selected_all_deboarding_stop) {
                foreach ($smartcard['selected_date_stop'] as $date => $deboarding_stop) {
                    $data_smart_route_stop_detail[] = array(
                        'day_of_week' => $date,
                        'deboarding_stop_id' => $deboarding_stop,
                        'smartcard_manifest_route_stop_id' => $smartcard_manifest_id_installed,
                    );
                }
                $this->db->insert_batch('smartcard_manifest_route_stop_detail', $data_smart_route_stop_detail);
            }
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $ex) {
            $this->db->trans_rollback();
        }
    }

    public function update_smartcards_manifest_to_stop($update_card)
    {
        try {
            $this->db->trans_begin();

            $is_select_all_day = json_decode($update_card['is_select_all_day']);
            if ($is_select_all_day) {
                $data_smart_route_stop = array(
                    'is_all_day' => $is_select_all_day,
                    'default_deboarding_stop_id' => $update_card['deboarding_stop_id']
                );
                $this->db->where('id', $update_card['smartcard_route_stop_id']);
                $this->db->update('smartcard_manifest_route_stop', $data_smart_route_stop);

                $this->db->where('smartcard_manifest_route_stop_id', $update_card['smartcard_route_stop_id']);
                $this->db->delete('smartcard_manifest_route_stop_detail');
            } else {
                $data_smart_route_stop = array(
                    'is_all_day' => $is_select_all_day,
                    'default_deboarding_stop_id' => null
                );
                $this->db->where('id', $update_card['smartcard_route_stop_id']);
                $this->db->update('smartcard_manifest_route_stop', $data_smart_route_stop);

                foreach ($update_card['selected_date_stop'] as $date => $deboarding_stop) {
                    $data_smart_route_stop_detail[] = array(
                        'day_of_week' => $date,
                        'deboarding_stop_id' => $deboarding_stop,
                        'smartcard_manifest_route_stop_id' => $update_card['smartcard_route_stop_id']
                    );
                }
                $this->db->where('smartcard_manifest_route_stop_id', $update_card['smartcard_route_stop_id']);
                $this->db->delete('smartcard_manifest_route_stop_detail');
                $this->db->insert_batch('smartcard_manifest_route_stop_detail', $data_smart_route_stop_detail);
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $ex) {
            $this->db->trans_rollback();
        }
    }

    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @description: Function set new smart cards passenger manifest from EXCEL file to stop in smartcard_detail_route_stop table
     */
    public function set_new_smartcards_manifest_from_excel_file_to_stops()
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $datacard = $_POST['datacard'];
        $flag = 0;
        try {
            $this->db->trans_begin();

            $cate_id = $datacard['cat_id'];
            //Analysis array $card_id $category_id $route_id $stop_id and insert smartcard_detail_route_stop table
            foreach ($datacard['datacard'] as $data) {

                $data_smart_route_stop[] = array(
                    'smartcard_type_detail_id' => $data['card_id'],
                    'cate_id' => $cate_id,
                    'route_id' => $data['route_id'],
                    'stop_id' => $data['stop_id']
                );
            }
            if ($this->db->insert_batch('smartcard_manifest_route_stop', $data_smart_route_stop) == 1) {
                $flag = 1;
            }
            if ($this->db->trans_status() === FALSE || $flag == 0) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $ex) {
            $this->db->trans_rollback();
        }
        return $flag;
    }

    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @description: Function delete data smart card, category id, route id, stop id if user delete route in form view Routes (/routes/routes_list)
     * Or just delete the smartcard of stop in form smartcard manifest . Only delete data in smartcard_manifest_route_stop table
     */
    public function del_data_smartcard_manifest($route_id, $stop_id, $card_id)
    {

        try {
            $this->db->trans_begin();
            if (!empty($route_id)) {
                //delete card of route
                $this->db->delete('smartcard_manifest_route_stop', array('smartcard_type_detail_id' => $card_id, 'stop_id' => $stop_id, 'route_id' => $route_id));
            }
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
                return 1;
            }
        } catch (Exception $ex) {
            $this->db->trans_rollback();
            return 0;
        }
    }

    //@Jira Task: TSM-751
    // public function update_skip_day_smartcard_manifest($id, $data_update)
    // {

    //     try {
    //         $this->db->trans_begin();
    //         if (!empty($id)) {
    //             //delete card of route
    //             $this->db->update('smartcard_manifest_route_stop', array('skip_day' => $data_update), array('id' => $id));
    //         }
    //         if ($this->db->trans_status() === FALSE) {
    //             $this->db->trans_rollback();
    //         } else {
    //             $this->db->trans_commit();
    //             return 1;
    //         }
    //     } catch (Exception $ex) {
    //         $this->db->trans_rollback();
    //         return 0;
    //     }
    // }

    /**
     * @JIRA TSM-346
     * @author: lam.tran
     * @description: get array coordinates of route line
     */
    public function get_array_route_line($route_id)
    {
        $this->db->where('route_id', $route_id);
        $this->db->select('lat, lng');
        $this->db->order_by("id", "asc");
        $results = $this->db->get('route_line')->result_array();
        return $results;
    }

    /**
     * @JIRA TSM-533
     * @author: nghi.doan
     * @description: get action category by id
     */
    public function get_allowed_category($cate_id = '')
    {
        $this->db->where('account_info_id', $this->account_info_id);
        $this->db->where('status', 1);
        if (!empty($cate_id)) {
            $this->db->where('category_id', $cate_id);
            $results = $this->db->get('permission_category_sub')->row_array();
            if (!empty($results)) {
                return $results['action'];
            } else {
                return false;
            }
        }
        $results = $this->db->get('permission_category_sub')->result_array();
        $arr_results = [];
        foreach ($results as $key => $value) {
            $arr_results[$value['category_id']] = $value['action'];
        }
        return $arr_results;
    }

    /**
     * @JIRA TSM-533
     * @author: nghi.doan
     * @description: get category route name by id
     */
    public function get_route_name($cat_id)
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $this->db->where('route_category_id =', $cat_id);
        $this->db->where('r.status', IS_ACTIVE);
        return $this->db->get('route r')->result_array();
    }

    // TME-117
    public function get_route_name_of_map($id)
    {
        /// Check User Session///
        $this->custom_model->check_session();
        $this->db->select('route_id');
        $this->db->where('id', $id);
        $route_id = $this->db->get('driver_bus_route')->row()->route_id;
        $this->db->select('name');
        $this->db->where('id', $route_id);

        return $this->db->get('route')->row();
    }

    /**
     * @JIRA TSM-677
     */
    function get_departure_time_loop_v2($route_id, $stop_id)
    {
        $this->db->select('departure_time, loop, id');
        $this->db->from('route_stop_departure_time');
        $this->db->where('route_id', $route_id);
        $this->db->where('stop_id', $stop_id);
        $this->db->order_by('loop', 'asc');
        $query = $this->db->get();
        $result = $query->result_array();
        return $result;
    }

    public function update_infor_route_stop()
    {
        $route_id = $this->input->post('route_id');
        $stop_id = $this->input->post('stop_id');
        $route_stop_id = $this->input->post('route_stop_id');
        $is_popular = $this->input->post('is_popular');
        $mid_point = $this->input->post('mid_point');
        $is_loop = $this->input->post('is_loop');
        $zone_id = $this->input->post('zone_id');
        $arr_departure_time = $this->input->post('arr_departure_time');

        //update tbl route_stop
        $arr_update_route_stop = array(
            'is_popular' => $is_popular,
            'midpoint' => $mid_point,
            'zone_id' => $zone_id,
        );

        $this->db->trans_begin();
        try {
            $this->db->where('id', $route_stop_id);
            $this->db->update('route_stops', $arr_update_route_stop);

            //update tbl departure_time
            if (!empty($arr_departure_time)) {
                foreach ($arr_departure_time as $value) {
                    $route_number = $value['route_number'];
                    $departure_time = $value['departure_time'];
                    if (empty($departure_time)) {
                        $departure_time = null;
                    }

                    $this->db->where('route_id', $route_id);
                    $this->db->where('stop_id', $stop_id);
                    $this->db->where('loop', $route_number);
                    $query = $this->db->get('route_stop_departure_time');
                    $row = $query->row();
                    if ($row) {
                        $this->db->set('created_date', 'NOW()', FALSE);
                        $this->db->where("id", $row->id);
                        $this->db->update('route_stop_departure_time', array('departure_time' => $departure_time));
                    } else {
                        $data = array(
                            'route_id' => $route_id,
                            'stop_id' => $stop_id,
                            'loop' => $route_number,
                            'departure_time' => $value['departure_time'],
                            'created_date' => date('Y-m-d H:i:s')
                        );
                        $this->db->insert('route_stop_departure_time', $data);
                        $this->db->insert_id();
                    }
                }
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return -1;
            } else {
                $this->db->trans_commit();
                return 1;
            }
        } catch (Exception $ex) {
            $this->db->trans_rollback();
            return -1;
        }
    }

    /**
     * @JIRA TSM-757
     */
    public function move_passenger_to_another_stop($route_stop_id, $stop_id, $passengers)
    {
        $flag = 0;
        try {
            $this->db->trans_begin();

            $this->db->set('stop_id', $stop_id, FALSE);
            $this->db->set('route_stop_id', $route_stop_id, FALSE);
            $this->db->where_in("id", $passengers);
            if ($this->db->update('smartcard_manifest_route_stop')) {
                $this->db->trans_commit();
                $flag = 1;
            } else {
                $this->db->trans_rollback();
            }
        } catch (Exception $ex) {
            $this->db->trans_rollback();
        }
        return $flag;
    }

    /**
     * @JIRA TSM-770
     */
    public function update_infor_route_stops()
    {
        $stops = $this->input->post('stops');
        $departing_stop_id = $this->input->post('departing_stop_id');
        $departure_time = $this->input->post('departure_time');
        $route_id_first = $this->input->post('route_id');

        $this->db->trans_begin();
        try {
            foreach ($stops as $stop) {
                $route_id = $stop['route_id'];
                $stop_id = $stop['stop_id'];
                $route_stop_id = $stop['route_stop_id'];
                $is_popular = $stop['is_popular'];
                $mid_point = $stop['mid_point'];
                $position = $stop['position'];
                $arr_departure_time = ($stop['arr_departure_time'] == '' || $stop['arr_departure_time'] == null) ? null : $stop['arr_departure_time'];
                $zone_id = $stop['zone_id'];

                //update tbl route_stop
                $arr_update_route_stop = array(
                    'is_popular' => $is_popular,
                    'midpoint' => $mid_point,
                    'stop_id' => $stop_id,
                    'departure_time' => $arr_departure_time,
                    'position' => $position,
                    'zone_id' => $zone_id
                );

                $this->db->where('id', $route_stop_id);
                $this->db->update('route_stops', $arr_update_route_stop);
            }

            $first_position = $this->find_place_stop_order(1, $route_id);
            foreach ($first_position as $value) {
                $first_position_stop_id = $value["stop_id"];
            }

            //  Update departing stop
            $data_route = array(
                'departing_stop' => $first_position_stop_id
            );
            $this->db->where('id', $route_id_first)->update('route', $data_route);

            $this->sort_route_stop($route_id_first);
            //  Update ticket_price_stops                
            $this->update_section_travel_by_route_id($route_id);

            $this->db->trans_commit();
            return 1;
        } catch (Exception $ex) {
            $this->db->trans_rollback();
            return -1;
        }
    }

    public function get_pending_routes()
    {
        $sql = "SELECT (
            select stops.address from route_stops
            INNER JOIN stops on route_stops.stop_id = stops.id
            where route_stops.route_id = route.id order by position asc
            limit 1
            ) as 'departure',(
            select stops.address from route_stops
            INNER JOIN stops on route_stops.stop_id = stops.id
             where route_stops.route_id = route.id order by position desc
            limit 1
            ) as 'destination', route.created_date, route.id,  CONCAT(driver_detail.first_name  , ' ', driver_detail.last_name ) as driver from route left JOIN driver_detail on route.created_by = driver_detail.id where route.status = 
            " . PENDING_ROUTE . " order by ID desc";
        return $this->db->query($sql)->result_array();
    }

    public function get_address_pending_route($route_id)
    {
        $sql = "SELECT route.name as name_route, route_category_id, route.created_date,  CONCAT(driver_detail.first_name  , ' ', driver_detail.last_name ) as driver 
        from route
        left JOIN driver_detail on route.created_by = driver_detail.id 
        where route.id =" . $route_id;
        return $this->db->query($sql)->row_array();
    }

    public function get_near_by_stop($lat, $lng)
    {
        $query = $this->db->query("SELECT id,name,(((acos(sin((" . $lat . "*pi()/180)) * sin((`lat`*pi()/180))+cos((" . $lat . "*pi()/180)) * cos((`lat`*pi()/180)) * cos(((" . $lng . "- `lng`)*pi()/180))))*180/pi())*60*1.1515*1.609344*1000) as distance
                    FROM `stops` where status = 1 having distance <= 100");
        return $query->result_array();
    }

    public function get_stop_pending_route($route_id)
    {
        $sql = "SELECT stops.id, stops.name, stops.near_by_stop, stops.address, stops.lat, stops.lng from route_stops
        INNER JOIN stops on stop_id = stops.id
        where route_stops.route_id =" . $route_id;
        return $this->db->query($sql)->result_array();
    }

    public function delete_route_line($route_id)
    {
        $this->db->where('route_id', $route_id)->delete('route_line');
    }

    public function delete_stop($stop_id)
    {
        $sql = "delete FROM route_stops WHERE stop_id = " . $stop_id;
        $this->db->query($sql);
        $sql = "delete FROM stops WHERE id = " . $stop_id;
        $this->db->query($sql);
    }

    public function delete_pending_route_stops($route_id)
    {
        $stops = [];
        $route_stops = $this->db->where('route_id', $route_id)->get('route_stops')->result_array();
        foreach ($route_stops as $row) {
            $stops[] = $row['stop_id'];
        }
        // delete pending route stop.
        $this->db->where('route_id', $route_id)->delete('route_stops');
        if (!empty($stops) || isset($stops[0])) {
            // delete pending stop.
            $sql = "delete FROM stops WHERE id IN (" . implode(',', $stops) . ")";
            $this->db->query($sql);
        }
    }

    public function delete_pending_route($route_id)
    {
        try {
            $this->db->where('id', $route_id)->delete('route');
        } catch (Exception $e) {
            return $e;
        }
    }

    public function get_all_contract_number()
    {
        return $this->db->get('contract_number')->result_array();
    }
    public function get_contract_number_by_id($id)
    {
        return $this->db->where('id', $id)->get('contract_number')->row_array();
    }
    public function get_contract_number_by_id_dropbox($id)
    {
        $sql = "SELECT * from contract_number
                WHERE id != " . $id;

        return $this->db->query($sql)->result_array();
    }
    //@JIRA TSM-68 
    public function get_all_route_id()
    {
        $sql = "SELECT DISTINCT route_id FROM route_stops";

        return $this->db->query($sql)->result_array();
    }
    public function get_frist_stop($route_id)
    {
        $sql = "SELECT * FROM route_stops WHERE route_id=$route_id AND `status` =1 AND position!=0 ORDER BY position";
        return $this->db->query($sql)->result_array();
    }
    public function get_all_same_position($route_id, $position_num)
    {
        $sql = "SELECT `id`,`route_id`,`position`,`section_number` FROM route_stops WHERE route_id=$route_id AND position!=0 AND position=$position_num AND `status` = 1 ORDER BY position";
        return $this->db->query($sql)->result_array();
    }
    public function get_same_position($route_id, $position_num)
    {
        $sql = "SELECT * FROM route_stops WHERE route_id=$route_id AND position=$position_num AND `status` = 1 ";
        return $this->db->query($sql)->result_array();
    }
    public function update_stop_order($position, $id)
    {
        $this->db->set('position', $position);
        $this->db->where('id', $id);
        $this->db->where('`status`', 1);
        $this->db->update('route_stops');
    }
    public function update_stop_order_previous_stop_id($id)
    {
        $this->db->set('previous_stop_id', 0);
        $this->db->set('position', 0);
        $this->db->where('id', $id);
        $this->db->where('`status`', 1);
        $this->db->update('route_stops');
    }
    public function find_departing_stop($route_id, $stop_id)
    {
        $sql = "SELECT * FROM route_stops WHERE route_id = $route_id AND `status` = 1 AND stop_id = $stop_id and position < 1 LIMIT 1 ";
        return $this->db->query($sql)->row_array();
    }

    public function find_departing_stop_route($route_id)
    {
        $sql = "SELECT * FROM route WHERE id=$route_id AND `status` = 1 ";
        return $this->db->query($sql)->row_array();
    }

    public function insert_route_stop($route_id, $previous_stop_id)
    {
        $created_by = isset($this->session_login['account_info_id']) ? $this->session_login['account_info_id'] : $this->session_login['id'];
        $data = array(
            'route_id' => $route_id,
            'stop_id' => $previous_stop_id,
            'previous_stop_id' => "0",
            'section_number' => "1",
            'created_date' => date("Y-m-d H:i:s"),
            'updated_date' => date("Y-m-d H:i:s"),
            'status' => "1",
            'position' => "0",
            'created_by' => $created_by,
            'created_date' => date('Y-m-d H:i:s'),
            'updated_by' => $created_by,
            'updated_date' => date('Y-m-d H:i:s'),
        );
        $this->db->insert('route_stops', $data);
    }
    public function insert_update_departing_stop($route_id, $stop_id, $departure_time)
    {
        $created_by = isset($this->session_login['account_info_id']) ? $this->session_login['account_info_id'] : $this->session_login['id'];
        $data = array(
            'route_id' => $route_id,
            'stop_id' => $stop_id,
            'previous_stop_id' => "0",
            'section_number' => "1",
            'created_date' => date("Y-m-d H:i:s"),
            'updated_date' => date("Y-m-d H:i:s"),
            'status' => "1",
            'position' => "0",
            'created_by' => $created_by,
            'created_date' => date('Y-m-d H:i:s'),
            'updated_by' => $created_by,
            'updated_date' => date('Y-m-d H:i:s'),
            'departure_time' => $departure_time,
            'is_popular' => 0

        );
        $this->db->insert('route_stops', $data);
    }
    // @JIRA TSM-815
    public function find_departure_time($route_id, $departing_stop)
    {
        $sql = "SELECT *
                FROM route_stop_departure_time 
                WHERE route_id = $route_id 
                AND stop_id = $departing_stop;";
        return $this->db->query($sql)->row_array();
    }

    // @JIRA TSM-68
    public function get_stop_limit($route_id, $from, $to, $id_except)
    {
        $sql = "SELECT *
                FROM route_stops 
                WHERE route_id = $route_id 
                AND `status` = 1 
                AND position  BETWEEN $from-1 AND $to-1
                AND NOT id=$id_except
                ORDER BY position;";
        return $this->db->query($sql)->result_array();
    }

    public function find_place_stop_order($order, $route_id)
    {
        $sql = "SELECT * 
                FROM route_stops 
                WHERE position = '$order'-1 
                AND route_id =$route_id and `status` = 1
                LIMIT 1";
        return $this->db->query($sql)->result_array();
    }

    public function create_route_stops()
    {
        error_reporting(0);
        $this->custom_model->check_session();
        $this->load->helper('url');
        $created_by = isset($this->session_login['account_info_id']) ? $this->session_login['account_info_id'] : $this->session_login['id'];
        $route_id = $this->input->post('route_id');
        $route_stop = $this->input->post('route_stop');
        $ispopular = $this->input->post('ispopular') == 1 ? 1 : 0;
        $midpoint = $this->input->post('midpoint');
        $zone_id = $this->input->post('zone_id');
        $departure_time = $this->input->post('departure_time') == '' ? null : $this->input->post('departure_time');
        $order = $this->input->post('order');

        try {
            $this->db->trans_start();
            $data = array(
                'route_id' => $route_id,
                'stop_id' => $route_stop,
                'previous_stop_id' => 1,
                'is_popular' => $ispopular,
                'section_number' => '1',
                'midpoint' => $midpoint,
                'position' => $order - 1,
                'created_by' => $created_by,
                'updated_by' => $created_by,
                'status' => '1',
                'departure_time' => $departure_time,
                'zone_id' => $zone_id,
            );

            $this->db->set('created_date', 'NOW()', FALSE);
            $this->db->set('updated_date', 'NOW()', FALSE);

            $this->db->insert('route_stops', $data);
            $this->recalculate_stop_order($route_id);
            $this->update_section_travel_by_route_id($route_id);
        } catch (Exception $exception) {
            echo $exception;
        } finally {
            $this->db->trans_commit();
        }
    }

    public function update_route_stop()
    {
        $route_stop_id = $this->input->post('id');
        $position = $this->input->post('order_update') - 1;
        $old_position = $this->input->post('order_before_update') - 1;
        $stop_id = $this->input->post('stop_id_update');
        $departure_time = $this->input->post('time');
        $is_popular = $this->input->post('checkedPopular');
        $is_midpoint = $this->input->post('checkedMidPoint');
        $zone_id = $this->input->post('zone_id');
        $user_id = isset($this->session_login['account_info_id']) ? $this->session_login['account_info_id'] : $this->session_login['id'];

        try {
            $this->db->trans_start();
            $route_stop_row = $this->db->from('route_stops')->where('id', $route_stop_id)->get()->row();
            $is_position_changed = $old_position != $position && $route_stop_row->position > 0;
            if ($is_position_changed) {
                if ($old_position != $route_stop_row->position) {
                    // Concurrent update
                    echo $this->transportlib->json_model(array('status' => 'error', 'message' => 'Stop position was modified by other user. Please review and retry.'));
                    return;
                }
            }

            // Update stop info
            $this->db->set('departure_time', $departure_time ?: null)
                ->set('is_popular', $is_popular)
                ->set('midpoint', $is_midpoint)
                ->set('zone_id', $zone_id)
                ->set('updated_by', $user_id)
                ->set('updated_date', 'NOW()', FALSE)
                ->where('id', $route_stop_id);

            if ($route_stop_row->position > 0) {
                $this->db->set('position', $position)->set('stop_id', $stop_id);
            }
            $this->db->update('route_stops');

            if ($is_position_changed) {
                $this->recalculate_stop_order(
                    $route_stop_row->route_id,
                    $position < $old_position ? 'desc' : 'asc'
                );

                $this->update_section_travel_by_route_id($route_stop_row->route_id);
            }
        } catch (Exception $exception) {
            echo $exception;
        } finally {
            $this->db->trans_commit();
        }
    }

    public function recalculate_stop_order($route_id, $sort_updated_at = 'desc')
    {
        if (!is_numeric($route_id)) {
            throw new Exception("Invalid route id");
        }

        // Update all order number staring from 1
        $this->db->query("update route_stops join 
            (select id, @row_number:= @row_number + 1 as orderPosition
            from route_stops as rs,
                (SELECT @row_number:= -1) as t
                where rs.route_id = ${route_id} and rs.status = 1
                order by position, updated_date ${sort_updated_at}) as orderedRS
            on route_stops.id = orderedRS.id
                set route_stops.position = orderedRS.orderPosition
            where route_stops.id = orderedRS.id and route_stops.status = 1 and route_stops.position > 0");

        // Update previous id
        $this->db->set('previous_stop_id', "(select rsq.stop_id from (SELECT * FROM route_stops) AS rsq where rsq.route_id = ${route_id} and rsq.status = 1 and rsq.position < rs.position order by rsq.position desc limit 1)", false)
            ->where('position >=', 1)
            ->where('route_id', $route_id)
            ->where('status', 1);
        $this->db->update('route_stops rs');
    }

    public function update_section()
    {
        $this->db->query("UPDATE route_stops set section_number=1");
    }

    // @JIRA TSM-815
    public function find_departure_time_in_route_stop_departure_time($route_id, $stop_id)
    {
        $sql = "SELECT * 
                FROM route_stop_departure_time 
                WHERE route_id = $route_id
                AND stop_id =$stop_id";
        return $this->db->query($sql)->row_array();
    }

    public function insert_route_stop_departure_time($route_id, $stop_id, $departure_time)
    {
        $data = array(
            'route_id' => $route_id,
            'stop_id' => $stop_id,
            'departure_time' => $departure_time,
            'loop' => "1",
            'created_date' => date("Y-m-d H:i:s"),
        );
        $this->db->insert('route_stop_departure_time', $data);
    }

    public function update_route_stop_departure_time($route_id, $stop_id, $departure_time)
    {
        $route_stop_departure_time = $this->routes_model->find_departure_time_in_route_stop_departure_time($route_id, $stop_id);
        if ($route_stop_departure_time) {
            // If found record => update
            $data_route = array(
                'departure_time' => $departure_time,
                'created_date' => date("Y-m-d H:i:s")
            );
            $this->db->where('id', $route_stop_departure_time['id'])->update('route_stop_departure_time', $data_route);
        } else {
            $this->routes_model->insert_route_stop_departure_time($route_id, $stop_id, $departure_time);
        }
    }
    public function delete_route_stop_departure_time($route_id, $stop_id)
    {
        $this->db->where("route_id =", $route_id);
        $this->db->where("stop_from =", $stop_id);

        $this->db->or_where("route_id =", $route_id);
        $this->db->where("stop_to =", $stop_id);

        $this->db->delete('ticket_price_stops');
    }
    public function sort_route_stop($route_id)
    {
        $sql = "SELECT * 
                FROM route_stops 
                WHERE route_id = $route_id
                AND `status` = 1
                ORDER BY position";
        $list = $this->db->query($sql)->result_array();
        $x = 0;
        for ($i = 01; $i <= count($list) - 1; $i++) {
            $data = array(
                'previous_stop_id' => $list[$x]['stop_id']
            );
            $this->db->where('id', $list[$i]['id'])->update('route_stops', $data);
            $x++;
        }
    }
    // TSM-927
    public function find_route_stop_departure_time($route_id)
    {
        $sql = "SELECT route_stop_departure_time.*, route_stops.zone_id
                FROM route_stop_departure_time 
                INNER JOIN route_stops ON route_stop_departure_time.route_id = route_stops.route_id AND route_stop_departure_time.stop_id = route_stops.stop_id
                WHERE route_stop_departure_time.route_id = $route_id AND route_stop_departure_time.departure_time IS NOT NULL
                
                ORDER BY route_stop_departure_time.departure_time ASC
                ";
        return $this->db->query($sql)->result_array();
    }
    public function find_all_route_stop($route_id)
    {
        $sql = "SELECT * 
                FROM route_stops 
                WHERE route_id = $route_id
                AND `status` = 1
                ORDER BY position";
        return $this->db->query($sql)->result_array();
    }
    public function find_last_position_in_route_stop($route_id)
    {
        $sql = "SELECT * 
                FROM route_stops 
                WHERE route_id = $route_id
                AND `status` = 1
                ORDER BY position DESC";
        return $this->db->query($sql)->row_array();
    }
    public function insert_route_stop_with_position($route_id, $stop_id, $previous_stop_id, $position, $departure_time, $zone_id)
    {
        $created_by = isset($this->session_login['account_info_id']) ? $this->session_login['account_info_id'] : $this->session_login['id'];
        $data = array(
            'route_id' => $route_id,
            'stop_id' => $stop_id,
            'previous_stop_id' => $previous_stop_id,
            'departure_time' => $departure_time,
            'section_number' => "1",
            'created_date' => date("Y-m-d H:i:s"),
            'updated_date' => date("Y-m-d H:i:s"),
            'status' => "1",
            'position' => $position,
            'created_by' => $created_by,
            'created_date' => date('Y-m-d H:i:s'),
            'updated_by' => $created_by,
            'updated_date' => date('Y-m-d H:i:s'),
            'zone_id' => $zone_id,
        );
        $this->db->insert('route_stops', $data);
    }
    public function update_departure_time_table_route_stop($id, $departure_time)
    {
        $this->db->set('departure_time', $departure_time);
        $this->db->where('id', $id);
        $this->db->update('route_stops');
    }
    //TSM-942
    public function get_all_name_db()
    {
        $sql = "SELECT db_name,order_stop_check,company_id
                FROM master_db.company_master_table
                WHERE db_name != 'master_db'
                ";
        return $this->db->query($sql)->result_array();
    }
    public function check_db_on_server($db_name)
    {
        $sql = " SELECT SCHEMA_NAME
                 FROM INFORMATION_SCHEMA.SCHEMATA
                 WHERE SCHEMA_NAME = '$db_name'
                ";
        return $this->db->query($sql)->row_array();
    }
    public function get_zone_by_zone_id($zone_id)
    {
        $sql = " SELECT *
                 FROM zone
                 WHERE id = $zone_id
                ";
        return $this->db->query($sql)->row_array();
    }
}
