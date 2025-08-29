//for security reasons, this whole script is a function
function init() {
    //Global Variables
    let editMode = false;

    let selectedRow = null;

    let selectedGroup = null;

    let configOptions;

    let oldVal;

    let newVal;

    let cellSelected = false;

    //required values for editing table rows
    let requiredValuesXref = ["zone_id", "room_id", "population"];

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

    //search for duplicate xrefs in the table
    function searchDuplicates(newData, tableData) {
        for (let i = 0; i < tableData.length; i++) {
            if (newData.facility_id == tableData[i].facility_id && newData.zone_name == tableData[i].zone_name) {
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

    //autocalculate values of the group passed to this
    function autoCalculate(id) {
        let table = $("#xrefTable").DataTable();

        //get all data in the group
        let group = table.rows(function (idx, data, node) {
            return data.facility_id == id ?
                true : false;
        });

        //loop though and add together group data
        for (let j = 0; j < group.data().length; j++) {
            group.data()[j].xref_area = (group.data()[j]['room_area'] / group.data().length) * 1;
            group.data()[j].xref_population = ((group.data()[j]['room_population'] + group.data()[j]['uncert_amt']) / group.data().length) * 1;
            group.data()[j].pr_percent = (1 / group.data().length) * 1;

            submit(group.data()[j], 0);
        }

        updateTable();
    }

    //load the table
    function loadTable() {
        //pre-load buildings
        if(configOptions.response.docs.defaultCampusId > 0){
            loadJsonXHR("/json/buildingsbycampus/" + configOptions.response.docs.defaultCampusId + ".json", "buildings");
        }
        $("#xrefTable").DataTable({
            destroy: true, info: false, responsive: true, select: { items: 'cell', style: 'single' }, blurable: true, autoWidth: false, pageLength: 12,
            dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Zones_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Zones_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Zones_' + getFileDate(), text: 'PDF' }] }],
            language: {
                emptyTable: "Please Select a Campus and Building",
                zeroRecords: "No Matching Room/Zone Connections in Selected Building"
            },
            orderFixed: [5, 'asc'],
            //enable row groupings and aggregate their data
            rowGroup: {
                className: 'custom-group',
                dataSrc: 'facility_id',
                //this renders the row group headings
                startRender: function (rows, group) {
                    //grab arrays of group data values
                    let groupPopulation = rows.data()[0]['room_population'];
                    let groupArea = rows.data()[0]['room_area'];

                    let uncert;

                    //get the uncert amount of the row
                    if (rows.data()[0]['uncert_amt'] != null) {
                        uncert = rows.data()[0]['uncert_amt'];
                    }
                    else {
                        uncert = 0;
                    }
                    
                    //generate a row element
                    let tr = document.createElement('tr');

                    areaSum = 0;
                    popSum = 0;
                    prSum = 0;

                    //aggregate group data
                    for (let i = 0; i < rows.data().length; i++) {
                        areaSum += rows.data()[i]['xref_area'] * 1;
                        popSum += rows.data()[i]['xref_population'] * 1;
                        prSum += rows.data()[i]['pr_percent'] * 1;
                    }

                    //begin adding cells to the grouping row
                    if (editMode == true) {
                        addCell(tr, '<i class="fas fa-calculator"></i>', 1, 'xref-group-start', group);
                        addCell(tr, rows.data()[0]['facility_id'], 1);
                    }
                    else {
                        addCell(tr, rows.data()[0]['facility_id'], '', 'xref-group-start');
                    }

                    //groupArea comes in as a string, and areaSum needs to be rounded to two decimals
                    if (areaSum.toFixed(2) != Number(groupArea)) {
                        addCell(tr, "Area: " + groupArea, '', 'makeRed');
                    }
                    else {
                        addCell(tr, "Area: " + groupArea, '');
                    }
                    //if zone values don't equal the max allowable by the room, make them red
                    if (popSum.toFixed(2) != groupPopulation + uncert) {
                        addCell(tr, 'Population: ' + groupPopulation + ' (+' + uncert + ' Uncertainty)', 1, 'makeRed');
                    }
                    else {
                        addCell(tr, 'Population: ' + groupPopulation + ' (+' + uncert + ' Uncertainty)', 1);
                    }

                    if (prSum.toFixed(2) > 1 || prSum.toFixed(2) < 0.99 || prSum == null) {
                        addCell(tr, "Total Pr/Pz: " + prSum, '', 'makeRed');
                    }
                    else {
                        addCell(tr, "Total Pr/Pz: 1", '');
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
            rowId: 'id',
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
                    data: 'zone_name',
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
                    data: "xref_area",
                    //this gives us a span that we can replace with an input box
                    render: function (data) {
                        if (data != null) {
                            return `<span class="inputable">${data}</span>`
                        }
                        else {
                            return `<span class="inputable">0</span>`
                        }
                    }
                },
                {
                    targets: 3,
                    data: "xref_population",
                    render: function (data) {
                        if (data != null) {
                            return `<span class="inputable">${data}</span>`
                        }
                        else {
                            return `<span class="inputable">0</span>`
                        }
                    }
                },
                {
                    targets: 4,
                    data: "pr_percent",
                    render: function (data) {
                        if (data != null) {
                            return `<span class="generated">${data}</span>`
                        }
                        else {
                            data = 0;
                            return `<span class="generated">0</span>`
                        }
                    }
                },
                {
                    //hidden column used for sorting
                    targets: 5,
                    data: 'room_id',
                    visible: false,
                },
                {
                    //hidden column used for sorting
                    targets: 6,
                    data: 'room_num',
                    visible: false,
                }
            ],
        });

        //get a reference to the table
        let table = $('#xrefTable').DataTable();

        //check to see if the user has the ability to edit the table and add the edit buttons to the toolbar
        if ((configOptions.response.docs.canEditAll == 'on' || configOptions.response.docs.canEditXrefs == 'on') && configOptions.response.docs.current_user.role * 1 > 1) {
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
                let dat = table.cell(this).node().lastChild.firstChild.value * 1;
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
                        if (checkValues(requiredValuesXref, table.rows(this).data()[0])) {
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
            $("#xrefTable").DataTable().ajax.url("/json/zonesxrooms/" + buildingID + ".json").load(null, false);
        }
        else {
            clearTableByID("#xrefTable");
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
            clearTableByID("#xrefTable");
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
            clearTableByID('#xrefTable');
        }
    }

    //load building data based on the campus selected
    function loadBuildingsByCampus() {
        updateBuildingDropDown(0);
        clearTableByID("#xrefTable");

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
            json.dcvsection = "xref";

            if (json.room_population >= 0 && json.uncert_amt >= 0) {
                json.pr_percent = (json.xref_population / (json.room_population + json.uncert_amt)) * 1;
            }

            json.delete = deleteData;

            //Convert JSON data to string
            let data = JSON.stringify(json);

            //Send data
            xhr.send(data);
        }
    }

    //if the user clicks on a trash icon, bring up the dialog box to confirm
    $("#xrefTable tbody").on('click', '.fa-trash-alt', function () {
        let table = $("#xrefTable").DataTable();
        document.querySelector(".alertTextDel").innerHTML = `<p>Delete cross-reference data for zone ${table.row($(this).parents('tr')).data().zone_name}?`;
        document.querySelector("#alertXrefDel").showModal();
        selectedRow = table.row($(this).parents('tr'))
    })

    //if the user clicks on a trash icon, bring up the dialog box to confirm
    $("#xrefTable tbody").on('click', '.fa-calculator', function () {
        selectedGroup = this.parentNode.attributes.room.value;
        openCalcModal();
    })

    //called if the user cancels the deletion
    function cancelDelete() {
        let dialogElement = document.querySelector("#alertXrefDel");
        selectedRow = null;
        dialogElement.close();
    }

    //remove the selected row and send it's data to the server tagged for deletion
    function confirmDelete() {
        let dialogElement = document.querySelector("#alertXrefDel");
        submit(selectedRow.data(), 1);
        selectedRow.remove().draw(false);
        selectedRow = null;
        dialogElement.close();
    }

    //open the auto-calculate modal
    function openCalcModal() {
        if (document.querySelector("#building").value > 0) {
            document.querySelector(".alertTextAuto").innerHTML = "<p>Recalculate cross-reference data for Room " + selectedGroup + " ?</p><p>This will overwrite any manual edits to this group.</p>";
            document.querySelector("#alertXrefAuto").showModal();
        }
    }

    //cancel autocalculation
    function cancelAuto() {
        let dialogElement = document.querySelector("#alertXrefAuto");
        dialogElement.close();
    }

    //confirm auto-calculation
    function confirmAuto() {
        autoCalculate(selectedGroup);
        let dialogElement = document.querySelector("#alertXrefAuto");
        dialogElement.close();
    }

    //allows for the editing of data
    function editModeToggle() {
        let table = $("#xrefTable").DataTable();
        if (editMode == false) {
            if (document.querySelector('#building').value > 0) {
                let groupings = document.querySelectorAll('.xref-group-start');
                groupings.forEach((cell) => cell.colSpan = 2);
                table.column(0).visible(true);
                editMode = true;
                table.button('1-1').text("Freeze");
                //create a pop-up that warns the user about editing data
                toastr.warning('The table can now be edited. Note that changes made cannot be undone.');
                table.draw(false);
            }
        }
        else {
            table.column(0).visible(false);
            let groupings = document.querySelectorAll('.xref-group-start');
            groupings.forEach((cell) => cell.colSpan = 1);
            editMode = false;
            table.button('1-1').text("Modify");
            table.draw(false);
        }
    }

    //opens the modal for adding rows to the table
    function openAddModal() {
        let modal = document.getElementById('addXrefModal');
        let table = $("#xrefTable").DataTable();
        let buildingSelect = document.querySelector('#building');

        if (buildingSelect.value > 0) {
            modal.style.display = 'block';
        }
    }

    //if the user cancels the addition, clear out the form
    function closeAddModal() {
        let modal = document.getElementById('addXrefModal');
        document.getElementById('facility_id').value = null;
        document.getElementById('zone_name').value = null;
        document.getElementById('xref_area').value = null;
        document.getElementById('xref_population').value = null;

        modal.style.display = 'none';
    }

    //submit the data to the server
    //instead of adding a row to the table, we simply reload the table
    //this is so the row grouping is applied correctly
    function submitAddModal(e) {
        let table = $("#xrefTable").DataTable();
        let buildingSelect = document.querySelector('#building');
        e.preventDefault();
        let formData = {
            facility_id: document.getElementById('facility_id').value,
            zone_name: document.getElementById('zone_name').value,
            xref_area: document.getElementById('xref_area').value,
            xref_population: document.getElementById('xref_population').value,
            pr_percent: 0,
            building_id: parseInt(buildingSelect.value),
            dcvsection: 'xref',
            delete: 0
        };

        //only send the new data to the server if the connection doesn't already exist.
        if (searchDuplicates(formData, table.rows().data())) {
            submit(formData, 0);
            updateTable();
            closeAddModal();
        }
        else {
            toastr.warning('That connection already exists!');
        }
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
        let table = $('#xrefTable').DataTable();
        let len = document.querySelector('#pageLen').value;
        table.page.len(len).draw(false);
    }



    //Give the dropdowns the proper event listeners
    document.querySelector("#campus").addEventListener("change", updateDropDowns);

    document.querySelector("#building").addEventListener("change", updateTable);

    //events for row addition, deletetion, and auto-calculation
    document.querySelector("#cancelXrefDel",).addEventListener('click', cancelDelete);

    document.querySelector("#confirmXrefDel",).addEventListener('click', confirmDelete);

    document.querySelector("#cancelButton").addEventListener('click', closeAddModal);

    document.querySelector("#xrefForm").addEventListener('submit', submitAddModal);

    document.querySelector('.close').addEventListener('click', closeAddModal);

    document.querySelector("#cancelXrefAuto",).addEventListener('click', cancelAuto);

    document.querySelector("#confirmXrefAuto",).addEventListener('click', confirmAuto);

    document.querySelector("#pageLen").addEventListener('change', changePageLength);

    loadJsonXHR("/json/campuses/all.json", "init");
}

document.addEventListener('DOMContentLoaded', init);