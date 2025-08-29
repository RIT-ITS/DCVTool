<?php
// Include bootstrap file
require_once __DIR__ . '/../bootstrap.php';
use App\Container\ServiceContainer;
// Get services using the app() helper function
$container = ServiceContainer::getInstance();
$securityService = $container->get('security');
$authResult = $securityService->checkAuthenticationStatus(false);

?>
    <!DOCTYPE html>
    <html lang="en">
<?php include_once('admin/src/php/htmlhead.php'); ?>
    <body class="hold-transition sidebar-mini sidebar-collapse">
    <div class="wrapper">
        <?php include_once('admin/src/php/main-header.php'); ?>
        <?php include_once('admin/src/php/aside.php'); ?>
        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <?php include_once('admin/src/php/content-header.php'); ?>
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
                                </div>
                                 <!--.card header-->
                                <div class="card-body">
<?php
// If user is authenticated, show the page content
if ($authResult['status'] === 'authorized') {
    echo("<h3>Hello! You are authenticated as ".$_SESSION['cn']." ".$_SESSION['sn']." ( ".$_SESSION['userid']." )</h3>");
    echo("<h5>You may proceed <a href='/admin/index.php'>to the Administration Section</a>.</h5>");

    } else {
    echo("<h3>You are currently not authenticated. Please <a href='login.php'>login here.</a></h3>");

}
?>
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

        <!-- Main Footer -->
        <?php include_once("admin/src/php/footer.php"); ?>
    </div>
    <!-- ./wrapper -->
    <?php include_once('admin/src/php/htmlfooter.php'); ?>
    </body>
</html>