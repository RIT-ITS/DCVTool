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

        $("#dynamicTable").DataTable({
            destroy: true, info: false, responsive: true, deferRender: true, lengthChange: false, autoWidth: false, paging: true, orderFixed: [[0, 'asc'], [2, 'asc'], [3, 'asc']], ordering: false, pageLength: 12,
            dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Dynamic_Calculations_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Dynamic_Calculations_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Dynamic_Calculations_' + getFileDate(), text: 'PDF' }] }],
            language: {
                emptyTable: "No Data for Selected Date(s)",
                zeroRecords: "No Matching Data for Selected Date(s)"
            },
            ajax: {
                url: "/json/setpointExpanded/" + new Date().toISOString().slice(0, 10).replaceAll("-", "") + "00000000.json",
                dataSrc: function (json) {
                    return json.response.docs[0];
                }
            },
            //enable row grouping. This is also where the null values are rendered at the end of each group
            rowGroup: {
                className: 'custom-group',
                dataSrc: function (row) {
                    if (document.querySelector('#end_date').value == '') {
                        return row.zone_name;
                    }
                    else {
                        return row.effectivetime.slice(8, 10);
                    }
                },
                startRender:
                    function (rows, group) {
                        //get a reference to the table and the second date control
                        let table = $("#dynamicTable").DataTable();
                        let secondDate = document.querySelector('#end_date').value;
                        if (secondDate == '') {
                            //Code to display null at the end of groups
                            if (rows[0][rows[0].length - 1] + 1 < table.rows()[0].length) {
                                if (table.cell(rows[0][rows[0].length - 1], 0).data() != table.cell(rows[0][rows[0].length - 1] + 1, 0).data()) {
                                    table.cell(rows[0][rows[0].length - 1], 8).data("Null");
                                }
                            }
                            else {
                                table.cell(rows[0][rows[0].length - 1], 8).data("Null");
                            }

                            let tr = document.createElement('tr');
                            addCell(tr, rows.data()[0]["zone_name"], 11);
                            return tr;
                        }
                        else {
                            //Code to display null at the end of groups disabled until we get more data
                            if (rows[0][rows[0].length - 1] + 1 < table.rows()[0].length) {
                                if (table.cell(rows[0][rows[0].length - 1], 2).data().slice(3, 5) != table.cell(rows[0][rows[0].length - 1] + 1, 2).data().slice(3, 5)) {
                                    table.cell(rows[0][rows[0].length - 1], 8).data("Null");
                                }
                            }
                            else {
                                table.cell(rows[0][rows[0].length - 1], 8).data("Null");
                            }

                            let tr = document.createElement('tr');
                            addCell(tr, rows.data()[0]["zone_name"] + " - " + new Date(rows.data()[0]['effectivetime']).toLocaleDateString(), 11);
                            return tr;
                        }
                    },
            },
            columnDefs: [
                {
                    targets: 0,
                    data: 'zone_name',
                },
                {
                    targets: 1,
                    data: 'facility_id',
                },
                {
                    targets: 2,
                    data: function (row) {
                        return new Date(row.effectivetime).toLocaleDateString('eg-GB', { timezone: 'America/New_york' });
                    }
                },
                {
                    targets: 3,
                    data: function (row) {
                        return new Date(row.effectivetime).toLocaleTimeString('eg-GB', { timezone: 'America/New_york' });
                    }
                },
                {
                    targets: 4,
                    data: 'coursetitle',
                    render: function (data, type) {
                        if (data == null || data == "") {
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
                    data: 'uncert_amt',
                },
                {
                    targets: 7,
                    data: 'pr_percent',
                },
                {
                    targets: 8,
                    data: 'pv',
                    render: function (data) {
                        if (data * 1 == 0) {
                            return data + " (null)";
                        }
                        else {
                            return data;
                        }
                    }
                },
                {
                    targets: 9,
                    data: { "xref_population": "xref_population", "ppl_oa_rate": "ppl_oa_rate" },
                    render: function (data) {
                        return (data.xref_population * data.ppl_oa_rate).toFixed(2);
                    }
                },
                {
                    targets: 10,
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

        let table = $("#dynamicTable").DataTable();

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

        $("#dynamicTable").DataTable().ajax.url("/json/setpointExpanded/" + firstDate.replaceAll('-', '') + endDate.replaceAll('-', '') + ".json").load(null, false);
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
        let table = $('#dynamicTable').DataTable();
        let len = document.querySelector('#pageLen').value;
        table.page.len(len).draw(false);
    }

    document.querySelector("#pageLen").addEventListener('change', changePageLength);

    document.querySelector('#first_date').addEventListener('blur', updateTable);

    document.querySelector('#end_date').addEventListener('blur', updateTable);

    loadTable();

}

//call the init function when the document is loaded.
document.addEventListener('DOMContentLoaded', init);