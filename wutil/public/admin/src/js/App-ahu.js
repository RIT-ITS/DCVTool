//Potential TODO: pull any generic functions into a 'utilities' or 'adminUtils' script an import them

//for security reasons, this whole script is a function
function init() {

    //Global Variables
    let editMode = false;

    let selectedRow = null;

    let configOptions;

    let oldVal;

    let newVal;

    let cellSelected = false;

    //required values for editing table rows
    let requiredValuesAHUs = ["ahu_name", "ahu_code"];

    //loads data from .json files
    async function loadJsonXHR() {
        try {
            let configResponse = await fetch("/json/configVars/1.json");

            configOptions = await configResponse.json();

            Object.freeze(configOptions.response.docs.current_user);
            Object.freeze(configOptions.response.docs);

            loadTable();

        } catch (e) {
            console.log(`In catch with e = ${e}`);
        }
    }

    //load the table
    function loadTable() {
        $("#ahuTable").DataTable({
            destroy: true, info: false, responsive: true, select: { items: 'cell', style: 'single' }, blurable: true, lengthChange: false, autoWidth: false, order: [1, "asc"], pageLength: 12,
            dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_AHUs_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_AHUs_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_AHUs_' + getFileDate(), text: 'PDF' }] }],
            language: {
                zeroRecords: "No Matching AHUs Found"
            },
            ajax: {
                url: "/json/ahu/all.json",
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
                    data: 'ahu_name',
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
                    targets: 2,
                    data: 'ahu_code',
                    render: function (data) {
                        if (data != null) {
                            return `<span class="inputable">${data}</span>`
                        }
                        else {
                            return `<span class="inputable"> </span>`
                        }
                    }
                },
            ],
        });

        //get a reference to the table
        let table = $('#ahuTable').DataTable();

        //check to see if the user has the ability to edit the table and add the edit buttons to the toolbar
        if ((configOptions.response.docs.canEditAll == 'on' || configOptions.response.docs.canEditAhus == 'on') && configOptions.response.docs.current_user.role * 1 > 1) {
            table.button().add(5, {
                extend: 'collection',
                text: 'Edit',
                autoClose: true,
                buttons: [{ text: 'Add', action: openAddModal }, { text: 'Modify', action: editModeToggle }]
            })
        }

        //Call this when a cell is selected
        table.on('select', function (e, dt, type, indexes) {
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
                //if the user clicks on a generated value, let them know it can't be edited
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


                //this method gathers data from the modified row to send to the server
                //make sure the selected cell isn't the trash icon, we don't want to send data from a row we may delete
                if (table.cell(this).index().columnVisible != 0) {
                    //check to make sure the user actually edited something
                    if (String(oldVal) != String(newVal)) {
                        //check row data against the required values
                        if (checkValues(requiredValuesAHUs, table.rows(this).data()[0])) {
                            //submit the data to the server tagged for addition.
                            submit(table.rows(this).data()[0], 0);
                            updateTable();
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
        })

        //in case the user selects a new cell before the table has re-drawn, set cellSelected to false after every draw.
        table.on('draw', function () {
            cellSelected = false;
        });
    }

    //update the table based on input. This is only used after the page loads.
    function updateTable() {
        $("#ahuTable").DataTable().ajax.url("/json/ahu/all.json").load(null, false);
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
            json.dcvsection = "ahu";

            json.delete = deleteData;

            //Convert JSON data to string
            let data = JSON.stringify(json);

            //Send data
            xhr.send(data);
        }
    }

    //if the user clicks on a trash icon, bring up the dialog box to confirm
    $("#ahuTable tbody").on('click', '.fa-trash-alt', function () {
        let table = $("#ahuTable").DataTable();
        document.querySelector(".alertTextDel").innerHTML = `<p>Delete data for ${table.row($(this).parents('tr')).data().ahu_name}?</p>`;
        document.querySelector("#alertAHUDel").showModal();
        selectedRow = table.row($(this).parents('tr'))
    })

    //called if the user cancels the deletion
    function cancelDelete() {
        let dialogElement = document.querySelector("#alertAHUDel");
        selectedRow = null;
        dialogElement.close();
    }

    //remove the selected row and send it's data to the server tagged for deletion
    function confirmDelete() {
        let dialogElement = document.querySelector("#alertAHUDel");
        submit(selectedRow.data(), 1);
        selectedRow.remove().draw(false);
        selectedRow = null;
        dialogElement.close();
        updateTable();
    }

    //allows for the editing and deletion of data
    function editModeToggle() {
        let table = $("#ahuTable").DataTable();
        if (editMode == false) {
            table.column(0).visible(true);
            editMode = true;
            table.button('1-1').text("Freeze");
            //create a pop-up that warns the user about editing data
            toastr.warning('The table can now be edited. Note that changes made cannot be undone.')
        }
        else {
            table.column(0).visible(false);
            table.button('1-1').text("Modify");
            editMode = false;
        }
    }

    //opens the modal for adding rows to the table
    function openAddModal() {
        let modal = document.getElementById('addAHUModal');
        let table = $("#ahuTable").DataTable();

        table.column('1').order('asc').draw(false);
        modal.style.display = 'block';

    }

    //if the user cancels the addition, clear out the form
    function closeAddModal() {
        let modal = document.getElementById('addAHUModal');
        document.getElementById('ahu_name').value = null;
        document.getElementById('ahu_code').value = null;
        modal.style.display = 'none';
    }

    //submit the data to the server and update the table
    function submitAddModal(e) {
        e.preventDefault();
        let formData = {
            ahu_name: document.getElementById('ahu_name').value,
            ahu_code: document.getElementById('ahu_code').value,
            dcvsection: 'floors',
            delete: 0
        };

        //clear out the form
        submit(formData, 0);

        //console.log('Form Data:', formData);

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


    //events for row addition and deletetion
    document.querySelector("#cancelAHUDel",).addEventListener('click', cancelDelete);

    document.querySelector("#confirmAHUDel",).addEventListener('click', confirmDelete);

    document.querySelector("#cancelButton").addEventListener('click', closeAddModal);

    document.querySelector("#ahuForm").addEventListener('submit', submitAddModal);

    document.querySelector('.close').addEventListener('click', closeAddModal);

    loadJsonXHR();
}
//when the page loads, load the content needed for rendering table data
document.addEventListener('DOMContentLoaded', init);

