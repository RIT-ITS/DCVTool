<?php

echo  '<ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
       <!-- Add icons to the links using the .nav-icon class with font-awesome or any other icon font library -->';

$activeInd = ($_SERVER["PHP_SELF"] == "/admin/index.php") ? "active" : "";
  echo '<li class="nav-item">
          <a href="index.php" class="nav-link '.$activeInd.'">
            <i class="nav-icon fas fa-solid fa-house-user"></i>
            <i class="fa-solid fa-house"></i>
            <p>Home</p>
          </a>
        </li>';

$isRef = ($_SERVER["PHP_SELF"] == "/admin/ashrae6-1.php" || $_SERVER["PHP_SELF"] == "/admin/ashrae6-4.php" || $_SERVER["PHP_SELF"] == "/admin/ncesTypes.php" || $_SERVER["PHP_SELF"] == "/admin/nces4-2.php" || $_SERVER["PHP_SELF"] == "/admin/uncertainty.php" );
$openRef = $isRef ? "menu-open" : '';
$activeRef = $isRef ? "active" : '';
  echo '<li class="nav-item '.$openRef.'">
          <a href="#" class="nav-link '.$activeRef.'">
            <i class="nav-icon fas fa-table"></i>
            <p>Reference
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">';

$active61 = ($_SERVER["PHP_SELF"] == "/admin/ashrae6-1.php") ? "active" : "";
  echo '<li class="nav-item">
          <a href="ashrae6-1.php" class="nav-link '.$active61.'">
            <i class="nav-icon fas fa-table"></i>
            <p>ASHRAE 62.1 Table 6-1</p>
          </a>
        </li>';

//  Commented out per request from user
//  $active64 = ($_SERVER["PHP_SELF"] == "/admin/ashrae6-4.php") ? "active" : "";
//  echo '<li class="nav-item">
//          <a href="ashrae6-4.php" class="nav-link '.$active64.'">
//            <i class="nav-icon fas fa-table"></i>
//            <p>ASHRAE 62.1 Table 6-4</p>
//          </a>
//        </li>';

$activeNcesT = ($_SERVER["PHP_SELF"] == "/admin/ncesTypes.php") ? "active" : "";
  echo '<li class="nav-item">
          <a href="ncesTypes.php" class="nav-link '.$activeNcesT.'">
            <i class="nav-icon fas fa-table"></i>
            <p>NCES Types</p>
          </a>
        </li>';
