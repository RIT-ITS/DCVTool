//for security reasons, this whole script is a function
function init() {
  //global variables
  let campusOptions;

  let buildingOptions;

  let configOptions;

  let termOptions;

  let importMode;

  //loads data from .json files
  async function loadJsonXHR() {

    try {
      let configResponse = await fetch("/json/configVars/1.json");

      let campusResponse = await fetch("/json/campuses/all.json");

      let termResponse = await fetch("/json/term/all.json");

      configOptions = await configResponse.json();

      campusOptions = await campusResponse.json();

      termOptions = await termResponse.json();

      Object.freeze(configOptions.response.docs.current_user);
      Object.freeze(configOptions.response.docs);

      loadCampusDropDown(campusOptions);

      loadTermDropDown(termOptions);

    } catch (e) {
      console.log(`In catch with e = ${e}`);
    }
  }

  //loads campuses into dropdowns
  function loadCampusDropDown(json) {
    let campusDropDowns = document.querySelectorAll(".campus");

    for (let i = 0; i < campusDropDowns.length; i++) {

      for (let j = 0; j < json.response.docs[0].length; j++) {

        if (json.response.docs[0][j].id == configOptions.response.docs.defaultCampusId) {
          campusDropDowns[i].innerHTML += "<option selected value=" + json.response.docs[0][j].id + ">" + json.response.docs[0][j].campus_name + "</option>";
        }
        else {
          campusDropDowns[i].innerHTML += "<option value=" + json.response.docs[0][j].id + ">" + json.response.docs[0][j].campus_name + "</option>";
        }

      }

      campusDropDowns[i].addEventListener('change', enableButtons);

    }

    enableButtons();

    updateBuildingDropDown();
  }

  //Loads terms into the dropdown
  function loadTermDropDown(json) {
    let dropDown = document.querySelector("#exportTerm");
    for (let i = 0; i < json.response.docs[0].length; i++) {

      if (json.response.docs[0][i].term_code == configOptions.response.docs.current_term) {
        dropDown.innerHTML += "<option selected value=" + json.response.docs[0][i].term_code + ">" + json.response.docs[0][i].term_code + ": " + json.response.docs[0][i].term_name + "</option>";
        currTerm = json.response.docs[0][i].term_code;
      }
      else {
        dropDown.innerHTML += "<option value=" + json.response.docs[0][i].term_code + ">" + json.response.docs[0][i].term_code + ": " + json.response.docs[0][i].term_name + "</option>";
      }
    }
    dropDown.addEventListener("change", enableExport);
  }

  //Loads buildings when the campus dropdown is changed.
  async function updateBuildingDropDown() {
    let buildingSelect = document.querySelector("#exportBuilding");
    let campus = document.querySelector("#exportCampus");

    let buildingResponse = await fetch("/json/buildingsbycampus/" + campus.value + ".json")
    buildingOptions = await buildingResponse.json();

    buildingSelect.innerHTML = "<option value=0> </option>";
    //if passed a 0 (i.e the user selected the blank option) clear the options and also send the floor dropdown a 0
    if (buildingOptions != 0) {
      for (let i = 0; i < buildingOptions.response.docs[0].length; i++) {
        if (buildingOptions.response.docs[0][i].active == 1) {
          buildingSelect.innerHTML += "<option value=" + buildingOptions.response.docs[0][i].id + ">" + buildingOptions.response.docs[0][i].bldg_num + ": " + buildingOptions.response.docs[0][i].bldg_name + "</option>";
        }
      }
      buildingSelect.addEventListener("change", enableExport);
    }
  }

  //toggle the active states of the import buttons
  function enableButtons() {
    let importConfig = configOptions.response.docs.canImport;

    if (document.querySelector("#campus").value != 0 && importConfig == 'on' && configOptions.response.docs.current_user.role * 1 > 2) {
      document.querySelector("#spaceImportButton").removeAttribute('disabled');
    }
    else {
      document.querySelector("#spaceImportButton").setAttribute('disabled', 0);
    }

    if (document.querySelector("#eventCampus").value != 0 && importConfig == 'on' && configOptions.response.docs.current_user.role * 1 > 2) {
      document.querySelector("#eventImportButton").removeAttribute('disabled');
    }
    else {
      document.querySelector("#eventImportButton").setAttribute('disabled', 0);
    }

    if (document.querySelector("#zoneCampus").value != 0 && importConfig == 'on' && configOptions.response.docs.current_user.role * 1 > 2) {
      document.querySelector("#zoneImportButton").removeAttribute('disabled');
    }
    else {
      document.querySelector("#zoneImportButton").setAttribute('disabled', 0);
    }

  }

  //show/hide the term selector
  function toggleTermSelector() {
    let optionSelect = document.querySelector("#exportMode").value;
    let termSelector = document.querySelector(".termRow");
    if (optionSelect == 1) {
      termSelector.classList.add('hidden');
    }
    if (optionSelect == 2 || optionSelect == 3) {
      termSelector.classList.remove('hidden');
    }
  }

  //toggle the export button depending on if a building is selected
  function enableExport() {
    let dropDownMode = document.querySelector("#exportMode");
    let dropDown = document.querySelector("#exportCampus");
    let dropDownTwo = document.querySelector("#exportBuilding");
    let button = document.querySelector("#exportButton");
    let termSelect = document.querySelector("#exportTerm");

    if (dropDown.value != 0 && dropDownTwo.value != 0) {
      if (dropDownMode.value == 1) {
        button.removeAttribute('disabled');
      }
      else {
        console.log("mode is 2 or 3.");
        if (!termSelect.classList.contains('hidden') && parseInt(termSelect.value) > 0) {
          button.removeAttribute('disabled');
        }
        else {
          button.setAttribute('disabled', 0);
        }
      }
    }
    else {
      button.setAttribute('disabled', 0);
    }
  }

  //submit data to the server
  function submit(json, deleteData, dcvSection) {
    if (configOptions.response.docs.current_user.role > 2) {

      let xhr = new XMLHttpRequest();
      let url = "/api/reference/update.php";

      //Open connection
      xhr.open("POST", url, true);

      //Set the request header
      xhr.setRequestHeader("Content-Type", "application/json");

      //Create callback
      xhr.onload = function () {
        if (xhr.readyState === xhr.DONE && xhr.status === 200) {

          //parse received data from server
          let responseObject = JSON.parse(this.response);

          //add text to the output box to let the usre know the results of the import
          if (dcvSection == "importSpace") {
            document.querySelector("#Output").innerHTML = `<p>Processed ${responseObject.totalRowsProcessed} rows of data.</p>
        <p>${responseObject.totalRowsNoImport} rows with redundant data.</p>
        <p>Added ${responseObject.totalBuildingsAdded} buildings to database.</p>
        <p>Added ${responseObject.totalFloorsAdded} floors to database.</p>
        <p>Added ${responseObject.totalRoomsAdded} rooms to database.</p>
        <p>${responseObject.totalRowsWithErrors} rows with errors.</p>
        `;
          }
          if (dcvSection == "importSis") {
            document.querySelector("#Output").innerHTML = `<p>Processed ${responseObject.totalRowsProcessed} rows of data.</p>
        <p>${responseObject.totalRowsNoImport} rows with redundant data.</p>
        <p>Added ${responseObject.totalClassesAdded} classes to database.</p>
        <p>${responseObject.totalRowsWithErrors} rows with errors.</p>
        `;
          }
          if (dcvSection == "importZone") {
            document.querySelector("#Output").innerHTML = `<p>Processed ${responseObject.totalRowsProcessed} rows of data.</p>
        <p>${responseObject.totalRowsNoImport} rows with redundant data.</p>
        <p>Added ${responseObject.totalZonesAdded} zones to database.</p>
        <p>Linked zones to ${responseObject.totalXrefsAdded} rooms.</p>
        <p>${responseObject.totalRowsWithErrors} rows with errors.</p>
        `;
          }
          //Add errors to the output box, if any
          if (responseObject.errorArray) {
            if (responseObject.errorArray.length > 0) {
              document.querySelector("#Output").innerHTML += `<ol>`
              for (let i = 0; i < responseObject.errorArray.length; i++) {
                document.querySelector("#Output").innerHTML += `<li>${responseObject.errorArray[i].error_message}</li>`
              }
              document.querySelector("#Output").innerHTML += `</ol>`
            }
          }
        }
      };

      //add to the data being sent to the server. Lets the server know what database to update
      json.dcvsection = dcvSection;

      json.delete = deleteData;

      if (dcvSection == "importSpace") {
        json.campus_id = document.querySelector("#campus").value;
      }
      if (dcvSection == "importSis") {
        json.campus_id = document.querySelector("#eventCampus").value;
      }
      if (dcvSection == "importZone") {
        json.campus_id = document.querySelector("#zoneCampus").value;
      }

      //Convert JSON data to string
      var data = JSON.stringify(json);

      //Send data
      xhr.send(data);
    }
  }

  //Open the file selector
  function selectFile() {
    importMode = this.attributes.importType.value;
    let fileInputElement = document.querySelector("#import");
    fileInputElement.click();
    fileInputElement.addEventListener('change', warnUser);
  }

  //warn the user about the dangers of file importing
  function warnUser() {
    let fileInputElement = document.querySelector("#import");

    let dialogElement = document.querySelector("#alertImport");

    let alertText = document.querySelector("#alertSpaceText");
    alertText.innerHTML = '<p class="h5">Import ' + fileInputElement.files[0].name + '?</p> <p>This action cannot be undone!</p>';
    dialogElement.showModal();
  }

  //close the submission modal
  function closeSubmitModal() {
    let dialogElement = document.querySelector("#alertImport");
    dialogElement.close();
  }

  //Parse a csv file and send it's data off for packaging
  function submitFile() {
    let fileInputElement = document.querySelector("#import");
    let reader = new FileReader();

    switch (importMode) {
      case "space":
        console.log("Importing space");
        reader.onload = function (e) {
          createSpaceJson(e.target.result);
        }
        break;
      case "event":
        console.log("Importing Classes");
        reader.onload = function (e) {
          createEventJson(e.target.result);
        }
        break;
      case "zone":
        console.log("Importing Zones");
        reader.onload = function (e) {
          createZoneJson(e.target.result);
        }
        break;
    }
    reader.readAsText(fileInputElement.files[0]);

    closeSubmitModal();
  }

  //TODO: It may be possible to combine/steamline the three createXYZJson functions
  //create a space import json load from the text passed to it
  function createSpaceJson(text) {
    let devHeaders = ["bldg_num", "bldg_name", "facility_code", "floor_designation", "room_num", "room_name", "space_function", "rtype_code", "space_use_name", "room_area", "room_population", "uncert_amt", "department_bu_name", "active", "reservable"];
    let headers = ["Building Number", "Building Name", "Building Code", "Floor", "Space Number", "Space Name", "Space Function", "Space Type", "Space Type Name", "Area SF", "Capacity", "Uncertainty", "Business Unit Name", "Active", "Reservable"];
    let jsonArray = { data: [] };

    //split the text into an array at linebreaks
    let splitStringBreaks;

    //check what line breaks the file used, and split it accordingly
    if (text.includes('\r\n')) {
      splitStringBreaks = text.split('\r\n');
    }
    else if (text.includes('\n')) {
      splitStringBreaks = text.split('\n');
    }

    //split broken arrays into individual values at commas that aren't followed by a space or part of a number
    for (let i = 0; i < splitStringBreaks.length; i++) {
      splitStringBreaks[i] = splitStringBreaks[i].split(/(?<!"\d+,\d+|"\d+),(?!\s)/);
    }

    //check the headers to make sure they are in the correct order
    for (let i = 0; i < headers.length; i++) {
      if (splitStringBreaks[2][i] != headers[i]) {
        toastr["error"]("Import Failed! Please format column header '" + splitStringBreaks[2][i] + "' to match '" + headers[i] + "'.");
        return;
      }
    }

    //parse the values into json objects that are then packaged into an array.
    for (let i = 3; i < splitStringBreaks.length; i++) {
      let jsonObject = {};
      try {
        for (let j = 0; j < splitStringBreaks[i].length; j++) {
          jsonObject[devHeaders[j]] = splitStringBreaks[i][j];
        }

        //sanitize building id to match database formatting
        switch (jsonObject.bldg_num.length) {
          case 1: {
            jsonObject.bldg_num = '0' + '0' + jsonObject.bldg_num;
          }
          case 2: {
            jsonObject.bldg_num = '0' + jsonObject.bldg_num;
          }
        }

        //generate the facility_id for each room
        if (jsonObject.room_num != null && jsonObject.bldg_num != "") {
          jsonObject.facility_id = jsonObject.bldg_num + '-' + jsonObject.room_num;
        }
        else {
          jsonObject.facility_id = jsonObject.facility_code + '-' + jsonObject.room_num;
        }

        jsonArray.data.push(jsonObject);
      }
      catch (e) {
        console.log("Error with row " + (i - 2) + " " + e);
      }
    }

    //submit packaged data
    submit(jsonArray, 0, "importSpace");
  }

  //create a sis import json load from the text passed to it
  function createEventJson(text) {
    let devHeaders = ["bldg_num", "facility_code", "room_num", "pp_search_id", "class_number_code", "coursetitle", "strm", "enrl_tot", "start_date", "end_date", "meeting_time_start", "meeting_time_end", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"]
    let headers = ["Building Number", "Building Code", "Space Number", "Search Id", "Class Code", "Title", "Term", "Event Occupancy", "Start Date", "End Date", "Class Start Time", "Class End Time", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

    let jsonArray = { data: [] };

    //split the text into an array at line breaks
    let splitStringBreaks;

    //check what line breaks the file used, and split it accordingly
    if (text.includes('\r\n')) {
      splitStringBreaks = text.split('\r\n');
    }
    else if (text.includes('\n')) {
      splitStringBreaks = text.split('\n');
    }

    //split broken arrays into individual values at commas that aren't followed by a space or part of a number
    for (let i = 0; i < splitStringBreaks.length; i++) {
      splitStringBreaks[i] = splitStringBreaks[i].split(/(?<!"\d+,\d+|"\d+),(?!\s)/); //this works!
    }

    //check headers for consistency
    for (let i = 0; i < headers.length; i++) {
      if (splitStringBreaks[2][i] != headers[i]) {
        toastr["error"]("Import Failed! Please format column header '" + splitStringBreaks[2][i] + "' to match '" + headers[i] + "'.");
        return;
      }
    }

    //parse the values into json objects that are then packaged into an array.
    for (let i = 3; i < splitStringBreaks.length; i++) {
      let jsonObject = {};
      try {
        for (let j = 0; j < splitStringBreaks[i].length; j++) {
          jsonObject[devHeaders[j]] = splitStringBreaks[i][j];
        }

        //sanitize building id to match database formatting
        switch (jsonObject.bldg_num.length) {
          case 1: {
            jsonObject.bldg_num = '0' + '0' + jsonObject.bldg_num;
          }
          case 2: {
            jsonObject.bldg_num = '0' + jsonObject.bldg_num;
          }
        }

        //sanitize date data to match database formatting
        if (jsonObject.start_date != null && jsonObject.end_date != null) {

          let dateStartArr = jsonObject.start_date.split('/');
          jsonObject.start_date = dateStartArr[2] + "-" + dateStartArr[0] + "-" + dateStartArr[1];

          let dateEndArr = jsonObject.end_date.split('/');
          jsonObject.end_date = dateEndArr[2] + "-" + dateEndArr[0] + "-" + dateEndArr[1];
        }

        jsonObject.facility_id = jsonObject.bldg_num + '-' + jsonObject.room_num;
        jsonArray.data.push(jsonObject);
      }
      catch (e) {
        console.log("Error with row " + (i - 2) + " " + e);
      }
    }

    //submit packaged data
    submit(jsonArray, 0, "importSis");
  }

  //create a zone import json load from the text passed to it
  function createZoneJson(text) {
    let devHeaders = ["bldg_num", "ahu_name", "zone_name", "zone_code", "occ_sensor", "active", "xrefs"];
    let headers = ["Building Number", "Air Handling Unit Name", "Zone Name", "Zone ID", "Occupancy Sensor Present", "Active", "Spaces Served by Zone"];

    let jsonArray = { data: [] };

    //split the text into an array at line breaks
    let splitStringBreaks;

    //check what line breaks the file used, and split it accordingly
    if (text.includes('\r\n')) {
      splitStringBreaks = text.split('\r\n');
    }
    else if (text.includes('\n')) {
      splitStringBreaks = text.split('\n');
    }

    //split broken arrays into individual values at commas that aren't followed by a space or part of a number
    for (let i = 0; i < splitStringBreaks.length; i++) {
      splitStringBreaks[i] = splitStringBreaks[i].split(/(?<!"\d+,\d+|"\d+),(?!\s)/);
    }

    //check headers for consistency
    for (let i = 0; i < headers.length; i++) {
      if (splitStringBreaks[2][i] != headers[i]) {
        toastr["error"]("Import Failed! Please format column header '" + splitStringBreaks[2][i] + "' to match '" + headers[i] + "'.");
        return;
      }
    }

    //parse the values into json objects that are then packaged into an array.
    for (let i = 3; i < splitStringBreaks.length; i++) {
      let jsonObject = {};
      let xrefs = [];
      try {
        for (let j = 0; j < splitStringBreaks[i].length; j++) {
          //if we've reached the end of the headers, assume all other values are xrefs
          if (j >= headers.length - 1) {
            xrefs.push(splitStringBreaks[i][j]);
          }
          else {
            jsonObject[devHeaders[j]] = splitStringBreaks[i][j];
          }
        }
        jsonObject['xrefs'] = xrefs;
        //sanitize building id to match database formatting
        switch (jsonObject.bldg_num.length) {
          case 1: {
            jsonObject.bldg_num = '0' + '0' + jsonObject.bldg_num;
          }
          case 2: {
            jsonObject.bldg_num = '0' + jsonObject.bldg_num;
          }
        }

        jsonArray.data.push(jsonObject);
      }
      catch (e) {
        console.log("Error with row " + (i - 2) + " " + e);
      }
    }

    //submit packaged data
    submit(jsonArray, 0, "importZone");
  }

  //convert json data to string
  async function makeCSV() {
    //get function-wide variables
    let buildingSelect = document.querySelector("#exportBuilding");
    let campusSelect = document.querySelector("#exportCampus");
    let termSelect = document.querySelector("#exportTerm");
    let mode = document.querySelector("#exportMode").value;
    let res;
    let fileName;
    let headers;

    //get room and 6-1 table data
    let responseCat = await fetch("/json/ashrae6-1/all.json");
    let categories = await responseCat.json();

    let floorsResponse = await fetch("/json/buildingfloors/" + buildingSelect.value + ".json");
    let floors = await floorsResponse.json();

    //get the data to be exported based on what the user selected
    if (mode == 0) {
      res = await fetch("/json/roomsbyvav/" + buildingSelect.value + ".json");
      fileName = 'DCV_Bldg_' + buildingSelect.value + '_Rooms_With_VAVs_' + getFileDate();
      headers = ["Id", "Building Name", "Space Id", "Room Name", "Area", "Popluation", "Ashrae 6-1 Id", "Floor", "Room Number", "Uncertainty", "Type Code", "Space Use Name", "Active", "Reservable"];
    }
    if (mode == 1) {
      res = await fetch("/json/roomsbyclass/" + buildingSelect.value + '-' + termSelect.value + ".json");
      fileName = 'DCV_Bldg_' + buildingSelect.value + '_' + termSelect.value + '_Rooms_With_Classes_' + getFileDate();
      headers = ["Id", "Building Name", "Room Code", "Room Name", "Area", "Popluation", "Ashrae 6-1 Id", "Floor", "Room Number", "Uncertainty", "Type Code", "Space Use Name", "Active", "Reservable"];
    }
    if (mode == 2) {
      res = await fetch("/json/zonesbyclass/" + buildingSelect.value + '-' + termSelect.value + ".json");
      fileName = 'DCV_Bldg_' + buildingSelect.value + '_' + termSelect.value + '_Zones_With_Events_' + getFileDate();
      headers = ["Id", "Zone Name", "Zone Code", "Building Name", "AHU Name", "Occupancy Sensor Present", "Active", "Served Spaces"];
    }

    let resDat = await res.json()
    let array = resDat.response.docs[0];
    let str = '';

    //Begin the process of converting json data to CSV-useable string
    let devHeaders = Object.keys(array[0][0]);

    let line = '';
    //add headers to the string
    for (let i = 0; i < headers.length; i++) {
      line += headers[i] + ',';
    }

    str += line + '\r\n';
    //loop through the values and add them to the string, rendering any ids as thier actual values
    for (let i = 0; i < array.length; i++) {
      let line = '';
      for (let key in devHeaders) {
        if (array[i][0][devHeaders[key]] != null) {
          if (devHeaders[key] == 'ash61_cat_id') {
            line += searchCategoryName(array[i][0][devHeaders[key]], categories) + ',';
          }
          else if (devHeaders[key] == 'floor_id') {
            line += searchFloorDesignation(array[i][0][devHeaders[key]], floors) + ',';
          }
          else if (devHeaders[key] == 'building_id') {
            line += searchBuildingName(array[i][0][devHeaders[key]], buildingOptions) + ','
          }
          else if (devHeaders[key] == 'reservable' || devHeaders[key] == 'active' || devHeaders[key] == 'occ_sensor') {
            line += returnYesNo(array[i][0][devHeaders[key]]) + ','
          }
          else if (devHeaders[key] == 'xrefs') {
            for (let j = 0; j < array[i][0][devHeaders[key]].length; j++) {
              line += array[i][0][devHeaders[key]][j] + ',';
            }
          }
          else if (devHeaders[key] != 'auto_mode') {
            line += array[i][0][devHeaders[key]] + ',';
          }
        }
        else {
          line += ' ,';
        }
      }
      str += line + '\r\n';
    }

    //send the string off to be exported as a CSV
    exportCSV(str, fileName);
  }

  //convert string data into a blob for exportation
  function exportCSV(text, fileName) {

    //create the file
    let blob = new Blob([text], { type: 'text/csv' });

    //download file
    let link = document.createElement("a");
    let url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", fileName);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

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

  //returns a building name based on the id passed to it.
  function searchBuildingName(id, buildings) {
    let building = "";
    for (let i = 0; i < buildings.response.docs[0].length; i++) {
      if (id == buildings.response.docs[0][i].id) {
        building = buildings.response.docs[0][i].bldg_name;
      }
    }
    return building;
  }

  //returns yes or no based on the value passed to it
  function returnYesNo(value) {
    if (value == 1) {
      return "Yes";
    }
    else {
      return "No";
    }
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

  //get and format the current date for file exportation purposes
  function getFileDate() {
    let today = new Date()
    let todayDate = today.toISOString().slice(0, 10);
    let todayTime1 = today.toISOString().slice(11, 13);
    let todayTime2 = today.toISOString().slice(14, 16);

    return todayDate + "-" + todayTime1 + "_" + todayTime2;
  }

  //set the proper event listeners

  document.querySelector("#spaceImportButton").setAttribute('disabled', 0);

  document.querySelector("#eventImportButton").setAttribute('disabled', 0);

  document.querySelector("#zoneImportButton").setAttribute('disabled', 0);

  document.querySelector("#exportButton").setAttribute('disabled', 0);

  document.querySelector("#cancelImport",).addEventListener('click', closeSubmitModal);

  document.querySelector("#confirmImport",).addEventListener('click', submitFile);

  document.querySelector("#spaceImportButton").addEventListener('click', selectFile);

  document.querySelector("#eventImportButton").addEventListener('click', selectFile);

  document.querySelector("#zoneImportButton").addEventListener('click', selectFile);

  document.querySelector("#exportCampus").addEventListener("change", updateBuildingDropDown);

  document.querySelector("#exportButton").addEventListener('click', makeCSV);

  document.querySelector("#exportMode").addEventListener('change', function () { toggleTermSelector(); enableExport() });

  document.querySelector("#exportTerm").addEventListener('change', enableExport);

  loadJsonXHR();
}

document.addEventListener('DOMContentLoaded', init);