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
$pageTitle = "Space Distribution by Zone";
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

            <div class="card" id="xref_wrapper">
              <!--card header-->
              <div class="card-header">
                <h3 class="card-title">Distribute Space Area and Max. Population (Capacity + Uncertainty) for each Zone</h3>
              </div>
              <!--.card body-->
              <div class="card-body">

                <!--Loading Overlay-->
                <div class="overlay-wrapper overlay">
                  <div class="overlay"><i class="fas fa-3x fa-sync-alt fa-spin"></i><div class="text-bold pt-2">Loading...</div></div>
                </div>

                <label for="campus">Campus:</label>
                <select name="campus" id="campus" class="customInput">
                  <option value="0"> </option>
                </select>

                <label for="building">Building:</label>
                <select  name="building" id="building" class="customInput">
                  <option value="0"> </option>
                </select>

                <label for="pageLen">Page Length:</label>
                <input class="customInput" type="number" id="pageLen" name="pageLen" min="1" value="12"><br>

                <table id="xrefTable" class="table table-bordered">
                  <thead>
                  <tr>
                          <th></th>
                          <th>Space ID / Zone Name</th>
                          <th>Area (sqft)</th>
                          <th>Max Population</th>
                          <th>Population Distribution (Pz/Pr)</th>
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
    <div id="addXrefModal" class="modal">
      <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Add Zone</h2>
        <form id="xrefForm">
        <label class="input customSelect" for="zone_id">Zone Name: </label>
          <input class="input customSelect" type="text" id="zone_name" name="zone_name" required><br>
          <label class="input customSelect" for="room_id">Space Id: </label>
          <input class="input customSelect" type="text" id="facility_id" name="facility_id" required><br>
          <label class="input customSelect" for="xref_area">Area (sqft): </label>
          <input class="input customSelect" type="number" id="xref_area" name="xref_area" step="any" required><br>
          <label class="input customSelect" for="xref_population">Max Population: </label>
          <input class="input customSelect" type="number" id="xref_population" name="xref_population" required><br>
          <div class="mt-3 d-flex justify-content-between">
          <button class="btn btn-primary" type="submit" id="addButton">Add</button>
          <button class="btn btn-default" type="button" id="cancelButton">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!--Dialog box for row deletions-->
    <dialog id="alertXrefDel" autofocus="true">
      <div>
        <p class="alertTextDel">this is a dialog</p>
        <button style="margin-right: 10px" id="cancelXrefDel" class="btn btn-default">Cancel</button>
        <button id="confirmXrefDel" style="float: right" class="btn btn-danger">Delete</button>
      </div>
    </dialog>

    <!--Dialog box for auto calculations-->
    <dialog id="alertXrefAuto" autofocus="true">
      <div>
        <p class="alertTextAuto">this is a dialog</p>
        <button style="margin-right: 10px" id="cancelXrefAuto" class="btn btn-default">Cancel</button>
        <button id="confirmXrefAuto" style="float: right" class="btn btn-danger">Confirm</button>
      </div>
    </dialog>

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
<script src="src/js/App-xref.js"></script>

<!-- Toastr -->
<script src="AdminLTE-3.2.0/plugins/toastr/toastr.min.js"></script>

</body>
</html>
<?php } ?>
