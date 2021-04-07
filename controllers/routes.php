<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');
session_start(); //we need to call PHP's session object to access it through CI

class Routes extends CI_Controller
{

    // @JIRA TSM-675
    protected $account_info_id;
    protected $session_login;
    // END TSM-675

    function __construct()
    {
        parent::__construct();
        $this->load->library('session');
        $this->load->helper('url');
        $this->load->helper('form');
        $this->load->model('routes_model');
        $this->load->model('stops_model');
        $this->load->model('zone_model');
        $this->load->model('custom_model');
        $this->load->model('late_sign_on_model');
        $this->load->model('transportme_api_model_v2');
        // check operator permission
        $this->transportlib->check_operator_permission();
        $controller = $this->uri->segment(1);
        if ($controller == get_class($this)) {
            checkPermissionMenu('route');
        }
        // @JIRA TSM-675
        $this->session_data = $this->session->userdata('logged_in');
        $this->account_info_id = isset($this->session_data['account_info_id']) ? $this->session_data['account_info_id'] : '';
        // END TSM-675
        $this->load->model('routes_model_v2'); // @JIRA TSM-676
    }

    function index()
    {
        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $array = $this->uri->uri_to_assoc();
            $this->load->helper('url');
            $this->load->helper('form');
            $this->load->library('form_validation');
            //@JIRA TSM-810        
            $query = "SELECT order_stop_check FROM company_master_table where company_id=" . $da['id'];
            $check_order_stop_check = $this->db->query($query)->row_array();
            //======================= END TSM-810 =======================   
            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "admin") {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['check_order_stop_check'] = $check_order_stop_check['order_stop_check'];

                $data['route_category_detail'] = $this->routes_model->get_route_category();
                $this->load->view('admin/routes', $data);
            } else {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];;
                $data['type'] = $session_data['type'];
                $data['check_order_stop_check'] = $check_order_stop_check['order_stop_check'];

