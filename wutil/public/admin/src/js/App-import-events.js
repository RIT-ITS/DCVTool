//for security reasons, this whole script is a function
function init() {

    //Global Variables
    let configOptions;

    //loads data from .json files
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

                    let campusResponse = await fetch("/json/campuses/all.json");

                    let campusOptions = await campusResponse.json();

                    document.querySelector("#start_date").value = new Date().toISOString().slice(0, 10);

                    Object.freeze(configOptions.response.docs.current_user);
                    Object.freeze(configOptions.response.docs);

                    loadCampusDropDown(campusOptions);

                    enableButton();

                    break;
                case 'buildings':
                    updateBuildingDropDown(json);
                    break;
            }

        } catch (e) {
            console.log(`In catch with e = ${e}`);
        }
    }

    //load campus data into the dropdown
    function loadCampusDropDown(json) {
        let dropDown = document.querySelector("#campus");
        for (let i = 0; i < json.response.numFound; i++) {
            if (json.response.docs[0][i].id == configOptions.response.docs.defaultCampusId) {
                dropDown.innerHTML += "<option selected value=" + json.response.docs[0][i].id + ">" + json.response.docs[0][i].campus_name + "</option>";
            }
            else {
                dropDown.innerHTML += "<option value=" + json.response.docs[0][i].id + ">" + json.response.docs[0][i].campus_name + "</option>";
            }
        }
        loadBuildingsByCampus();
        dropDown.addEventListener("change", loadBuildingsByCampus);
    }

    //load building data based on the campus selected
    function loadBuildingsByCampus() {
        updateBuildingDropDown(0);

        //if no campus is selected, start the chain of clearing dropdowns
        let campusSelect = document.querySelector("#campus");
        if (campusSelect.value > 0) {
            loadJsonXHR("/json/buildingsbycampus/" + campusSelect.value + ".json", "buildings");
        }
        else {
            updateBuildingDropDown(0);
        }
    }

    //load building data into the dropdown
    function updateBuildingDropDown(json) {
        let buildingSelect = document.querySelector("#bldg_num");
        buildingSelect.innerHTML = "<option value=0> </option>";

        //if passed a 0 (i.e the user selected the blank option) clear the options and also send the floor dropdown a 0
        if (json != 0) {
            for (let i = 0; i < json.response.numFound; i++) {
                if (json.response.docs[0][i].active == 1) {
                    buildingSelect.innerHTML += "<option value=" + json.response.docs[0][i].bldg_num + ">" + json.response.docs[0][i].bldg_num + ": " + json.response.docs[0][i].bldg_name + "</option>";
                }
            }
        }
    }

    //enable/disable the room input if a building is selected
    function toggleRoomInput() {
        let buildingSelect = document.querySelector("#bldg_num");
        if (buildingSelect.value != 0) {
            document.querySelector('#room_num').removeAttribute('disabled');
        }
        else {
            document.querySelector('#room_num').setAttribute('disabled', 0);
            document.querySelector('#room_num').value = null;
        }
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
                    document.querySelector('#output').innerHTML = ``;
                    //parse the server's reponses into the output box

                    let responseObject = JSON.parse(this.response);

                    for (let i = 0; i < responseObject.outputs.length; i++) {
                        document.querySelector('#output').innerHTML += `<p>${responseObject.outputs[i]}</p>`;
                    }

                    //only clear the form if the sever responds with an ok
                    if (responseObject.type == 'ok') {
                        clearModal();
                    }
                }
            };

            //Add to the data being sent to the server. Lets the server know what database to update
            json.dcvsection = "expandedSchedule";

            json.delete = deleteData;

            //Convert JSON data to string
            let data = JSON.stringify(json);

            //console.log(data);

            //Send data
            xhr.send(data);
        }
    }

    //submit data from the form/modal
    function submitAddModal(e) {
        e.preventDefault();

        let formData = {
            coursetitle: document.getElementById('coursetitle').value,
            bldg_num: document.getElementById('bldg_num').value,
            room_num: document.getElementById('room_num').value,
            eventDate: document.getElementById('start_date').value,
            meeting_time_start: document.getElementById('start_time').value,
            meeting_time_end: document.getElementById('end_time').value,
            campus: document.getElementById('campus').value,
            enrl_tot: document.getElementById('enrl_tot').value,
            dcvsection: 'expandedSchedule',
            delete: 0
        }

        //validate that the dates are sequential
        if (new Date(formData.eventDate + "T" + formData.meeting_time_start) < new Date(formData.eventDate + "T" + formData.meeting_time_end)) {
            submit(formData, 0);
        }
        else {
            toastr["error"]("Invalid Event Times!");
        }

    }

    //clear/reset data from the form/modal
    function clearModal() {
        document.querySelector("#campus").value = configOptions.response.docs.defaultCampusId;
        document.querySelector("#room_num").value = null;
        document.querySelector("#bldg_num").value = null;
        document.querySelector("#coursetitle").value = null;
        document.querySelector("#enrl_tot").value = null;
        document.querySelector("#start_date").value = new Date().toISOString().slice(0, 10);
        document.querySelector("#start_time").value = null;
        document.querySelector("#end_time").value = null;
        loadBuildingsByCampus();
    }

    //enable the button if the config option is set to active
    function enableButton() {
        let button = document.querySelector("#addButton");
        if (configOptions.response.docs.canImportEvents == 'on' && configOptions.response.docs.current_user.role > 2) {
            button.removeAttribute("disabled");
        }
    }


    //add the proper event listeners
    document.querySelector("#bldg_num").addEventListener('change', toggleRoomInput);

    document.querySelector("#subForm").addEventListener('submit', submitAddModal);

    document.querySelector("#cancelButton").addEventListener('click', clearModal);

    loadJsonXHR("/json/campuses/all.json", "init");

}

document.addEventListener('DOMContentLoaded', init);