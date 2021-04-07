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
        .theme--light.v-chip.mchip {
            background: none;
            border-radius: 0;
            font-size: .875rem;
            font-weight: 500;
            justify-content: center;
            letter-spacing: .0892857143em;
            line-height: normal;
            outline: none;
            color: black;
            padding: 0 16px;
            position: relative;
            text-align: center;
            text-decoration: none;
            text-transform: uppercase;
            padding: 23px;
        }

        .theme--light.v-chip.mchip.mselected {
            border-bottom: 1px solid black;
            color: black;
        }

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

        .v-application #color_driver_handbook a {
            color: #ffff;
        }

        .handbook .v-application--wrap {
            min-height: unset;
        }
    </style>
</head>

<body>
    <div id="driver_handbook">
        <v-app id="inspire">
            <v-container class="full-size-container mt-0 mb-0 ml-2">
                <v-alert class="text-center" :type="alert.type" v-model="alert.isShowAlert" dismissible>
                    {{alert.message}}
                </v-alert>

                <v-dialog v-model="delDialog" persistent max-width="290">
                    <v-card>
                        <v-card-title class="headline">
                            Delete handbook?
                        </v-card-title>
                        <v-card-text>Did you want to delete this handbook?</v-card-text>
                        <v-card-actions>
                            <v-spacer></v-spacer>
                            <v-btn color="blue darken-1" text @click="delDialog = false; selected_handbook = ''">
                                CANCEL
                            </v-btn>
                            <v-btn color="blue darken-1" text @click="deleteDriverHandbook">
                                CONFIRM
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-card class="v-card-handbook">
                    <div class="ma-4">
                        <v-chip @click="setHandbookType(0)" v-bind:class="handbook_type == 0 ? 'mchip mselected' : 'mchip'">Driver Handbook</v-chip>
                        <v-chip @click="setHandbookType(1)" v-bind:class="handbook_type == 1 ? 'mchip mselected' : 'mchip'">Vehicle Handbook</v-chip>
                    </div>
                    <v-card class="ma-4 v-card v-sheet theme--light" v-show="handbook_type == 0 && showDetailHandbook == false">
                        <v-card-title>
                            <v-text-field v-model="searchConfigName" append-icon="mdi-magnify" label="Search" max-width="300px" single-line hide-details></v-text-field>
                            <v-spacer></v-spacer>
                            <v-row class="mr-4">
                                <v-spacer></v-spacer>
                                <v-btn class="v-btn v-btn--contained theme--light v-size--default v-btn-handbook" @click="showViewDetailHandbook()">
                                    + Driver Handbook
                                </v-btn>
                            </v-row>
                        </v-card-title>
                        <v-data-table :headers="basicFreeTravelHeaders" :items="handbook_driver_data.driver" :sort-by="['organisation', 'name']" :search="searchConfigName" class="border-table-config mt-6">
                            <template v-slot:item.action="{ item }">
                                <v-icon @click="showViewDetailHandbook(item.id)">mdi-pencil-outline</v-icon>
                                <v-icon @click="openDeleteConfig(item.id)">mdi-delete-empty-outline</v-icon>
                            </template>
                        </v-data-table>

                    </v-card>
                    <v-card class="ma-4 v-card v-sheet theme--light" v-show="handbook_type == 1 && showDetailHandbook == false">
                        <v-card-title>
                            <v-text-field v-model="searchConfigName" append-icon="mdi-magnify" label="Search" max-width="300px" single-line hide-details></v-text-field>
                            <v-spacer></v-spacer>
                            <v-row class="mr-4">
                                <v-spacer></v-spacer>
                                <v-btn class="v-btn v-btn--contained theme--light v-size--default v-btn-handbook" id="color_driver_handbook" @click="showViewDetailHandbook()">
                                    + Vehicle Handbook
                                </v-btn>
                            </v-row>
                        </v-card-title>
                        <v-data-table :headers="basicFreeTravelHeaders" :items="handbook_driver_data.vehicle" :sort-by="['organisation', 'name']" :search="searchConfigName" class="border-table-config mt-6">
                            <template v-slot:item.action="{ item }">
                                <v-icon @click="showViewDetailHandbook(item.id)">mdi-pencil-outline</v-icon>
                                <v-icon @click="openDeleteConfig(item.id)">mdi-delete-empty-outline</v-icon>
                            </template>
                        </v-data-table>
                    </v-card>
                    <div v-show="showDetailHandbook" class="test">
                        <v-card-title><b>HANDBOOK INFORMATION</b></v-card-title>
                        <v-card class="ma-4 mpa v-card v-sheet theme--light v-card v-sheet theme--light">
                            <div class="pb-4">
                                <v-card-text>
                                    <span v-once></span>
                                    <v-form v-model="valid" ref="form">
                                        <v-text-field dense label="Handbook name" v-model="handbook_edit_data.handbook.handbook_details.name" :rules="rules" style="width: 50%; padding: 10px;"></v-text-field>
                                        <v-textarea dense label="Label" v-model="handbook_edit_data.handbook.handbook_details.description" :rules="descriptionRules" style="padding: 10px;"></v-textarea>
                                        <v-autocomplete label="Vehicles" v-show="handbook_type == 1 && showDetailHandbook == true" v-model="selected_bus" :items="buses" filled chips color="blue-grey lighten-2" item-text="bus_num" item-value="id" multiple>
                                        </v-autocomplete>
                                        <v-spacer></v-spacer>
                                        <v-btn :disabled="!valid" class="v-btn v-btn--contained theme--light v-size--default btn-handbook" @click="validateDetailHandbook(handbook_edit_data.handbook.handbook_details.name,handbook_edit_data.handbook.handbook_details.description)">SAVE</v-btn>
                                        <v-btn class="v-btn v-btn--contained theme--light v-size--default btn-handbook" @click="resetDetailHandbook">CANCEL</v-btn>
                                    </v-form>
                                </v-card-text>
                            </div>
                        </v-card>
                        <v-cad>
                            <v-card-title><b>HANDBOOK FILE</b></v-card-title>
                            <v-card class="ma-4 v-card v-sheet theme--light v-card v-sheet theme--light">
                                <v-card-title>
                                    <v-text-field v-model="searchConfigName" append-icon="mdi-magnify" label="Search" max-width="300px" single-line hide-details></v-text-field>
                                    <v-spacer></v-spacer>
                                    <v-row class="mr-3">
                                        <v-spacer></v-spacer>
                                        <v-card-actions>
                                            <v-spacer></v-spacer>
                                            <v-btn class="v-btn v-btn--contained theme--light v-size--default btn-handbook ma-0" v-show="selected_handbook_id !== undefined" @click="onFileChange()">ADD</v-btn>
                                        </v-card-actions>
                                    </v-row>
                                </v-card-title>
                                <v-data-table :headers="basicFreeTravelHeaders" :items="handbook_edit_data.handbook.documents" :sort-by="['organisation', 'name']" :search="searchConfigName" class="border-table-config mt-6">
                                    <template v-slot:item.action="{ item }">
                                        <a :href="'<?php echo base_url(); ?>index.php/handbooks/viewFile/' +item.file" target="blank">
                                            <v-icon>mdi-eye</v-icon>
                                        </a>
                                        <v-icon @click="onFileChange(item.id)">mdi-pencil-outline</v-icon>
                                        <v-icon @click="openDeleteDetailHandbook(item.id)">mdi-delete-empty-outline</v-icon>
                                    </template>
                                </v-data-table>
                            </v-card>
                            <v-dialog v-model="delDialogDeleteDetailHandbook" persistent max-width="290">
                                <v-card>
                                    <v-card-title class="headline">
                                        Delete handbook file?
                                    </v-card-title>
                                    <v-card-text>Did you want to delete this handbook file?</v-card-text>
                                    <v-card-actions>
                                        <v-spacer></v-spacer>
                                        <v-btn color="blue darken-1" text @click="delDialogDeleteDetailHandbook = false; selected_handbook = ''">
                                            CANCEL
                                        </v-btn>
                                        <v-btn color="blue darken-1" text @click="deleteDetailHandbookFile">
                                            CONFIRM
                                        </v-btn>
                                    </v-card-actions>
                                </v-card>
                            </v-dialog>

                            <v-dialog v-model="detailHandbookFile" persistent max-width="500px">
                                <v-alert class="text-center" :type="alert.type" v-model="alert.isShowAlert" dismissible>
                                    {{alert.message}}
                                </v-alert>
                                <v-card class='pa-4'>
                                    <v-card-title class="text-h5">Handbook file</v-card-title>
                                    <v-card>
                                        <div id="app" class="handbook">
                                            <v-app>
                                                <v-content>
                                                    <v-form v-model="valid" ref="form">
                                                        <v-file-input v-model="documents_data.file" accept=".pdf" label="File input" style="padding: 20px;" :rules="[v => !!v || 'Required!']"></v-file-input>
                                                        <v-text-field dense label="Description" v-model="documents_data.description" style="padding: 20px;" :rules="descriptionRules"></v-text-field>
                                                        <v-spacer></v-spacer>
                                                        <v-btn :disabled="!valid" class="v-btn v-btn--contained theme--light v-size--default btn-handbook" text @click="detailHandbookFileDocument(documents_data.file,documents_data.description)">
                                                            SAVE
                                                        </v-btn>
                                                        <v-btn class="v-btn v-btn--contained theme--light v-size--default btn-handbook" text @click="detailHandbookFile = false; this.id = ''; this.$refs.form.resetValidation();">
                                                            CANCEL
                                                        </v-btn>
                                                        <v-spacer></v-spacer>
                                                    </v-form>
                                                </v-content>
                                            </v-app>
                                        </div>
                                    </v-card>
                                </v-card>
                            </v-dialog>
                        </v-cad>
                        <v-cad>
                            <v-card-title><b>CHANGE LOG</b></v-card-title>
                            <v-card class="ma-4 v-card v-sheet theme--light v-card v-sheet theme--light">
                                <v-card-title>
                                    <v-text-field v-model="searchConfigName" append-icon="mdi-magnify" label="Search" max-width="300px" single-line hide-details></v-text-field>
                                    <v-spacer></v-spacer>
                                    <v-row class="mr-3">
                                        <v-spacer></v-spacer>
                                        <v-card-actions>
                                            <v-spacer></v-spacer>
                                            <v-btn class="v-btn v-btn--contained theme--light v-size--default btn-handbook ma-0" v-show="selected_handbook_id !== undefined" @click="openAddHandbookChangeLog">ADD</v-btn>
                                        </v-card-actions>
                                    </v-row>
                                </v-card-title>
                                <v-data-table :headers="basicChangeLogHeaders" :items="handbook_edit_data.handbook.change_logs" :sort-by="['organisation', 'name']" :search="searchConfigName" class="border-table-config mt-6">
                                    <template v-slot:item.action="{ item }">
                                        <v-icon @click="openEditChangeLog(item.id)">mdi-pencil-outline</v-icon>
                                    </template>
                                </v-data-table>
                            </v-card>
                            <v-dialog v-model="editChangeLogEdit" persistent max-width="500px">
                                <v-alert class="text-center" :type="alert.type" v-model="alert.isShowAlert" dismissible>
                                    {{alert.message}}
                                </v-alert>
                                <v-card class='pa-4'>
                                    <v-card-title class="text-h5">Edit handbook change log</v-card-title>
                                    <v-card>
                                        <div id="app" class="handbook">
                                            <v-app>
                                                <v-content>
                                                    <v-form v-model="valid" ref="form">
                                                        <v-text-field dense label="Description" v-model="description_edit_change_log.change_log" style="padding: 20px;" :rules="descriptionRules"></v-text-field>
                                                        <v-spacer></v-spacer>
                                                        <v-btn :disabled="!valid" class="v-btn v-btn--contained theme--light v-size--default btn-handbook" text @click="updateChangeLog(description_edit_change_log.change_log)">
                                                            SAVE
                                                        </v-btn>
                                                        <v-btn class="v-btn v-btn--contained theme--light v-size--default btn-handbook" text @click="editChangeLogEdit = false; this.$refs.form.resetValidation();">
                                                            CANCEL
                                                        </v-btn>
                                                    </v-form>
                                                </v-content>
                                            </v-app>
                                        </div>
                                    </v-card>
                                </v-card>
                            </v-dialog>

                            <v-dialog v-model="addChangeLogEdit" persistent max-width="500px">
                                <v-alert class="text-center" :type="alert.type" v-model="alert.isShowAlert" dismissible>
                                    {{alert.message}}
                                </v-alert>
                                <v-card class='pa-4'>
                                    <v-card-title class="text-h5">Handbook change log</v-card-title>
                                    <v-card>
                                        <div id="app" class="handbook">
                                            <v-app>
                                                <v-content>
                                                    <v-form v-model="valid" ref="form">
                                                        <v-text-field dense label="Description" v-model="description_add_change_log" style="padding: 20px;" :rules="descriptionRules"></v-text-field>
                                                        <v-spacer></v-spacer>
                                                        <v-btn :disabled="!valid" class="v-btn v-btn--contained theme--light v-size--default btn-handbook" text @click="addChangeLog(description_add_change_log)">
                                                            SAVE
                                                        </v-btn>
                                                        <v-btn class="v-btn v-btn--contained theme--light v-size--default btn-handbook" text @click="addChangeLogEdit = false; this.$refs.form.resetValidation();">
                                                            CANCEL
                                                        </v-btn>
                                                    </v-form>
                                                </v-content>
                                            </v-app>
                                        </div>
                                    </v-card>
                                </v-card>
                            </v-dialog>
                        </v-cad>
                    </div>
                </v-card>
            </v-container>
        </v-app>
    </div>
