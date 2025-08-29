<?php
// Include your bootstrap file that initializes the DatabaseManager
require_once __DIR__ . '/../../bootstrap.php';
use App\Container\ServiceContainer;
use App\Security\Security;
// Initialize ServiceContainer
$container = ServiceContainer::getInstance();
// Get Security service from container (or instantiate it if not in container yet)
if ($container->has('security')) {
    $security = $container->get('security');
} else {
    $security = new Security($container->get('dbManager'));
}
$authResult = $security->checkAuthenticationStatus();
// If user is authenticated, show the page content
if ($authResult['status'] === 'authorized'){
    if($authResult){
        $userData = $security->checkAuthorized();
        if($userData['role'] > 2){
            $pageTitle = "Import";
?>
<!DOCTYPE html>
<html lang="en">
<!--
This is a starter template page. Use this page to start your new project from
scratch. This page gets rid of all links and provides the needed markup only.
-->
<html lang="en">
<?php include_once('src/php/htmlhead.php'); ?>

<body class="hold-transition sidebar-mini"> <!-- sidebar collaspe"> -->
<div class="wrapper">

<!-- Navbar -->
  <?php include_once('src/php/main-header.php'); ?>
<!-- /.navbar -->

 <!-- Main Sidebar Container -->
 <aside class="main-sidebar sidebar-no-expand sidebar-light-primary  elevation--10">
  <!-- Brand Logo -->
  <a href="index.php" class="brand-link">
    <img src="src/images/RIT_orange.jpg" alt="RIT Logo" class="brand-image elevation-0" style="opacity: 1">
    <span class="brand-text font-weight-light">DCV Tool</span>
  </a>

  <!-- Sidebar -->
  <div class="sidebar">
    <!-- Sidebar user panel (optional) -->
    

    <!-- SidebarSearch Form -->

    <!-- Sidebar Menu -->
    <nav class="mt-2">
    <?php include "src/php/menu.php" ?>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>

 
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <?php include_once('src/php/content-header.php'); ?>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">

      <!--Loading Overlay-->
      <div class="overlay-wrapper overlay">
        <div class="overlay" style="display:none"><i class="fas fa-3x fa-sync-alt fa-spin"></i><div class="text-bold pt-2">Loading...</div></div>
      </div>

      <div class="container-fluid">
        <div class="row">
          <div class="col-6">
            
            <div class="card" id="import_wrapper">
              <div class="card-header">
                <ul class="nav nav-pills ml-auto">
                  <li class="nav-item">
                    <a class="nav-link active" href="#spaceTab" data-toggle="tab">Import Spaces</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="#eventTab" data-toggle="tab">Import SIS Data</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="#zoneTab" data-toggle="tab">Import Zones</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="#exportTab" data-toggle="tab">Additional Exports</a>
                  </li>
                </ul>
              </div>

              <div class="card-body">
                <!--Hidden input used for file uploads-->
                <input type="file" id="import" name="fileUpload" class="fileSelector" accept=".csv" style="display: none">
                <div class="tab-content p-0">
                  <div class="tab-pane active" id="spaceTab">
                    <p>Import bulk space data via a CSV file. This file must follow a specific format, <em><strong>use the template provided below</strong></em>.</p>

                    <p><strong>Warning:</strong> Importing adds a large amount of data to multiple tables. Be sure the data you are uploading is accurate and formatted correctly.</p>

                    <div class="row">
                      <div class="col-lg-12">
                        <label for="campus">Import data to:</label>
                        <select id="campus" class="input customInput campus" name="campus">
                          <option value=0></option>
                        </select>
                      </div>
                    </div>

                    <div class="mt-3 d-flex justify-content-between">
                      <a class="btn btn-default" href="src/files/space_import_template.csv">Space Template <i class="fas fa-download"></i></a>
                
                      <button importType="space" id="spaceImportButton" class="btn btn-primary" disabled>Upload <i class="fas fa-upload"></i></button>
                    </div>
                  </div>

                  <div class="tab-pane" id="eventTab">
                    <p>Import bulk SIS class data via a CSV file. To import a single non-class event, see the Event Import page. This file must follow a specific format, <em><strong>use the template provided below</strong></em>.</p>

                    <p><strong>Warning:</strong> Importing adds a large amount of data to multiple tables. Be sure the data you are uploading is accurate and formatted correctly.</p>

                    <div class="row">
                      <div class="col-lg-12">
                        <label for="eventCampus">Import data to:</label>
                        <select id="eventCampus" class="input customInput campus" name="eventCampus">
                          <option value=0></option>
                        </select>
                      </div>
                    </div>
                    
                    <div class="mt-3 d-flex justify-content-between">
                      <a class="btn btn-default" href="src/files/sis_import_template.csv">Event Template <i class="fas fa-download"></i></a>
                
                      <button importType="event" id="eventImportButton" style="float: right" class="btn btn-primary">Upload <i class="fas fa-upload"></i></button>
                    </div>
                  </div>

                  <div class="tab-pane" id="zoneTab">
                    <p>Import bulk zone data via a CSV file. This file must follow a specific format, <em><strong>use the template provided below.</strong></em>. Note that each value in a row beyond the 'spaces served by zone' column will be treated as an additional room to link to the zone. </p>

                    <p><strong>Warning:</strong> Importing adds a large amount of data to multiple tables. Be sure the data you are uploading is accurate and formatted correctly.</p>

                    <div class="row">
                      <div class="col-lg-12">
                        <label for="zoneCampus">Import data to:</label>
                        <select id="zoneCampus" class="input customInput campus" name="zoneCampus">
                          <option value=0></option>
                        </select>
                      </div>
                    </div>

                    <div class="mt-3 d-flex justify-content-between">
                      <a class="btn btn-default" href="src/files/zone_import_template.csv">Zone Template <i class="fas fa-download"></i></a>
                
                      <button importType="zone" id="zoneImportButton" style="float: right" class="btn btn-primary">Upload <i class="fas fa-upload"></i></button>
                    </div>
                  </div>

                  <div class="tab-pane" id="exportTab">
                    <p>Export lists of data that meet criteria not provided in the tables.</p>
                    <div class="row">
                      <div class="col-lg-12">
                        <label for="exportMode">Export: </label>
                        <select id="exportMode" class="input customInput" name="exportMode">
                          <option value=1>Rooms with linked Zones/Vavs</option>
                          <option value=2>Rooms with Events</option>
                          <option value=3>Zones/Vavs with Events</option>
                        </select>
                      </div>
                    </div>
                    
                    <div class="row">
                      <div class="col-lg-12">
                        <label for="exportCampus">From Campus: </label>
                        <select id="exportCampus" class="input customSelect customInput campus" name="exportCampus">
                          <option value=0></option>
                        </select>
                      </div>
                    </div>

                    <div class="row termRow hidden">
                      <div class="col-lg-12">
                        <label for="exportTerm">From Term: </label>
                        <select id="exportTerm" class="input customSelect customInput" name="exportTerm"></select>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-lg-12">
                        <label for="exportBuilding">From Building: </label>
                        <select id="exportBuilding" class="input customSelect customInput" name="exportBuilding">
                          <option value=0></option>
                        </select>
                      </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-end">
                      <button id="exportButton" style="float: right" class="btn btn-primary">Download <i class="fas fa-download"></i></button>
                    </div>
                  </div>
                  

                </div>

              </div>

            </div>

            <!--/col-12-->            
          </div>

          <div class="col-6">
            
            <div class="card" id="import_wrapper">
              <!--card header-->
              <div class="card-header">
                <h3 class="card-title">Output</h3>
              </div>
              <!--.card header-->

              <div class="card-body">
                <div id="Output"></div>
              </div>

              <!--/card-->
            </div>
            <!--/col-12-->            
          </div>
          <!-- /.col -->
        </div>
        <!-- /.row -->  
      </div>
      <!-- /.container-fluid -->
    </section>
    <!-- /.content -->

    <dialog id="alertImport" autofocus="true">
      <div>
        <p id="alertSpaceText">this is a dialog</p>
        <button id="cancelImport" class="btn btn-danger cancelImport">Cancel</button>
        <button id="confirmImport" style="float: right" class="btn btn-default">Confirm</button>
      </div>
    </dialog>

    <!--<dialog id="alertEventImport" autofocus="true">
      <div>
        <p id="alertEventText">this is a dialog</p>
        <button id="cancelEventImport" class="btn btn-danger cancelImport">Cancel</button>
        <button id="confirmEventImport" style="float: right" class="btn btn-default">Confirm</button>
      </div>
    </dialog>

    <dialog id="alertZoneImport" autofocus="true">
      <div>
        <p id="alertZoneText">this is a dialog</p>
        <button id="cancelZoneImport" class="btn btn-danger cancelImport">Cancel</button>
        <button id="confirmZoneImport" style="float: right" class="btn btn-default">Confirm</button>
      </div>
    </dialog>-->

  </div>
  <!-- /.content-wrapper -->

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
    <div class="p-3">
      <h5>Title</h5>
      <p>Sidebar content</p>
    </div>
  </aside>
  <!-- /.control-sidebar -->

  <!-- Main Footer -->
    <!-- Main Footer -->
    <?php include "src/php/footer.php" ?>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>



<!-- DataTables  & Plugins -->
<script src="AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>

<script src="AdminLTE-3.2.0/plugins/datatables-select/js/dataTables.select.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-select/js/dataTables.select.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-select/js/select.bootstrap4.js"></script>

<script src="AdminLTE-3.2.0/plugins/jszip/jszip.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/pdfmake/pdfmake.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/pdfmake/vfs_fonts.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>

<!-- AdminLTE App -->
<script src="AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>

<!-- App Script -->
<script src="src/js/App-import.js"></script>

<!-- Toastr -->
<script src="AdminLTE-3.2.0/plugins/toastr/toastr.min.js"></script>

</body>
</html>
<?php
        }
    }
} else {
  include_once("src/php/unauthed.php");
} ?>
