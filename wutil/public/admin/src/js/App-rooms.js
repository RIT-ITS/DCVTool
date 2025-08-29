//for security reasons, this whole script is a function
function init() {
    //global variables
    let categories;

    let floors;

    let editMode = false;

    let selectedRow = null;

    let configOptions;

    let dcv_reqs;

    let roomCategories;

    let oldVal;

    let newVal;

    let cellSelected = false;

    //required values for editing table rows
    let requiredValuesRooms = ["facility_id", "floor_id", "room_num", "room_area", "room_name", "rtype_code", "space_use_name", "room_population"];

    //loads data from .json files and sends it to the correct function
    //used for loading categories, buildings, rooms, and floors for the dropdowns
    //data actually for the table is loaded in drawTable()
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
                    let responseCat = await fetch("/json/ashrae6-1/all.json");

                    let configResponse = await fetch("/json/configVars/1.json");

                    let dcvReqResponse = await fetch("/json/configVars/2.json");

                    let responseRoomCat = await fetch("/json/ashrae6-1types/all.json");

                    categories = await responseCat.json();

                    configOptions = await configResponse.json();

                    dcv_reqs = await dcvReqResponse.json();

                    roomCategories = await responseRoomCat.json();

                    let campusResponse = await fetch("/json/campuses/all.json");

                    let campusOptions = await campusResponse.json();

                    Object.freeze(configOptions.response.docs.current_user);
                    Object.freeze(configOptions.response.docs);

                    loadCampusDropDown(campusOptions);

                    loadTable();
                    break;
                case 'buildings':
                    updateBuildingDropDown(json);
                    break;
                case 'floors':
                    updateFloorDropdown(json);
                    floors = json;
                    break;
            }

        } catch (e) {
            console.log(`In catch with e = ${e}`);
        }
    }

    //returns a selector full of all possible categories, with the cell's current category already selected.
    function searchCategories(id, categories, roomCats) {
        let category = `<select class="input customSelect customInput" id='ash61_cat_id' name='ash61_cat_id'>`

        for (let j = 0; j < roomCats.response.docs[0].length; j++) {
            category += `<optgroup label="${roomCats.response.docs[0][j].type}:">`;

            for (let i = 0; i < categories.response.docs[0].length; i++) {
                if (categories.response.docs[0][i].type == roomCats.response.docs[0][j].id) {
                    if (id == categories.response.docs[0][i].id) {
                        category += `<option value=${categories.response.docs[0][i].id} selected='selected'>${categories.response.docs[0][i].category}</option>`
                    }
                    else {
                        category += `<option value=${categories.response.docs[0][i].id}>${categories.response.docs[0][i].category}</option>`
                    }
                }
            }

            category += `</optgroup>`;
        }


        category += `</select>`
        return category;
    }

    //returns a category name based on the id passed to it.
    function searchCategoryName(id, categories) {
        let category = "";
        for (let i = 0; i < categories.response.docs[0].length; i++) {
            if (id == categories.response.docs[0][i].id) {
                category = categories.response.docs[0][i].category;
                break;
            }
        }
        return category;
    }

    //return a selectro of all the floor designations
    function searchFloors(id, floors) {
        let floor = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" size='1' name='floor_id' id='floor_id' required>`

        for (let i = 0; i < floors.response.docs[0].length; i++) {
            if (id == floors.response.docs[0][i].id) {
                floor += `<option value=${floors.response.docs[0][i].id} selected='selected'>${floors.response.docs[0][i].floor_designation}</option>`
            }
            else {
                floor += `<option value=${floors.response.docs[0][i].id}>${floors.response.docs[0][i].floor_designation}</option>`
            }
        }
        floor += `</select>`
        return floor;
    }

    //return the designation of a specific floor
    function searchFloorDesignation(id, floors) {
        let floor = "";
        for (let i = 0; i < floors.response.docs[0].length; i++) {
            if (id == floors.response.docs[0][i].id) {
                floor = floors.response.docs[0][i].floor_designation;
                break;
            }
        }
        return floor;
    }

    //load the table
    function loadTable() {
        if(configOptions.response.docs.defaultCampusId > 0){
            loadJsonXHR("/json/buildingsbycampus/" + configOptions.response.docs.defaultCampusId + ".json", "buildings");
        }
        $("#roomsTable").DataTable({
            info: false, responsive: true, select: { items: 'cell', style: 'single' }, blurable: true, autoWidth: false, order: [3, "asc"], pageLength: 12,
            dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Rooms_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Rooms_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Rooms_' + getFileDate(), text: 'PDF' }] }],
            language: {
                emptyTable: "Please Select a Campus and Building",
                zeroRecords: "No Matching Rooms in Selected Building"
            },
            ajax: {
                //load a dummy .json file until we get the real deal
                url: "src/test_json/cleaner.json",
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
                    data: 'floor_id',
                    //this gives us a span that we can replace with a dropdown of building floors
                    render: function (data) {
                        if (data != null && floors != null) {
                            return `<span class="dropdownFloor">${searchFloorDesignation(data, floors)}</span>`
                        }
                        else {
                            return `<span class="dropdownFloor"> </span>`
                        }
                    }
                },
                {
                    targets: 3,
                    data: 'facility_id',
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
                    targets: 4,
                    data: 'room_num',
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
                    targets: 5,
                    data: 'room_name',
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
                    targets: 6,
                    data: 'rtype_code',
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
                    targets: 7,
                    data: 'space_use_name',
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
                    targets: 8,
                    data: 'ash61_cat_id',
                    //this gives us a span that we can replace with a dropdown of categories
                    render: function (data) {
                        if (data != null) {
                            return `<span class="dropdown">${searchCategoryName(data, categories)}</span>`
                        }
                        else {
                            return `<span class="dropdown"> </span>`
                        }
                    }
                },
                {
                    targets: 9,
                    data: 'reservable',
                    //this gives us a span we can replace with a yes/no dropdown
                    render: function (data) {
                        if (data != null) {
                            if (data == 1) {
                                return `<span class="dropdownRes">Yes</span>`
                            }
                            else {
                                return `<span class="dropdownRes">No</span>`
                            }
                        }
                        else {
                            return `<span class="dropdownRes">No</span>`
                        }
                    }
                },
                {
                    targets: 10,
                    data: 'room_area',
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
                    targets: 11,
                    data: 'room_population',
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
                    targets: 12,
                    data: 'uncert_amt',
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
                    targets: 13,
                    data: 'room_area',
                    render: function (data) {
                        if (data != null) {
                            let check = data;
                            if (data * 1 > dcv_reqs.response.docs.minSpaceSize * 1) {
                                return `<span class="generated">Yes</span>`
                            }
                            else {
                                return `<span class="generated">No</span>`
                            }
                        }
                        else {
                            return `<span class="generated">No</span>`
                        }
                    }
                },
                {
                    targets: 14,
                    data: 'active',
                    //this gives us a span that we can replace with a dropdown of active states
                    render: function (data) {
                        if (data != null) {
                            if (data == 1) {
                                return `<span class="dropdownActive">Active</span>`
                            }
                            else {
                                return `<span class="dropdownActive">Deactivated</span>`
                            }
                        }
                        else {
                            return `<span class="dropdownActive">Deactivated</span>`
                        }
                    }
                }
            ],
        });

        //get a reference to the table
        let table = $('#roomsTable').DataTable();

        //check to see if the user has the ability to edit the table and add the edit buttond to the toolbar
        if ((configOptions.response.docs.canEditAll == 'on' || configOptions.response.docs.canEditRooms == 'on') && configOptions.response.docs.current_user.role * 1 > 1) {
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
                //if the cell has a .dropdown span, replace it with a dropdown
                if (document.querySelector(".selected .dropdown")) {
                    document.querySelector(".selected .dropdown").innerHTML = searchCategories(dat, categories, roomCategories);
                    //focus on the new input
                    document.querySelector("td select").focus();
                    cellSelected = true;
                }
                //if the user clicks on the floor, only give them the option of choosing existing floors
                if (document.querySelector(".selected .dropdownFloor")) {
                    document.querySelector(".selected .dropdownFloor").innerHTML = searchFloors(dat, floors);
                    document.querySelector("td select").focus();
                    cellSelected = true;
                }
                //if the cell has a .dropdownActive span, replace it with the proper dropdown
                if (document.querySelector(".selected .dropdownActive")) {
                    if (dat == 1) {
                        document.querySelector(".selected .dropdownActive").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" size='1' name="row-dropdown-active">
                        <option value='1' selected="selected">Active</option>
                        <option value='0'>Deactivate</option>
                        </select>`;
                    }
                    else {
                        document.querySelector(".selected .dropdownActive").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" size='1' name="row-dropdown-active">
                        <option value='1'>Activate</option>
                        <option value='0' selected="selected">Deactivated</option>
                        </select>`;
                    }
                    //focus on the new input
                    document.querySelector("td select").focus();
                    cellSelected = true;
                }
                //if the cell has a .dropdown span, replace it with a dropdown
                if (document.querySelector(".selected .dropdownRes")) {
                    if (dat == 1) {
                        document.querySelector(".selected .dropdownRes").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" name="row-dropdown-res">
                    <option value='1' selected="selected">Yes</option>
                    <option value='0'>No</option>
                    </select>`;
                    }
                    else {
                        document.querySelector(".selected .dropdownRes").innerHTML = `<select style="border: 1px solid #aaa width: 100%;" class="form-control input form-select" name="row-dropdown-res">
                    <option value='1'>Yes</option>
                    <option value='0' selected="selected">No</option>
                    </select>`;
                    }
                    //focus on the new input
                    document.querySelector("td select").focus();
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
                    table.cell(this).data(dat);
                }
                else {
                    table.cell(this).data(null);
                    newVal = null;
                }

                //this method is used to gather data from the modified row to send to the server
                //make sure the selected cell isn't the trash icon, we don't want to send data from a row we may delete
                if (table.cell(this).index().columnVisible != 0) {
                    //check to make sure the user actually edited something
                    if (String(oldVal) != String(newVal)) {
                        //check row data against the required values
                        if (checkValues(requiredValuesRooms, table.rows(this).data()[0])) {
                            //submit the data to the server tagged for addition
                            submit(table.rows(this).data()[0], 0);
                            oldVal = null;
                            newVal = null;
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
    async function updateTable() {
        let buildingID = document.querySelector("#building").value;
        let floorID = document.querySelector("#floor").value;

        //load the table based on what data is input, and the floors
        if (buildingID != null && buildingID > 0) {

            if (floorID != null && floorID > 0) {
                $("#roomsTable").DataTable().ajax.url("/json/roomsbybuildingfloor/" + buildingID + "-" + floorID + ".json").load(null, false);
            }
            else {
                loadJsonXHR("/json/buildingfloors/" + buildingID + ".json", 'floors');
                $("#roomsTable").DataTable().ajax.url("/json/roomsbybuilding/" + buildingID + ".json").load(null, false);
            }
        }
        else {
            clearTableByID("#roomsTable");
            updateFloorDropdown(floors);
        }
    }

    //part 1 of the dropdown update chain.
    function updateDropDowns() {
        let campus = document.querySelector("#campus").value;
        if (campus > 0) {
            loadJsonXHR("/json/buildingsbycampus/" + campus + ".json", 'buildings');
        }
        else {
            //clear the table is no campus is selected
            clearTableByID("#roomsTable");
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

        //if passed a 0 (i.e the user selected the blank option) clear the options and also send the floor dropdown a 0
        if (json != 0) {
            for (let i = 0; i < json.response.docs[0].length; i++) {
                if (json.response.docs[0][i].active == 1) {
                    buildingSelect.innerHTML += "<option value=" + json.response.docs[0][i].id + ">" + json.response.docs[0][i].bldg_num + ": " + json.response.docs[0][i].bldg_name + "</option>";
                }
            }
        }
        else {
            updateFloorDropdown(floors);
        }
    }

    //part 4 of the dropdown update chain. Loads the floor options when the building is changed
    async function updateFloorDropdown(floors) {
        let floorSelect = document.querySelector("#floor");
        floorSelect.innerHTML = "<option value=0>All</option>";
        let buildingSelect = document.querySelector("#building");
        if (buildingSelect.value > 0) {
            //if passed a 0 (i.e the user choose a blank building option) clear the floor options
            if (floors) {
                for (let i = 0; i < floors.response.docs[0].length; i++) {
                    if (floors.response.docs[0][i].active == 1) {
                        floorSelect.innerHTML += "<option value=" + floors.response.docs[0][i].id + ">" + floors.response.docs[0][i].floor_designation + "</option>";
                    }
                }
            }
        }
    }

    //load building data based on the campus selected
    function loadBuildingsByCampus() {
        updateBuildingDropDown(0);
        clearTableByID("#roomsTable");

        //if no campus is selected, start the chain of clearing dropdowns
        let campusSelect = document.querySelector("#campus");
        if (campusSelect.value > 0) {
            loadJsonXHR("/json/buildingsbycampus/" + campusSelect.value + ".json", 'buildings');
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
            json.dcvsection = "rooms";

            json.delete = deleteData;

            //Convert JSON data to string
            let data = JSON.stringify(json);

            //Send data
            xhr.send(data);
        }
    }

    //if the user clicks on a trash icon, bring up the dialog box to confirm
    $("#roomsTable tbody").on('click', '.fa-trash-alt', function () {
        let table = $("#roomsTable").DataTable();
        document.querySelector(".alertTextDel").innerHTML = `<p>Delete data for room ${table.row($(this).parents('tr')).data().facility_id}?</p>`;
        document.querySelector("#alertRoomsDel").showModal();
        selectedRow = table.row($(this).parents('tr'))
    })

    //called if the user cancels the deletion
    function cancelDelete() {
        let dialogElement = document.querySelector("#alertRoomsDel");
        selectedRow = null;
        dialogElement.close();
    }

    //remove the selected row and send it's data to the server tagged for deletion
    function confirmDelete() {
        let dialogElement = document.querySelector("#alertRoomsDel");
        submit(selectedRow.data(), 1);
        selectedRow.remove().draw(false);
        selectedRow = null;
        dialogElement.close();
    }

    //allows for the editing of data
    function editModeToggle() {
        let table = $("#roomsTable").DataTable();
        if (editMode == false) {
            if (document.querySelector('#building').value > 0) {
                table.column(0).visible(true);
                editMode = true;
                table.button('1-1').text("Freeze");
                //create a pop-up that warns the user about editing data
                toastr.warning('The table can now be edited. Click on a field to open its editing function. Note that changes made cannot be undone.')
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
        let modal = document.getElementById('addRoomModal');
        let table = $("#roomsTable").DataTable();
        let buildingSelect = document.querySelector('#building');
        document.getElementById('catHolder').innerHTML = searchCategories("", categories, roomCategories);

        if (buildingSelect.value > 0) {
            table.column('1').order('asc').draw(false);
            modal.style.display = 'block';
            document.getElementById('floorHolder').innerHTML = searchFloors(1, floors);
        }
    }

    //if the user cancels the addition, clear out the form
    function closeAddModal() {
        let modal = document.getElementById('addRoomModal');
        document.getElementById('facility_id').value = null;
        document.getElementById('floor_id').value = 0;
        document.getElementById('room_num').value = null;
        document.getElementById('room_name').value = null;
        document.getElementById('room_area').value = null;
        document.getElementById('uncert_amt').value = null;
        document.getElementById('room_population').value = null;
        document.getElementById('rtype_code').value = null;
        document.getElementById('space_use_name').value = null;
        document.getElementById('active').value = 0;
        document.getElementById('reservable').value = 0;
        modal.style.display = 'none';
    }

    //submit the data to the server and add a row to the table
    function submitAddModal(e) {
        let floorSelect = document.querySelector('#floor');
        let buildingSelect = document.querySelector('#building');

        //default to the first floor if none is choosen
        let floorVal = floorSelect.value;
        if (floorVal == 0) {
            floorVal = 1;
        }
        e.preventDefault();
        let formData = {
            facility_id: document.getElementById('facility_id').value,
            floor_id: document.getElementById('floor_id').value,
            room_num: document.getElementById('room_num').value,
            room_name: document.getElementById('room_name').value,
            room_area: document.getElementById('room_area').value,
            room_population: document.getElementById('room_population').value,
            uncert_amt: document.getElementById('uncert_amt').value,
            building_id: buildingSelect.value,
            rtype_code: document.getElementById('rtype_code').value,
            space_use_name: document.getElementById('space_use_name').value,
            ash61_cat_id: document.getElementById('ash61_cat_id').value,
            active: document.getElementById('active').value,
            reservable: document.getElementById('reservable').value,
            dcvsection: 'rooms',
            delete: 0
        };

        //clear out and submit the form
        submit(formData, 0);

        closeAddModal();

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

    //change the length of the table page when the user changes it
    function changePageLength() {
        let table = $('#roomsTable').DataTable();
        let len = document.querySelector('#pageLen').value;
        table.page.len(len).draw(false);
    }

    //Give the dropdowns the proper event listeners
    document.querySelector("#campus").addEventListener("change", updateDropDowns);

    document.querySelector("#building").addEventListener("change", updateTable);

    document.querySelector("#floor").addEventListener("change", updateTable);


    //events for file sumbission and row deletetion
    document.querySelector("#cancelRoomsDel",).addEventListener('click', cancelDelete);

    document.querySelector("#confirmRoomsDel",).addEventListener('click', confirmDelete);

    document.querySelector("#cancelButton").addEventListener('click', closeAddModal);

    document.querySelector("#roomForm").addEventListener('submit', submitAddModal);

    document.querySelector('.close').addEventListener('click', closeAddModal);

    document.querySelector("#pageLen").addEventListener('change', changePageLength);

    loadJsonXHR("src/test_json/cleaner.json", 'init');
}

document.addEventListener('DOMContentLoaded', init);