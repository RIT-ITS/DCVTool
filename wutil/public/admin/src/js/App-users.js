//for security reasons, this whole script is a function
function init() {
  //global variables
  let editMode = false;

  let selectedRow = null;

  let configOptions;

  let globalJson;

  let oldVal;

  let newVal;

  let cellSelected = false;

  //required values for editing table rows
  let requiredValuesUsers = ["first_name", "last_name", "email", "role"];

  //loads data from .json files
  async function loadJsonXHR() {

    try {
      let configResponse = await fetch("/json/configVars/1.json");

      if (!configResponse.ok) {
        if (configResponse.status == 404) console.log("404 Error!");
        throw new Error(`HTTP error! Status: ${configResponse.status}`);
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

  //search for and return a type based on id
  function searchRoleName(idPre, roles) {
    id = parseInt(idPre);
    if (id > 0) {
      for (let i = 0; i < roles.response.user_roles.length; i++) {
        if (roles.response.user_roles[i].id == id) {
          return roles.response.user_roles[i].role_name;
        }
      }
    }
    else {
      return "";
    }
  }

  //returns a selector full of all possible categories, with the cell's current category already selected.
  function searchRoles(id, roles) {
    let selector = `<select style="border: 1px solid #aaa; width: 100%;" class="form-control input form-select" size='1' name='row-category'>`

    for (let i = 0; i < roles.response.user_roles.length; i++) {
      if (id == roles.response.user_roles[i].id) {
        selector += `<option value=${roles.response.user_roles[i].id} selected='selected'>${roles.response.user_roles[i].role_name}</option>`
      }
      else {
        selector += `<option value=${roles.response.user_roles[i].id}>${roles.response.user_roles[i].role_name}</option>`
      }
    }
    selector += `</select>`
    return selector;
  }

  //load data into the table. This is done here because we need to wait for other content to load
  function loadTable() {
    $('#usersTable').DataTable({
      destroy: true, responsive: true, info: false, select: { items: 'cell', style: 'single' }, blurable: true, lengthChange: false, autoWidth: false, order: [1, "asc"], pageLength: 12,
      dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Users_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Users_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Users_' + getFileDate(), text: 'PDF' }] }],
      language: {
        zeroRecords: "No Matching User Data Found"
      },
      ajax: {
        url: "/json/users/all.json",
        dataSrc: function (json) {
          //because the data is packaged differently, get a reference to the whole object
          globalJson = json;
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
          data: 'first_name',
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
          data: 'last_name',
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
          data: 'email',
          //this gives us a span that we can replace with an email input
          render: function (data) {
            if (data != null) {
              return `<span class="email">${data}</span>`
            }
            else {
              return `<span class="email"> </span>`
            }
          }
        },
        {
          targets: 5,
          data: 'role',
          //this gives us a span that we can replace with a dropdown of role names
          render: function (data) {
            if (data != null) {
              return `<span class="dropdown">${searchRoleName(data, globalJson)}</span>`
            }
            else {
              return `<span class="dropdown"> </span>`
            }
          }
        },
        {
          targets: 6,
          data: 'uid',
          //this gives us a span that we can replace with a dropdown of role names
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
    let table = $('#usersTable').DataTable();

    //check to see if the user has the ability to edit the table and add the edit buttons to the toolbar
    if ((configOptions.response.docs.canEditAll == 'on' || configOptions.response.docs.canEditUsers == 'on') && configOptions.response.docs.current_user.role * 1 > 2) {
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
          document.querySelector(".selected .dropdown").innerHTML = searchRoles(dat, globalJson);
          //focus on the new input
          document.querySelector("td select").focus();
          cellSelected = true;
        }
        //if the user clicks on an email field, give them an email input
        if (document.querySelector(".selected .email")) {
          document.querySelector(".selected .email").innerHTML = `<input class="input" type="email" name="row-text-box" style="width: 100%;" value="${dat}"></input>`
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


        //this is the method to be used to gather data from the modified row to send to the server
        //make sure the selected cell isn't the trash icon, we don't want to send data from a row we may delete
        if (table.cell(this).index().columnVisible != 0) {
          //check to make sure the user actually edited something
          if (String(oldVal) != String(newVal)) {
            //check row data against the required values
            if (checkValues(requiredValuesUsers, table.rows(this).data()[0])) {
              table.rows(this).data()[0].role_name = searchRoleName(table.rows(this).data()[0].role, globalJson)
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
    })

    //in case the user selects a new cell before the table has re-drawn, set cellSelected to false after every draw.
    table.on('draw', function () {
      cellSelected = false;
    });

  }

  //reload data into the table
  function updateTable() {
    $("#usersTable").DataTable().ajax.url("/json/users/all.json").load(null, false);
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

      //Create callback
      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {

          //print received data from server
          console.log(this.responseText);
        }
      };

      //Add to the data being sent to the server. Lets the server know what database to update
      json.dcvsection = "users";

      json.delete = deleteData;

      //Convert JSON data to string
      var data = JSON.stringify(json);

      //Send data
      xhr.send(data);
    }
  }

  //if the user clicks on a trash icon, bring up the dialog box to confirm
  $("#usersTable tbody").on('click', '.fa-trash-alt', function () {
    let table = $("#usersTable").DataTable();
    document.querySelector(".alertTextDel").innerHTML = `<p>Delete user data for ${table.row($(this).parents('tr')).data().first_name} ${table.row($(this).parents('tr')).data().last_name}?</p>`;
    document.querySelector("#alertUsersDel").showModal();
    selectedRow = table.row($(this).parents('tr'))
  })

  //called if the user cancels the deletion
  function cancelDelete() {
    let dialogElement = document.querySelector("#alertUsersDel");
    selectedRow = null;
    dialogElement.close();
  }

  //remove the selected row and eventually send a command to the server to remove the data from database
  function confirmDelete() {
    let dialogElement = document.querySelector("#alertUsersDel");
    submit(selectedRow.data(), 1);
    selectedRow.remove().draw(false);
    selectedRow = null;
    dialogElement.close();
  }

  //allows for the editing of data
  function editModeToggle() {
    let table = $("#usersTable").DataTable();
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
    let modal = document.getElementById('addUsersModal');
    let table = $("#usersTable").DataTable();
    table.column('1').order('asc').draw(false);
    modal.style.display = 'block';

  }

  //if the user cancels the addition, clear out the form
  function closeAddModal() {
    let modal = document.getElementById('addUsersModal');
    document.getElementById('first_name').value = null;
    document.getElementById('last_name').value = null;
    document.getElementById('email').value = null;
    document.getElementById('role').value = 1;
    document.getElementById('uid').value = null;

    modal.style.display = 'none';
  }

  //submit the data to the server and update the table
  function submitAddModal(e) {
    e.preventDefault();
    let formData = {
      first_name: document.getElementById('first_name').value,
      last_name: document.getElementById('last_name').value,
      email: document.getElementById('email').value,
      role: document.getElementById('role').value,
      role_name: searchRoleName(document.getElementById('role').value, globalJson),
      dcvsection: 'users',
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
  document.querySelector("#cancelUsersDel",).addEventListener('click', cancelDelete);

  document.querySelector("#confirmUsersDel",).addEventListener('click', confirmDelete);

  document.querySelector("#cancelButton").addEventListener('click', closeAddModal);

  document.querySelector("#usersForm").addEventListener('submit', submitAddModal);

  document.querySelector('.close').addEventListener('click', closeAddModal);

  loadJsonXHR();
}

document.addEventListener('DOMContentLoaded', init);