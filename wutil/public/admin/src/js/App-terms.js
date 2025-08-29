//for security reasons, this whole script is a function
function init() {
  //global variables

  let editMode = false;

  let selectedRow = null;

  let configOptions;

  let oldVal;

  let newVal;

  let cellSelected = false;

  //required values for editing and adding table rows
  let requiredValuesTerms = ["term_name", "term_start", "term_end", "term_code"];

  //load config options and then load the table
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

  //load data into the table. This is done here because we need to wait for the types to load
  function loadTable() {
    $('#termsTable').DataTable({
      destroy: true, responsive: true, info: false, select: { items: 'cell', style: 'single' }, blurable: true, lengthChange: false, autoWidth: false, order: [3, "asc"], pageLength: 12,
      dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Terms_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Terms_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Terms_' + getFileDate(), text: 'PDF' }] }],
      language: {
        zeroRecords: "No Matching Terms Found"
      },
      ajax: {
        url: "/json.php?q=15",
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
          data: 'term_name',
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
          targets: 3,
          data: 'term_start',
          //this gives us a span that we can click on and replace with a date input
          render: function (data) {
            if (data != null) {
              return `<span class="date">${data}</span>`
            }
            else {
              return `<span class="date"> </span>`
            }
          }
        },
        {
          targets: 4,
          data: 'term_end',
          render: function (data) {
            if (data != null) {
              return `<span class="date">${data}</span>`
            }
            else {
              return `<span class="date"> </span>`
            }
          }
        },
        {
          targets: 5,
          data: 'term_code',
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
    let table = $('#termsTable').DataTable();

    //check to see if the user has the ability to edit the table and add the edit buttons to the toolbar
    if ((configOptions.response.docs.canEditAll == 'on' || configOptions.response.docs.canEditTerms == 'on') && configOptions.response.docs.current_user.role * 1 > 1) {
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
        if (document.querySelector(".selected .date")) {
          if (dat != null) {
            document.querySelector(".selected .date").innerHTML = `<input class="input" type="date" name="row-date-box" style="width: 100%;" value="${dat}"></input>`;
          }
          else {
            document.querySelector(".selected .date").innerHTML = `<input class="input" type="date" name="row-date-box" style="width: 100%;" value=""></input>`;
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
            if (checkValues(requiredValuesTerms, table.rows(this).data()[0])) {
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
    $("#termsTable").DataTable().ajax.url("/json.php?q=15").load(null, false);
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
      json.dcvsection = "terms";

      json.delete = deleteData;
      //Convert JSON data to string
      var data = JSON.stringify(json);

      //Sending data
      xhr.send(data);
    }
  }

  //if the user clicks on a trash icon, bring up the dialog box to confirm
  $("#termsTable tbody").on('click', '.fa-trash-alt', function () {
    let table = $("#termsTable").DataTable();
    document.querySelector(".alertTextDel").innerHTML = `<p>Delete data for ${table.row($(this).parents('tr')).data().term_name}?</p>`;
    document.querySelector("#alertTermsDel").showModal();
    selectedRow = table.row($(this).parents('tr'))
  })

  //called if the user cancels the deletion
  function cancelDelete() {
    let dialogElement = document.querySelector("#alertTermsDel");
    selectedRow = null;
    dialogElement.close();
  }

  //remove the selected row and eventually send a command to the server to remove the data from database
  function confirmDelete() {
    let dialogElement = document.querySelector("#alertTermsDel");
    submit(selectedRow.data(), 1);
    selectedRow.remove().draw(false);
    selectedRow = null;
    dialogElement.close();
  }

  //allows for the editing of data
  function editModeToggle() {
    let table = $("#termsTable").DataTable();
    if (editMode == false) {
      table.column(0).visible(true);
      editMode = true;
      table.button('1-1').text("Freeze");
      //create a pop-up that warns the user about editing data
      toastr.warning('The table can now be edited. Click on a field to open its editing function. Note that changes made cannot be undone.')
    }
    else {
      table.column(0).visible(false);
      table.button('1-1').text("Modify");
      editMode = false;
    }
  }

  //opens the modal for adding rows to the table
  function openAddModal() {
    let modal = document.getElementById('addTermModal');
    let table = $("#termsTable").DataTable();
    table.column('1').order('asc').draw(false);
    modal.style.display = 'block';
  }

  //if the user cancels the addition, clear out the form
  function closeAddModal() {
    let modal = document.getElementById('addTermModal');
    document.getElementById('term_name').value = null;
    document.getElementById('term_start').value = null;
    document.getElementById('term_end').value = null;
    document.getElementById('term_code').value = null;

    modal.style.display = 'none';
  }

  //submit the data to the server and add a row to the table
  function submitAddModal(e) {
    e.preventDefault();
    let formData = {
      term_name: document.getElementById('term_name').value,
      term_start: document.getElementById('term_start').value,
      term_end: document.getElementById('term_end').value,
      term_code: document.getElementById('term_code').value,
      dcvsection: 'terms',
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

  loadJsonXHR();

  //set the proper event listeners
  document.querySelector("#cancelTermsDel",).addEventListener('click', cancelDelete);

  document.querySelector("#confirmTermsDel",).addEventListener('click', confirmDelete);

  document.querySelector("#cancelButton").addEventListener('click', closeAddModal);

  document.querySelector("#termForm").addEventListener('submit', submitAddModal);

  document.querySelector('.close').addEventListener('click', closeAddModal);
}

document.addEventListener('DOMContentLoaded', init);