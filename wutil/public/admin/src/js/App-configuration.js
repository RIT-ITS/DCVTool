//for security reasons, this whole script is a function
function init() {

    //global variables
    let configOptions;

    let campusData;

    //loads data from .json files and sends it to the correct function
    async function loadJsonXHR() {

        try {
            let configs = await fetch("/json/configVars/all.json");

            let campusResponse = await fetch("/json/campuses/all.json");

            configOptions = await configs.json();

            campusData = await campusResponse.json();

            Object.freeze(configOptions.response.docs.current_user);
            Object.freeze(configOptions.response.docs);

            loadConfigs();

        } catch (e) {
            console.log(`In catch with e = ${e}`);
        }
    }

    //check/uncheck/set configs when loading them in
    function loadConfigs() {
        let configs = document.querySelectorAll('.editableConfig');

        for (let key in configOptions.response.docs) {
            for (let i = 0; i < configs.length; i++) {
                if (configs[i].attributes.config.value == key) {
                    switch (configs[i].type) {
                        case "checkbox":
                            if (configOptions.response.docs[key] == 'on') {
                                configs[i].checked = true;
                            }
                            break;
                        case "text":
                            configs[i].value = configOptions.response.docs[key];
                            break;
                    }
                }
                if(configOptions.response.docs.current_user.role < 3){
                    configs[i].setAttribute('disabled', true);
                }
            }
        }

        //load active campuses and select the current default
        let dropDown = document.querySelector("#campus");
        for (let i = 0; i < campusData.response.docs[0].length; i++) {
            if (campusData.response.docs[0][i].active == 1) {
                if (campusData.response.docs[0][i].id == configOptions.response.docs.defaultCampusId) {
                    dropDown.innerHTML += "<option selected value=" + campusData.response.docs[0][i].id + ">" + campusData.response.docs[0][i].campus_name + "</option>";
                }
                else {
                    dropDown.innerHTML += "<option value=" + campusData.response.docs[0][i].id + ">" + campusData.response.docs[0][i].campus_name + "</option>";
                }
            }
        }

        //process the edit all toggle seperately
        if (document.querySelector('.configToggleAll').checked) {
            toggleEditAll();
        }
    }

    //submit all config options
    function submitConfigOptions() {
        if (configOptions.response.docs.current_user.role > 2) {
            let json;

            if (this.attributes) {
                json = { "config_key": this.attributes.config.value, "config_value": '', "config_scope": this.attributes.scope.value };
                switch (this.type) {
                    case "text":
                        json.config_value = this.value;
                        break;
                    case "select-one":
                        json.config_value = this.value;
                        break;
                    case "checkbox":
                        if (this.checked == true) {
                            json.config_value = "on";
                        }
                        else {
                            json.config_value = "off";
                        }
                        break;
                }
                submit(json);
            }
        }
    }

    //submit data to the server
    function submit(json) {
        if (configOptions.response.docs.current_user.role > 2) {

            let xhr = new XMLHttpRequest();
            let url = "/api/reference/update.php";

            //Open connection
            xhr.open("POST", url, true);

            //Set request header
            xhr.setRequestHeader("Content-Type", "application/json");

            //Create state change callback
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {

                    // Print received data from server
                    console.log(this.responseText);

                }
            };

            //add to the data being sent to the server. Lets the server know what database to update
            json.dcvsection = 'configuration';

            // Convert JSON data to string
            let data = JSON.stringify(json);

            // Send data
            xhr.send(data);
        }
    }

    //function for toggling checkboxes en-mass
    function toggleEditAll() {
        let toggles = document.querySelectorAll(".configToggleSub");
        let mainToggle = document.querySelector(".configToggleAll");
        if (mainToggle.checked) {
            for (let i = 0; i < toggles.length; i++) {
                toggles[i].setAttribute('disabled', true);
            }
        }
        else {
            for (let i = 0; i < toggles.length; i++) {
                toggles[i].removeAttribute('disabled');
            }
        }
    }

    //set event listeners to config inputs
    document.querySelector(".configToggleAll").addEventListener("change", function () { submitConfigOptions(); toggleEditAll() });

    //let mainToggle = document.querySelector(".configToggleAll");
    let options = document.querySelectorAll(".editableConfig");

    for (let i = 0; i < options.length; i++) {
        options[i].addEventListener("change", submitConfigOptions);
    }

    loadJsonXHR();

}

document.addEventListener('DOMContentLoaded', init);