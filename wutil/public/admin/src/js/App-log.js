//for security reasons, this whole script is a function
function init() {

  //load data into the table.
  function loadTable() {
    $('#logTable').DataTable({
      destroy: true, responsive: true, info: false, lengthChange: false, autoWidth: false, order: [0, "desc"], pageLength: 12,
      dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_System_Log_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_System_Log_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_System_Log_' + getFileDate(), text: 'PDF' }] }],
      language: {
        zeroRecords: "No Matching Logs Found"
      },
      ajax: {
        url: "/json/logs/all.json",
        dataSrc: function (json) {
          return json.response.docs[0];
        }
      },
      columnDefs: [
        {
          targets: 0,
          data: 'id'
        },
        {
          targets: 1,
          data: 'tag'
        },
        {
          targets: 2,
          data: 'logged_element'
        },
        {
          targets: 3,
          data: function (row) {
            return new Date(row.logged_date);
          },
          render: function (data) {
            return data.toLocaleString('eg-GB', { timeZone: "America/New_york" });
          }
        }
      ],
    });


    //get a reference to the dataTable
    let table = $('#logTable').DataTable();


    //disable the loading overlay when the table has finished loading data
    table.on('xhr.dt', function (e, settings, json, xhr) {
      document.querySelector(".overlay").style.display = "none";
    })

  }

  //get and format the current date for file exportation purposes
  function getFileDate() {
    let today = new Date()
    let todayDate = today.toISOString().slice(0, 10);
    let todayTime1 = today.toISOString().slice(11, 13);
    let todayTime2 = today.toISOString().slice(14, 16);

    return todayDate + "-" + todayTime1 + "_" + todayTime2;
  }

  loadTable();
}

document.addEventListener('DOMContentLoaded', init);