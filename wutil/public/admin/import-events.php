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
            $pageTitle = "Import Events";
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
      <div class="container-fluid">

          <!-- /.col -->
        <!-- /.row -->

        <!--Equipment Row-->
        <div class="row">

          <div class="col-lg-6">

            <div class="card" id="config_wrapper">
              <!--card header-->
              <div class="card-header">
                <h3 class="card-title">Import Event</h3>
              </div>
              <!--card body-->
              <div class="card-body" id="equipment_tree">
                <div class="col-lg-12">
                  <p>Add a single-occurance event to the Expanded Schedule Data. For importing one or more re-occuring classes, see the imports page. <strong>Note:</strong> at the <em>latest</em>, events must be entered before midnight on the day before they occur.</p>
                  <form id="subForm">
                  <label for="event_name">Event Name: </label>
                  <input class="input customSelect customInput" type="text" id="coursetitle" name="coursetitle" required><br>

                  <label for="campus">Campus: </label>
                  <select name="campus" id="campus" class="customSelect customInput" required>
                  </select><br>

                  <label for="bldg_num">Building: </label>
                  <select name="bldg_num" id="bldg_num" class="customSelect customInput" required>
                  </select><br>
                  
                  <label for="room_num">Room Code: </label>
                  <input disabled required type="text" name="room_num" id="room_num" class="input customSelect customInput"><br>

                  <label for="enrl_tot">Event Enrollment: </label>
                  <input class="input customSelect customInput" type="number" id="enrl_tot" name="enrl_tot" min="1" required><br>

                  <label for="start_date">Event Date: </label>
                  <input class="input customSelect customInput" type="date" id="start_date" name="start_date" required><br>

                  <label for="start_time">Start Time: </label>
                  <input class="input customSelect customInput" type="time" id="start_time" name="start_time" required>

                  <label for="end_time">End Time: </label>
                  <input class="input customSelect customInput" type="time" id="end_time" name="end_time" required> (EST)<br>

                  <div class="mt-3 d-flex justify-content-between">
                    <button class="btn btn-primary" type="submit" disabled id="addButton">Submit</button>
                    <button class="btn btn-default" type="button" id="cancelButton">Clear</button>
                  </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <div class="col-6">
            
            <div class="card" id="import_wrapper" style="position: sticky">
              <!--card header-->
              <div class="card-header">
                <h3 class="card-title">Output</h3>
              </div>
              <!--.card header-->

              <div class="card-body">
                <div class="col-lg-12">
                  <div id="output"></div>
                </div>
              </div>

              <!--/card-->
            </div>
            <!--/col-12-->            
          </div>

          </div>
        </div>
    
    </section>
    <!-- /.content -->
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
<script src="src/js/App-import-events.js"></script>

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