$activeNces = ($_SERVER["PHP_SELF"] == "/admin/nces4-2.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="nces4-2.php" class="nav-link '.$activeNces.'">
          <i class="nav-icon fas fa-table"></i>
          <p>NCES/ASHRAE 62.1</p>
        </a>
      </li>';

$activeUncertainty = ($_SERVER["PHP_SELF"] == "/admin/uncertainty.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="uncertainty.php" class="nav-link '.$activeUncertainty.'">
          <i class="nav-icon fas fa-question"></i>
          <p>Uncertainty</p>
        </a>
      </li>';

echo '<li class="nav-item">
        <a href="https://dcvtool.rit.edu/reporting/login" class="nav-link" target="_blank" rel="noopener">
          <i class="nav-icon fas fa-chart-bar"></i>
          <p>Reporting</p>
        </a>
      </li>
    </ul>
  </li>';


$isOutdoorAirflowActive = ($_SERVER["PHP_SELF"] == "/admin/outdoorAirflowRooms.php" || $_SERVER["PHP_SELF"] == "/admin/outdoorAirflowZones.php" || $_SERVER["PHP_SELF"] == "/admin/outdoorAirflowEvents.php" ||  $_SERVER["PHP_SELF"] == "/admin/dynamic.php");
$openOutdoor = $isOutdoorAirflowActive ? "menu-open" : "";
$activeOutdoor = $isOutdoorAirflowActive ? "active" : "";
echo '<li class="nav-item '.$openOutdoor.'">
        <a href="#" class="nav-link '.$activeOutdoor.'">
          <i class="nav-icon fas fa-wind"></i>
          <p>Outdoor Airflow
            <i class="right fas fa-angle-left"></i>
          </p>
        </a>
        <ul class="nav nav-treeview">';

$activeRooms = ($_SERVER["PHP_SELF"] == "/admin/outdoorAirflowRooms.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="outdoorAirflowRooms.php" class="nav-link '.$activeRooms.'">
          <i class="nav-icon fas fa-wind"></i>
          <p>Max Per Room</p>
        </a>
      </li>';

$activeZones = ($_SERVER["PHP_SELF"] == "/admin/outdoorAirflowZones.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="outdoorAirflowZones.php" class="nav-link '.$activeZones.'">
          <i class="nav-icon fas fa-wind"></i>
          <p>Max Per Zone</p>
        </a>
      </li>';

$activeAEvents = ($_SERVER["PHP_SELF"] == "/admin/outdoorAirflowEvents.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="outdoorAirflowEvents.php" class="nav-link '.$activeAEvents.'">
          <i class="nav-icon fas fa-wind"></i>
          <p>Max Per Event</p>
        </a>
      </li>';

$activeDynamic = ($_SERVER["PHP_SELF"] == "/admin/dynamic.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="dynamic.php" class="nav-link '.$activeDynamic.'">
          <i class="nav-icon fas fa-wind"></i>
          <p>Dynamic Calculations</p>
        </a>
      </li>';

  echo '</ul>
  </li>';

$isEventsActive = ($_SERVER["PHP_SELF"] == "/admin/sis.php" || $_SERVER["PHP_SELF"] == "/admin/terms.php" || $_SERVER["PHP_SELF"] == "/admin/events.php");
$openEvents = $isEventsActive ? "menu-open" : "";
$activeEvents = $isEventsActive ? "active" : "";
echo '<li class="nav-item '.$openEvents.'">
        <a href="#" class="nav-link '.$activeEvents.'">
          <i class="nav-icon fas fa-bookmark"></i>
          <p>Events
            <i class="right fas fa-angle-left"></i>
          </p>
        </a>';

$activeSis = ($_SERVER["PHP_SELF"] == "/admin/sis.php") ? "active" : "";
echo '<ul class="nav nav-treeview">
        <li class="nav-item">
          <a href="sis.php" class="nav-link '.$activeSis.'">
            <i class="nav-icon fas fa-bookmark"></i>
            <p>SIS</p>
          </a>
        </li>';

$activeEEvents = ($_SERVER["PHP_SELF"] == "/admin/events.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="events.php" class="nav-link '.$activeEEvents.'">
          <i class="nav-icon fas fa-bookmark"></i>
          <p>Expanded Schedule</p>
        </a>
      </li>';

$activeTerms = ($_SERVER["PHP_SELF"] == "/admin/terms.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="terms.php" class="nav-link '.$activeTerms.'">
          <i class="nav-icon fas fa-clock"></i>
          <p>Terms</p>
        </a>
      </li>';

echo '</ul>
      </li>';

$isSpacesActive = ($_SERVER["PHP_SELF"] == "/admin/campuses.php" || $_SERVER["PHP_SELF"] == "/admin/buildings.php" || $_SERVER["PHP_SELF"] == "/admin/floors.php" || $_SERVER["PHP_SELF"] == "/admin/rooms.php" || $_SERVER["PHP_SELF"] == "/admin/xref.php");
$openSpaces = $isSpacesActive ? "menu-open" : "";
$activeSpaces = $isSpacesActive ? "active" : "";
echo '<li class="nav-item '.$openSpaces.'">
        <a href="#" class="nav-link '.$activeSpaces.'">
          <i class="nav-icon fas fa-building"></i>
          <p>Spaces
            <i class="right fas fa-angle-left"></i>
          </p>
        </a>
        <ul class="nav nav-treeview">';

$activeCampuses = ($_SERVER["PHP_SELF"] == "/admin/campuses.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="campuses.php" class="nav-link '.$activeCampuses.'">
          <i class="nav-icon fas fa-building"></i>
          <p>Campuses</p>
        </a>
      </li>';

$activeBuildings = ($_SERVER["PHP_SELF"] == "/admin/buildings.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="buildings.php" class="nav-link '.$activeBuildings.'">
          <i class="nav-icon fas fa-building"></i>
          <p>Buildings</p>
        </a>
      </li>';

$activeFloors = ($_SERVER["PHP_SELF"] == "/admin/floors.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="floors.php" class="nav-link '.$activeFloors.'">
          <i class="nav-icon fas fa-building"></i>
          <p>Floors</p>
        </a>
      </li>';

$activeRooms = ($_SERVER["PHP_SELF"] == "/admin/rooms.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="rooms.php" class="nav-link '.$activeRooms.'">
          <i class="nav-icon fas fa-building"></i>
          <p>Rooms</p>
        </a>
      </li>';

$activeXref = ($_SERVER["PHP_SELF"] == "/admin/xref.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="xref.php" class="nav-link '.$activeXref.'">
          <i class="nav-icon fas fa-table"></i>
          <p>Space/Zone</p>
        </a>
      </li>';


echo '</ul>
      </li>';

$isEquipmentActive = ($_SERVER["PHP_SELF"] == "/admin/equipment.php" || $_SERVER["PHP_SELF"] == "/admin/ahu.php" || $_SERVER["PHP_SELF"] == "/admin/zones.php");
$openEquipment = $isEquipmentActive ? "menu-open" : "";
$activeEquipment = $isEquipmentActive ? "active" : "";
echo '<li class="nav-item '.$openEquipment.'">
        <a href="#" class="nav-link '.$activeEquipment.'">
          <i class="nav-icon fas fa-cogs"></i>
          <p>Equipment
            <i class="right fas fa-angle-left"></i>
          </p>
        </a>
        <ul class="nav nav-treeview">';


if($userData["role"] > 2){

  $activeEquipment = ($_SERVER["PHP_SELF"] == "/admin/equipment.php") ? "active" : "";
  echo '<li class="nav-item">
        <a href="equipment.php" class="nav-link '.$activeEquipment.'">
          <i class="nav-icon fas fa-bolt"></i>
          <p>Devices</p>
        </a>
      </li>';

}
$activeAhu = ($_SERVER["PHP_SELF"] == "/admin/ahu.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="ahu.php" class="nav-link '.$activeAhu.'">
          <i class="nav-icon fas fa-building"></i>
          <p>AHUs</p>
        </a>
      </li>';

$activeZones = ($_SERVER["PHP_SELF"] == "/admin/zones.php") ? "active" : "";
echo '<li class="nav-item">
        <a href="zones.php" class="nav-link '.$activeZones.'">
          <i class="nav-icon fas fa-building"></i>
          <p>Zones</p>
        </a>
      </li>';


echo '</ul>
      </li>';

if($userData["role"] > 2){

  $isAdminActive = ($_SERVER["PHP_SELF"] == "/admin/configuration.php" ||
      $_SERVER["PHP_SELF"] == "/admin/users.php" ||
      $_SERVER["PHP_SELF"] == "/admin/updates.php" ||
      $_SERVER["PHP_SELF"] == "/admin/log.php" ||
      $_SERVER["PHP_SELF"] == "/admin/import.php" ||
      $_SERVER["PHP_SELF"] == "/admin/import_log.php" ||
      $_SERVER["PHP_SELF"] == "/admin/commands.php" ||
      $_SERVER["PHP_SELF"] == "/admin/import-events.php");
  $openAdmin = $isAdminActive ? "menu-open" : "";
  $activeAdmin = $isAdminActive ? "active" : "";
  echo '<li class="nav-item '.$openAdmin.'">
        <a href="#" class="nav-link '.$activeAdmin.'">
          <i class="nav-icon fas fa-lock"></i>
          <p>Admin
            <i class="right fas fa-angle-left"></i>
          </p>
        </a>
      <ul class="nav nav-treeview">';


if($_SERVER["PHP_SELF"] == "/admin/configuration.php"){
  echo '<li class="nav-item">
          <a href="configuration.php" class="nav-link active">
            <i class="nav-icon fas fa-cog"></i>
            <p>Settings</p>
          </a>
        </li>';
}
else{
  echo '<li class="nav-item">
          <a href="configuration.php" class="nav-link">
            <i class="nav-icon fas fa-cog"></i>
            <p>Settings</p>
          </a>
        </li>';
}

if($_SERVER["PHP_SELF"] == "/admin/users.php"){
  echo '<li class="nav-item">
          <a href="users.php" class="nav-link active">
            <i class="nav-icon fas fa-user"></i>
            <p>Users</p>
          </a>
        </li>';
}
else{
  echo '<li class="nav-item">
          <a href="users.php" class="nav-link">
            <i class="nav-icon fas fa-user"></i>
            <p>Users</p>
          </a>
        </li>';
}

if($_SERVER["PHP_SELF"] == "/admin/import.php"){
  echo '<li class="nav-item">
          <a href="import.php" class="nav-link active">
            <i class="nav-icon fas fa-file-import"></i>
            <p>Import</p>
          </a>
        </li>';
}
else{
  echo '<li class="nav-item">
          <a href="import.php" class="nav-link">
            <i class="nav-icon fas fa-file-import"></i>
            <p>Import</p>
          </a>
        </li>';
}

if($_SERVER["PHP_SELF"] == "/admin/import-events.php"){
  echo '<li class="nav-item">
          <a href="import-events.php" class="nav-link active">
            <i class="nav-icon fas fa-bookmark"></i>
            <p>Event Import</p>
          </a>
        </li>';
}
else{
  echo '<li class="nav-item">
          <a href="import-events.php" class="nav-link">
            <i class="nav-icon fas fa-bookmark"></i>
            <p>Event Import</p>
          </a>
        </li>';
}

if($_SERVER["PHP_SELF"] == "/admin/import_log.php"){
  echo '<li class="nav-item">
          <a href="import_log.php" class="nav-link active">
            <i class="nav-icon fas fa-file-export"></i>
            <p>Import Log</p>
          </a>
        </li>';
}
else{
  echo '<li class="nav-item">
          <a href="import_log.php" class="nav-link">
            <i class="nav-icon fas fa-file-export"></i>
            <p>Import Log</p>
          </a>
        </li>';
}

if($_SERVER["PHP_SELF"] == "/admin/commands.php"){
  echo '<li class="nav-item">
          <a href="commands.php" class="nav-link active">
            <i class="nav-icon fas fa-table"></i>
            <p>Command Data</p>
          </a>
        </li>';
}
else{
  echo '<li class="nav-item">
          <a href="commands.php" class="nav-link">
            <i class="nav-icon fas fa-table"></i>
            <p>Command Data</p>
          </a>
        </li>';
}

if($_SERVER["PHP_SELF"] == "/admin/updates.php"){
  echo '<li class="nav-item">
          <a href="updates.php" class="nav-link active">
            <i class="nav-icon fas fa-chart-bar"></i>
            <p>Updates</p>
          </a>
        </li>';
}
else{
  echo '<li class="nav-item">
          <a href="updates.php" class="nav-link">
            <i class="nav-icon fas fa-chart-bar"></i>
            <p>Updates</p>
          </a>
        </li>';
}

if($_SERVER["PHP_SELF"] == "/admin/log.php"){
  echo '<li class="nav-item">
          <a href="log.php" class="nav-link active">
            <i class="nav-icon fas fa-terminal"></i>
            <p>System Log</p>
          </a>
        </li>';
}
else{
  echo '<li class="nav-item">
          <a href="log.php" class="nav-link">
            <i class="nav-icon fas fa-terminal"></i>
            <p>System Log</p>
          </a>
        </li>';
}
}
echo '</ul>
      </li>
      </ul>';

?>

