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
  $pageTitle = "Admin Home";
?>
<!DOCTYPE html>
<html lang="en">

<?php include_once('src/php/htmlhead.php'); ?>

<body class="hold-transition sidebar-mini">
  <div class="wrapper">

    <?php include_once('src/php/main-header.php'); ?>

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-no-expand sidebar-light-primary elevation--10">
      <!-- Brand Logo -->
      <a href="/" class="brand-link">
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
      <div class="content">
        <div class="container-fluid">
          <div class="row">
            <div class="col-lg-12">
              <!--Card - dashboard 1-->
              <div class="card">
                <!--card header-->
                <div class="card-header">
                  <div>
                    <ul class="nav nav-pills ml-auto">
                      <li class="nav-item">
                        <a class="nav-link active" href="#r2" data-toggle="tab">R2</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" href="#r4" data-toggle="tab">R4</a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" href="#r7" data-toggle="tab">R7</a>
                      </li>
                    </ul>
                  </div>
                </div>
                <!--.card header-->

                <div class="card-body">
                  <div class="tab-content p-0">
                  <div id="r2" class="tab-pane active">
                    <iframe src="/reporting/d/f659d1c2-f05f-46e9-af96-6ae2cc1a6149/r2?orgId=1&from=1673902382320&to=1682899200000&theme=light" frameborder="0" height="2278" class="dashboard"></iframe>
                  </div>

                  <div id="r4" class="tab-pane">
                      <iframe src="/reporting/d/d7f7223b-2b5b-47a2-9570-33b790d11441/r4?orgId=1&from=1691993713896&to=1692015313896&theme=light" frameborder="0" height="780" class="dashboard"></iframe>
                  </div>

                  <div id="r7" class="tab-pane">
                    <iframe src="/reporting/d/e0dc84d8-e64b-4641-9479-a0fbed36781a/r7?orgId=1&from=1691993784813&to=1692015384813&theme=light" frameborder="0" height="2200" class="dashboard"></iframe>
                  </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- /.row -->
        </div>
        <!-- /.container-fluid -->
      </div>
      <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
      <!-- Control sidebar content goes here -->
    </aside>
    <!-- /.control-sidebar -->

    <!-- Main Footer -->
    <?php include "src/php/footer.php" ?>
  </div>
  <!-- ./wrapper -->

  <!-- REQUIRED SCRIPTS -->

  <!-- jQuery -->
  <script src="AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
  <!-- Bootstrap -->
  <script src="AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- AdminLTE -->
  <script src="AdminLTE-3.2.0/dist/js/adminlte.js"></script>

  <!-- OPTIONAL SCRIPTS -->
  <script src="AdminLTE-3.2.0/plugins/chart.js/Chart.min.js"></script>
  <!-- AdminLTE for demo purposes -->
  <script src="AdminLTE-3.2.0/dist/js/demo.js"></script>
  <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
  <script src="AdminLTE-3.2.0/dist/js/pages/dashboard3.js"></script>


</body>

</html>
<?php } ?>