                $data['route_category_detail'] = $this->routes_model->get_route_category();
                $this->load->view('admin/routes', $data);
            }
        } else {
            //If no session, redirect to login page
            redirect('admin', 'refresh');
        }
    }
    function save_pending_route()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $stops = $data['stops'];
        $route_name = $data['route_name'];

        if (count($stops)) {
            $departing_stop =  $stops[0]['near_by_stop'] != null ? $stops[0]['near_by_stop'] : $stops[0]['id'];
        }
        if ($departing_stop == null) {
            echo 'Route must have stops.';
            exit();
        }
        $route_category_id = $data['route_category_id'];
        $route_id = $data['route_id'];
        $data_route = array(
            'name' => $route_name,
            'route_category_id' => $route_category_id,
            'departing_stop' => $departing_stop,
        );
        if ($this->routes_model->update_route_from_data($route_id, $data_route) != '1') {
            echo 'Can not update route info';
            exit();
        }

        foreach ($stops as &$value_array) {
            $id = isset($value_array['id']) ? $value_array['id'] : '';
            if (!empty($value_array['name'])) {
                if ($value_array['name'] === 0) { // 0 mean click near by stop = none.
                    $near_by_stop = '';
                } else {
                    $near_by_stop = $value_array['near_by_stop'];
                }
            } else {
                $near_by_stop = '';
            }
            $name = isset($value_array['name']) ? $value_array['name'] : '';

            if ($id != '' && $name != '') {
                $this->stops_model->update_stop_name($id, $name, $near_by_stop);
            }
        }
        echo 'Successful';
        exit();
    }

    function delete_pending_route()
    {
        $route_id = json_decode(file_get_contents('php://input'), true)['route_id'];
        try {
            $this->routes_model->delete_route_line($route_id);
            $this->routes_model->delete_pending_route_stops($route_id);
            $this->routes_model->delete_pending_route($route_id);
            $result['type'] = "success";
            $result['status'] = "success";
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }
        echo json_encode($result);
    }

    function get_near_by_stop($data)
    {
        $results = [];
        foreach ($data as $value) {
            $lat = $value['lat'];
            $lng = $value['lng'];
            array_push($results, $this->routes_model->get_near_by_stop($lat, $lng));
        }
        return $results;
    }
    function get_pending_route_by_id()
    {
        $route_id = json_decode(file_get_contents('php://input'), true)['route_id'];
        $results = [];
        $results['route_detail'] = $this->routes_model->get_address_pending_route($route_id);
        $results['stops'] = $this->routes_model->get_stop_pending_route($route_id);
        $results['near_by_stops'] = $this->get_near_by_stop($results['stops']);
        echo json_encode($results);
    }
    function get_pending_route()
    {
        echo json_encode($this->routes_model->get_pending_routes());
    }
    function get_route_category()
    {
        echo json_encode($this->routes_model->get_route_category());
    }
    function delete_pending_stop()
    {
        $stop_id = json_decode(file_get_contents('php://input'), true)['stop_id'];
        try {
            $this->routes_model->delete_stop($stop_id);
            $result['type'] = "success";
            $result['status'] = "success";
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['type'] = "error";
            $result['status'] = "error";
        }
        echo json_encode($result);
    }
    public function add_category()
    {
        $this->load->helper('url');
        $this->load->helper('form');


        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "admin") {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];

                $this->load->view('admin/routes_category_add', $data);
            } else {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];

                $this->load->view('admin/routes_category_add', $data);
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function create_route_category()
    {
        $this->load->helper('form');
        $this->routes_model->create_route_category();
    }

    public function delete_route_category($id)
    {

        echo "<script> alert('Can't Deleted, there is route/s in this category.'); </script>";


        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "admin") {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];

                $this->routes_model->delete_route_category($id);
            } else {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];

                $this->routes_model->delete_route_category($id);
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function generate_loop_stop()
    {
        $route_id = $_GET['route_id'];
        $total_loop = $this->input->post('total_loop');
        if ($total_loop) {
            $this->routes_model->generate_loop_stop($route_id, $total_loop);
        } else {
            redirect('routes/edit_route/id/' . $route_id . '?tab=stop', 'refresh');
        }
    }

    public function edit_category()
    {
        $this->load->helper('url');
        $array = $this->uri->uri_to_assoc();
        $id = $array['id'];

        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "admin") {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];
                $data['category_id'] = $id;
                // @JIRA TSM-533
                // default is operator
                $data['action'] = SUB_OPERATOR_EXECUTE_CONTROL;
                if (isset($session_data['account_info_id'])) {
                    $data['action'] = $this->routes_model->get_allowed_category($id);
                }
                // END @JIRA TSM-533

                $this->load->view('admin/routes_category_edit', $data);
            } else {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];
                $data['category_id'] = $id;
                // @JIRA TSM-533
                if (isset($session_data['account_info_id'])) {
                    $data['action'] = $this->routes_model->get_allowed_category($id);
                }
                // END @JIRA TSM-533
                $this->load->view('admin/routes_category_edit', $data);
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function update_route_category()
    {
        $this->load->helper('form');
        $this->routes_model->update_route_category();
    }

    ///////////////////////////////////// Display Routes List /////////

    public function routes_list()
    {

        $array = $this->uri->uri_to_assoc();
        $da = $this->session->userdata('logged_in');

        if (isset($array['route_stops'])) {
            $data['route_stop'] = "1";
            $data['route_id'] = $array['route_stops'];
            // @JIRA <TSM-521
            // check user login has permission route
            $list_route = $this->routes_model->get_route();
            // fix bug @JIRA TSM-588
            $data['permisson_stops'] = SUB_OPERATOR_EXECUTE_CONTROL;
            if (isset($da['account_info_id'])) {
                $arr_action_route = array_column($list_route, 'action', 'id');
                $data['permisson_stops'] = $arr_action_route[$array['route_stops']];
                // END @JIRA TSM-588
            }
            if (!in_array($data['route_id'], array_column($list_route, 'id'))) {
                echo "<script>alert('You do not have a permission add stop to route');</script>";
                redirect('/index.php/routes/routes_list', 'refresh');
            }
            // END TSM-521
            $data['selected_route_detail'] = $this->routes_model->get_route_selected($array['route_stops']);
            $data['route_stops_ticket_detail'] = $this->routes_model->get_stops_ticket_from_route($data['route_id']);
        } else {
            $data['route_stop'] = "0";
        }

        if ($da['id'] != "" && $da['id'] != "0") {
            $array = $this->uri->uri_to_assoc();
            $this->load->helper('url');
            $this->load->helper('form');


            $this->load->library('form_validation');

            $session_data = $this->session->userdata('logged_in');
            //@JIRA TSM-810        
            $query = "SELECT order_stop_check FROM company_master_table where company_id=" . $da['id'];
            $check_order_stop_check = $this->db->query($query)->row_array();

            //======================= END TSM-810 ======================= 
            if ($session_data['type'] == "admin") {
                $data['check_order_stop_check'] = $check_order_stop_check['order_stop_check'];
                $data['session_data'] = $session_data;
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];;
                $data['type'] = $session_data['type'];
                $data['gps_bundle'] = $session_data['gps_bundle'];
                // @JIRA TSM 317
                $data['passenger_manifest'] = $session_data['passenger_manifest'];
                $data['route_detail'] = $this->routes_model->get_route();
                //@JIRA TSM-670
                $this->load->view('admin/routes_list_new', $data);
            } else {
                $data['check_order_stop_check'] = $check_order_stop_check['order_stop_check'];
                $data['session_data'] = $session_data;
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];;
                $data['type'] = $session_data['type'];
                $data['gps_bundle'] = $session_data['gps_bundle'];
                // @JIRA TSM 317
                $data['passenger_manifest'] = $session_data['passenger_manifest'];
                $data['route_detail'] = $this->routes_model->get_route();
                //@JIRA TSM-670
                $this->load->view('admin/routes_list_new', $data);
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }


    public function edit_pending_routes()
    {
        $this->load->helper('url');
        $array = $this->uri->uri_to_assoc();
        $id = $array['id'];
        $da = $this->session->userdata('logged_in');
        $data['route_id'] = $id;
        if ($da['id'] != "" && $da['id'] != "0") {
            $this->load->view('admin/edit_pending_route', $data);
        } else {
            redirect('admin', 'refresh');
        }
    }

    public function pending_routes()
    {
        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $this->load->view('admin/pending_routes');
        } else {
            //If no session, redirect to login page
            redirect('admin', 'refresh');
        }
    }
    ////////////////////////////////////// Add Routes ///////////////

    public function add_routes()
    {
        $session_data = $this->session->userdata('logged_in');
        $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
        $data['type'] = $session_data['type'];
        $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];
        $data['route_category_detail'] = $this->routes_model->get_route_category();
        $data['use_zone_to_zone_ticket'] = $session_data['use_zone_to_zone_ticket'];

        $data['stops_detail'] = $this->stops_model->get_stops_detail();
        $data['ticket_type_detail'] = $this->routes_model->get_ticket_type();
        $data['passenger_list_data'] = $this->routes_model->get_passenger_list($session_data);

        // VINASOURCE add 04March206 -- start
        $data['zone_ticket_ability'] = $session_data['zone_ticket_ability'];
        $data['pax_count_ability'] = $session_data['pax_count_ability'];
        $data['on_time_running_check'] = $session_data['on_time_running_check'];
        $data['accessible_bus_services_check'] = $session_data['accessible_bus_services_check'];
        $data['hyor2_check'] = $session_data['hyor2_check'];
        $data['hyor3_check'] = $session_data['hyor3_check'];
        if ($session_data['zone_ticket_ability'] == 1) {
            $data['zones'] = $this->zone_model->get_all_zone();
        }

        $data['bus_roll_check'] = $session_data['bus_roll_check'];
        $data['esm_passenger'] = $session_data['esm_passenger'];
        $data['sort_ticket_ability'] = $session_data['sort_ticket_ability'];
        $data['late_sign_on_alert_check'] = $session_data['late_sign_on_alert_check'];
        $data['on_demand_service'] = $session_data['on_demand_service'];
        // VINASOURCE add 04March206 -- end
        // @JIRA TSM 317
        $data['passenger_manifest'] = $session_data['passenger_manifest'];
        $data['clear_of_bus'] = $session_data['clear_of_bus'];
        $data['contract'] = $this->routes_model->get_all_contract_number(); //@JIRA TSM-781
        $this->load->view('admin/routes_add', $data);
    }

    /**
     * This method has been modified  to edit routes
     * @author Shyam Sundar Pandey
     */
    public function edit_route()
    {
        $array = $this->uri->uri_to_assoc();
        $id = $array['id'];

        $session_data = $this->session->userdata('logged_in');
        $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
        $data['type'] = $session_data['type'];
        $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];
        $data['use_zone_to_zone_ticket'] = $session_data['use_zone_to_zone_ticket'];

        //  @JIRA TSM-673
        // check permission route
        if (!empty($this->account_info_id)) {
            $list_route = $this->routes_model->get_route();
            $list_route_id = array_column($list_route, 'id');
            if (!in_array($id, $list_route_id)) {
                echo "<script>alert('You don\'t have permission access page !!!')</script>";
                redirect('/routes/routes_list', 'refresh');
            }
        }
        //  END TSM-673
        $data['ticket_type_detail'] = $this->routes_model->get_ticket_type();
        $data['selected_ticket_type'] = $this->routes_model->get_route_ticket_selected($id);

        // VINASOURCE modify
        $routes = $this->routes_model->get_route_info($id);

        if ($routes->status == PENDING_ROUTE) {
            $data['stops_detail'] = $this->stops_model->get_stops_detail_by_route($id, PENDING_ROUTE);
        } else {
            $data['stops_detail'] = $this->stops_model->get_stops_detail();
        }

        $data['routes'] = $routes;
        $data['route_id'] = $id;

        $data['zone_ticket_ability'] = $session_data['zone_ticket_ability'];
        $data['pax_count_ability'] = $session_data['pax_count_ability'];
        $data['on_time_running_check'] = $session_data['on_time_running_check'];
        $data['accessible_bus_services_check'] = $session_data['accessible_bus_services_check'];
        $data['hyor2_check'] = $session_data['hyor2_check'];
        $data['hyor3_check'] = $session_data['hyor3_check'];
        if ($session_data['zone_ticket_ability'] == 1) {
            $data['zones'] = $this->zone_model->get_all_zone();
            // @JIRA TSM-673
            $data['zone_selected'] = 0;
            if (!empty($routes) && $routes->departing_zone_id > 0) {
                $arr_list_zones = array_column($data['zones'], 'name', 'id');
                $data['zone_selected'] = $arr_list_zones[$routes->departing_zone_id];
            }
            // END TSM-673
        }
        $departure_time = null;
        if ($session_data['on_time_running_check'] && $routes) {
            // get departure time
            $departure_time_obj = $this->routes_model->get_departure_time_loop($id, $routes->departing_stop);
            // @JIRA TSM-673
            $data['arr_departure_time'] = $departure_time_obj;
            // END TSM-673
            if ($departure_time_obj) {
                $departure_time = isset($departure_time_obj[1]) ? $departure_time_obj[1] : null;
            }
        }
        $data['departure_time'] = $departure_time;

        $data['bus_roll_check'] = $session_data['bus_roll_check'];
        $data['sort_ticket_ability'] = $session_data['sort_ticket_ability'];

        $data['late_sign_on_alert_check'] = $session_data['late_sign_on_alert_check'];
        if (!empty($data['late_sign_on_alert_check'])) {
            $data['activation_time'] = !empty($routes->activation_time) ? $routes->activation_time : '';
            $data['late_sign_on'] = !empty($routes->late_sign_on) ? $routes->late_sign_on : '';
        }
        $data['esm_passenger'] = isset($session_data['esm_passenger']) ? $session_data['esm_passenger'] : 0;
        $data['passenger_list_data'] = $this->routes_model->get_passenger_list($session_data);

        // popular stop info
        $data['stop_is_popular'] = $this->routes_model->departing_is_popular_stop($id);
        $data['on_demand_service'] = $session_data['on_demand_service'];
        // @JIRA TSM 347
        $data['gps_bundle'] = $session_data['gps_bundle'];
        // @JIRA TSM 317
        $data['passenger_manifest'] = $session_data['passenger_manifest'];
        // JIRA TSM 533
        // default is operator
        $data['action_route'] = SUB_OPERATOR_EXECUTE_CONTROL; // @JIRA @TSM-673 rename variable
        $data['permisson_stops'] = SUB_OPERATOR_EXECUTE_CONTROL; //@JIRA @TSM-676 add permission stops
        if (isset($session_data['account_info_id'])) {
            $data['action_route'] = $this->routes_model->get_allowed_category($routes->route_category_id); // @JIRA @TSM-673 rename variable
            // @JIRA TSM-676
            $data['permisson_stops'] = $data['action_route'];
            //END TSM-676
        }
        // END JIRA TSM-533
        // @JIRA TSM-673
        // @decription get more data show to view
        $data['categories'] = $categories = $this->routes_model->get_route_category();
        $arr_list_cat = array_column($data['categories'], 'name', 'id');
        if (!empty($routes)) {
            //@JIRA TSM-681
            $data['category_name_selected'] = isset($arr_list_cat[$routes->route_category_id]) ? $arr_list_cat[$routes->route_category_id] : reset($arr_list_cat);
            // fix case data in DB not map
            $route_type_selected = isset($data['passenger_list_data'][$routes->special_route_type]) ? $data['passenger_list_data'][$routes->special_route_type] : $data['passenger_list_data'][0];
            $data['route_type_selected'] = $route_type_selected;
            $arr_list_stops = array_column($data['stops_detail'], 'name', 'id');
            // @JIRA TSM-675
            $data['departing_stop_selected'] = 0;
            // if data in database false (in case value column departing_stop is 0)

            if ($routes->departing_stop > 0 && $routes->status == 1) {
                //@JIRA TSM-68 
                $find_departing = $this->routes_model->find_departing_stop($routes->id, $routes->departing_stop);
                if (count($find_departing) == 0) {
                    $find_departure_time = $this->routes_model->find_departure_time($routes->id, $routes->departing_stop);
                    $this->routes_model->insert_update_departing_stop($routes->id, $routes->departing_stop, $find_departure_time['departure_time']);
                    // $this->routes_model->insert_update_departing_stop($routes->id,$routes->departing_stop);
                } else {
                    if ($find_departing['previous_stop_id'] != 0 || $find_departing['position'] < 1) {
                        $this->routes_model->update_stop_order_previous_stop_id($find_departing['id']);
                    }
                }
                //@JIRA END TSM-68
                // if data in database false (stop_id not exist in table route_stop)
                if (isset($arr_list_stops[$routes->departing_stop])) {
                    $data['departing_stop_selected'] = $arr_list_stops[$routes->departing_stop];
                } else {
                    $stop_info = $this->stops_model->get_stop_by_id([$routes->departing_stop]);
                    $data['departing_stop_selected'] = $stop_info[0]['name'];
                }
            }
        }

        //@JIRA TSM-677
        $stops_list_in_route = [];
        if (isset($routes->number_of_section) && $routes->number_of_section > 0) {
            $arr = $this->stops_model->get_list_stop_by_section_route($id);
            $stops_list_in_route = array_merge($stops_list_in_route, $arr);
        }
        // mapping stop order stop
        $data['stops_list_in_route'] = $stops_list_in_route;
        $data['stops_list'] = $this->stops_model->get_stops_detail();
        // END TSM-673

        //@JRIA TSM-681
        $data['route_detail'] = $this->routes_model->get_route();
        $data['route_ticket_detail'] = $this->routes_model->get_route_ticket_detail($id);
        $data['route_stops_detail'] = $this->routes_model->route_stops($id);
        $data['route_all_stops_detail'] = $this->routes_model->get_all_stops_from_route();
        $data['zone_all_stops_detail'] = $this->routes_model->get_all_zone_from_route();
        $get_stops_from_route_by_id_list = $this->routes_model->get_stops_from_route_by_id($id);
        $get_stops_from_route_by_id = [];
        foreach ($get_stops_from_route_by_id_list as $value) {
            $get_stops_from_route_by_id[] = $value['stop_id'];
        }
        $data['get_stops_from_route_by_id'] = $get_stops_from_route_by_id;
        $data['route_stops_list'] = $this->routes_model->get_stops_from_route($id);
        //JIRA TSM-677
        $data['route_stop'] = 1;
        //@JIRA TSM-671 change new view route edit
        $data['clear_of_bus'] = $session_data['clear_of_bus'];

        $this->load->view('admin/routes_edit_new', $data);
    }
    public function update_data_travel_chart()
    {
        $session_data = $this->session->userdata('logged_in');
        $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
        $data['type'] = $session_data['type'];
        $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];

        $route_id = $_GET['route_id'];
        $data['route_id'] = $route_id;
        $data['route_stops_detail'] = $this->routes_model->route_stops($route_id);
        // $this->load->view('admin/routes/travel_chart_edit', $data);
        $action_route = SUB_OPERATOR_EXECUTE_CONTROL;
        $data['is_execute'] = ($action_route == SUB_OPERATOR_EXECUTE_CONTROL);
        $resp = $this->load->view('admin/routes/travel_chart_edit', $data, TRUE);

        // $this->set_output($data); 
        echo $resp;
    }
    public function get_stops_id()
    {
        $id = $_GET['id'];
        $data['route_stops_list'] = $this->routes_model->get_stops_from_route_new($id);
        $data['zone_list'] = $this->routes_model->get_zone_names();
        echo json_encode($data);
    }

    public function create_route()
    {
        $this->routes_model->create_route();
    }

    public function update_section_travel_by_route_id()
    {
        $route_id = $this->input->get('route_id');
        $this->routes_model->update_section_travel_by_route_id($route_id);
        redirect('routes/edit_route/id/' . $route_id, 'refresh');
    }

    public function copy_section_matrix()
    {
        $this->routes_model->copy_section_matrix();
    }

    public function show_copy_section_matrix()
    {
        $this->load->helper('url');
        $this->load->helper('form');

        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "admin") {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];
                $data['route_category_detail'] = $this->routes_model->get_route_category();
                $this->load->view('admin/routes_copy_section_matrix', $data);
            } else {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];
                $data['route_category_detail'] = $this->routes_model->get_route_category();
                $this->load->view('admin/routes_copy_section_matrix', $data);
            }
        } else {
            //If no session, redirect to login page
            redirect('admin', 'refresh');
        }
    }

    public function copy_routes()
    {
        $this->load->helper('url');
        $this->load->helper('form');


        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "admin") {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];
                $data['route_category_detail'] = $this->routes_model->get_route_category();


                $this->load->view('admin/routes_copy', $data);
            } else {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];
                $data['route_category_detail'] = $this->routes_model->get_route_category();

                $data['stops_detail'] = $this->stops_model->get_stops_detail();

                $data['ticket_type_detail'] = $this->routes_model->get_ticket_type();

                $this->load->view('admin/routes_copy', $data);
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function create_copy_route()
    {
        $this->load->helper('form');
        $this->routes_model->copy_route();
    }

    public function update_route()
    {
        $this->routes_model->update_route();
    }

    public function delete_route($id)
    {



        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "admin") {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];
                echo "<script> {window.location = '" . base_url() . "index.php/routes/delete_route_soft/" . $id . "';}</script>";

                //	$this->routes_model->delete_route($id);
            } else {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];

                echo "<script>var answer = confirm('Do you want to delete ?'); if (answer) {window.location = '" . base_url() . "index.php/routes/delete_route_soft/" . $id . "';}</script>";

                //	$this->routes_model->delete_route($id);
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function delete_route_full($id)
    {
        $this->routes_model->delete_route_full($id);
    }

    public function delete_route_soft($id)
    {
        $this->routes_model->delete_route_soft($id);
    }

    public function update_route_stops_to_stops_single()
    {
        $this->load->helper('form');
        $this->routes_model->update_route_stops_to_stops_single();
    }

    public function add_routes_stops($route_id)
    {
        $session_data = $this->session->userdata('logged_in');
        $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
        $data['type'] = $session_data['type'];
        $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];

        $data['route_stops_detail'] = $this->routes_model->get_stops_from_route($route_id);

        $data['route_stops_previous'] = $this->routes_model->route_stops_previous($route_id);

        $data['route_id'] = $route_id;

        if ($session_data['zone_ticket_ability'] == 1) {
            $data['zones'] = $this->zone_model->get_all_zone();
        }
        $data['zone_ticket_ability'] = $session_data['zone_ticket_ability'];
        $data['on_time_running_check'] = $session_data['on_time_running_check'];
        //if ($session_data['on_time_running_check']){
        $routes = $this->routes_model->get_route_info($route_id);
        $data['routes'] = $routes;
        //}
        //@JIRA TSM-677 change file new view
        $this->load->view('admin/routes/add_route_stop', $data);
    }

    public function add_routes_stops_to_stops($route_id)
    {
        $this->load->helper('url');
        $this->load->helper('form');


        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "admin") {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];

                $data['route_stops_detail'] = $this->routes_model->route_stops($route_id);


                $data['route_id'] = $route_id;

                $this->load->view('admin/routes_stops_to_stops_add', $data);
            } else {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];


                $data['route_stops_detail'] = $this->routes_model->route_stops($route_id);

                $data['route_id'] = $route_id;

                $this->load->view('admin/routes_stops_to_stops_add', $data);
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }


    /**
     * @JIRA TSM-246
     *
     */
    public function routes_kml_add()
    {
        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $data['username']   = isset($da['account_info_name']) ? $da['account_info_name'] : $da['name'];
            $data['type']       = $da['type'];
            $data['user_id']    = isset($da['account_info_id']) ? $da['account_info_id'] : $da['id'];
            $data['gps_bundle'] = $da['gps_bundle'];
            // @JIRA TSM-621
            $data['route_category_detail'] = $this->routes_model->get_route_category();

            if ($da['type'] == "admin") {
                $this->load->view('admin/routes_kml_add', $data);
            } else {
                $this->load->view('admin/routes_kml_add', $data);
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    /**
     * @JIRA TSM-246
     *
     */
    public function get_stops_for_kml()
    {
        $this->stops_model->get_stops();
    }

    /**
     * @JIRA TSM-347
     *
     */
    public function get_stops_by_route_id_for_kml()
    {
        $this->routes_model->get_stops_by_route_id_for_kml();
    }

    /**
     * @JIRA TSM-246
     *
     */
    public function set_route_stops_routestops_by_kml()
    {
        $data = $this->routes_model->set_route_stops_routestops_by_kml();
        echo json_encode($data);
    }
    /**
     * @JIRA TSM-341
     *
     */
    public function set_routestops_by_kml()
    {
        $data = $this->routes_model->set_routestops_by_kml();
        echo json_encode($data);
    }

    /**
     * @JIRA TSM-246
     *
     */
    public function set_update_route_by_kml()
    {
        $data = $this->routes_model->set_update_route_by_kml();
        echo $data;
    }

    /**
     * @JIRA TSM-246
     *
     */
    public function do_upload_kml_file()
    {
        $response = new stdClass();
        $return = array(
            'status' => 'error',
            'message' => ''
        );
        if (isset($_FILES['file']) && $_FILES['file']['size'] > 0) {
            // $name = $_FILES["file"]["name"];
            // $ext = end((explode(".", $name)));
            $route_id = $this->input->post('route_id');
            // echo $ext;
            $upload = $this->transportme_api_model_v2->do_upload('file', 'kml');
            if ($upload && !$upload['error']) {
                //@JIRA TSM-388 update linkfile
                $filename = $upload['filename'];
                //echo '<csript>alert("'.$filename['filename'].'")</csript>';
                $this->routes_model->update_link_kml_for_route($route_id, $filename);
                $return['status'] = 'KML file inserted';
                $return['message'] = $upload['msg'];
            } else {
                $return['status'] = 'error';
                $return['message'] = $upload['msg'];
            }
        } else {
            $return['status'] = 'error';
            $return['message'] = 'Please select file';
        }
        $response->result = $return;
        echo json_encode($response);
    }

    /**
     * @JIRA TSM-347
     *
     */
    public function check_esixted_route_line()
    {

        $result = $this->routes_model->get_route_line($_GET['route_id']);
        echo $result;
    }

    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @Description: Function get stops and smartcard detail of stop for route in form route_passenger_manifest
     */
    public function get_stops_detail_route_for_manifest()
    {
        //Reuse function get_stops_by_route_id_for_kml get stops data for passenger manifest
        $this->routes_model->get_stops_by_route_id_for_kml();
    }

    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @Description: Function get group stops_id, card_number, cardholder_name on route to view form route_passenger_manifest (routes_list.php)
     */
    public function get_smartcard_detail_of_stops_on_route_for_manifest_view()
    {
        //Result XML group stops_id, card_number, cardholder_name
        $route_id = $_GET['route_id'];
        $stop_id = ''; //Get all card of route
        $this->routes_model->get_smartcard_detail_of_stops_on_route_for_manifest_view($route_id, $stop_id);
    }
    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @Description: Function view form route_passenger_manifest for search card
     */
    public function route_passenger_manifest_smartcard_add()
    {
        $da = $this->session->userdata('logged_in');
        $array = $this->uri->uri_to_assoc();
        $route_stop_id = $array['route_stop_id'];
        $route_id = $array['route_id'];
        $stop_id = $array['stop_id'];
        if ($da['id'] != "" && $da['id'] != "0") {
            $data['username'] = isset($da['account_info_name']) ? $da['account_info_name'] : $da['name'];
            $data['type'] = $da['type'];
            $data['user_id'] = isset($da['account_info_id']) ? $da['account_info_id'] : $da['id'];
            $smart_card_manifest = $this->routes_model->get_all_smartcard_manifest_route_stop($route_stop_id);

            $data['route_id'] = $route_id;
            $data['stop_id'] = $stop_id;
            $data['route_stop_id'] = $route_stop_id;
            $data['smart_card_manifest'] = $smart_card_manifest;
            $data['smart_card_manifest_detail'] = $this->routes_model->get_all_smartcard_manifest_route_stop_detail($smart_card_manifest);

            //Get data for function search card
            $data['stops'] = json_encode($this->routes_model->get_stops_from_route($data['route_id']));

            if ($da['type'] == "admin") {
                $this->load->view('admin/route_passenger_manifest_smartcard_add', $data);
            }
        } else {
            //If no session, redirect to login page
            redirect('admin', 'refresh');
        }
    }

    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @Description: Function generate_route_manifest_bulk_sample_file view form route passenger manifest
     */
    public function generate_route_manifest_bulk_sample_file()
    {
        $data = array();
        header("Content-Type: application/json"); // Since we are sending a JSON response here (not an HTML document), set the MIME Type to application/json

        //Because you are posting the data via fetch(), php has to retrieve it elsewhere.
        $json_str = file_get_contents('php://input');
        //This will store the data into an associative array
        $json_obj = json_decode($json_str, true);

        $data['route_name'] = $json_obj['route_name'];
        $data['route_id']   = $json_obj['route_id'];
        $data['datastop']   = $json_obj['datastop'];

        $this->load->view('admin/passenger_manifest_cardholder_bulk_sample_file_excel.php', $data);
    }

    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @Description: Function get smart card detail of stops for manifest view form route passenger manifest or check existed card in stop
     */
    public function get_smartcard_detail_of_stops_for_manifest()
    {
        $route_id = $_GET['route_id'];
        $stop_id = $_GET['stop_id'];
        $this->routes_model->get_smartcard_detail_of_stops_on_route_for_manifest_view($route_id, $stop_id);
    }

    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @Description: Function set new smardcards manifest to stop in smartcard_detail_route_stop table
     */
    public function set_new_smartcards_manifest_to_stop()
    {
        echo $this->routes_model->set_new_smartcards_manifest_to_stop();
    }

    public function get_smartcard_with_string()
    {
        echo $this->routes_model->get_smartcard_with_string();
    }

    public function update_smartcards_manifest_to_stop()
    {
        echo $this->routes_model->update_smartcards_manifest_to_stop();
    }

    public function select_deboarding_stop_all_day()
    {
        echo $this->routes_model->select_deboarding_stop_all_day();
    }

    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @Description: Function get smart card data to check card in file excel for manifest form
     */
    public function get_data_check_card_file_excel_for_manifest()
    {
        //Get data for function check card
        echo json_encode($this->routes_model->get_smartcard_detail_for_manifest());
    }

    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @Description: Function set new smardcards manifest to stop in smartcard_detail_route_stop table
     */
    public function set_new_smartcards_manifest_from_excel_file_to_stops()
    {
        echo $this->routes_model->set_new_smartcards_manifest_from_excel_file_to_stops();
    }

    /**
     * @JIRA TSM-317
     * @author: kiet.nguyen
     * @Description: Function delete smardcards manifest of the stop in smartcard_detail_route_stop table
     */
    public function del_data_smartcard_manifest()
    {
        // -> To check card deletion of stop or delete all cards in route
        $stop_id = $this->input->post('stop_id');
        $card_id = $this->input->post('card_id');
        $route_id = $this->input->post('route_id');

        echo $this->routes_model->del_data_smartcard_manifest($route_id, $stop_id, $card_id);
    }

    //@Jira Task: TSM-751
    public function update_skip_day_smartcard_manifest()
    {

        $id = $this->input->post('id');
        $data_update = $this->input->post('data_update');

        echo $this->routes_model->update_skip_day_smartcard_manifest($id, $data_update);
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

        $file = "transportmelog/" . date('mdy') . 'log.txt'; //log file
        if (file_exists($file)) {
            $handle = fopen($file, 'a');
        } else {
            $handle = fopen($file, 'w');
        }
        fputs($handle, $textInput);
        fputs($handle, "\n");
        fclose($handle);
    }

    public function edit_route_stops_to_stops_ticket($route_id)
    {
        //$this->logdata();
        //$this->logText("step1");

        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $array = $this->uri->uri_to_assoc();
            $this->load->helper('url');
            $this->load->helper('form');


            $this->load->library('form_validation');

            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "admin") {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];
                $data['route_id'] = $route_id;


                $data['route_detail'] = $this->routes_model->get_route();
                $data['route_ticket_detail'] = $this->routes_model->get_route_ticket_detail($route_id);
                $data['route_stops_detail'] = $this->routes_model->route_stops($route_id);
                //$this->logdata();
                //   $this->logText("step2");
                $this->load->view('admin/routes_stops_to_stops_edit', $data);
                //	$this->logdata();
                //    $this->logText("step3");
            } else {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];
                $data['route_id'] = $route_id;


                $data['route_detail'] = $this->routes_model->get_route();
                $data['route_ticket_detail'] = $this->routes_model->get_route_ticket_detail($route_id);
                $data['route_stops_detail'] = $this->routes_model->route_stops($route_id);
                //$this->logdata();
                //  $this->logText("step2");
                $this->load->view('admin/routes_stops_to_stops_edit', $data);
                //$this->logdata();
                //  $this->logText("step3");
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    public function create_route_stops()
    {
        $this->load->helper('form');
        $this->routes_model->create_route_stops();
    }

    public function update_route_stops_to_stops()
    {
        $this->load->helper('form');
        $this->routes_model->update_route_stops_to_stops();
    }

    public function create_route_stops_to_stops()
    {
        $this->load->helper('form');
        $this->routes_model->create_route_stops_to_stops();
    }

    public function edit_route_stops_to_stops()
    {
        $this->load->helper('form');
        $this->routes_model->edit_route_stops_to_stops();
    }

    public function ticket_type_check()
    {
        $this->load->helper('url');
        $section_no = $this->input->post('section_no');
        $ticket_type = $this->input->post('ticket_type');


        if ($this->routes_model->check_tycket_type_with_route_create($ticket_type, $section_no) == 1) {
            echo "you can proceed";
        } else {
            echo "Selected Ticket type section number is less then selected section number";
        }
    }

    /////////////////////////////

    public function section_isloop()
    {
        $this->routes_model->section_isloop();
    }

    public function change_previous_stop_by_secction_no()
    {
        $this->load->helper('url');
        $section_no = $this->input->post('section_no');
        $route_id = $this->input->post('route_id');

        $da = $this->session->userdata('logged_in');

        if ($da['id'] != "" && $da['id'] != "0") {
            $session_data = $this->session->userdata('logged_in');
            if ($session_data['type'] == "admin") {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];

                $data['previous_route_stops_details'] = $this->routes_model->get_stops_from_route_by_section_no($route_id, $section_no);
                $data['present_route_stops_details'] = $this->routes_model->get_stops_from_route_by_section_no_present($route_id, $section_no);


                $data['route_id'] = $route_id;
                $data['section_no'] = $section_no;

                $this->load->view('admin/routes_stops_by_section', $data);
            } else {
                $session_data = $this->session->userdata('logged_in');
                $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
                $data['type'] = $session_data['type'];
                $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];


                $data['previous_route_stops_details'] = $this->routes_model->get_stops_from_route_by_section_no($route_id, $section_no);
                $data['present_route_stops_details'] = $this->routes_model->get_stops_from_route_by_section_no_present($route_id, $section_no);
                $data['route_id'] = $route_id;
                $data['section_no'] = $section_no;
                $this->load->view('admin/routes_stops_by_section', $data);
            }
        } else {
            //If no session, redirect to login page

            redirect('admin', 'refresh');
        }
    }

    //////////// Delete route stops //////////
    public function delete_route_stop()
    {
        $this->routes_model->delete_route_stop();
    }

    public function save_popular_stops_by_section()
    {


        $this->routes_model->save_popular_stops_by_section();
    }

    // vinasource add -- start
    function update_zone_stops()
    {
        $this->routes_model->update_zone_stops();
    }

    function update_departure_time()
    {
        $this->routes_model->update_departure_time();
    }

    // vinasource add -- end

    function validate_midpoint()
    {
        $this->routes_model->validateMidpoint();
    }

    function update_stop_midpoint()
    {
        $this->routes_model->updateStopMidpoint();
    }

    function enable_loop_route()
    {
        $route_id = $this->input->post('route_id');
        $data['stop_id'] = $this->input->post('stop_id');
        $data['info'] = $this->routes_model->get_route_info($route_id);
        $this->load->view('admin/_loop_route_tpl', $data);
    }

    function show_departure_time_loop()
    {
        $route_id = $this->input->post('route_id');
        $stop_id = $this->input->post('stop_id');
        $data['number_of_route_loop'] = $this->input->post('number_of_route_loop');
        $data['arr_departure_time'] = $this->routes_model->get_departure_time_loop($route_id, $stop_id);
        $this->load->view('admin/_loop_route_form', $data);
    }

    function sort_ticket()
    {
        $data['tickets'] = $this->routes_model->get_sort_ticket();
        $this->load->view('admin/_sort_ticket', $data);
    }

    function show_sort_ticket()
    {
        $route_id = $this->input->post('route_id');
        $data['tickets'] = $this->routes_model->get_ticket_of_route($route_id);
        $this->load->view('admin/_sort_ticket', $data);
    }

    function check_valid_route_by_ticket_type()
    {
        echo $this->routes_model->check_valid_route_by_ticket_type();
    }

    function check_valid_all_route_by_ticket_type()
    {
        echo $this->routes_model->check_valid_all_route_by_ticket_type();
    }

    function check_valid_multi_route_category_by_ticket_type()
    {
        echo $this->routes_model->check_valid_multi_route_category_by_ticket_type();
    }

    function check_route_linked_to_split_trip_rule()
    {
        $route_id = $this->input->post('route_id');
        echo $this->routes_model->check_route_linked_to_split_trip_rule($route_id);
    }

    public function operation_date_list()
    {
        $userSession = $this->session->userdata('logged_in');
        if (empty($userSession['late_sign_on_alert_check'])) {
            redirect('admin', 'refresh');
            exit;
        }

        $data = array();
        $data['routes'] = $this->routes_model->get_route();
        $data['user_name'] = isset($userSession['account_info_id']) ? $userSession['account_info_name'] : $userSession['name'];
        $this->load->view('admin/operation_date_list', $data);
    }

    public function add_operation_date_list()
    {
        $userSession = $this->session->userdata('logged_in');
        if (empty($userSession['late_sign_on_alert_check'])) {
            redirect('admin', 'refresh');
            exit;
        }

        if (!empty($userSession['id'])) {
            $routeId = $this->input->post('route');
            $routeInfo = $this->routes_model->getRouteById($routeId);
            if (!empty($routeInfo)) {
                $delimiter = ', ';
                $datePick = $this->input->post('activation_date');
                if (empty($datePick)) {
                    $this->session->set_flashdata('message_type', MESSAGE_ERROR_TYPE);
                    $this->session->set_flashdata('message', 'Please choose the operation dates !');
                    redirect('routes/operation_date_list', 'refresh');
                    exit;
                }
                $datePickArr = explode($delimiter, $datePick);

                $routeIdList = array($routeId);
                $routeName = $routeInfo->name . $delimiter;
                $cloneRoutes = $this->input->post('clone_routes');
                foreach ($cloneRoutes as $routeId) {
                    $routeInfo = $this->routes_model->getRouteById($routeId);
                    if (!empty($routeInfo)) {
                        $routeIdList[] = $routeId;
                        $routeName .= $routeInfo->name . $delimiter;
                    }
                }
                $routeName = rtrim($routeName, $delimiter);

                $data = array();
                $i = 0;
                foreach ($routeIdList as $routeId) {
                    $this->late_sign_on_model->clearOperationDateFromNow($routeId);
                    foreach ($datePickArr as $date) {
                        $data[$i]['route_id'] = $routeId;
                        $data[$i]['date'] = $date;
                        $data[$i]['status'] = DAYS_OFF_STATUS;
                        $i++;
                    }
                }

                $result = $this->late_sign_on_model->insertMultipleOperationDateRecord($data);
                if (!$result) {
                    $this->session->set_flashdata('message_type', MESSAGE_ERROR_TYPE);
                    $this->session->set_flashdata('message', 'Opps there is something wrong please contact the administrator and notify them of this error !');
                    redirect('routes/operation_date_list', 'refresh');
                    exit;
                }
                $this->session->set_flashdata('message_type', MESSAGE_SUCCESS_TYPE);
                $this->session->set_flashdata('message', 'The route [' . $routeName . '] operation date(s) has been setup successfully !');
            } else {
                $this->session->set_flashdata('message_type', MESSAGE_ERROR_TYPE);
                $this->session->set_flashdata('message', 'Please choose the route to add or edit the operation dates !');
            }
            redirect('routes/operation_date_list', 'refresh');
            exit;
        } else {
            redirect('admin', 'refresh');
        }
    }

    public function get_route_by_multi_route_category_id()
    {
        $catIds = [];
        $oldData = 0;
        $onDemandService = false;
        if (!empty($this->input->get('catIds'))) {
            $catIds = base64_unserialize($this->input->get('catIds'));
        }

        if (!empty($this->input->get('oldData'))) {
            $oldData = (int) $this->input->get('oldData');
        }

        if (!empty($this->input->get('onDemandService'))) {
            $onDemandService = true;
        }

        $result = $this->routes_model->get_route_by_multi_route_category_id($catIds, $oldData, $onDemandService);
        jsonResponse(true, $result);
    }

    public function get_smart_cards_manifest_route()
    {
        $result = $this->routes_model->get_smart_cards_manifest_route();
        jsonResponse(true, $result);
    }

    public function get_smart_cards_manifest_report()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $routes = $data['routes'];
            $arr_route_id = [];
            foreach ($routes as $value) {
                $route_id = (int)$value;
                array_push($arr_route_id, $route_id);
            }
            $arr_route_id = substr(json_encode($arr_route_id), 1, -1);
            $results['data'] = $this->routes_model->get_smart_cards_manifest_report($arr_route_id);
            $results['type'] = "success";
            $results['status'] = "success";
        } catch (Exception $e) {
            $results['message'] = $e->getMessage();
            $results['type'] = "error";
            $results['status'] = "error";
        }
        echo json_encode($results);
        exit();
    }
    /**
     * @JIRA TSM-533
     * @author: nghi.doan
     * @description: get route name category by id
     */
    public function get_route_by_category()
    {
        $cat_id = $this->input->post('cat_id');
        $results = [];
        if (!empty($cat_id)) {
            $results = $this->routes_model->get_route_name($cat_id);
        }
        echo json_encode($results);
        exit();
    }

    /**
     * @JIRA TSM-533
     * @author: nghi.doan
     * @description: get action category by id
     */
    public function show_hidden_button_control()
    {
        $cat_id = $this->input->post('cat_id');
        if (!empty($cat_id)) {
            $data['action'] = $this->routes_model->get_allowed_category($cat_id);
            $data['route_id'] = $this->input->post('route_id');
            $this->load->view('admin/smart_card_manifest', $data);
        }
    }


    /**
     * @JIRA TSM-675
     * update_route_general_info
     * @return boolean
     */
    public function update_route_general_info()
    {
        $results = [];
        $route_general_info = $this->input->post();
        $link_redirect = base_url() . 'routes/edit_route/id/' . $route_general_info['route_id'];
        $route_id = $route_general_info['route_id'];
        $current_cate_id = $route_general_info['current_cate_id'];
        $data_update = $route_general_info['data_update'];
        if (empty($route_id)) {
            $results = ['status' => true, 'message' => 'Route id update invalid !!!'];
        } else {
            // If user login is subadmin
            if (!empty($this->account_info_id)) {
                // check case only has permission update route
                $route_list_permission = $this->routes_model->get_route();
                $route_id_list = array_column($route_list_permission, 'id');
                if (!in_array($route_id, $route_id_list)) {
                    $results = ['status' => false, 'message' => 'You don\'t have permission update route !!!'];
                    echo json_encode($results);
                    exit();
                }
            }
            $status = $this->routes_model_v2->update_general_infomation($route_id, $data_update, $current_cate_id);
            if ($status) {
                $results = ['status' => true, 'message' => 'Update route general information successfully !!!'];
            } else {
                $results = ['status' => false, 'message' => 'Update route general information fail !!!'];
            }
        }
        echo json_encode($results);
        exit();
    }
    /**
     * @JIRA TSM-676
     * show_departure_time_loop_v2 show view loop route
     * @return
     */
    function show_departure_time_loop_v2()
    {
        $route_id = $this->input->post('route_id');
        $stop_id = $this->input->post('stop_id');
        $data['number_of_route_loop'] = $this->input->post('number_of_route_loop');
        $data['arr_departure_time'] = $this->routes_model->get_departure_time_loop($route_id, $stop_id);
        $this->load->view('admin/_loop_route_form_v2', $data);
    }

    /**
     * @JIRA TSM-676
     * update_route_detail_info update detail information route
     */
    public function update_route_detail_info()
    {
        $this->routes_model_v2->update_route_detail_info();
    }
    /**
     * @JIRA TSM-677
     */
    public function update_infor_route_stop()
    {
        echo $this->routes_model->update_infor_route_stop();
    }

    /**
     * @JIRA TSM-757
     */
    public function move_passenger_to_another_stop()
    {
        $stop_id = (int) $this->input->post('stop_id');
        $route_stop_id = (int) $this->input->post('route_stop_id');
        $ids = $this->input->post('ids');

        if ($stop_id > 0 && is_array($ids) && count($ids) > 0) {
            echo $this->routes_model->move_passenger_to_another_stop($route_stop_id, $stop_id, $ids);
        } else {
            echo "0";
        }
    }
    /**
     * @JIRA TSM-770
     */
    public function update_infor_route_stops()
    {
        echo $this->routes_model->update_infor_route_stops();
    }

    //TSM-68    
    function update_route_stop()
    {
        $this->routes_model->update_route_stop();
    }

    //======================= END TSM-810 =======================

    public function edit_route_detail_info()
    {
        $array = $this->uri->uri_to_assoc();
        $id = $_GET['route_id'];


        $session_data = $this->session->userdata('logged_in');
        $data['username'] = isset($session_data['account_info_name']) ? $session_data['account_info_name'] : $session_data['name'];
        $data['type'] = $session_data['type'];
        $data['user_id'] = isset($session_data['account_info_id']) ? $session_data['account_info_id'] : $session_data['id'];
        $data['use_zone_to_zone_ticket'] = $session_data['use_zone_to_zone_ticket'];
        //  @JIRA TSM-673
        // check permission route
        if (!empty($this->account_info_id)) {
            $list_route = $this->routes_model->get_route();
            $list_route_id = array_column($list_route, 'id');
            if (!in_array($id, $list_route_id)) {
                echo "<script>alert('You don\'t have permission access page !!!')</script>";
                redirect('/routes/routes_list', 'refresh');
            }
        }
        //  END TSM-673
        // @ JIRA TSM-319
        $data['stops_detail'] = $this->stops_model->get_stops_detail();

        $data['ticket_type_detail'] = $this->routes_model->get_ticket_type();
        $data['selected_ticket_type'] = $this->routes_model->get_route_ticket_selected($id);

        // VINASOURCE modify
        $routes = $this->routes_model->get_route_info($id);
        $data['routes'] = $routes;
        $data['route_id'] = $id;

        $data['zone_ticket_ability'] = $session_data['zone_ticket_ability'];
        $data['pax_count_ability'] = $session_data['pax_count_ability'];
        $data['on_time_running_check'] = $session_data['on_time_running_check'];
        $data['accessible_bus_services_check'] = $session_data['accessible_bus_services_check'];
        $data['hyor2_check'] = $session_data['hyor2_check'];
        $data['hyor3_check'] = $session_data['hyor3_check'];
        if ($session_data['zone_ticket_ability'] == 1) {
            $data['zones'] = $this->zone_model->get_all_zone();
            // @JIRA TSM-673
            $data['zone_selected'] = 0;
            if (!empty($routes) && $routes->departing_zone_id > 0) {
                $arr_list_zones = array_column($data['zones'], 'name', 'id');
                $data['zone_selected'] = $arr_list_zones[$routes->departing_zone_id];
            }
            // END TSM-673
        }
        $departure_time = null;
        if ($session_data['on_time_running_check'] && $routes) {
            // get departure time
            $departure_time_obj = $this->routes_model->get_departure_time_loop($id, $routes->departing_stop);
            // @JIRA TSM-673
            $data['arr_departure_time'] = $departure_time_obj;
            // END TSM-673
            if ($departure_time_obj) {
                $departure_time = isset($departure_time_obj[1]) ? $departure_time_obj[1] : null;
            }
        }
        $data['departure_time'] = $departure_time;

        $data['bus_roll_check'] = $session_data['bus_roll_check'];
        $data['sort_ticket_ability'] = $session_data['sort_ticket_ability'];

        $data['late_sign_on_alert_check'] = $session_data['late_sign_on_alert_check'];
        if (!empty($data['late_sign_on_alert_check'])) {
            $data['activation_time'] = !empty($routes->activation_time) ? $routes->activation_time : '';
            $data['late_sign_on'] = !empty($routes->late_sign_on) ? $routes->late_sign_on : '';
        }
        $data['esm_passenger'] = isset($session_data['esm_passenger']) ? $session_data['esm_passenger'] : 0;
        $data['passenger_list_data'] = $this->routes_model->get_passenger_list($session_data);

        // popular stop info
        $data['stop_is_popular'] = $this->routes_model->departing_is_popular_stop($id);
        $data['on_demand_service'] = $session_data['on_demand_service'];
        // @JIRA TSM 347
        $data['gps_bundle'] = $session_data['gps_bundle'];
        // @JIRA TSM 317
        $data['passenger_manifest'] = $session_data['passenger_manifest'];
        // JIRA TSM 533
        // default is operator
        $data['action_route'] = SUB_OPERATOR_EXECUTE_CONTROL; // @JIRA @TSM-673 rename variable
        $data['permisson_stops'] = SUB_OPERATOR_EXECUTE_CONTROL; //@JIRA @TSM-676 add permission stops
        if (isset($session_data['account_info_id'])) {
            $data['action_route'] = $this->routes_model->get_allowed_category($routes->route_category_id); // @JIRA @TSM-673 rename variable
            // @JIRA TSM-676
            $data['permisson_stops'] = $data['action_route'];
            //END TSM-676
        }
        // END JIRA TSM-533
        // @JIRA TSM-673
        // @decription get more data show to view
        $data['categories'] = $categories = $this->routes_model->get_route_category();
        $arr_list_cat = array_column($data['categories'], 'name', 'id');
        if (!empty($routes)) {
            //@JIRA TSM-681
            $data['category_name_selected'] = isset($arr_list_cat[$routes->route_category_id]) ? $arr_list_cat[$routes->route_category_id] : reset($arr_list_cat);
            // fix case data in DB not map
            $route_type_selected = isset($data['passenger_list_data'][$routes->special_route_type]) ? $data['passenger_list_data'][$routes->special_route_type] : $data['passenger_list_data'][0];
            $data['route_type_selected'] = $route_type_selected;
            $arr_list_stops = array_column($data['stops_detail'], 'name', 'id');
            // @JIRA TSM-675
            $data['departing_stop_selected'] = 0;
            // if data in database false (in case value column departing_stop is 0)

            if ($routes->departing_stop > 0 && $routes->status == 1) {
                //@JIRA TSM-68 
                $find_departing = $this->routes_model->find_departing_stop($routes->id, $routes->departing_stop);
                if (count($find_departing) == 0) {
                    $find_departure_time = $this->routes_model->find_departure_time($routes->id, $routes->departing_stop);
                    $this->routes_model->insert_update_departing_stop($routes->id, $routes->departing_stop, $find_departure_time['departure_time']);
                    // $this->routes_model->insert_update_departing_stop($routes->id,$routes->departing_stop);
                } else {
                    if ($find_departing['previous_stop_id'] != 0 || $find_departing['position'] < 1) {
                        $this->routes_model->update_stop_order_previous_stop_id($find_departing['id']);
                    }
                }
                //@JIRA END TSM-68
                // if data in database false (stop_id not exist in table route_stop)
                if (isset($arr_list_stops[$routes->departing_stop])) {
                    $data['departing_stop_selected'] = $arr_list_stops[$routes->departing_stop];
                } else {
                    $stop_info = $this->stops_model->get_stop_by_id([$routes->departing_stop]);
                    $data['departing_stop_selected'] = $stop_info[0]['name'];
                }
            }
        }

        //@JIRA TSM-677
        $stops_list_in_route = [];
        if (isset($routes->number_of_section) && $routes->number_of_section > 0) {
            $arr = $this->stops_model->get_list_stop_by_section_route($id);
            $stops_list_in_route = array_merge($stops_list_in_route, $arr);
        }

        // mapping stop order stop
        $data['stops_list_in_route'] = $stops_list_in_route;
        $data['stops_list'] = $this->stops_model->get_stops_detail();
        // END TSM-673

        //@JRIA TSM-681
        $data['route_detail'] = $this->routes_model->get_route();
        $data['route_ticket_detail'] = $this->routes_model->get_route_ticket_detail($id);
        $data['route_stops_detail'] = $this->routes_model->route_stops($id);
        $data['route_all_stops_detail'] = $this->routes_model->get_all_stops_from_route();
        $data['zone_all_stops_detail'] = $this->routes_model->get_all_zone_from_route();
        $get_stops_from_route_by_id_list = $this->routes_model->get_stops_from_route_by_id($id);
        $get_stops_from_route_by_id = [];
        foreach ($get_stops_from_route_by_id_list as $value) {
            $get_stops_from_route_by_id[] = $value['stop_id'];
        }
        $data['get_stops_from_route_by_id'] = $get_stops_from_route_by_id;
        $data['route_stops_list'] = $this->routes_model->get_stops_from_route($id);
        //JIRA TSM-677
        $data['route_stop'] = 1;
        //@JIRA TSM-671 change new view route edit
        $data['clear_of_bus'] = $session_data['clear_of_bus'];
        $data['is_execute'] = SUB_OPERATOR_EXECUTE_CONTROL;
        $data['user_is_staff'] = "";

        $this->load->view('admin/routes/detail_information', $data);
    }
}
