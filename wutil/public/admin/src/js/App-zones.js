//for security reasons, this whole script is a function
function init() {
    //Global Variables
    let editMode = false;

    let selectedRow = null;

    let configOptions;

    let oldVal;

    let newVal;

    let cellSelected = false;

    //required values for editing and adding table rows
    let requiredValuesZones = ["zone_name", "zone_code"];

    //loads data from .json files and sends it to the correct function
    //used for loading buildings for the dropdowns
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

                    Object.freeze(configOptions.response.docs.current_user);
                    Object.freeze(configOptions.response.docs);

                    loadCampusDropDown(json);
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

    //load the table
    function loadTable() {
        //pre-load buildings
        if(configOptions.response.docs.defaultCampusId > 0){
            loadJsonXHR("/json/buildingsbycampus/" + configOptions.response.docs.defaultCampusId + ".json", "buildings");
        }

        $("#zonesTable").DataTable({
            destroy: true, info: false, responsive: true, select: { items: 'cell', style: 'single' }, blurable: true, lengthChange: false, autoWidth: false, order: [1, "asc"], pageLength: 12,
            dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Zones_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Zones_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Zones_' + getFileDate(), text: 'PDF' }] }],
            language: {
                emptyTable: "Please Select a Campus and Building",
                zeroRecords: "No Matching VAVs in Selected Building"
            },
            orderFixed: [6, 'asc'],
            rowGroup: {
                className: 'custom-group',
                dataSrc: 'ahu_name',
                startRender: function (rows, group) {
                    return `<strong>${group} Zones</strong>`;
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
                    data: 'id',
                    //this gives us a span that when clicked informs the user that the variable is not editable
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
                    targets: 2,
                    data: 'zone_name',
                    //this gives us a span that we can replace with an input box
                    render: function (data) {
                        if (data != null) {
                            return `<span class="inputable">${data}</span>`
                        }
                        else {
                            return `<span class="inputable"> </span>`
                        }
                    }
                },
                {
                    targets: 3,
                    data: 'zone_code',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="inputable">${data}</span>`
                        }
                        else {
                            return `<span class="inputable"> </span>`
                        }
                    }
                },
                {
                    targets: 4,
                    data: 'occ_sensor',
                    //this gives us a span that we can replace with a yes/no dropdown
                    render: function (data) {
                        if (data == 1) {
                            return `<span class="ynDropdown">Yes</span>`
                        }
                        else {
                            return `<span class="ynDropdown">No</span`
                        }
                    }
                },
                {
                    targets: 5,
                    data: 'xrefs',
                    //this gives us a span that we can replace with a input that the user can add a list of room codes too
                    render: function (data) {
                        let refs = "";
                        if (data != null) {
                            for (let i = 0; i < data.length; i++) {
                                if (i == data.length - 1) {
                                    refs += data[i];
                                }
                                else {
                                    refs += data[i] + ", ";
                                }
                            }
                            return `<span class="listInput">${refs}</span>`;
                        }
                        else {
                            return `<span class="listInput"> </span>`;
                        }
                    }
                },
                {
                    targets: 6,
                    data: 'ahu_name',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="inputable">${data}</span>`
                        }
                        else {
                            return `<span class="inputable"> </span>`
                        }
                    }
                }
                ,
                {
                    targets: 7,
                    data: 'active',
                    //this gives us a span that we can replace with a dropdown of the active states
                    render: function (data) {
                        if (data == 1) {
                            return `<span class="dropdownActive">Active</span>`
                        }
                        else {
                            return `<span class="dropdownActive">Deactivated</span>`
                        }
                    }
                }
            ],
        });

        //get a reference to the table
        let table = $('#zonesTable').DataTable();

        //check to see if the user has the ability to edit the table and add the edit buttons to the toolbar
        if ((configOptions.response.docs.canEditAll == 'on' || configOptions.response.docs.canEditZones == 'on') && configOptions.response.docs.current_user.role * 1 > 1) {
            table.button().add(5, {
                extend: 'collection',
                text: 'Edit',
                autoClose: true,
                buttons: [{ text: 'Add', action: openAddModal }, { text: 'Modify', action: editModeToggle }]
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
                //if the cell has a .ynDropdown span, replace it with a dropdown
                if (document.querySelector(".selected .ynDropdown")) {
                    if (dat == 1) {
                        document.querySelector(".selected .ynDropdown").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" name="row-dropdown-yn">
                        <option value='1' selected="selected">Yes</option>
                        <option value='0'>No</option>
                        </select>`;
                    }
                    else {
                        document.querySelector(".selected .ynDropdown").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" name="row-dropdown-yn">
                        <option value='1'>Yes</option>
                        <option value='0' selected="selected">No</option>
                        </select>`;
                    }
                    //focus on the new input
                    document.querySelector("td select").focus();
                    cellSelected = true;
                }
                //if the cell has a .dropdownActive span, replace it with the proper dropdown
                if (document.querySelector(".selected .dropdownActive")) {
                    if (dat == 1) {
                        document.querySelector(".selected .dropdownActive").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" name="row-dropdown-active">
                    <option value='1' selected="selected">Active</option>
                    <option value='0'>Deactivate</option>
                    </select>`;
                    }
                    else {
                        document.querySelector(".selected .dropdownActive").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" name="row-dropdown-active">
                    <option value='1'>Activate</option>
                    <option value='0' selected="selected">Deactivated</option>
                    </select>`;
                    }
                    //focus on the new input
                    document.querySelector("td select").focus();
                    cellSelected = true;
                }
                //if the cell has a listInput span, replace it with the proper input
                if (document.querySelector(".selected .listInput")) {
                    if (dat != null) {
                        let xrefs = "";
                        for (let i = 0; i < dat.length; i++) {
                            if (i == dat.length - 1) {
                                xrefs += dat[i];
                            }
                            else {
                                xrefs += dat[i] + ", ";
                            }
                        }
                        document.querySelector(".selected .listInput").innerHTML = `<input class="input" type="text" name="row-text-box" style="width: 100%;" value="${xrefs}"></input>`;
                    }
                    else {
                        document.querySelector(".selected .listInput").innerHTML = `<input class="input" type="text" name="row-text-box" style="width: 100%;" value=""></input>`;
                    }
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

                //check to see if the data is coming from the xref list and process it accordingly
                if (table.cell(this).node().firstChild.className == "listInput") {
                    let newVals = dat.split(/,\s*/);
                    dat = [];
                    newVals.forEach((val) => dat.push(val));
                }

                newVal = dat;

                //replace the input with the data in it
                if (dat != null && dat != "") {
                    table.cell(this).data(dat);
                }
                else {
                    table.cell(this).data(null);
                    newVal = null;
                }


                //this is the method used to gather data from the modified row to send to the server
                //make sure the selected cell isn't the trash icon, we don't want to send data from a row we may delete
                if (table.cell(this).index().columnVisible != 0) {
                    //check to make sure the user actually edited something
                    if (String(oldVal) != String(newVal)) {
                        //check row data against the required values
                        if (checkValues(requiredValuesZones, table.rows(this).data()[0])) {
                            //set the data to be submitted's building id to the value of the building selector
                            table.rows(this).data()[0].building_id = document.querySelector("#building").value;

                            //submit the data to the server tagged for addition.
                            submit(table.rows(this).data()[0], 0);

                            oldVal = null;
                            newVal = null;

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

        //load the table based on what data is input
        if (buildingID != null && buildingID > 0) {
            $("#zonesTable").DataTable().ajax.url("/json/zones/" + buildingID + ".json").load(null, false);
        }
        else {
            clearTableByID("#zonesTable");
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
            clearTableByID("#zonesTable");
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
            clearTableByID('#zonesTable');
        }
    }

    //load building data based on the campus selected
    function loadBuildingsByCampus() {
        updateBuildingDropDown(0);
        clearTableByID("#zonesTable");

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
                    //Print received data from server
                    console.log(this.responseText);
                }
            };

            //Add to the data being sent to the server. Lets the server know what database to update
            json.dcvsection = "zones";

            json.delete = deleteData;

            //Convert JSON data to string
            let data = JSON.stringify(json);

            //Send data
            xhr.send(data);
        }
    }

    //if the user clicks on a trash icon, bring up the dialog box to confirm
    $("#zonesTable tbody").on('click', '.fa-trash-alt', function () {
        let table = $("#zonesTable").DataTable();
        document.querySelector(".alertTextDel").innerHTML = `Delete data for zone ${table.row($(this).parents('tr')).data().zone_code}?`;
        document.querySelector("#alertZonesDel").showModal();
        selectedRow = table.row($(this).parents('tr'))
    })

    //called if the user cancels the deletion
    function cancelDelete() {
        let dialogElement = document.querySelector("#alertZonesDel");
        selectedRow = null;
        dialogElement.close();
    }

    //remove the selected row and send it's data to the server tagged for deletion
    function confirmDelete() {
        let dialogElement = document.querySelector("#alertZonesDel");
        submit(selectedRow.data(), 1);
        selectedRow.remove().draw(false);
        selectedRow = null;
        dialogElement.close();
    }

    //allows for the editing of data
    function editModeToggle() {
        let table = $("#zonesTable").DataTable();
        if (editMode == false) {
            if (document.querySelector('#building').value > 0) {
                table.column(0).visible(true);
                editMode = true;
                table.button('1-1').text("Freeze");
                //create a pop-up that warns the user about editing data
                toastr.warning('The table can now be edited. Note that changes made cannot be undone.')
            }
        }
        else {
            table.column(0).visible(false);
            table.button('1-1').text("Modify");
            editMode = false;
        }
    }

    //opens the modal for adding rows to the table
    function openAddModal() {
        let modal = document.getElementById('addZoneModal');
        let table = $("#zonesTable").DataTable();
        let buildingSelect = document.querySelector('#building');

        if (buildingSelect.value > 0) {
            table.column('1').order('asc').draw(false);
            modal.style.display = 'block';
        }
    }

    //if the user cancels the addition, clear out the form
    function closeAddModal() {
        let modal = document.getElementById('addZoneModal');
        document.getElementById('zone_name').value = null;
        document.getElementById('zone_code').value = null;
        document.getElementById('ahu_name').value = null;
        document.getElementById('xrefs').value = null;
        document.getElementById('occ_sensor').value = 0;
        document.getElementById('active').value = 0;
        modal.style.display = 'none';
    }

    //submit the data to the server and update the table
    function submitAddModal(e) {
        let buildingSelect = document.querySelector('#building');
        e.preventDefault();
        let formData = {
            zone_name: document.getElementById('zone_name').value,
            zone_code: document.getElementById('zone_code').value,
            occ_sensor: document.getElementById('occ_sensor').value,
            ahu_name: document.getElementById('ahu_name').value,
            building_id: parseInt(buildingSelect.value),
            dcvsection: 'floors',
            xrefs: document.getElementById('xrefs').value.split(/,\s*/),
            auto_mode: false,
            active: document.getElementById('active').value,
            delete: 0
        };

        //clear out and submit the form
        closeAddModal();

        submit(formData, 0);

        updateTable();
    }

    //get and format the current date for file exportation purposes
    function getFileDate() {
        let today = new Date()
        let todayDate = today.toISOString().slice(0, 10);
        let todayTime1 = today.toISOString().slice(11, 13);
        let todayTime2 = today.toISOString().slice(14, 16);

        return todayDate + "-" + todayTime1 + "_" + todayTime2;
    }

    //Give the dropdowns the proper event listeners
    document.querySelector("#campus").addEventListener("change", updateDropDowns);

    document.querySelector("#building").addEventListener("change", updateTable);

    //events for row addition and deletetion
    document.querySelector("#cancelZonesDel",).addEventListener('click', cancelDelete);

    document.querySelector("#confirmZonesDel",).addEventListener('click', confirmDelete);

    document.querySelector("#cancelButton").addEventListener('click', closeAddModal);

    document.querySelector("#zoneForm").addEventListener('submit', submitAddModal);

    document.querySelector('.close').addEventListener('click', closeAddModal);

    loadJsonXHR("/json/campuses/all.json", "init");
}

document.addEventListener('DOMContentLoaded', init);