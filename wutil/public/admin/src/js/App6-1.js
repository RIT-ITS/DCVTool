//for security reasons, this whole script is a function
function init() {
  //global variables
  let types = null;

  let editMode = false;

  let selectedRow = null;

  let configOptions;

  let oldVal;

  let newVal;

  let cellSelected = false;

  //required values for editing table rows
  let requiredValues61 = ["category", "ppl_oa_rate", "area_oa_rate", "occ_density", "occ_stdby_allowed", "type"];

  //loads data from .json files
  async function loadJsonXHR() {
    let url = "/json/ashrae6-1types/all.json";

    try {
      let response = await fetch(url);

      let configResponse = await fetch("/json/configVars/1.json");

      if (!response.ok) {
        if (response.status == 404) console.log("404 Error!");
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      let json = await response.json();

      configOptions = await configResponse.json();

      Object.freeze(configOptions.response.docs.current_user);
      Object.freeze(configOptions.response.docs);

      //load the table once the categories are loaded
      if (json.response.docs[0].length > 0) {
        if (json.response.docs[0][0].type != null) {
          types = json;
          loadTable();
        }
      }
    }
    catch (e) {
      console.log(`In catch with e = ${e}`);
    }
  }

  //search for and return a type based on id
  function searchTypeName(id) {
    if (id > 0) {
      for (let i = 0; i < types.response.docs[0].length; i++) {
        if (types.response.docs[0][i].id == id) {
          return types.response.docs[0][i].type;
        }
      }
    }
    else {
      return "";
    }
  }

  //returns a selector full of all possible categories, with the cell's current category already selected.
  function searchTypes(id, types) {
    let selector = `<select class="input customSelect customInput" name='type' id='type' required>`

    for (let i = 0; i < types.response.docs[0].length; i++) {
      if (id == types.response.docs[0][i].id) {
        selector += `<option value=${types.response.docs[0][i].id} selected='selected'>${types.response.docs[0][i].type}</option>`
      }
      else {
        selector += `<option value=${types.response.docs[0][i].id}>${types.response.docs[0][i].type}</option>`
      }
    }
    selector += `</select>`
    return selector;
  }

  //reload data into the table
  function updateTable() {
    $("#ashrae61Table").DataTable().ajax.url("/json/ashrae6-1/all.json").load(null, false);
  }

  //load data into the table.
  function loadTable() {
    $('#ashrae61Table').DataTable({
      destroy: true, responsive: true, info: false, select: { items: 'cell', style: 'single' }, blurable: true, lengthChange: false, autoWidth: false, order: [1, "asc"], pageLength: 12,
      dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Ashrae_62.1_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Ashrae_62.1_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Ashrae_62.1_' + getFileDate(), text: 'PDF' }] }],
      language: {
        zeroRecords: "No Matching Occupancy Categories Found"
      },
      ajax: {
        url: "/json/ashrae6-1/all.json",
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
          data: 'type',
          //this gives is a span that we can click on and replace with a dropdown
          render: function (data) {
            if (data != null) {
              return `<span class="dropdownType">${searchTypeName(data)}</span>`
            }
            else {
              return `<span class="dropdownType"> </span>`
            }
          }
        },
        {
          targets: 3,
          data: 'category',
          //this gives us a span that we can click on and replace with a text box
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
          data: 'ppl_oa_rate',
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
          data: 'area_oa_rate',
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
          data: 'occ_density',
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
          data: 'occ_stdby_allowed',
          //this gives is a span that we can click on and replace with a dropdown
          render: function (data) {
            if (data == 1) {
              return `<span class="dropdown">Yes</span>`
            }
            else {
              return `<span class="dropdown">No</span>`
            }
          }
        },
        {
          targets: 8,
          data: 'notes',
          render: function (data) {
            if (data != null) {
              return `<span class="inputable">${data}</span>`
            }
            else {
              return `<span class="inputable"> </span>`
            }
          }
        }
      ],
    });


    //get a reference to the dataTable
    let table = $('#ashrae61Table').DataTable();

    //check to see if the user has the ability to edit the table and add the edit buttons to the toolbar
    if ((configOptions.response.docs.canEditAll == 'on' || configOptions.response.docs.canEdit61 == 'on') && configOptions.response.docs.current_user.role * 1 > 1) {
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
            document.querySelector(".selected .dropdown").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" name="row-dropdown">
            <option value='1' selected="selected">Yes</option>
            <option value='0'>No</option>
            </select>`;
          }
          else {
            document.querySelector(".selected .dropdown").innerHTML = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" name="row-dropdown">
            <option value='1'>Yes</option>
            <option value='0' selected="selected">No</option>
            </select>`;
          }
          //focus on the new input
          document.querySelector("td select").focus();
          cellSelected = true;
        }
        if (document.querySelector(".selected .dropdownType")) {
          document.querySelector(".selected .dropdownType").innerHTML = searchTypes(dat, types);
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
      //if a cell is selected
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


        //this is the method used to gather data from the modified row to send to the server
        //make sure the selected cell isn't the trash icon, we don't want to send data from a row we may delete
        if (table.cell(this).index().columnVisible != 0) {
          //check to make sure the user actually edited something
          if (String(oldVal) != String(newVal)) {
            //check row data against the required values
            if (checkValues(requiredValues61, table.rows(this).data()[0])) {
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

          //print received data from server
          console.log(this.responseText);
        }
      };

      //Add to the data being sent to the server. Lets the server know what database to update
      json.dcvsection = "ashrae61";

      json.delete = deleteData;

      //Convert JSON data to string
      var data = JSON.stringify(json);

      //Send data
      xhr.send(data);
    }
  }

  //if the user clicks on a trash icon, bring up the dialog box to confirm
  $("#ashrae61Table tbody").on('click', '.fa-trash-alt', function () {
    let table = $("#ashrae61Table").DataTable();
    document.querySelector(".alertTextDel").innerHTML = `<p>Delete data for category ${table.row($(this).parents('tr')).data().category}?</p>`;
    document.querySelector("#alert61Del").showModal();
    selectedRow = table.row($(this).parents('tr'))
  })

  //called if the user cancels the deletion
  function cancelDelete() {
    let dialogElement = document.querySelector("#alert61Del");
    selectedRow = null;
    dialogElement.close();
  }

  //remove the selected row and send  it's data to the server tagged for deletion
  function confirmDelete() {
    let dialogElement = document.querySelector("#alert61Del");
    submit(selectedRow.data(), 1);
    selectedRow.remove().draw(false);
    selectedRow = null;
    dialogElement.close();
  }

  //allows for the editing of data
  function editModeToggle() {
    let table = $("#ashrae61Table").DataTable();
    if (editMode == false) {
      table.column(0).visible(true);
      editMode = true;
      table.button('1-1').text("Freeze");
      //create a pop-up that warns the user about editing data
      toastr.warning('The table can now be edited. Click on a field to open its editing function. Note that changes made cannot be undone.');
    }
    else {
      table.column(0).visible(false);
      table.button('1-1').text("Modify");
      editMode = false;
    }
  }

  //opens the modal for adding rows to the table
  function openAddModal() {
    let modal = document.getElementById('add61Modal');
    let table = $("#ashrae61Table").DataTable();
    table.column('1').order('asc').draw(false);
    modal.style.display = 'block';
    document.getElementById('catHolder').innerHTML = searchTypes(0, types);
  }

  //if the user cancels the addition, clear out the form
  function closeAddModal() {
    let modal = document.getElementById('add61Modal');
    document.getElementById('category').value = null;
    document.getElementById('ppl_oa_rate').value = null;
    document.getElementById('area_oa_rate').value = null;
    document.getElementById('occ_density').value = null;
    document.getElementById('occ_stdby_allowed').value = 0;
    document.getElementById('type').value = null;
    modal.style.display = 'none';
  }

  //submit the data to the server and update the table
  function submitAddModal(e) {
    e.preventDefault();
    let formData = {
      category: document.getElementById('category').value,
      ppl_oa_rate: document.getElementById('ppl_oa_rate').value,
      area_oa_rate: document.getElementById('area_oa_rate').value,
      occ_density: document.getElementById('occ_density').value,
      occ_stdby_allowed: document.getElementById('occ_stdby_allowed').value,
      type: document.getElementById('type').value,
      dcvsection: 'ashrae61',
      notes: '',
      ok: false,
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

  //set the proper event listeners
  document.querySelector("#cancel61Del",).addEventListener('click', cancelDelete);

  document.querySelector("#confirm61Del",).addEventListener('click', confirmDelete);

  document.querySelector("#cancelButton").addEventListener('click', closeAddModal);

  document.querySelector("#ashrae61Form").addEventListener('submit', submitAddModal);

  document.querySelector('.close').addEventListener('click', closeAddModal);

  loadJsonXHR();
}

document.addEventListener('DOMContentLoaded', init);