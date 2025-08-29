//for security reasons, this whole script is a function
function init() {

  //load data into the table.
  function loadTables() {
    $('#importsTable').DataTable({
      destroy: true, responsive: true, info: false, lengthChange: false, autoWidth: false, order: [0, "asc"], pageLength: 12,
      dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Import_Log_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Import_Log_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Import_Log_' + getFileDate(), text: 'PDF' }] }],
      language: {
        zeroRecords: "No Matching Import Logs Found"
      },
      ajax: {
        url: "/json/loggedImports/all.json",
        dataSrc: function (json) {
          return json.response.docs[0];
        }
      },
      columnDefs: [
        {
          targets: 0,
          className: 'dt-control',
          orderable: false,
          data: null,
          defaultContent: ''
        },
        {
          targets: 1,
          data: 'id',
        },
        {
          targets: 2,
          data: 'import_type',
        },
        {
          targets: 3,
          data: function (row) {
            return new Date(row.import_date);
          },
          render: function (data) {
            return data.toLocaleString('eg-GB', { timeZone: "America/New_york" });
          }
        }
      ],
    });


    //get a reference to the dataTable
    let table = $('#importsTable').DataTable();

    // Add event listener for opening and closing details
    table.on('click', 'td.dt-control', function (e) {
      let tr = e.target.closest('tr');
      let row = table.row(tr);

      if (row.child.isShown()) {
        // This row is already open - close it
        row.child.hide();
      }
      else {
        // Open this row
        row.child.show();
      }
    });

    //disable the loading overlay when the table has finished loading data
    table.on('xhr.dt', function (e, settings, json, xhr) {
      document.querySelector(".overlay").style.display = "none";
    })

    //generate child rows when the table is drawn
    table.on('draw', function () {
      table.rows().every(function (rowIdx, tableLoop, rowLoop) {
        generateChild(this);
      });
    });
  }

  //generate a child row based on the type of import preformed
  function generateChild(row) {

    if (row.data().import_type == "buildings") {

      let returning = '<table class="table childTable table-bordered"><thead><tr><th>Id</th><th>Building Number</th><th>Campus Id</th><th>Building Name</th><th>Building Code</th></tr></thead><tbody>';
      for (let i = 0; i < row.data().import_data.length; i++) {
        returning += '<tr>' +
          '<td>' + row.data().import_data[i].id + '</td>' +
          '<td>' + row.data().import_data[i].bldg_num + '</td>' +
          '<td>' + row.data().import_data[i].campus_id + '</td>' +
          '<td>' + row.data().import_data[i].bldg_name + '</td>' +
          '<td>' + row.data().import_data[i].facility_code + '</td>' +
          '</tr>'
      };
      returning += '</tbody></table>'
      row.child(returning);
    }
    else if (row.data().import_type == "floors") {

      let returning = '<table class="table childTable table-bordered"><thead><tr><th>Id</th><th>Floor Designation</th><th>Campus Id</th><th>Building Id</th></tr></thead><tbody>';
      for (let i = 0; i < row.data().import_data.length; i++) {
        returning += '<tr>' +
          '<td>' + row.data().import_data[i].id + '</td>' +
          '<td>' + row.data().import_data[i].floor_designation + '</td>' +
          '<td>' + row.data().import_data[i].campus_id + '</td>' +
          '<td>' + row.data().import_data[i].buildings_id + '</td>' +
          '</tr>'
      }
      returning += '</table>'
      row.child(returning);

    }
    else if (row.data().import_type == "rooms") {

      let returning = '<table class="table childTable table-bordered"><thead><tr><th>Id</th><th>Campus Id</th><th>Building Id</th><th>Floor Id</th><th>Facility Id</th><th>Room Number</th><th>Room Name</th><th>Room Area</th><th>Population</th><th>Uncertainty Amount</th><th>Ashrae 6-1 Type Id</th><th>Space Use Code</th><th>Space Use Name</th><th>Reservable</th><th>Active</th></tr></thead><tbody>';
      for (let i = 0; i < row.data().import_data.length; i++) {
        returning +=
          '<tr>' +
          '<td>' + row.data().import_data[i].id + '</td>' +
          '<td>' + row.data().import_data[i].campus_id + '</td>' +
          '<td>' + row.data().import_data[i].building_id + '</td>' +
          '<td>' + row.data().import_data[i].floor_id + '</td>' +
          '<td>' + row.data().import_data[i].facility_id + '</td>' +
          '<td>' + row.data().import_data[i].room_num + '</td>' +
          '<td>' + row.data().import_data[i].room_name + '</td>' +
          '<td>' + row.data().import_data[i].room_area + '</td>' +
          '<td>' + row.data().import_data[i].room_population + '</td>' +
          '<td>' + row.data().import_data[i].uncert_amt + '</td>' +
          '<td>' + row.data().import_data[i].ash61_cat_id + '</td>' +
          '<td>' + row.data().import_data[i].rtype_code + '</td>' +
          '<td>' + row.data().import_data[i].space_use_name + '</td>' +
          '<td>' + row.data().import_data[i].reservable + '</td>' +
          '<td>' + row.data().import_data[i].active + '</td>' +
          '</tr>'
      }
      returning += '</table>'

      row.child(returning);
    }
    else if (row.data().import_type == "classes") {
      let returning = '<table class="table childTable table-bordered"><thead><tr><th>Id</th><th>Search Id</th><th>Title</th><th>Occupancy</th><th>Space Id</th><th>Building Code</th><th>Campus Id</th><th>Start Date</th><th>End Date</th><th>Start Time</th><th>End Time</th><th>Mon</th><th>Tues</th><th>Weds</th><th>Thurs</th><th>Fri</th><th>Sat</th><th>Sun</th></tr></thead><tbody>'
      for (let i = 0; i < row.data().import_data.length; i++) {
        returning +=
          '<tr>' +
          '<td>' + row.data().import_data[i].id + '</td>' +
          '<td>' + row.data().import_data[i].pp_search_id + '</td>' +
          '<td>' + row.data().import_data[i].coursetitle + '</td>' +
          '<td>' + row.data().import_data[i].enrl_tot + '</td>' +
          '<td>' + row.data().import_data[i].facility_id + '</td>' +
          '<td>' + row.data().import_data[i].bldg_code + '</td>' +
          '<td>' + row.data().import_data[i].campus + '</td>' +
          '<td>' + row.data().import_data[i].start_date + '</td>' +
          '<td>' + row.data().import_data[i].end_date + '</td>' +
          '<td>' + row.data().import_data[i].meeting_time_start + '</td>' +
          '<td>' + row.data().import_data[i].meeting_time_end + '</td>' +
          '<td>' + row.data().import_data[i].monday + '</td>' +
          '<td>' + row.data().import_data[i].tuesday + '</td>' +
          '<td>' + row.data().import_data[i].wednesday + '</td>' +
          '<td>' + row.data().import_data[i].thursday + '</td>' +
          '<td>' + row.data().import_data[i].friday + '</td>' +
          '<td>' + row.data().import_data[i].saturday + '</td>' +
          '<td>' + row.data().import_data[i].sunday + '</td>' +
          '</tr>'
      }
      returning += '</table>'

      row.child(returning);
    }
    else if (row.data().import_type == "zones") {
      let returning = '<table class="table childTable table-bordered"><thead><tr><th>Id</th><th>Zone Name</th><th>Zone Code</th><th>Building Id</th><th>Ahu</th><th>Campus Id</th><th>Active</th></tr></thead><tbody>'
      for (let i = 0; i < row.data().import_data.length; i++) {
        returning +=
          '<tr>' +
          '<td>' + row.data().import_data[i].id + '</td>' +
          '<td>' + row.data().import_data[i].zone_name + '</td>' +
          '<td>' + row.data().import_data[i].zone_code + '</td>' +
          '<td>' + row.data().import_data[i].building_id + '</td>' +
          '<td>' + row.data().import_data[i].ahu_name + '</td>' +
          '<td>' + row.data().import_data[i].campus + '</td>' +
          '<td>' + row.data().import_data[i].active + '</td>' +
          '</tr>'
      }
      returning += '</table>'
      row.child(returning);
    }
    else if (row.data().import_type == "xrefs") {
      let returning = '<table class="table childTable table-bordered"><thead><tr><th>Id</th><th>Zone Name</th><th>Space Id</th></tr></thead><tbody>'
      for (let i = 0; i < row.data().import_data.length; i++) {
        returning +=
          '<tr>' +
          '<td>' + row.data().import_data[i].id + '</td>' +
          '<td>' + row.data().import_data[i].zone_name + '</td>' +
          '<td>' + row.data().import_data[i].facility_id + '</td>' +
          '</tr>'
      }
      returning += '</table>'
      row.child(returning);
    }
    else if (row.data().import_type == "events") {
      let returning = '<table class="table childTable table-bordered"><thead><tr><th>Id</th><th>Event Name</th><th>Campus Id</th><th>Building Num</th><th>Building Code</th><th>Room Num</th><th>Facility Id</th><th>Enrl Tot</th><th>Term</th><th>Datetime Start</th><th>Datetime End</th></tr></thead><tbody>'
      returning +=
        '<tr>' +
        '<td>' + row.data().import_data.id + '</td>' +
        '<td>' + row.data().import_data.coursetitle + '</td>' +
        '<td>' + row.data().import_data.campus + '</td>' +
        '<td>' + row.data().import_data.bldg_num + '</td>' +
        '<td>' + row.data().import_data.bldg_code + '</td>' +
        '<td>' + row.data().import_data.room_number + '</td>' +
        '<td>' + row.data().import_data.facility_id + '</td>' +
        '<td>' + row.data().import_data.enrl_tot + '</td>' +
        '<td>' + row.data().import_data.strm + '</td>' +
        '<td>' + row.data().import_data.datetime_start + '</td>' +
        '<td>' + row.data().import_data.datetime_end + '</td>' +
        '</tr>'
      returning += '</table>'

      row.child(returning);
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

  loadTables();
}
document.addEventListener('DOMContentLoaded', init);