<?php $base =  base_url(); ?>
<?php include 'header.php'; ?>

<head>

    <script src="<?php echo base_url() ?>assests/lib/vue/vue.min.js"></script>
    <script src="<?php echo base_url() ?>assests/lib/vue/vuetify.min.js"></script>
    <script src="<?php echo base_url() ?>assests/lib/lodash/lodash.min.js"></script>


    <script src="<?php echo base_url() ?>assests/lib/vue/validators.min.js"></script>
    <script src="<?php echo base_url() ?>assests/lib/vue/vuelidate.min.js"></script>
    <script src="<?php echo base_url(); ?>assests/library/moment/moment.min.js"></script>

    <!-- Add vuetify -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@5.x/css/materialdesignicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.css" rel="stylesheet">

    <script src="<?php echo $base ?>assests/js/gen_validatorv4.js" type="text/javascript"></script>
    <style>
        html {
            font-size: 16px;
        }
    </style>
    <!-- component template -->
    <style scoped>
        .theme--light.v-application {
            background: none;
        }

        .basil {
            background-color: #FFFBE6 !important;
        }

        .basil--text {
            color: #356859 !important;
        }

        .border-table-config {
            border: 0.5px solid #e0e0e0
        }
    </style>



    <script type="text/x-template" id="smartcard-free-travel-config-table">
        <div class="pb-4"> 
            <v-alert class="text-center" :type="alert.type" v-model="alert.isShowAlert" dismissible>
                {{alert.message}}
            </v-alert>
            <v-card class="ma-4">
            </v-dialog>
        </div>
    </script>
</head>

<body>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td class="page_title" style="vertical-align:top">
                <div class="right_nav_table">
                    <?php include 'route_left_menu.php'; ?>
                </div>
            </td>
            <td>
                <div id="smartcard_config">
                    <v-app id="inspire">
                        <v-container class="full-size-container mt-0 mb-0 ml-2">
                            <v-card>
                                <v-card-title class="text-center justify-center py-6">
                                    <span class="font-weight-bold display-1 black--text">PENDING ROUTES</span>
                                </v-card-title>
                                <v-card>
                                    <v-data-table :headers="list_data_headers" :footer-props="{ 'items-per-page-options': [10, 20, 30, 40, 50] }" :items-per-page="20" :items="list_data" class="elevation-1">
                                        <template v-slot:item.action="{ item }">
                                            <a v-bind:href="'edit_pending_routes/' + 'id/' + item.id">
                                                <v-icon>mdi-pencil-outline</v-icon>
                                            </a>
                                            <!-- <v-icon @click="openEdit(item.id)">mdi-pencil-outline</v-icon> -->
                                            <v-icon @click="showDelConfirm(item.id)">mdi-delete-empty-outline</v-icon>
                                        </template>
                                    </v-data-table>

                                    <v-dialog v-model="delDialog" persistent max-width="290">
                                        <v-card>
                                            <v-card-title class="headline">
                                                Confirmation
                                            </v-card-title>
                                            <v-card-text>Are you sure to delete this item?</v-card-text>
                                            <v-card-actions>
                                                <v-spacer></v-spacer>
                                                <v-btn color="blue darken-1" text @click="delDialog = false; selected_route = ''">
                                                    No
                                                </v-btn>
                                                <v-btn color="blue darken-1" text @click="deletePendingRoute">
                                                    Yes
                                                </v-btn>
                                            </v-card-actions>
                                        </v-card>
                                    </v-dialog>
                                </v-card>
                            </v-card>
                        </v-container>
                    </v-app>
                </div>
            </td>
        </tr>
    </table>
