//for security reasons, this whole script is a function
function init() {
  //load data into the table. This is done here because we need to wait for the types to load
  function loadTable() {

    //pre-load today's date
    document.querySelector("#first_date").value = new Date().toISOString().slice(0, 10);

    $('#updatesTable').DataTable({
      destroy: true, responsive: true, info: false, select: { items: 'cell', style: 'single' }, blurable: true, lengthChange: false, autoWidth: false, order: [0, "desc"], pageLength: 12,
      dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Updates_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Updates_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Updates_' + getFileDate(), text: 'PDF' }] }],
      language: {
        emptyTable: "No Updates Logged for Selected Date(s)",
        zeroRecords: "No Matching Updates Logged for Selected Date(s)"
      },
      ajax: {
        url: "/json/updatedVars/" + new Date().toISOString().slice(0, 10).replaceAll("-", "") + "00000000.json",
        dataSrc: function (json) {
          return json.response.docs[0];
        }
      },
      columnDefs: [
        {
          targets: 0,
          data: 'id',
        },
        {
          targets: 1,
          data: 'common_name',
        },
        {
          targets: 2,
          data: 'updated_table_name',
        },
        {
          targets: 3,
          data: 'updated_table_id',
        },
        {
          targets: 4,
          data: 'column_name',
        },
        {
          targets: 5,
          data: 'old_value',
          render: function (data) {
            if (data == null) {
              return "null"
            }
            else {
              return data;
            }
          }
        },
        {
          targets: 6,
          data: 'new_value',
          render: function (data) {
            if (data == null) {
              return "null"
            }
            else {
              return data;
            }
          }
        },
        {
          targets: 7,
          data: function (row) {
            return new Date(row.time_updated);
          },
          render: function (data) {
            return data.toLocaleString('eg-GB', { timeZone: "America/New_york" });
          }
        }
      ],
    });

    //get a reference to the dataTable
    let table = $('#updatesTable').DataTable();

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

    $("#updatesTable").DataTable().ajax.url("/json/updatedVars/" + firstDate.replaceAll('-', '') + endDate.replaceAll('-', '') + ".json").load(null, false);
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
  document.querySelector('#first_date').addEventListener('blur', updateTable);

  document.querySelector('#end_date').addEventListener('blur', updateTable);

  loadTable();
}

document.addEventListener('DOMContentLoaded', init);