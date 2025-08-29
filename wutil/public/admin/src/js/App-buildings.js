//for security reasons, this whole script is a function
function init() {
  //global variables
  let campusData;

  let editMode = false;

  let selectedRow = null;

  let configOptions;

  let oldVal;

  let newVal;

  let cellSelected = false;

  //required values for editing rows
  let requiredValuesBuildings = ["bldg_num", "bldg_name", "campus_id", "facility_code"];

  //loads data from .json files and sends it to the correct function
  //used for loading campuses for the dropdowns and config options
  async function loadJsonXHR() {
    try {

      let response = await fetch("/json/campuses/all.json");

      if (!response.ok) {
        if (response.status == 404) console.log("404 Error!");
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      //only get the configs in the scope of editing
      let configResponse = await fetch("/json/configVars/1.json");

      configOptions = await configResponse.json();

      Object.freeze(configOptions.response.docs.current_user);
      Object.freeze(configOptions.response.docs);

      campusData = await response.json();

      loadCampusDropDown(campusData);
      loadTable();

    }
    catch (e) {
      console.log(`In catch with e = ${e}`);
    }

  }

  //returns a selector full of all possible campuses, with the cell's current campus already selected.
  function searchCampuses(id, campuses) {
    let campus = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" name='campus' id='campus'> <option value='0'> </option>`
    for (let i = 0; i < campuses.response.docs[0].length; i++) {
      if (id == campuses.response.docs[0][i].id) {
        campus += `<option value=${campuses.response.docs[0][i].id} selected='selected'>${campuses.response.docs[0][i].campus_name}</option>`
      }
      else {
        campus += `<option value=${campuses.response.docs[0][i].id}>${campuses.response.docs[0][i].campus_name}</option>`
      }
    }
    campus += `</select>`
    return campus;
  }

  //returns a campus name based on the id passed to it.
  function searchCampusName(id, campuses) {
    let campus = "";
    for (let i = 0; i < campuses.response.docs[0].length; i++) {
      if (id == campuses.response.docs[0][i].id) {
        campus = campuses.response.docs[0][i].campus_name;
      }
    }
    return campus;
  }

  //Loads campuses into the dropdown
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
  }

  //load the table
  function loadTable() {
    $("#buildingTable").DataTable({
      destroy: true, info: false, responsive: true, select: { items: 'cell', style: 'single' }, blurable: true, lengthChange: false, autoWidth: false, order: [1, "asc"], pageLength: 12,
      dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Buildings_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Buildings_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Buildings_' + getFileDate(), text: 'PDF' }] }],
      language: {
        emptyTable: "Please Select a Campus",
        zeroRecords: "No Matching Buildings Found"
      },
      ajax: {
        //load a dummy .json file until we get the real deal
        url: "/json/buildingsbycampus/" + configOptions.response.docs.defaultCampusId + ".json",
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
          data: 'bldg_num',
          //this gives us a function that we can replace with an input box
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
          data: 'facility_code',
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
          data: 'bldg_name',
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
          data: 'short_desc',
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
          data: 'campus_id',
          //this gives us a span that we can replace with a dropdown
          render: function (data) {
            if (data != null) {
              return `<span class="dropdown">${searchCampusName(data, campusData)}</span>`
            }
            else {
              return `<span class="dropdown"> </span>`
            }
          }

        },
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
    let table = $('#buildingTable').DataTable();

    //check to see if the user has the ability to edit the table and add the edit buttons to the toolbar
    if ((configOptions.response.docs.canEditAll == 'on' || configOptions.response.docs.canEditBuildings == 'on') && configOptions.response.docs.current_user.role * 1 > 1) {
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
            document.querySelector(".selected .inputable").innerHTML = `<input class="input" type="text" style="width: 100%;" name="row-text-box" value="${dat}"></input>`;
          }
          else {
            document.querySelector(".selected .inputable").innerHTML = `<input class="input" type="text" style="width: 100%;" name="row-text-box" value=""></input>`;
          }
          //focus on the new input
          document.querySelector("td input").focus();
          cellSelected = true;
        }

        //if the cell has a .dropdownActive span, replace it with the proper dropdown
        if (document.querySelector(".selected .dropdownActive")) {
          if (dat == 1) {
            document.querySelector(".selected .dropdownActive").innerHTML = `<select style="border: 1px solid #aaa; width: 100%" class="form-control input form-select" size='1' name="row-dropdown-active">
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
        if (document.querySelector(".selected .dropdown")) {
          document.querySelector(".selected .dropdown").innerHTML = searchCampuses(dat, campusData);
          //focus on the new input
          document.querySelector("td select").focus();
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


        //this is the method used to gather data from the modified row to send to the server
        //make sure the selected cell isn't the trash icon, we don't want to send data from a row we may delete
        if (table.cell(this).index().columnVisible != 0) {
          //check to make sure the user actually edited something
          if (String(oldVal) != String(newVal)) {
            //check row data against the required values
            if (checkValues(requiredValuesBuildings, table.rows(this).data()[0])) {
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

  //load/reload new data into the table based on the value of the campus selector
  function updateTable() {
    let campusSelect = document.querySelector("#campus");

    if (campusSelect.value > 0) {
      $("#buildingTable").DataTable().ajax.url("/json/buildingsbycampus/" + campusSelect.value + ".json").load(null, false);
    }
    else {
      clearTableByID("#buildingTable");
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
      json.dcvsection = "buildings";

      json.delete = deleteData;

      //Convert JSON data to string
      let data = JSON.stringify(json);

      //Send data
      xhr.send(data);
    }
  }

  //if the user clicks on a trash icon, bring up the dialog box to confirm deletetion
  $("#buildingTable tbody").on('click', '.fa-trash-alt', function () {
    let table = $("#buildingTable").DataTable();
    document.querySelector(".alertTextDel").innerHTML = `<p>Delete data for ${table.row($(this).parents('tr')).data().bldg_name}?</p> <p>This will also delete any floors and rooms in this building.</p>`;
    document.querySelector("#alertBuildingDel").showModal();
    selectedRow = table.row($(this).parents('tr'))
  })

  //called if the user cancels the deletion
  function cancelDelete() {
    let dialogElement = document.querySelector("#alertBuildingDel");
    selectedRow = null;
    dialogElement.close();
  }

  //remove the selected row and send it's data to the server tagged for deletion
  function confirmDelete() {
    let dialogElement = document.querySelector("#alertBuildingDel");
    submit(selectedRow.data(), 1);
    selectedRow.remove().draw(false);
    selectedRow = null;
    dialogElement.close();
  }

  //allows for the editing and deletion of data
  function editModeToggle() {

    let table = $("#buildingTable").DataTable();
    if (editMode == false) {
      if (document.querySelector('#campus').value > 0) {
        table.column(0).visible(true);
        editMode = true;
        table.button('1-1').text("Freeze");

        //create a pop-up that warns the user about editing data
        toastr.warning('The table can now be edited. Click on a field to open its editing function. Note that changes made cannot be undone.');
      }
    }
    else {
      table.button('1-1').text("Modify");
      table.column(0).visible(false);
      editMode = false;
    }
  }

  //opens the modal for adding rows to the table
  function openAddModal() {
    let modal = document.getElementById('addBuildingModal');
    let table = $("#buildingTable").DataTable();
    let campusSelect = document.querySelector('#campus');

    if (campusSelect.value > 0) {
      table.column('1').order('asc').draw(false);
      modal.style.display = 'block';
    }
  }

  //if the user cancels the addition, clear out the form
  function closeAddModal() {
    let modal = document.getElementById('addBuildingModal');
    document.getElementById('bldg_num').value = null;
    document.getElementById('bldg_name').value = null;
    document.getElementById('facility_code').value = null;
    document.getElementById('active').value = 0;
    document.getElementById('short_desc').value = null;
    modal.style.display = 'none';
  }

  //submit the data to the server and update the table
  function submitAddModal(e) {
    let campusSelect = document.querySelector('#campus');
    e.preventDefault();
    let formData = {
      bldg_num: document.getElementById('bldg_num').value,
      bldg_name: document.getElementById('bldg_name').value,
      campus_id: parseInt(campusSelect.value),
      facility_code: document.getElementById('facility_code').value,
      active: document.getElementById('active').value,
      short_desc: document.getElementById('short_desc').value,
      dcvsection: 'buildings',
      delete: 0
    };

    //clear out the form
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

  //Give the dropdown the proper event listener
  document.querySelector("#campus").addEventListener("change", updateTable);

  //events for file sumbission and row deletetion
  document.querySelector("#cancelBuildingDel",).addEventListener('click', cancelDelete);

  document.querySelector("#confirmBuildingDel",).addEventListener('click', confirmDelete);

  document.querySelector("#cancelButton").addEventListener('click', closeAddModal);

  document.querySelector("#buildingForm").addEventListener('submit', submitAddModal);

  document.querySelector('.close').addEventListener('click', closeAddModal);

  //load XHR Data
  loadJsonXHR();
}

document.addEventListener('DOMContentLoaded', init);