</body>
<script>
    $(document).ready(function() {
        var configsData = [];

        const STATE_CONST = {
            ADD: 'ADD',
            EDIT: 'EDIT',
            DEL: 'DEL',
            UNCHANGED: 'UNCHANGED',
        }

        var vueApp = new Vue({
            el: '#driver_handbook',
            vuetify: new Vuetify(),
            icons: {
                iconfont: 'mdi', // default - only for display purposes
            },
            data() {
                return {
                    handbook_type: 0,
                    deleteHandbookAdd: false,
                    editChangeLogAdd: false,
                    change_log: '',
                    selected_change_log: '',
                    editChangeLogEdit: false,
                    description_add_change_log: '',
                    description_edit_change_log: '',
                    addChangeLogEdit: false,
                    documents_data: {
                        file: '',
                        description: '',
                    },
                    delDialogDeleteDetailHandbook: false,
                    handbook_edit_data: {
                        handbook: {
                            change_logs: [{}],
                            documents: [{
                                id: '',
                                name: '',
                                file: '',
                                handbook_id: '',
                                description: '',
                                created_at: '',
                                updated_at: '',
                            }],
                            handbook_details: [{
                                created_at: '',
                                description: '',
                                id: '',
                                name: '',
                                type: '',
                                updated_at: '',
                            }],
                        }
                    },
                    buses: [],
                    selected_bus: [],
                    description_changelog: '',
                    changelogDialog: false,
                    basicChangeLogHeaders: [{
                            text: 'LOG UPDATE',
                            divider: true,
                            align: 'center',
                            width: 180,
                            value: 'updated_at'
                        },
                        {
                            text: 'DESCRIPTION',
                            align: 'start',
                            divider: true,
                            value: 'change_log',
                        },
                        {
                            text: 'ACTION',
                            divider: true,
                            align: 'center',
                            value: 'action',
                            width: 150
                        },
                    ],
                    addDialogHandbookFileEdit: false,
                    delDialogHandbookFile: false,
                    file: '',
                    basicHandbookFileHeaders: [{
                            text: 'HANDBOOK FILE',
                            align: 'left',
                            divider: true,
                            value: 'name',
                        },
                        {
                            text: 'DESCRIPTION',
                            align: 'start',
                            divider: true,
                            value: 'description',
                        },
                        {
                            text: 'LATEST UPDATE',
                            divider: true,
                            align: 'center',
                            width: 180,
                            value: 'updated_at'
                        },
                        {
                            text: 'ACTION',
                            divider: true,
                            align: 'center',
                            value: 'action',
                            width: 150
                        },
                    ],
                    tab: null,
                    alert: {
                        type: '',
                        message: '',
                        isShowAlert: false
                    },
                    valid: true,
                    rules: [
                        value => !!value || 'Required.',
                        value => (value && value.length >= 3) || 'Min 3 characters',
                    ],
                    descriptionRules: [
                        value => !!value || 'Required.',
                        value => (value && value.length >= 3) || 'Min 3 characters',
                    ],
                    handbook: '',
                    handbook_id: '',
                    description: '',
                    description_file: '',
                    handbook_changelog_data: [],
                    handbook_file_data: [],
                    handbook_file: [],
                    handbook_name: '',
                    showDetailHandbook: false,
                    detailHandbookFile: false,
                    showHandbook: true,
                    handbook_driver_data: [],
                    rootData: [],
                    search: '',
                    selected_handbook: '',
                    selected_handbook_id: '',
                    searchConfigName: '',
                    organisations: [],
                    deleteResolve: null,
                    delDialog: false,
                    freeTravelConfigsData: [],
                    isShowOrganisation: false,
                    basicFreeTravelHeaders: [{
                            text: 'HANDBOOK NAME',
                            align: 'left',
                            divider: true,
                            value: 'name',
                        },
                        {
                            text: 'DESCRIPTION',
                            align: 'start',
                            divider: true,
                            value: 'description',
                        },
                        {
                            text: 'LATEST UPDATE',
                            divider: true,
                            align: 'center',
                            width: 180,
                            value: 'updated_at'
                        },
                        {
                            text: 'ACTION',
                            divider: true,
                            align: 'center',
                            value: 'action',
                            width: 150
                        },
                    ],
                    basicHeaders: [{
                            text: 'Valid from',
                            divider: true,
                            sortable: false,
                            align: 'center',
                            value: 'valid_from'
                        },
                        {
                            text: 'Valid to',
                            divider: true,
                            sortable: false,
                            align: 'center',
                            value: 'valid_to'
                        },
                        {
                            text: 'All days',
                            divider: true,
                            sortable: false,
                            align: 'center',
                            value: 'all_days'
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
            mounted() {
                this.getHandbookDriver();
                this.getBus();
            },
            created() {
                this.rootData = [...configsData];
            },
            computed: {
                transformData: function() {
                    return this.createDataToMapWithLayout(this.rootData);
                },
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
                setHandbookType: function(param) {
                    this.handbook_type = param;
                },
                updateChangeLog: function(params) {
                    this.change_log = params;
                    try {
                        const _this = this;
                        let apiUrl = "index.php/handbooks/update_log";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify({
                                change_log: _this.change_log,
                                id: _this.selected_change_log,
                                handbook_id: _this.selected_handbook_id
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
                                        _this.timeout();
                                    } else {
                                        data.logs.forEach(element => {
                                            element['updated_at'] = moment(element['updated_at']).format('DD/MM/YYYY');
                                        });
                                        _this.handbook_edit_data.handbook.change_logs = data.logs;
                                        _this.$refs.form.resetValidation();
                                        _this.editChangeLogEdit = false;
                                        _this.alert = {
                                            type: 'success',
                                            message: 'Change log update successfully',
                                            isShowAlert: true,
                                        };
                                        _this.description_add_change_log = '',
                                            _this.timeout();
                                    }
                                } else {
                                    _this.alert = {
                                        type: 'error',
                                        message: 'Failed to update change log'
                                    }
                                    _this.timeout();
                                }
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                        this.timeout();
                    }
                    this.delDialog = false;
                },
                openEditChangeLog: function(params) {
                    this.selected_change_log = params
                    try {
                        const _this = this;
                        let apiUrl = "index.php/handbooks/get_changelog_detail";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify({
                                id: this.selected_change_log
                            }),
                            contentType: 'application/json',
                            success: function(response) {
                                var data = JSON.parse(response);
                                if (data) {
                                    if (data.status === 'error' || data.log[0].length === 0) {
                                        _this.alert = {
                                            type: 'error',
                                            message: data.message,
                                            isShowAlert: true
                                        };
                                        _this.timeout();
                                    } else {
                                        _this.description_edit_change_log = data.log[0];
                                        _this.editChangeLogEdit = true;
                                    }
                                } else {
                                    _this.alert = {
                                        type: 'error',
                                        message: 'Failed to add handbook'
                                    }
                                    _this.timeout();
                                }
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                        this.timeout();
                    }
                },
                timeout() {
                    setTimeout(() => {
                        this.alert = {
                            isShowAlert: false
                        };
                    }, 1000)
                },
                addChangeLog: function(change_log) {
                    this.change_log = change_log;
                    try {
                        const _this = this;
                        let apiUrl = "index.php/handbooks/create_log";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify({
                                change_log: _this.change_log,
                                handbook_id: _this.selected_handbook_id
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
                                        _this.timeout();
                                    } else {
                                        data.logs.forEach(element => {
                                            element['updated_at'] = moment(element['updated_at']).format('DD/MM/YYYY');
                                        });
                                        _this.handbook_edit_data.handbook.change_logs = data.logs;
                                        _this.addChangeLogEdit = false;
                                        _this.alert = {
                                            type: 'success',
                                            message: 'Change log add successfully',
                                            isShowAlert: true,
                                        };
                                        _this.timeout();
                                    }
                                } else {
                                    _this.alert = {
                                        type: 'error',
                                        message: 'Failed to add change log'
                                    }
                                    _this.timeout();
                                }
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                        this.timeout();
                    }
                    this.delDialog = false;
                },
                openAddHandbookChangeLog: function() {
                    this.$refs.form.resetValidation();
                    this.addChangeLogEdit = true;
                },
                detailHandbookFileDocument: function(file, description_file) {
                    this.file = file;
                    this.description_file = description_file;
                    if (this.id !== undefined) {
                        // edit handbook file
                        try {
                            const _this = this;
                            var fd = new FormData();
                            fd.append('file', _this.file);
                            fd.append('id', _this.id);
                            fd.append('handbook_id', _this.selected_handbook_id);
                            fd.append('description', _this.description_file);
                            let apiUrl = "index.php/handbooks/update_document";

                            $.ajax({
                                type: "POST",
                                url: base_url + apiUrl,
                                data: fd,
                                processData: false,
                                contentType: false,
                                success: function(response) {
                                    var data = JSON.parse(response);
                                    if (data) {
                                        if (data.status === 'error') {
                                            _this.alert = {
                                                type: 'error',
                                                message: response.message,
                                                isShowAlert: true
                                            };
                                        } else {
                                            _this.handbook_edit_data.handbook.documents = data.handbook;
                                            _this.detailHandbookFile = false;
                                            _this.file = null;
                                            _this.description_file = '';
                                            _this.id = '';
                                            _this.alert = {
                                                type: 'success',
                                                message: 'Handbook edit successfully',
                                                isShowAlert: true,
                                            };
                                            _this.timeout();
                                        }
                                    } else {
                                        _this.alert = {
                                            type: 'error',
                                            message: 'Failed to edit handbook'
                                        }
                                        _this.timeout();
                                    }
                                }
                            });
                        } catch (e) {
                            this.alert = {
                                type: 'error',
                                message: e.message,
                                isShowAlert: true
                            }
                            this.timeout();
                        }
                    } else {
                        // add handbook file 
                        try {
                            const _this = this;
                            var fd = new FormData();
                            fd.append('file', _this.file);
                            fd.append('handbook_id', _this.selected_handbook_id);
                            fd.append('description', _this.description_file);
                            let apiUrl = "index.php/handbooks/create_handbook_document";
                            $.ajax({
                                type: "POST",
                                url: base_url + apiUrl,
                                data: fd,
                                processData: false,
                                contentType: false,
                                success: function(response) {
                                    var data = JSON.parse(response);
                                    if (data) {
                                        if (data.status === 'error') {
                                            _this.alert = {
                                                type: 'error',
                                                message: response.message,
                                                isShowAlert: true
                                            };
                                            _this.timeout();
                                        } else {
                                            data.documents.forEach(element => {
                                                element['updated_at'] = moment(element['updated_at']).format('DD/MM/YYYY');
                                            });
                                            _this.handbook_edit_data.handbook.documents = data.documents;
                                            _this.detailHandbookFile = false;
                                            _this.file = null;
                                            _this.description_file = '';
                                        }
                                    } else {
                                        _this.alert = {
                                            type: 'error',
                                            message: 'Failed to add handbook file'
                                        }
                                        _this.timeout();
                                    }
                                }
                            });
                        } catch (e) {
                            this.alert = {
                                type: 'error',
                                message: e.message,
                                isShowAlert: true
                            }
                            this.timeout();
                        }
                    }
                },
                deleteDetailHandbookFile: function() {
                    try {
                        const _this = this;
                        let apiUrl = "index.php/handbooks/delete_document";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify({
                                id: _this.selected_handbook,
                                handbook_id: _this.selected_handbook_id
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
                                        _this.timeout();
                                    } else {
                                        _this.handbook_edit_data.handbook.documents = data.handbook;
                                        _this.delDialogDeleteDetailHandbook = false;
                                        _this.selected_handbook = '';
                                        _this.alert = {
                                            type: 'success',
                                            message: 'Handbook file removed successfully',
                                            isShowAlert: true,
                                        };
                                        _this.timeout();
                                    }
                                } else {
                                    _this.alert = {
                                        type: 'error',
                                        message: 'Failed to delete handbook file'
                                    }
                                    _this.timeout();
                                }
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                        this.timeout();
                    }
                    this.delDialog = false;
                },
                openDeleteDetailHandbook: function(params) {
                    this.delDialogDeleteDetailHandbook = true;
                    this.selected_handbook = params;
                },
                validateDetailHandbook(handbook_name, description) {
                    this.handbook_name = handbook_name;
                    this.description = description;
                    if (this.selected_handbook_id !== undefined) {
                        this.update_handbook();
                    } else {
                        this.create_handbook();
                        this.handbook_name = '';
                        this.description = '';
                    };
                },
                update_handbook: function() {
                    try {
                        const _this = this;
                        let apiUrl = "index.php/handbooks/update_handbook";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify({
                                id: _this.selected_handbook_id,
                                name: _this.handbook_name,
                                description: _this.description,
                                buses: JSON.stringify(_this.selected_bus),
                                type: _this.handbook_type == 0 ? 'driver' : 'vehicle',
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
                                        _this.timeout();
                                    } else {
                                        _this.alert = {
                                            type: 'success',
                                            message: 'Handbook update successfully',
                                            isShowAlert: true
                                        };
                                        _this.timeout();
                                    }
                                } else {
                                    _this.alert = {
                                        type: 'error',
                                        message: 'Failed to update handbook'
                                    }
                                    _this.timeout();
                                }
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                        this.timeout();
                    }
                },
                showViewDetailHandbook: function(params) {
                    this.showHandbook = false;
                    this.showDetailHandbook = true;
                    this.selected_handbook_id = params;
                    if (this.selected_handbook_id !== undefined) {

                        // edit mode.
                        try {
                            const _this = this;
                            let apiUrl = "index.php/handbooks/get_handbook_details";
                            $.ajax({
                                type: "POST",
                                url: base_url + apiUrl,
                                data: JSON.stringify({
                                    handbook_id: _this.selected_handbook_id
                                }),
                                contentType: 'application/json',
                                success: function(response) {
                                    var data = JSON.parse(response);
                                    data.handbook.change_logs.forEach(element => {
                                        element['updated_at'] = moment(element['updated_at']).format('DD/MM/YYYY');
                                    });
                                    data.handbook.documents.forEach(element => {
                                        element['updated_at'] = moment(element['updated_at']).format('DD/MM/YYYY');
                                    });
                                    _this.handbook_edit_data = data;
                                    _this.selected_bus = JSON.parse(data.handbook.handbook_details.buses);
                                }
                            });
                        } catch (e) {
                            this.alert = {
                                type: 'error',
                                message: e.message,
                                isShowAlert: true
                            }
                            this.timeout();
                        }
                    } else {
                        // add mode.
                        this.handbook_edit_data = {
                            handbook: {
                                change_logs: [],
                                documents: [{
                                    id: '',
                                    name: '',
                                    file: '',
                                    handbook_id: '',
                                    description: '',
                                    created_at: '',
                                    updated_at: '',
                                }],
                                handbook_details: [{
                                    created_at: '',
                                    description: '',
                                    id: '',
                                    name: '',
                                    type: '',
                                    updated_at: '',
                                }],
                            }
                        };
                        this.$refs.form.resetValidation();
                    }
                },
                create_handbook: function() {
                    try {
                        const _this = this;
                        let apiUrl = "index.php/handbooks/create_handbook";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify({
                                name: _this.handbook_name,
                                description: _this.description,
                                buses: JSON.stringify(_this.selected_bus),
                                type: _this.handbook_type == 0 ? 'driver' : 'vehicle'
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
                                        _this.timeout();
                                    } else {
                                        _this.selected_handbook_id = data.id;
                                        _this.handbook_name = '';
                                        _this.description = '';
                                        _this.alert = {
                                            type: 'success',
                                            message: 'Handbook add successfully',
                                            isShowAlert: true
                                        };
                                        _this.timeout();
                                    }
                                } else {
                                    _this.alert = {
                                        type: 'error',
                                        message: 'Failed to add handbook'
                                    }
                                    _this.timeout();
                                }
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                        this.timeout();
                    }
                },
                validate(handbook_name, description) {
                    if (this.valid == false) {
                        this.alert = {
                            type: 'error',
                            message: 'Failed to validate'
                        };
                        this.timeout();
                    } else {
                        this.handbook_name = handbook_name;
                        this.description = description;
                        this.create_handbook();
                    };

                },
                getBus() {
                    try {
                        const _this = this;
                        let apiUrl = "index.php/buses/get_all_buses";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify({}),
                            contentType: 'application/json',
                            success: function(response) {
                                var data = JSON.parse(response);
                                _this.buses = data.bus_detail;
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                        this.timeout();
                    }
                },
                onChangeLog() {
                    this.changelogDialog = true;
                },
                closeDialog: function() {
                    this.timeout();
                    this.delDialogHandbookFile = false;
                    this.description_file = '';
                    this.file = null;
                },
                onFileChange: function(params) {
                    this.detailHandbookFile = true;
                    this.id = params;
                    if (this.id !== undefined) {
                        // edit file.
                        try {
                            const _this = this;
                            let apiUrl = "index.php/handbooks/get_file_details";
                            $.ajax({
                                type: "POST",
                                url: base_url + apiUrl,
                                data: JSON.stringify({
                                    file_id: _this.id
                                }),
                                contentType: 'application/json',
                                success: function(response) {
                                    var data = JSON.parse(response);
                                    if (data.status === 'error' || data.handbook.length == 0) {
                                        _this.alert = {
                                            type: 'error',
                                            message: data.message,
                                            isShowAlert: true
                                        };
                                        _this.timeout();
                                    } else {
                                        _this.documents_data = data.handbook[0];
                                    }
                                }
                            });
                        } catch (e) {
                            this.alert = {
                                type: 'error',
                                message: e.message,
                                isShowAlert: true
                            }
                            this.timeout();
                        }
                    } else {
                        // add mode.
                        this.documents_data = {
                            file: '',
                            description: '',
                        };
                        this.$refs.form.resetValidation();
                    }
                },
                resetValidation() {
                    this.showHandbook = true;
                    this.showAddHandbook = false;
                    this.handbook_name = '';
                    this.description = '';
                },
                resetDetailHandbook() {
                    this.showHandbook = true;
                    this.showDetailHandbook = false;
                    this.$refs.form.resetValidation();
                    this.getHandbookDriver();
                    this.handbook_name = '';
                    this.description = '';
                },
                openDeleteConfig: function(params) {
                    this.delDialog = true;
                    this.selected_handbook = params;
                },
                deleteDriverHandbook: function() {
                    try {
                        const _this = this;
                        let apiUrl = "index.php/handbooks/delete_handbook";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify({
                                handbook_id: this.selected_handbook
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
                                        _this.timeout();
                                    } else {
                                        _this.handbook_driver_data.driver = _.filter(_this.handbook_driver_data.driver, function(currentObject) {
                                            return currentObject.id !== _this.selected_handbook;
                                        });
                                        _this.delDialog = false;
                                        _this.alert = {
                                            type: 'success',
                                            message: 'Handbook removed successfully',
                                            isShowAlert: true
                                        };
                                        _this.timeout();
                                    }
                                } else {
                                    _this.alert = {
                                        type: 'error',
                                        message: 'Failed to delete handbook'
                                    }
                                    _this.timeout();
                                }
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                        this.timeout();
                    }
                    this.delDialog = false;
                },
                getHandbookDriver: function() {
                    try {
                        const _this = this;
                        let apiUrl = "index.php/handbooks/get_handbook_driver";
                        $.ajax({
                            type: "POST",
                            url: base_url + apiUrl,
                            data: JSON.stringify({}),
                            contentType: 'application/json',
                            success: function(response) {
                                var data = JSON.parse(response);
                                if (data) {
                                    data.driver.forEach(element => {
                                        element['updated_at'] = moment(element['updated_at']).format('DD/MM/YYYY');
                                    });
                                    data.vehicle.forEach(element => {
                                        element['updated_at'] = moment(element['updated_at']).format('DD/MM/YYYY');
                                    });
                                    _this.handbook_driver_data = data
                                } else {
                                    _this.alert = {
                                        type: 'error',
                                        message: 'Failed to get handbook'
                                    }
                                    _this.timeout();
                                }
                            }
                        });
                    } catch (e) {
                        this.alert = {
                            type: 'error',
                            message: e.message,
                            isShowAlert: true
                        }
                        this.timeout();
                    }
                },
                convertBinaryCodeToBool: function(value) {
                    return value === '1' ? true : false
                },
                createDataToMapWithLayout: function(rootData) {
                    const newData = [];
                    rootData.forEach(da => {
                        const object = {
                            id: da.id,
                            name: da.name,
                            organisation_id: da.organisation_id
                        };
                        newData.push(object);
                    });
                    return newData;
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
                        let apiUrl = "index.php/smart_cards/save_smartcard_travel_timeframe_config";
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
                showDelConfirm: function(params, deleteSuccessCallBack, type) {
                    this.delDialog = true;
                    this.deleteResolve = {
                        params,
                        deleteSuccessCallBack,
                        type
                    };
                },
                closeAlert: function() {
                    this.alert.isShowAlert = false
                },
            }

        })
    })
</script>

<?php include 'footer.php'; ?>