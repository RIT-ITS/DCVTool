//for security reasons, this whole script is a function
function init() {

    //global variables
    let campuses;

    let termData;

    let currTerm = 0;

    let configOptions;

    //loads data from .json files and then loads the table
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
                    configOptions = json;

                    let campusResponse = await fetch("/json/campuses/all.json");

                    let termResponse = await fetch("/json/term/all.json");

                    campuses = await campusResponse.json();

                    termData = await termResponse.json();

                    Object.freeze(configOptions.response.docs.current_user);
                    Object.freeze(configOptions.response.docs);

                    loadTermDropDown(termData);

                    loadTable();
                    break;
            }

        } catch (e) {
            console.log(`In catch with e = ${e}`);
        }
    }

    //return a campus based on the id and data passed to it
    function searchCampuses(id, campuses) {
        if (id > 0 && id != null) {
            for (let i = 0; i < campuses.response.docs[0].length; i++) {
                if (campuses.response.docs[0][i].id == id) {
                    return campuses.response.docs[0][i].campus_name;
                }
            }
        }
        else {
            return "";
        }
    }

    //Loads terms into the dropdown and selects the current one
    function loadTermDropDown(json) {
        let dropDown = document.querySelector("#term");

        for (let i = 0; i < json.response.numFound; i++) {

            if (json.response.docs[0][i].term_code == configOptions.response.docs.current_term) {
                dropDown.innerHTML += "<option selected value=" + json.response.docs[0][i].term_code + ">" + json.response.docs[0][i].term_code + ": " + json.response.docs[0][i].term_name + "</option>";
                currTerm = json.response.docs[0][i].term_code;
            }
            else {
                dropDown.innerHTML += "<option value=" + json.response.docs[0][i].term_code + ">" + json.response.docs[0][i].term_code + ": " + json.response.docs[0][i].term_name + "</option>";
            }
        }
    }


    //load the table
    function loadTable() {
        $("#sisTable").DataTable({
            destroy: true, info: false, responsive: true, lengthChange: false, autoWidth: false, paging: true, order: [1, "asc"], pageLength: 12,
            dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_SIS_Data_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_SIS_Data_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_SIS_Data_' + getFileDate(), text: 'PDF' }] }, 'colvis'],
            language: {
                emptyTable: "Please Select a Current or Past Term",
                zeroRecords: "No Matching Data in Selected Term"
            },
            ajax: {
                url: "/json/classes/" + configOptions.response.docs.current_term + ".json",
                dataSrc: function (json) {
                    return json.response.docs[0];
                }
            },
            columnDefs: [
                {
                    targets: 0,
                    data: 'campus',
                    render: function (data) {
                        return searchCampuses(data, campuses);
                    }
                },
                {
                    targets: 1,
                    data: 'facility_id'
                },
                {
                    targets: 2,
                    data: 'bldg_code'
                },
                {
                    targets: 3,
                    data: 'bldg_num'
                },
                {
                    targets: 4,
                    data: 'room_num'
                },
                {
                    targets: 5,
                    data: 'pp_search_id'
                },
                {
                    targets: 6,
                    data: 'class_number_code'
                },
                {
                    targets: 7,
                    data: 'coursetitle'
                },
                {
                    targets: 8,
                    data: 'strm'
                },
                {
                    targets: 9,
                    data: 'enrl_tot'
                },
                {
                    targets: 10,
                    data: 'start_date',
                },
                {
                    targets: 11,
                    data: 'end_date',
                },
                {
                    targets: 12,
                    data: function (row) {
                        return new Date(row.start_date + " " + row.meeting_time_start).toLocaleTimeString('eg-GB', { timezone: 'America/New_york' });
                    },
                },
                {
                    targets: 13,
                    data: function (row) {
                        return new Date(row.start_date + " " + row.meeting_time_end).toLocaleTimeString('eg-GB', { timezone: 'America/New_york' });
                    },
                },
                {
                    targets: 14,
                    data: 'monday',
                    render: function (data) {
                        return renderDay(data);
                    }
                },
                {
                    targets: 15,
                    data: 'tuesday',
                    render: function (data) {
                        return renderDay(data);
                    }
                },
                {
                    targets: 16,
                    data: 'wednesday',
                    render: function (data) {
                        return renderDay(data);
                    }
                },
                {
                    targets: 17,
                    data: 'thursday',
                    render: function (data) {
                        return renderDay(data);
                    }
                },
                {
                    targets: 18,
                    data: 'friday',
                    render: function (data) {
                        return renderDay(data);
                    }
                },
                {
                    targets: 19,
                    data: 'saturday',
                    render: function (data) {
                        return renderDay(data);
                    }
                },
                {
                    targets: 20,
                    data: 'sunday',
                    render: function (data) {
                        return renderDay(data);
                    }
                },
                {
                    //hide columns that are only used for ordering or are initially unessecary
                    visible: false,
                    targets: [0, 2, 3, 4, 5]
                }
            ],
        });

        let table = $("#sisTable").DataTable();

        //disable the loading overlay when the table has finished loading data
        table.on('xhr.dt', function (e, settings, json, xhr) {
            document.querySelector(".overlay").style.display = "none";
        })
    }

    function updateTable() {
        let term = document.querySelector('#term').value;

        $("#sisTable").DataTable().ajax.url("/json/classes/" + term + ".json").load(null, false);
    }

    //return a Y or N for displaying the days of the week a class meets
    function renderDay(day) {
        if (day == 1) {
            return 'Y';
        }
        else {
            return 'N';
        }
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
        let table = $('#sisTable').DataTable();
        let len = document.querySelector('#pageLen').value;
        table.page.len(len).draw(false);
    }

    //call the XHR function when the document is loaded.
    loadJsonXHR("/json/configVars/1.json", "init");

    document.querySelector("#pageLen").addEventListener('change', changePageLength);

    document.querySelector("#term").addEventListener('change', updateTable);
}

document.addEventListener('DOMContentLoaded', init);