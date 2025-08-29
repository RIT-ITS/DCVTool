//for security reasons, this whole script is a function, this page is also unused
function init() {
  //global variable to hold categories
  let categories = [];

  //loads data from .json files, this is used to load the categories
  async function loadJsonXHR(url) {

    try {
      let response = await fetch(url);

      if (!response.ok) {
        if (response.status == 404) console.log("404 Error!");
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      let json = await response.json();

      //send to loadCategories
      if (json.response.docs[0].length > 0) {
        if (json.response.docs[0][0].name != null) {
          loadCategories(json);
        }
      }
    }
    catch (e) {
      console.log(`In catch with e = ${e}`);
    }
  }


  //parse categories from category json for later use
  function loadCategories(json) {
    for (let i = 0; i < json.response.docs[0].length; i++) {
      categories[i] = json.response.docs[0][i];
    }

    //once the categories are parsed, load the table.
    loadTable();
  }

  //load and order the table.
  function loadTable() {
    $("#ashrae6-4").DataTable({
      destroy: true, info: false, responsive: true, select: { items: 'cell', style: 'single' }, blurable: true, lengthChange: false, autoWidth: false, paging: false, order: [1, "asc"], pageLength: 12,
      dom: 'Bfrtip', buttons: [{ extend: 'collection', text: 'Export', autoClose: true, buttons: [{ extend: 'copy', text: 'Copy to clipboard' }, { extend: 'csv', filename: 'DCV_Ashrae_6.4_' + getFileDate(), text: 'CSV' }, { extend: 'excel', filename: 'DCV_Ashrae_6.4_' + getFileDate(), text: 'Excel' }, { extend: 'pdf', filename: 'DCV_Ashrae_6.4_' + getFileDate(), text: 'PDF' }] }],
      orderFixed: [2, 'asc'],
      rowGroup: {
        dataSrc: 'category',
        startRender: function (rows, group) {
          return `<strong>${categories[group - 1].name}</strong>`;
        }
      },
      ajax: {
        url: "/json/ashrae6-4/all.json",
        dataSrc: function (json) {
          return json.response.docs[0];
        }
      },
      columnDefs: [
        {
          targets: 0,
          data: 'configuration'
        },
        {
          targets: 1,
          data: 'ez'
        },
        {
          targets: 2,
          data: 'category'
        },
        {
          //hide the category column since it's only used for ordering
          visible: false,
          targets: 2
        }
      ],
    });

    let table = $("#ashrae6-4").DataTable();

    //disable the loading overlay when the table has finished loading data
    table.on('xhr.dt', function (e, settings, json, xhr) {
      document.querySelector(".overlay").style.display = "none";
    })
  }

  //load required urls
  function loadUrls() {
    loadJsonXHR("/json/ashrae6-4categories/all.json")
  }

  //get and format the current date for file exportation purposes
  function getFileDate() {
    let today = new Date()
    let todayDate = today.toISOString().slice(0, 10);
    let todayTime1 = today.toISOString().slice(11, 13);
    let todayTime2 = today.toISOString().slice(14, 16);

    return todayDate + "-" + todayTime1 + "_" + todayTime2;
  }

  //load urls when document is loaded.
  loadUrls();
}

document.addEventListener('DOMContentLoaded', init);