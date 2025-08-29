//for security reasons, this whole script is a function
function init() {

    //Global Variables
    let editMode = false;

    let configOptions;

    let campusOptions;

    let termData;

    let oldVal;

    let newVal;

    let sortMode = 0;

    let cellSelected = false;

    //required values for editing table rows
    //currently unused in this table
    let requiredValuesAirflow = ["auto_mode"];

    //loads data from .json files and sends it to the correct function
    //used for loading campuses and buildings for the dropdowns
    async function loadJsonXHR(url, reason) {

        try {
            let response = await fetch(url);

            if (!response.ok) {
                if (response.status == 404) console.log("404 Error!");
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            let json = await response.json();

            switch (reason) {
                case 'init':
                    let configResponse = await fetch("/json/configVars/1.json");

                    configOptions = await configResponse.json();

                    let campusResponse = await fetch("/json/campuses/all.json");

                    campusOptions = await campusResponse.json();

                    let termResponse = await fetch("/json/term/all.json");

                    termData = await termResponse.json();

                    Object.freeze(configOptions.response.docs.current_user);
                    Object.freeze(configOptions.response.docs);

                    loadCampusDropDown(campusOptions);

                    loadTermDropDown(termData);

                    loadTable();
                    break;
                case 'buildings':
                    updateBuildingDropDown(json);
                    break;
            }

        } catch (e) {
            console.log(`In catch with e = ${e}`);
        }
    }

    //search for duplicate xrefs in the table
    function searchDuplicates(newData, tableData) {
        for (let i = 0; i < tableData.length; i++) {
            if (newData.room_id == tableData[i].room_id && newData.zone_id == tableData[i].zone_id) {
                return false;
            }
        }
        return true;
    }

    //adds cells to the row groupings based on the data passed to it
    function addCell(tr, content, colSpan = 1, className = null, attribute = null) {
        let td = document.createElement('th');

        td.colSpan = colSpan;
        td.innerHTML = content;
        td.setAttribute('room', attribute);
        if (className) {
            td.classList.add(className);
        }
        tr.appendChild(td);
    }

    //load the table
    function loadTable() {

        //pre-load buildings
        if(configOptions.response.docs.defaultCampusId > 0){
            loadJsonXHR("/json/buildingsbycampus/" + configOptions.response.docs.defaultCampusId + ".json", "buildings");
        }

        $("#airflowTable").DataTable({
            destroy: true, info: false, responsive: true, deferRender: true, select: { items: 'cell', style: 'single' }, blurable: true, autoWidth: false, pageLength: 12, order: [[3, 'asc']],
            dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Max_Outdooor_Airflow_Per_Event_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Max_Outdoor_Airflow_Per_Event_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Max_Outdoor_Airflow_Per_Event_' + getFileDate(), text: 'PDF' }] }, { extend: 'collection', text: "Grouping", autoClose: true, buttons: [{ text: "Group by Room", action: deactivateZoneMode }, { text: "Group by Zone", action: activateZoneMode }, { text: "Group by Zone and Event", action: sortByZonesAndEvents }, { text: "Group by Room and Event", action: sortByRoomsAndEvents }, { text: "Group by Event", action: sortByEvents }] }],
            language: {
                emptyTable: "Please Select a Campus, Building, and Term",
                zeroRecords: "No Matching Room/Zone/Event Connections in Selected Building"
            },
            //enable row groupings and aggregate their data
            rowGroup: {
                className: 'custom-group',
                dataSrc: 'zone_name',
                //this renders the row group headings
                startRender: null,
                endRender: function (rows, group) {
                    let uncert;

                    //get the row's uncertainty value
                    if (rows.data().pluck('uncert_amt')[0] != null) {
                        uncert = rows.data().pluck('uncert_amt')[0];
                    }
                    else {
                        uncert = 0;
                    }

                    let rpPrESum = 0;
                    let rpPrZSum = 0;
                    let pplOaRate = 0;

                    //aggregate group data
                    for (let i = 0; i < rows.data().length; i++) {
                        rpPrESum += rows.data()[i]["ppl_oa_rate"] * rows.data()[i]["enrl_tot"];
                        rpPrZSum += (rows.data()[i]["pr_percent"] * (rows.data()[i]["uncert_amt"] + rows.data()[i]["enrl_tot"])) * rows.data()[i]["ppl_oa_rate"];
                        pplOaRate += rows.data()[i]["ppl_oa_rate"] * 1;
                    }

                    //create a row element
                    let tr = document.createElement('tr');

                    //change the rendering based on how the table is grouped
                    switch (sortMode) {
                        case 0: //rooms
                            addCell(tr, group, 1, 'airflow-group-start');
                            addCell(tr, "Total People oa Rate: " + Math.round(pplOaRate), 1);
                            addCell(tr, "Total Oa_people Event: " + Math.round(rpPrESum), 1);
                            addCell(tr, "Total Oa_people Zone: " + Math.round(rpPrZSum), 23);
                            break;
                        case 1: //zones
                            addCell(tr, group, 1, 'airflow-group-start');
                            addCell(tr, "Total People oa Rate: " + Math.round(pplOaRate), 1);
                            addCell(tr, "Total Oa_people Event: " + Math.round(rpPrESum), 1);
                            addCell(tr, "Total Oa_people Zone: " + Math.round(rpPrZSum), 23);
                            break;
                        case 2: //events and zones
                            addCell(tr, group, 1, 'airflow-group-start');
                            addCell(tr, "Total People oa Rate: " + Math.round(pplOaRate), 1);
                            addCell(tr, "Total Oa_people Event: " + Math.round(rpPrESum), 1);
                            addCell(tr, "Total Oa_people Zone: " + Math.round(rpPrZSum), 23);
                            break;
                        case 3: //rooms and events
                            addCell(tr, group, 1, 'airflow-group-start');
                            addCell(tr, "Total People oa Rate: " + Math.round(pplOaRate), 1);
                            addCell(tr, "Total Oa_people Event: " + Math.round(rpPrESum), 1);
                            addCell(tr, "Total Oa_people Zone: " + Math.round(rpPrZSum), 23);
                            break;
                        case 4: //events
                            addCell(tr, group, 1, 'airflow-group-start');
                            addCell(tr, "Total People oa Rate: " + Math.round(pplOaRate), 1);
                            addCell(tr, "Total Oa_people Event: " + Math.round(rpPrESum), 1);
                            addCell(tr, "Total Oa_people Zone: " + Math.round(rpPrZSum), 23);
                            break;
                    }

                    return tr;
                }
            },
            ajax: {
                //load a dummy .json file until we get the real deal
                url: "src/test_json/cleaner3.json",
                dataSrc: function (json) {
                    return json.response.docs[0];
                }
            },
            columnDefs: [
                {
                    visible: false,
                    orderable: false,
                    targets: 0,
                    data: null,
                    className: 'dt-center',
                    render: function () {
                        return `<i class="far fa-trash-alt"></i>`;
                    }
                },
                {
                    targets: 1,
                    data: 'facility_id',
                    //this gives us a span that when clicked informs the user that the variable is not editable
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${data}</span>`
                        }
                        else {
                            return `<span class="generateded"> </span>`
                        }
                    }
                },
                {
                    targets: 2,
                    data: 'room_name',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${data}</span>`
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 3,
                    data: 'coursetitle',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${data}</span>`
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 4,
                    data: { 'class_number_code': 'class_number_code', 'pp_search_id': 'pp_search_id' },
                    render: function (data) {
                        if (data.class_number_code != null) {
                            return `<span class="generated">${data.class_number_code}</span>`
                        }
                        else {
                            return `<span class="generated">${data.pp_search_id}</span>`
                        }
                    }
                },
                {
                    targets: 5,
                    data: { "enrl_tot": "enrl_tot", "room_population": "room_population", "uncert_amt": "uncert_amt" },
                    //if the value is less than the room's population (+uncertainty), color the text red
                    render: function (data) {
                        if (data != null) {
                            if (data.enrl_tot > data.room_population + data.uncert_amt) {
                                return `<span class="generated makeRed">${data.enrl_tot}</span>`
                            }
                            else {
                                return `<span class="generated">${data.enrl_tot}</span>`
                            }
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },

                {
                    targets: 6,
                    data: { "room_population": "room_population", "uncert_amt": "uncert_amt" },
                    render: function (data) {
                        if (data != null) {
                            if (data != null) {
                                return `<span class="generated">${data.room_population} (+${data.uncert_amt})</span>`
                            }
                            else {
                                return `<span class="generated">${data}</span>`
                            }
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 7,
                    data: { "enrl_tot": "enrl_tot", "uncert_amt": "uncert_amt" },
                    render: function (data) {
                        if (data != null) {
                            if (data != null) {
                                return `<span class="generated">${data.enrl_tot} (+${data.uncert_amt})</span>`
                            }
                            else {
                                return `<span class="generated">${data}</span>`
                            }
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 8,
                    data: "ppl_oa_rate",
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${(data * 1).toFixed(2)}</span>`
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 9,
                    data: { "ppl_oa_rate": "ppl_oa_rate", "enrl_tot": "enrl_tot", "uncert_amt": "uncert_amt" },
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${(data.ppl_oa_rate * (data.enrl_tot + data.uncert_amt)).toFixed(2)}</span>`
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 10,
                    data: 'zone_name',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${data}</span>`
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 11,
                    data: "pr_percent",
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${(data * 1).toFixed(2)}</span>`
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 12,
                    data: "xref_population",
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${(data * 1).toFixed(2)}</span>`
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }

                    }
                },
                {
                    targets: 13,
                    data: { "pr_percent": "pr_percent", "enrl_tot": "enrl_tot", "uncert_amt": "uncert_amt" },
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${(data.pr_percent * (data.enrl_tot + data.uncert_amt)).toFixed(2)}</span>`
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 14,
                    data: { "pr_percent": "pr_percent", "enrl_tot": "enrl_tot", "uncert_amt": "uncert_amt", "ppl_oa_rate": "ppl_oa_rate", "pr_percent": "pr_percent" },
                    render: function (data) {
                        return `<span class="generated">${((data.pr_percent * (data.enrl_tot + data.uncert_amt)).toFixed(2) * data.ppl_oa_rate).toFixed(2)}</span>`
                    }
                },
                {
                    targets: 15,
                    data: 'start_date',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${data}</span>`
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 16,
                    data: 'end_date',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${data}</span>`
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 17,
                    data: function (row) {
                        return new Date(row.start_date + " " + row.meeting_time_start).toLocaleTimeString('eg-GB', { timezone: 'America/New_york' });
                    },
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${data}</span>`
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 18,
                    data: function (row) {
                        return new Date(row.start_date + " " + row.meeting_time_end).toLocaleTimeString('eg-GB', { timezone: 'America/New_york' });
                    },
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${data}</span>`
                        }
                        else {
                            return `<span class="generated"> </span>`
                        }
                    }
                },
                {
                    targets: 19,
                    data: 'monday',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${renderDay(data)}</span>`
                        }
                        else {
                            return `<span class="generated">${renderDay(0)}</span>`
                        }
                    }
                },
                {
                    targets: 20,
                    data: 'tuesday',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${renderDay(data)}</span>`
                        }
                        else {
                            return `<span class="generated">${renderDay(0)}</span>`
                        }
                    }
                },
                {
                    targets: 21,
                    data: 'wednesday',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${renderDay(data)}</span>`
                        }
                        else {
                            return `<span class="generated">${renderDay(0)}</span>`
                        }
                    }
                },
                {
                    targets: 22,
                    data: 'thursday',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${renderDay(data)}</span>`
                        }
                        else {
                            return `<span class="generated">${renderDay(0)}</span>`
                        }
                    }
                },
                {
                    targets: 23,
                    data: 'friday',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${renderDay(data)}</span>`
                        }
                        else {
                            return `<span class="generated">${renderDay(0)}</span>`
                        }
                    }
                },
                {
                    targets: 24,
                    data: 'saturday',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${renderDay(data)}</span>`
                        }
                        else {
                            return `<span class="generated">${renderDay(0)}</span>`
                        }
                    }
                },
                {
                    targets: 25,
                    data: 'sunday',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${renderDay(data)}</span>`
                        }
                        else {
                            return `<span class="generated">${renderDay(0)}</span>`
                        }
                    }
                },
                {
                    //hidden column used for sorting
                    targets: 26,
                    data: 'room_num',
                    visible: false,
                }
            ],
        });

        //get a reference to the table
        let table = $('#airflowTable').DataTable();

        //check to see if the user has the ability to edit the table and add the edit buttons to the toolbar
        if ((configOptions.response.docs.canEditAll == 'on' || configOptions.response.docs.canEditAirflow == 'on') && configOptions.response.docs.current_user.role * 1 > 1) {
            table.button().add(5, {
                extend: 'collection',
                text: 'Edit',
                autoClose: true,
                buttons: [{ text: 'Modify', action: editModeToggle }]
            })
        }

        //Call this when a cell is selected
        table.on('select', function (e, dt, type, indexes) {
            //if a cell is not selected
            if (cellSelected == false && editMode == true) {

                let dat = table.cell({ selected: true }).data();
                oldVal = dat;

                //if the cell has an .inputable span, replace it with a text box
                if (document.querySelector(".selected .inputable")) {
                    if (dat != null) {
                        document.querySelector(".selected .inputable").innerHTML = `<input class="input" type="text" name="row-text-box" style="width: 100%;" value="${dat}"></input>`;
                    }
                    else {
                        document.querySelector(".selected .inputable").innerHTML = `<input class="input" type="text" name="row-text-box" style="width: 100%;" value=""></input>`;
                    }
                    //focus on the new input
                    document.querySelector("td input").focus();
                    cellSelected = true;
                }
                //if the user clicks on the id, let them know it can't be edited
                if (document.querySelector(".selected .generated")) {
                    toastr.warning('This value is generated by the system and cannot be edited.')
                }
            }
        });

        //call when input is blurred
        table.on('blur', 'td', function () {
            //if a cell is selected
            if (cellSelected == true) {
                let dat = table.cell(this).node().lastChild.firstChild.value;
                newVal = dat;
                //replace the input with the data in it
                if (dat != null && dat != "") {
                    if (table.cell(this).node().firstChild.className == "dropdown") {
                        table.cell(this).data().auto_mode = dat;
                    }
                    else {
                        table.cell(this).data(dat);
                    }
                }
                else {
                    table.cell(this).data(null);
                    newVal = null;
                }

                //editing and adding data is disabled on this table, but the code remains should it ever be required

                //this is the method used to gather data from the modified row to send to the server
                //make sure the selected cell isn't the trash icon, we don't want to send data from a row we may delete
                if (table.cell(this).index().columnVisible != 0) {
                    //check to make sure the user actually edited something
                    if (String(oldVal) != String(newVal)) {
                        //check row data against the required values
                        if (checkValues(requiredValuesAirflow, table.rows(this).data()[0])) {
                            //set the data to be submitted's building id to the value of the building selector
                            table.rows(this).data()[0].building_id = document.querySelector("#building").value;

                            //submit the data to the server tagged for addition.
                            submit(table.rows(this).data()[0], 0);
                            newVal = null;
                            oldVal = null;

                            updateTable();
                        }
                        else {
                            toastr["error"]("Row Missing Required Data!");
                        }
                    }
                }
                cellSelected = false;
            }
        });

        //enable and disable row grouping if the user is searching
        table.on('search.dt', function () {
            if (table.search() == null || table.search() == "") {
                table.rowGroup().disable();
            }
            else {
                table.rowGroup().enable();
            }
        });

        //disable the loading overlay when the table has finished loading data
        table.on('xhr.dt', function (e, settings, json, xhr) {
            document.querySelector(".overlay").style.display = "none";
        });

        //in case the user selects a new cell before the table has re-drawn, set cellSelected to false after every draw.
        table.on('draw', function () {
            cellSelected = false;
        });
    }


    //update the table based on input. This is only used after the page loads.
    function updateTable() {
        let buildingID = document.querySelector("#building").value;
        let termSelect = document.querySelector("#term").value;

        //load the table based on what data is input
        if (buildingID != null && buildingID > 0 && termSelect > 0) {
            $("#airflowTable").DataTable().ajax.url("/json/airfloweventdata/" + buildingID + "-" + termSelect + ".json").load(null, false);
        }
        else {
            clearTableByID("#airflowTable");
        }
    }

    //Loads terms into the dropdown and selects the current one
    function loadTermDropDown(json) {
        let dropDown = document.querySelector("#term");

        for (let i = 0; i < json.response.numFound; i++) {

            if (json.response.docs[0][i].term_code == configOptions.response.docs.current_term) {
                dropDown.innerHTML += "<option selected value=" + json.response.docs[0][i].term_code + ">" + json.response.docs[0][i].term_code + ": " + json.response.docs[0][i].term_name + "</option>";
                currTerm = json.response.docs[0][i].term_code;
            }
            else {
                dropDown.innerHTML += "<option value=" + json.response.docs[0][i].term_code + ">" + json.response.docs[0][i].term_code + ": " + json.response.docs[0][i].term_name + "</option>";
            }
        }
    }

    //part 1 of the dropdown update chain.
    function updateDropDowns() {
        let campus = document.querySelector("#campus");
        if (campus.value > 0) {
            loadJsonXHR("/json/buildingsbycampus/" + campus.value + ".json", "buildings");
        }
        else {
            //clear the table is no campus is selected
            clearTableByID("#airflowTable");
        }
    }

    //part 2 of the dropdown update chain. Loads campuses into the dropdown.
    function loadCampusDropDown(json) {
        let dropDown = document.querySelector("#campus");
        for (let i = 0; i < json.response.docs[0].length; i++) {
            if (json.response.docs[0][i].active == 1) {
                if (json.response.docs[0][i].id == configOptions.response.docs.defaultCampusId) {
                    dropDown.innerHTML += "<option selected value=" + json.response.docs[0][i].id + ">" + json.response.docs[0][i].campus_name + "</option>";
                }
                else {
                    dropDown.innerHTML += "<option value=" + json.response.docs[0][i].id + ">" + json.response.docs[0][i].campus_name + "</option>";
                }
            }
        }
        dropDown.addEventListener("change", loadBuildingsByCampus);
    }

    //part 3 of the dropdown update chain. Loads buildings when the campus dropdown is changed.
    function updateBuildingDropDown(json) {
        let buildingSelect = document.querySelector("#building");
        buildingSelect.innerHTML = "<option value=0> </option>";

        if (json != 0) {
            for (let i = 0; i < json.response.docs[0].length; i++) {
                if (json.response.docs[0][i].active == 1) {
                    buildingSelect.innerHTML += "<option value=" + json.response.docs[0][i].id + ">" + json.response.docs[0][i].bldg_num + ": " + json.response.docs[0][i].bldg_name + "</option>";
                }
            }
            buildingSelect.addEventListener("change", updateTable);
        }
        else {
            clearTableByID('#airflowTable');
        }
    }

    //load building data based on the campus selected
    function loadBuildingsByCampus() {
        updateBuildingDropDown(0);
        clearTableByID("#airflowTable");

        //if no campus is selected, start the chain of clearing dropdowns
        let campusSelect = document.querySelector("#campus");
        if (campusSelect.value > 0) {
            loadJsonXHR("/json/buildingsbycampus/" + campusSelect.value + ".json", "buildings");
        }
        else {
            updateBuildingDropDown(0);
        }
    }

    //clears a table based on the id passed to it
    function clearTableByID(id) {
        if (editMode == true) {
            editModeToggle();
        }
        let table = $(id).DataTable();
        table.clear();
        table.draw();
    }

    //loop through the data in a row and check it against the requred values
    function checkValues(requiredValues, rowData) {
        let isFull = true;

        for (let key in rowData) {
            for (let i = 0; i < requiredValues.length; i++) {
                if (key == requiredValues[i] && rowData[key] === null) {
                    isFull = false;
                    break;
                }
                else if (key == requiredValues[i] && rowData[key] === "") {
                    isFull = false;
                    break;
                }
            }
        }
        return isFull;
    }

    //submit data to the server
    function submit(json, deleteData) {
        if (configOptions.response.docs.current_user.role > 1) {
            let xhr = new XMLHttpRequest();
            let url = "/api/reference/update.php";


            //Open connection
            xhr.open("POST", url, true);

            //Set request header
            xhr.setRequestHeader("Content-Type", "application/json");

            //Create callback
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    // Print received data from server
                    console.log(this.responseText);
                }
            };

            //Add to the data being sent to the server. Lets the server know what database to update
            json.dcvsection = "airflow";

            json.delete = deleteData;

            //Convert JSON data to string
            let data = JSON.stringify(json);

            //Send data
            xhr.send(data);
        }
    }

    //allows for the editing of data
    function editModeToggle() {
        let table = $("#airflowTable").DataTable();
        if (editMode == false) {
            if (document.querySelector('#building').value > 0) {
                editMode = true;
                table.button('1-1').text("Freeze");
                //create a pop-up that warns the user about editing data
                toastr.warning('The table can now be edited. Click on a field to open its editing function. Note that changes made cannot be undone.');
                table.draw(false);
            }
        }
        else {
            table.column(0).visible(false);
            let groupings = document.querySelectorAll('.airflow-group-start');
            groupings.forEach((cell) => cell.colSpan = 1);
            editMode = false;
            table.button('1-1').text("Modify");
            table.draw(false);
        }
    }

    //opens the modal for adding rows to the table
    function openAddModal() {
        let modal = document.getElementById('addAirflowModal');
        let buildingSelect = document.querySelector('#building');

        if (buildingSelect.value > 0) {
            modal.style.display = 'block';
        }
    }

    //if the user cancels the addition, clear out the form
    function closeAddModal() {
        let modal = document.getElementById('addAirflowModal');
        document.getElementById('room_num').value = null;
        document.getElementById('zone_code').value = null;
        document.getElementById('pr_percent').value = null;
        modal.style.display = 'none';
    }

    //submit the data to the server
    //instead of adding a row to the table, we simply reload the table
    //this is so the row grouping is applied correctly
    function submitAddModal(e) {
        let table = $("#airflowTable").DataTable();
        let buildingSelect = document.querySelector('#building');
        e.preventDefault();
        let formData = {
            room_num: parseFloat(document.getElementById('room_num').value),
            zone_code: parseFloat(document.getElementById('zone_code').value),
            pr_percent: parseFloat(document.getElementById('pr_percent').value),
            building_id: parseInt(buildingSelect.value),
            dcvsection: 'airflow',
            delete: 0
        };


        //only send the new data to the server if the connection doesn't already exist.
        if (searchDuplicates(formData, table.rows().data())) {
            //submit(formData, 0);
            updateTable();
        }
        else {
            toastr.warning('That connection already exists!');
        }

        closeAddModal();
    }

    //get and format the current date for file exportation purposes
    function getFileDate() {
        let today = new Date()
        let todayDate = today.toISOString().slice(0, 10);
        let todayTime1 = today.toISOString().slice(11, 13);
        let todayTime2 = today.toISOString().slice(14, 16);

        return todayDate + "-" + todayTime1 + "_" + todayTime2;
    }

    //change the length of the table page when the user changes it
    function changePageLength() {
        let table = $('#airflowTable').DataTable();
        let len = document.querySelector('#pageLen').value;
        table.page.len(len).draw(false);
    }

    //group table by rooms
    function deactivateZoneMode() {
        let table = $('#airflowTable').DataTable();
        sortMode = 0;
        table.rowGroup().dataSrc("facility_id");
        table.order([1, 'asc']);
        table.draw(false);
    }

    //group table by zones
    function activateZoneMode() {
        let table = $('#airflowTable').DataTable();
        sortMode = 1;
        table.rowGroup().dataSrc("zone_name");
        table.order([3, 'asc']);
        table.draw(false);
    }

    //group the table by zones and events
    function sortByZonesAndEvents() {
        let table = $('#airflowTable').DataTable();
        sortMode = 2;
        table.rowGroup().dataSrc(["zone_name", "coursetitle"]);
        table.order([[3, 'asc'], [5, 'asc']]);
        table.draw(false);
    }

    //group the table by rooms and events
    function sortByRoomsAndEvents() {
        let table = $('#airflowTable').DataTable();
        sortMode = 3;
        table.rowGroup().dataSrc(["facility_id", "coursetitle"]);
        table.order([1, 'asc'], [5, 'asc']);
        table.draw(false);
    }

    //group the table by events
    function sortByEvents() {
        let table = $('#airflowTable').DataTable();
        sortMode = 4;
        table.rowGroup().dataSrc("class_number_code");
        table.order([5, 'asc']);
        table.draw(false);
    }


    //return a Y or N for displaying the days of the week a class meets
    function renderDay(day) {
        if (day == 1) {
            return 'Y';
        }
        else {
            return 'N';
        }
    }

    //when the page loads, load the campuses into the dropdown

    //Give the dropdowns the proper event listeners
    document.querySelector("#campus").addEventListener("change", updateDropDowns);

    document.querySelector("#building").addEventListener("change", updateTable);

    document.querySelector('#term').addEventListener("change", updateTable);

    //events for row addition
    document.querySelector("#cancelButton").addEventListener('click', closeAddModal);

    document.querySelector("#airflowForm").addEventListener('submit', submitAddModal);

    document.querySelector('.close').addEventListener('click', closeAddModal);

    document.querySelector("#pageLen").addEventListener('change', changePageLength);

    loadJsonXHR("src/test_json/cleaner.json", "init");
}

document.addEventListener('DOMContentLoaded', init);