</body>
<script>
    $(document).ready(function() {
        var configsData = [];
        var configFreeTravel = [];
        var organisations = [];
        var is_show_organisation = [];

        const STATE_CONST = {
            ADD: 'ADD',
            EDIT: 'EDIT',
            DEL: 'DEL',
            UNCHANGED: 'UNCHANGED',
        }

        var vueApp = new Vue({
            el: '#smartcard_config',
            vuetify: new Vuetify(),
            icons: {
                iconfont: 'mdi', // default - only for display purposes
            },
            mounted() {
                this.loadData();
            },
            data() {
                return {
                    selected_route: '',
                    list_data_headers: [{
                            text: 'DEPARTURE',
                            value: 'departure',
                        },
                        {
                            text: 'DESTINATION',
                            value: 'destination'
                        },
                        {
                            text: 'CREATED DATE',
                            value: 'created_date'
                        },
                        {
                            text: 'DRIVER',
                            value: 'driver',
                            width: 120
                        },
                        {
                            text: 'ACTIONS',
                            value: 'action',
                            width: 120
                        },
                    ],
                    list_data: [],
                    tab: null,
                    alert: {
                        type: '',
                        message: '',
                        isShowAlert: false
                    },
                    items: [
                        'Free Travel', 'Eligible Travel Days',
                    ],
                    // rootData: [],
                    organisations: [],
                    deleteResolve: null,
                    delDialog: false,
                    freeTravelConfigsData: [],
                    isShowOrganisation: false,
                    basicFreeTravelHeaders: [{
                            text: 'Departure',
                            align: 'left',
                            divider: true,
                            value: 'organisation',
                        },
                        {
                            text: 'Destination',
                            align: 'start',
                            divider: true,
                            value: 'name',
                        },
                        {
                            text: 'Create date',
                            divider: true,
                            align: 'center',
                            width: 120,
                            value: 'valid_from'
                        },
                        {
                            text: 'Driver',
                            divider: true,
                            width: 120,
                            align: 'center',
                            value: 'valid_to'
                        },
                        {
                            text: 'Action',
                            divider: true,
                            align: 'center',
                            value: 'action',
                            width: 100
                        },
                    ],
                    basicHeaders: [{
                            text: 'Valid from',
                            divider: true,
                            sortable: false,
                            align: 'center',
                            value: 'valid_from',
                        },
                        {
                            text: 'Valid to',
                            divider: true,
                            sortable: false,
                            align: 'center',
                            value: 'valid_to',
                        },
                        {
                            text: 'All days',
                            divider: true,
                            sortable: false,
                            align: 'center',
                            value: 'all_days',
                        },
                        {
                            text: 'Mon',
                            value: 'monday',
                            sortable: false,
                            align: 'center',
                        },
                        {
                            text: 'Tue',
                            value: 'tuesday',
                            sortable: false,
                            align: 'center',
                        },
                        {
                            text: 'Wed',
                            sortable: false,
                            value: 'wed',
                            align: 'center',
                        },
                        {
                            text: 'Thu',
                            value: 'thurs',
                            sortable: false,
                            align: 'center',
                        },
                        {
                            text: 'Fri',
                            value: 'friday',
                            sortable: false,
                            align: 'center',
                        },
                        {
                            text: 'Sat',
                            value: 'sat',
                            sortable: false,
                            align: 'center',
                        },
                        {
                            text: 'Sun',
                            value: 'sun',
                            align: 'center',
                            sortable: false,
                            divider: true,
                        },
                        {
                            text: 'Delete',
                            divider: true,
                            sortable: false,
                            align: 'center',
                            value: 'del'
                        },
                    ]

                }
            },
            created() {
                //this.rootData = [...configsData];
                this.freeTravelConfigsData = [...configFreeTravel];
                this.organisations = [...organisations];
                this.isShowOrganisation = is_show_organisation;
            },
            computed: {
                // transformData: function() {
                //     return this.createDataToMapWithLayout(this.rootData);
                // },
                transFreeConfigData: function() {
                    const newData = [];
                    this.freeTravelConfigsData.forEach(c => {
                        const object = {
                            id: c.id,
                            valid_from: moment(c.valid_from, 'HH:mm:ss').format('HH:mm'),
                            valid_to: moment(c.valid_to, 'HH:mm:ss').format('HH:mm'),
                            name: c.name,
                            charge_once: c.charge_once === '1' ? true : false,
                            duration: c.duration,
                            turns: c.number_of_free_turns,
                            status: STATE_CONST.UNCHANGED,
                            organisation_id: c.organisation_id,
                            exclude_public_holiday: this.convertBinaryCodeToBool(c.exclude_public_holiday),
                            exclude_school_holiday: this.convertBinaryCodeToBool(c.exclude_school_holiday),
                            exclude_weekend: this.convertBinaryCodeToBool(c.exclude_weekend)
                        };
                        newData.push(object)
                    });
                    return newData
                }
            },
            methods: {
                loadData: function() {
                    try {
                        const _this = this;
                        let apiUrl = "index.php/routes/get_pending_route";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: {},
                            success: function(response) {
                                _this.list_data = JSON.parse(response);
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                    }
                },
                getOrganisationInfor: function(org_id) {
                    let res = null;
                    if (org_id && this.organisations.length > 0) {
                        res = this.organisations.find(org => org.id == org_id)
                    }
                    return res
                },
                convertBinaryCodeToBool: function(value) {
                    return value === '1' ? true : false
                },

                openAlert: function(type, message) {
                    this.alert = {
                        type,
                        message,
                        isShowAlert: true
                    };
                },
                submitData: function(params, successCallBack) {
                    try {
                        const _this = this;
                        let apiUrl = "index.php/routes/save_smartcard_travel_timeframe_config";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify(params),
                            contentType: 'application/json',
                            success: function(response) {
                                _this.alert = {
                                    type: 'success',
                                    message: 'Configuration saved successfully',
                                    isShowAlert: true
                                };
                                const submitedData = JSON.parse(response);
                                successCallBack(submitedData);
                            },
                            error: (e) => {
                                _this.alert = {
                                    type: 'error',
                                    message: 'Failed to save Configuration.' + e.message
                                }
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                    }
                },
                deleteConfirmClick: function() {
                    if (this.deleteResolve && this.deleteResolve.type === 'del_free_travel') {
                        const {
                            config
                        } = this.deleteResolve.params;
                        if (config && config.id) {
                            this.deleteFreeTravel({
                                config_id: config.id
                            });
                        } else {
                            this.deleteResolve.deleteSuccessCallBack(config)
                        }
                    } else {
                        this.deleteConfig(this.deleteResolve.params, this.deleteResolve.deleteSuccessCallBack);
                    }
                    this.delDialog = false;
                },
                showDelConfirm: function(params) {
                    this.delDialog = true;
                    this.selected_route = params;
                },
                deletePendingRoute: function() {
                    try {
                        const _this = this;
                        let apiUrl = "index.php/routes/delete_pending_route";
                        route_id = this.selected_route
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify({
                                route_id: this.selected_route
                            }),
                            contentType: 'application/json',
                            success: function(response) {
                                var data = JSON.parse(response);
                                if (data) {
                                    if (data.status === 'error') {
                                        _this.alert = {
                                            type: 'error',
                                            message: data.message,
                                            isShowAlert: true
                                        };
                                    } else {
                                        _this.list_data = _.filter(_this.list_data, function(currentObject) {
                                            return currentObject.id !== route_id;
                                        });
                                        _this.delDialog = false;
                                        _this.alert = {
                                            type: 'success',
                                            message: 'Configuration removed successfully',
                                            isShowAlert: true
                                        };
                                    }
                                } else {
                                    _this.alert = {
                                        type: 'error',
                                        message: 'Failed to delete configuration'
                                    }
                                }
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                    }
                },
                closeAlert: function() {
                    this.alert.isShowAlert = false
                },
                deleteFreeTravel: function(params) {
                    try {
                        const _this = this;
                        let apiUrl = "index.php/routes/delete_smartcard_free_travel_config";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify(params),
                            contentType: 'application/json',
                            success: function(response) {
                                var data = JSON.parse(response);
                                if (data) {
                                    if (data.type === 'error') {
                                        _this.alert = {
                                            type: 'error',
                                            message: data.message,
                                            isShowAlert: true
                                        };
                                        return;
                                    } else {
                                        _this.alert = {
                                            type: 'success',
                                            message: 'Configuration deleted successfully',
                                            isShowAlert: true
                                        };
                                        if (_this.deleteResolve) {
                                            _this.deleteResolve.deleteSuccessCallBack(_this.deleteResolve.params)
                                        }
                                        _this.deleteResolve = null;
                                    }

                                } else {
                                    _this.alert = {
                                        type: 'error',
                                        message: 'Failed to delete configuration'
                                    }
                                }
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                    }
                },
                submitFreeData: function(params, successCallBack) {
                    try {
                        const _this = this;
                        let apiUrl = "index.php/routes/save_smartcard_free_travel_config";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify(params),
                            contentType: 'application/json',
                            success: function(response) {
                                var data = JSON.parse(response);
                                if (data) {
                                    if (successCallBack) {
                                        successCallBack(data);
                                    }
                                    if (_this.deleteResolve) {
                                        _this.deleteResolve.deleteSuccessCallBack(params.config)
                                    }
                                    _this.deleteResolve = null;

                                    _this.alert = {
                                        type: 'success',
                                        message: 'Configuration saved successfully',
                                        isShowAlert: true
                                    };
                                } else {
                                    _this.alert = {
                                        type: 'error',
                                        message: 'Failed to save configuration'
                                    }
                                }
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                    }
                }
            }

        })
    })
</script>

<?php include 'footer.php'; ?>