//for security reasons, this whole script is a function
function init() {
  //global variables
  let editMode = false;

  let configOptions;

  let oldStart;

  let oldEnd;

  //loads data from .json files and sends it to the correct function
  //used for loading campuses for the dropdowns and config options
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
          //only get the configs in the scope of editing
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


    }
    catch (e) {
      console.log(`In catch with e = ${e}`);
    }

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

  //load building data based on the campus selected
  function loadBuildingsByCampus() {
    updateBuildingDropDown(0);
    clearTableByID("#eventTable");

    //if no campus is selected, clear the dropdown
    let campusSelect = document.querySelector("#campus");
    if (campusSelect.value > 0) {
      loadJsonXHR("/json/buildingsbycampus/" + campusSelect.value + ".json", "buildings");
    }
    else {
      clearTableByID("#eventTable")
      updateBuildingDropDown(0);
    }
  }

  //load building data into the dropdown
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
  }

  //load the table
  function loadTable() {

    //pre-load buildings and the current date
    if(configOptions.response.docs.defaultCampusId > 0){
      loadJsonXHR("/json/buildingsbycampus/" + configOptions.response.docs.defaultCampusId + ".json", "buildings");
    }

    document.querySelector("#first_date").value = new Date().toISOString().slice(0, 10);

    $("#eventTable").DataTable({
      destroy: true, info: false, responsive: true, select: { items: 'cell', style: 'single' }, blurable: true, autoWidth: false, order: [6, "asc"], pageLength: 12,
      dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Events_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Events_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Events_' + getFileDate(), text: 'PDF' }] }],
      language: {
        emptyTable: "Please Select a Campus, Building, and Date(s)",
        zeroRecords: "No Matching Data in the Selected Building for the Selected Date(s)"
      },
      ajax: {
        //load a dummy json file until we get the real deal
        url: "src/test_json/cleaner2.json",
        dataSrc: function (json) {
          return json.response.docs[0];
        }
      },
      columnDefs: [
        {
          targets: 0,
          data: 'pp_search_id',
        },
        {
          targets: 1,
          data: 'strm',
        },
        {
          targets: 2,
          data: 'facility_id',
        },
        {
          targets: 3,
          data: 'class_number_code',
        },
        {
          targets: 4,
          data: 'coursetitle',
          render: function (data, type, row, meta) {
            if (data == '' || data == null) {
              return 'Exam';
            }
            else {
              return data;
            }
          }
        },
        {
          targets: 5,
          data: 'enrl_tot',
        },
        {
          targets: 6,
          data: function (row) {
            return new Date(row.datetime_start);
          },
          render: function (data) {
            return data.toLocaleString('eg-GB', { timeZone: "America/New_york" });
          }
        },
        {
          targets: 7,
          data: function (row) {
            return new Date(row.datetime_end);
          },
          render: function (data) {
            return data.toLocaleString('eg-GB', { timeZone: "America/New_york" }
            );
          }
        }

      ],
    });

    // //get a reference to the table
    let table = $('#eventTable').DataTable();

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
    let buildingSelect = document.querySelector("#building").value
    let firstDate = document.querySelector('#first_date').value;
    let endDate = document.querySelector('#end_date').value;

    if (endDate == '') {
      endDate = '00000000';
    }

    if (firstDate != '' && buildingSelect > 0) {
      $("#eventTable").DataTable().ajax.url("/json/eventsbybuilding/" + firstDate.replaceAll('-', '') + endDate.replaceAll('-', '') + buildingSelect + ".json").load(null, false);
    }
    else {
      clearTableByID("#eventTable");
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

  //change the length of the table page when the user changes it
  function changePageLength() {
    let table = $('#eventTable').DataTable();
    let len = document.querySelector('#pageLen').value;
    table.page.len(len).draw(false);
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
  document.querySelector("#campus").addEventListener("change", loadBuildingsByCampus);

  document.querySelector("#first_date").addEventListener("blur", function () { if (this.value != oldStart) { updateTable(); oldStart = this.value } });

  document.querySelector("#end_date").addEventListener("blur", function () { if (this.value != oldEnd) { updateTable(); oldEnd = this.value } });

  document.querySelector("#pageLen").addEventListener('change', changePageLength);

  document.querySelector("#building").addEventListener("change", updateTable);


  //load XHR Data
  loadJsonXHR("/json/campuses/all.json", "init");

}

document.addEventListener('DOMContentLoaded', init);