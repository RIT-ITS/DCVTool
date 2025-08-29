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
if ($authResult['status'] === 'authorized') {
    if($authResult){
        $userData = $security->checkAuthorized();
    } else {
        $userData = null;
    }
$pageTitle = "Outdoor Airflow Per Event";
?>
<!DOCTYPE html>
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
        <div class="row">
          <div class="col-12">

            <div class="card" id="event_airflow_wrapper">
              <!--card header-->
              <div class="card-header">
                <h3 class="card-title">Outdoor Airflow Calculations</h3>
              </div>
              <!--.card body-->
              <div class="card-body">

                <!--Loading Overlay-->
                <div class="overlay-wrapper overlay">
                  <div class="overlay"><i class="fas fa-3x fa-sync-alt fa-spin"></i><div class="text-bold pt-2">Loading...</div></div>
                </div>

                <label for="term">Term:</label>
                <select name="term" id="term" class="customInput">
                  <option value="0"></option>
                </select>

                <label for="campus">Campus:</label>
                <select name="campus" id="campus" class="customInput">
                  <option value="0"> </option>
                </select>

                <label for="building">Building:</label>
                <select  name="building" id="building" class="customInput">
                  <option value="0"> </option>
                </select><br>

                <label for="pageLen">Page Length:</label>
                <input class="customInput" type="number" id="pageLen" name="pageLen" min="1" value="12">

                <table id="airflowTable" class="table table-bordered">
                  <thead>
                  <tr>
                      <th></th>
                      <th>Space ID</th>
                      <th>Space Name</th>
                      <th>Class Name</th>
                      <th>Class Section</th>
                      <th>Event Enrollment</th>
                      <th>Room Capacity (+Uncertainty)</th>
                      <th>Event Enrollment (+Uncertainty)</th>
                      <th>People OA Rate Rp (cfm/pp)</th>
                      <th>Oa_people Event (cfm)</th>
                      <th>Zone Name</th>
                      <th>Pz/Pr_max</th>
                      <th>Max Population Per Zone Pz_max</th>
                      <th>Event Population Per Zone Pz_dyn</th>
                      <th>Oa_people Zone (cfm)</th>
                      <th>Start Date</th>
                      <th>End Date</th>
                      <th>Start Time</th>
                      <th>End Time</th>
                      <th>Mon</th>
                      <th>Tues</th>
                      <th>Weds</th>
                      <th>Thurs</th>
                      <th>Fri</th>
                      <th>Sat</th>
                      <th>Sun</th>
                  </tr>
                  </thead>
                  <tbody>
                    <!--table content goes here-->
                  </tbody>
                </table>
                <!--card body-->
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

    <!--Modal for adding rows-->
    <div id="addAirflowModal" class="modal">
      <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Add Zone</h2>
        <form id="airflowForm">
        <label class="input customSelect" for="zone_id">Zone Code: </label>
          <input class="input customSelect" type="text" id="zone_code" name="zone_code" required><br>
          <label class="input customSelect" for="room_id">Room Number: </label>
          <input class="input customSelect" type="number" id="room_num" name="room_num" required><br>
          <label class="input customSelect" for="pr_percent">Pr/Pz: </label>
          <input class="input customSelect" type="number" step="0.0001" id="pr_percent" name="pr_percent" required><br>
          <div class="mt-3 d-flex justify-content-between">
          <button class="btn btn-primary" type="submit" id="addButton">Add</button>
          <button class="btn btn-default" type="button" id="cancelButton">Cancel</button>
          </div>
        </form>
      </div>
    </div>

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
<script src="AdminLTE-3.2.0/plugins/jszip/jszip.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/pdfmake/pdfmake.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/pdfmake/vfs_fonts.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>

<script src="AdminLTE-3.2.0/plugins/datatables-select/js/dataTables.select.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-select/js/dataTables.select.min.js"></script>
<script src="AdminLTE-3.2.0/plugins/datatables-select/js/select.bootstrap4.js"></script>

<!-- DataTables RowGroup lets us group by row data-->
<script src="AdminLTE-3.2.0/plugins/datatables-rowgroup/js/dataTables.rowGroup.min.js"></script>


<!-- AdminLTE App -->
<script src="AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>

<!--App Script-->
<script src="src/js/App-outdoorAirflowEvents.js"></script>

<!-- Toastr -->
<script src="AdminLTE-3.2.0/plugins/toastr/toastr.min.js"></script>

</body>
</html>
<?php } ?>
