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
$pageTitle = "Settings";
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

          <!-- /.col -->
        <!-- /.row -->

        <!--Equipment Row-->
        <div class="row">


          <section class="col-lg-6">

            <div class="card" id="config_wrapper">
              <!--card header-->
              <div class="card-header">
                <h3 class="card-title">Editing Settings</h3>
              </div>

              <!--card body-->
              <div class="card-body">
                <div class="col-lg-12">
                  <ul class="nav nav-pills flex-column nav-sidebar">
                    <li>
                      <input class="editableConfig configToggle configToggleAll" type="checkbox" name="check" config="canEditAll" scope="1">
                      <label for="check" class="checBoxlabel">Enable All Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEdit61" scope="1">
                      <label for="check" class="checBoxlabel">Enable Ashrae 6-1 Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEdit42Types" scope="1">
                      <label for="check" class="checBoxlabel">Enable NCES 4-2 Type Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEdit42" scope="1">
                      <label for="check" class="checBoxlabel">Enable NCES 4-2 Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditUncert" scope="1">
                      <label for="check" class="checBoxlabel">Enable Uncertainty Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditAirflowRooms" scope="1">
                      <label for="check" class="checBoxlabel">Enable Max Airflow Per Room Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditAirflowZones" scope="1">
                      <label for="check" class="checBoxlabel">Enable Max Airflow Per Zone Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditTerms" scope="1">
                      <label for="check" class="checBoxlabel">Enable Terms Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditCampuses" scope="1">
                      <label for="check" class="checBoxlabel">Enable Campuses Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditBuildings" scope="1">
                      <label for="check" class="checBoxlabel">Enable Buildings Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditFloors" scope="1">
                      <label for="check" class="checBoxlabel">Enable Floors Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditRooms" scope="1">
                      <label for="check" class="checBoxlabel">Enable Rooms Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditXrefs" scope="1">
                      <label for="check" class="checBoxlabel">Enable Space/Zone Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditEquip" scope="1">
                      <label for="check" class="checBoxlabel">Enable Devices Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditAhus" scope="1">
                      <label for="check" class="checBoxlabel">Enable AHU Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditZones" scope="1">
                      <label for="check" class="checBoxlabel">Enable Zone Editing</label>
                    </li>
                    <li>
                      <input class="editableConfig configToggle configToggleSub" type="checkbox" name="check" config="canEditUsers" scope="1">
                      <label for="check" class="checBoxlabel">Enable Users Editing</label>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </section>
          <section class="col-lg-6">

            <div class="card" id="config_wrapper_2">
              <!--card header-->
              <div class="card-header">
                <h3 class="card-title">Other Settings</h3>
              </div>

              <!--card body-->
              <div class="card-body">
                <div class="col-lg-12">
                  <ul class="nav nav-pills flex-column nav-sidebar">
                    <li>
                      <input class="importableConfigAll editableConfig configToggle" type="checkbox" name="check" config="canImport" scope="1">
                      <label for="check" class="checBoxlabel">Enable Bulk Data Importing</label>
                    </li>
                    <li>
                      <input class="importableConfigEvent editableConfig configToggle" type="checkbox" name="check" config="canImportEvents" scope="1">
                      <label for="check" class="checBoxlabel">Enable Single Event Importing</label>
                    </li>
                    <li>
                      <input class="detailedLogging editableConfig" type="checkbox" name="check" config="detailedlogging" scope="0">
                      <label for="check" class="checBoxlabel">Enable Detailed Logging</label>
                      <span class="tooltipBox">
                        <span><i style="color: #6c757d;" class="nav-icon fa fa-exclamation-triangle"></i></span>
                        <div class="tooltipText">Significantly increases the logs genereated by the system!</div>
                      </span>
                    </li>
                    <li>
                    <label for="campus" style="display: block">Default Campus:</label>
                    <select name="campus" id="campus" class="customInput editableConfig" config="defaultCampusId" scope="1">
                      <option value="0"> </option>
                    </select>
                    </li>
                    <li>                
                      <label for="preString" class="checBoxlabel" style="display: block">Uname Pre-String</label>
                      <input class="unameString customInput editableConfig" type="text" name="preString" config="unamePreString" scope="3">
                    </li>
                    <li>
                      <label for="postString" class="checBoxlabel" style="display: block">Uname Post-String</label>
                      <input class="unameString customInput editableConfig" type="text" name="postString" config="unamePostString" scope="3">
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="card" id="config_wrapper_3">
              <!--card header-->
              <div class="card-header">
                <h3 class="card-title">International Energy Conservation Code IECC Criteria (v. 2021)</h3>
              </div>

              <!--card body-->
              <div class="card-body">
                <div class="col-lg-12">
                  <ul class="nav nav-pills flex-column nav-sidebar">
                    <li>
                      <label for="min_space_size" class="checBoxlabel" style="display:block">Minimum Space Size (sqft)</label>
                      <input class="dcvReq customInput editableConfig" type="text" name="min_space_size" config="minSpaceSize" scope="2">
                    </li>
                    <li>
                    <label for="min_occ_load" class="checBoxlabel" style="display:block">Average Occupant Load</label>
                    <input class="dcvReq customInput editableConfig" type="text" name="min_occ_load" config="minOccLoad" scope="2">
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </section>
        </div>
        
      </div>
      <!-- /.container-fluid -->
    </section>
  <!-- card section -->

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

<!-- AdminLTE App -->
<script src="AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>

<!--App Script-->
<script src="src/js/App-configuration.js"></script>

</body>
</html>
<?php
        }
    }
} else {
  include_once("src/php/unauthed.php");
} ?>
