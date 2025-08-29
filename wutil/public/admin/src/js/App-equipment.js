//for security reasons, this whole script is a function
function init() {
  //global variables

  let editMode = false;

  let selectedRow = null;

  let configOptions;

  let oldVal;

  let newVal;

  let cellSelected = false;

  //required values for editing table rows
  let requiredValuesEquip = ["sysname", "path", "pointtype", "enabled"];

  //load config options and then the table
  async function loadJsonXHR() {

    try {

      let configResponse = await fetch("/json/configVars/1.json");

      if (!configResponse.ok) {
        if (configResponse.status == 404) console.log("404 Error!");
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      configOptions = await configResponse.json();

      Object.freeze(configOptions.response.docs.current_user);
      Object.freeze(configOptions.response.docs);

      loadTable();
    }
    catch (e) {
      console.log(`In catch with e = ${e}`);
    }
  }

  //load data into the table.
  function loadTable() {
    $('#equipmentTable').DataTable({
      destroy: true, responsive: true, info: false, select: { items: 'cell', style: 'single' }, blurable: true, pageLength: 12, autoWidth: false, order: [1, "asc"],
      dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Equipment_Map_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Equipment_Map_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Equipment_Map_' + getFileDate(), text: 'PDF' }] }],
      language: {
        zeroRecords: "No Matching Equipment Maps Found"
      },
      ajax: {
        url: "/json/equipment/all.json",
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
          data: 'sysname',
          //this gives us a span that we can click on and replace with an input box
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
          data: 'path',
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
          data: 'pointtype',
          width: '3.45rem',
          //this gives us a span that we can replace with a dropdown of types
          render: function (data) {
            if (data != null) {
              return `<span class="dropdownType">${data}</span>`
            }
            else {
              return `<span class="dropdownType"> </span>`
            }
          }
        },
        {
          targets: 4,
          data: 'uname',
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
          data: 'description',
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
          data: 'units',
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
          targets: 8,
          data: 'enabled',
          //this gives us a span that we can replace with the enabled states
          render: function (data) {
            if (data == 1) {
              return `<span class="dropdown">Enabled</span>`
            }
            else {
              return `<span class="dropdown">Disabled</span>`
            }
          }
        }
      ],
    });

    //get a reference to the dataTable
    let table = $('#equipmentTable').DataTable();

    //check to see if the user has the ability to edit the table and add the edit buttons to the toolbar
    if ((configOptions.response.docs.canEditAll == 'on' || configOptions.response.docs.canEditEquip == 'on') && configOptions.response.docs.current_user.role * 1 > 2) {
      table.button().add(5, {
        extend: 'collection',
        text: 'Edit',
        autoClose: true,
        buttons: [{ text: 'Add', action: openAddModal }, { text: 'Modify', action: editModeToggle }]
      })
    }

    //call this function when a cell in the table is selected
    table.on('select', function () {
      //if a cell is not currently selected
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
          if (dat == 1) {
            document.querySelector(".selected .dropdown").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" size='1' name="row-dropdown">
            <option value='1' selected="selected">Enabled</option>
            <option value='0'>Disable</option>
            </select>`;
          }
          else {
            document.querySelector(".selected .dropdown").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" size='1' name="row-dropdown">
            <option value='1'>Enable</option>
            <option value='0' selected="selected">Disabled</option>
            </select>`;
          }
          //focus on the new input
          document.querySelector("td select").focus();
          cellSelected = true;
        }

        //if it's a dropdown span for the type column, allow for different select options
        if (document.querySelector(".selected .dropdownType")) {
          if (dat == "trend") {
            document.querySelector(".selected .dropdownType").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" size='1' name="row-dropdown-type">
            <option value="trend" selected="selected">trend</option>
            <option value="pv">pv</option>
            <option value="meter">meter</option>
            </select>`;
          }
          if (dat == "pv") {
            document.querySelector(".selected .dropdownType").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" size='1' name="row-dropdown-type">
          <option value="trend">trend</option>
          <option value="pv" selected="selected">pv</option>
          <option value="meter">meter</option>
            </select>`;
          }
          if (dat == "meter") {
            document.querySelector(".selected .dropdownType").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" size='1' name="row-dropdown-type">
          <option value="trend">trend</option>
          <option value="pv">pv</option>
          <option value="meter" selected="selected">meter</option>
            </select>`;
          }
          if (dat == null) {
            document.querySelector(".selected .dropdownType").innerHTML = `<select style="border: 1px solid #aaa" class="form-control input" size='1' style='width: 100%;' class='form-select' row-dropdown-type>
          <option value="trend">trend</option>
          <option value="pv">pv</option>
          <option value="meter">meter</option>
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

    //call this when the input is blurred
    table.on('blur', 'td', function () {
      //if a cell isn't selected
      if (cellSelected == true) {
        let dat = table.cell(this).node().lastChild.firstChild.value;
        newVal = dat;

        //replace the input with the data entered
        if (dat != null && dat != "") {
          table.cell(this).data(dat);
        }
        else {
          table.cell(this).data(null);
          newVal = null;
        }


        //this is the (likely) method to be used to gather data from the modified row to send to the server
        //make sure the selected cell isn't the trash icon, we don't want to send data from a row we may delete
        if (table.cell(this).index().columnVisible != 0) {
          //check to make sure the user actually edited something
          if (String(oldVal) != String(newVal)) {
            //check row data against the required values
            if (checkValues(requiredValuesEquip, table.rows(this).data()[0])) {
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

  //reload data into the table
  function updateTable() {
    $("#equipmentTable").DataTable().ajax.url("/json/equipment/all.json").load(null, false);
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
    if (configOptions.response.docs.current_user.role > 2) {

      let xhr = new XMLHttpRequest();
      let url = "/api/reference/update.php";

      //Open connection
      xhr.open("POST", url, true);

      //Set request header
      xhr.setRequestHeader("Content-Type", "application/json");

      // Create callback
      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {

          // Print received data from server
          console.log(this.responseText);
        }
      };

      //Add to the data being sent to the server. Lets the server know what database to update
      json.dcvsection = "equipment_map";

      json.delete = deleteData;

      //Convert JSON data to string
      var data = JSON.stringify(json);

      //Send data
      xhr.send(data);
    }
  }

  //if the user clicks on a trash icon, bring up the dialog box to confirm
  $("#equipmentTable tbody").on('click', '.fa-trash-alt', function () {
    let table = $("#equipmentTable").DataTable();
    document.querySelector(".alertTextDel").innerHTML = `<p>Delete data for row ${table.row($(this).parents('tr')).data().id}?</p>`;
    document.querySelector("#alertEqDel").showModal();
    selectedRow = table.row($(this).parents('tr'))
  });

  //called if the user cancels the deletion
  function cancelDelete() {
    let dialogElement = document.querySelector("#alertEqDel");
    selectedRow = null;
    dialogElement.close();
  }

  //remove the selected row and send it to the server tagged to deletion
  function confirmDelete() {
    let dialogElement = document.querySelector("#alertEqDel");
    //since we don't want the user deleting equipment maps disable deleting from the equipment_map
    //submit(selectedRow.data(), 1);
    //selectedRow.remove().draw(false);
    selectedRow = null;
    dialogElement.close();
  }

  //allows for the editing of data
  function editModeToggle() {
    let table = $("#equipmentTable").DataTable();
    if (editMode == false) {
      //since we don't want the user to be able to delete equipment maps, disable the code to show the deletion column
      //table.column(0).visible(true);
      editMode = true;
      table.button('1-1').text("Freeze");

      //create a pop-up that warns the user about editing data
      toastr.warning('The table can now be edited. Click on a field to open its editing function. Note that changes made cannot be undone.')
    }
    else {
      table.button('1-1').text("Modify");

      //table.column(0).visible(false);
      editMode = false;
    }
  }

  //opens the modal for adding rows to the table
  function openAddModal() {
    let modal = document.getElementById('addEquipmentModal');
    let table = $("#equipmentTable").DataTable();
    table.column('1').order('asc').draw(false);
    modal.style.display = 'block';

  }

  //if the user cancels the addition, clear out the form
  function closeAddModal() {
    let modal = document.getElementById('addEquipmentModal');
    document.getElementById('sysname').value = null;
    document.getElementById('path').value = null;
    document.getElementById('pointtype').value = null;
    document.getElementById('enabled').value = 0;
    document.getElementById('uname').value = null;
    document.getElementById('desc').value = null;
    document.getElementById('path').value = null;
    document.getElementById('uname').value = null;
    document.getElementById('units').value = null;

    modal.style.display = 'none';
  }

  //submit the data to the server and add a row to the table
  function submitAddModal(e) {
    e.preventDefault();
    let formData = {
      sysname: document.getElementById('sysname').value,
      path: document.getElementById('path').value,
      pointtype: document.getElementById('pointtype').value,
      enabled: document.getElementById('enabled').value,
      dcvsection: 'equipment',
      uname: document.getElementById('uname').value,
      description: document.getElementById('desc').value,
      units: document.getElementById('units').value,
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
    let table = $('#equipmentTable').DataTable();
    let len = document.querySelector('#pageLen').value;
    table.page.len(len).draw(false);
  }

  //set the proper event listeners
  document.querySelector("#cancelEqDel",).addEventListener('click', cancelDelete);

  document.querySelector("#confirmEqDel",).addEventListener('click', confirmDelete);

  document.querySelector("#cancelButton").addEventListener('click', closeAddModal);

  document.querySelector("#equipmentForm").addEventListener('submit', submitAddModal);

  document.querySelector('.close').addEventListener('click', closeAddModal);

  document.querySelector("#pageLen").addEventListener('change', changePageLength);

  loadJsonXHR();
}

document.addEventListener('DOMContentLoaded', init);