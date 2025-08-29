//for security reasons, this whole script is a function
function init() {
    //adds cells to the row groupings based on the data passed to it
    function addCell(tr, content, colSpan = 1, className = null, attribute = null) {
        let td = document.createElement('th');

        td.colSpan = colSpan;
        td.innerHTML = content;
        td.setAttribute('date', attribute);
        if (className) {
            td.classList.add(className);
        }
        tr.appendChild(td);
    }

    //load the table
    function loadTable() {

        document.querySelector("#first_date").value = new Date().toISOString().slice(0, 10);

        $("#commandTable").DataTable({
            destroy: true, info: false, responsive: true, deferRender: true, lengthChange: false, autoWidth: false, paging: true, orderFixed: [[0, 'asc'], [1, 'asc'], [2, 'asc']], ordering: false, pageLength: 12,
            dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Command_Log_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Command_Log_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Command_Log_' + getFileDate(), text: 'PDF' }] }],
            language: {
                emptyTable: "No Data for Selected Date(s)",
                zeroRecords: "No Matching Command Data Found"
            },
            ajax: {
                url: "/json/setpoint/" + new Date().toISOString().slice(0, 10).replaceAll("-", "") + "00000000.json",
                dataSrc: function (json) {
                    return json.response.docs[0];
                }
            },
            //enabe row grouping. This is also where the 'null' values are inserted into the table at the end of each group
            rowGroup: {
                className: 'custom-group',
                dataSrc: function (row) {
                    if (document.querySelector('#end_date').value != '') {
                        return row.effectivetime.slice(8, 10);
                    }
                    else {
                        return row.uname;
                    }
                },
                startRender:
                    function (rows, group) {
                        let table = $("#commandTable").DataTable();
                        if (document.querySelector('#end_date').value != '') {
                            if (rows[0][rows[0].length - 1] + 1 < table.rows()[0].length) {
                                if (table.cell(rows[0][rows[0].length - 1], 1).data().slice(3, 5) != table.cell(rows[0][rows[0].length - 1] + 1, 1).data().slice(3, 5)) {
                                    table.cell(rows[0][rows[0].length - 1], 3).data("Null");
                                }
                            }
                            else {
                                table.cell(rows[0][rows[0].length - 1], 3).data("Null");
                            }
                            let tr = document.createElement('tr');
                            addCell(tr, rows.data()[0]["uname"] + " - " + new Date(rows.data()[0]['effectivetime']).toLocaleDateString('eg-GB', { timezone: 'America/New_york' }), 7);
                            return tr;
                        }
                        else {
                            if (rows[0][rows[0].length - 1] + 1 < table.rows()[0].length) {
                                if (table.cell(rows[0][rows[0].length - 1], 0).data() != table.cell(rows[0][rows[0].length - 1] + 1, 0).data()) {
                                    table.cell(rows[0][rows[0].length - 1], 3).data("Null");
                                }
                            }
                            else {
                                table.cell(rows[0][rows[0].length - 1], 3).data("Null");
                            }
                            let tr = document.createElement('tr');
                            addCell(tr, rows.data()[0]["uname"], 7);
                            return tr;
                        }
                    },
            },
            columnDefs: [
                {
                    targets: 0,
                    data: 'uname',
                },
                {
                    targets: 1,
                    data: function (row) {
                        return new Date(row.effectivetime).toLocaleDateString('eg-GB', { timezone: 'America/New_york' });
                    },
                },
                {
                    targets: 2,
                    data: function (row) {
                        return new Date(row.effectivetime).toLocaleTimeString('eg-GB', { timezone: 'America/New_york' });
                    },
                },
                {
                    targets: 3,
                    data: 'pv',
                    render: function (data) {
                        if (data == 0) {
                            return data + " (null)"
                        }
                        else {
                            return data;
                        }
                    }
                },
                {
                    targets: 4,
                    data: "xrefs",
                    render: function (data) {
                        let refs = "";
                        let total = 0;
                        if (data != null) {
                            for (let i = 0; i < data.length; i++) {
                                if (i == data.length - 1) {
                                    refs += data[i].ppl_oa_rate * data[i].xref_population;
                                }
                                else {
                                    refs += data[i].ppl_oa_rate * data[i].xref_population + ", ";
                                }
                                total += data[i].ppl_oa_rate * data[i].xref_population * 1;
                            }
                            if (data.length > 1) {
                                return refs + " (" + total + ")";
                            }
                            else {
                                return refs;
                            }
                        }
                        else {
                            return "";
                        }
                    }
                },
                {
                    targets: 5,
                    data: "xrefs",
                    render: function (data) {
                        let refs = "";
                        if (data != null) {
                            for (let i = 0; i < data.length; i++) {
                                if (i == data.length - 1) {
                                    refs += data[i].facility_id;
                                }
                                else {
                                    refs += data[i].facility_id + ", ";
                                }
                            }
                            return refs;
                        }
                        else {
                            return "";
                        }
                    }
                },
                {
                    targets: 6,
                    data: 'dispatched',
                    render: function (data) {
                        if (data == 1) {
                            return "Y";
                        }
                        else {
                            return "N";
                        }
                    }
                },
            ],
        });

        //get a reference to the table
        let table = $("#commandTable").DataTable();

        //disable the loading overlay when the table has finished loading data
        table.on('xhr.dt', function (e, settings, json, xhr) {
            document.querySelector(".overlay").style.display = "none";
        })
    }

    //update the table based on input. This is only used after the page loads.
    function updateTable() {
        let firstDate = document.querySelector('#first_date').value;
        let endDate = document.querySelector('#end_date').value;

        if (endDate == '') {
            endDate = '00000000';
        }

        $("#commandTable").DataTable().ajax.url("/json/setpoint/" + firstDate.replaceAll('-', '') + endDate.replaceAll('-', '') + ".json").load(null, false);
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
        let table = $('#commandTable').DataTable();
        let len = document.querySelector('#pageLen').value;
        table.page.len(len).draw(false);
    }

    //call the XHR function when the document is loaded.
    document.querySelector("#pageLen").addEventListener('change', changePageLength);

    document.querySelector('#first_date').addEventListener('blur', updateTable);

    document.querySelector('#end_date').addEventListener('blur', updateTable);

    loadTable();
}
document.addEventListener('DOMContentLoaded', init);