<?php

// Array of page names to breadcrumb labels
$breadcrumbs = [
    'ahu.php' => 'Air Handling Units',
    'ashrae6-1.php' => 'ASHRAE 62.1 Table 6-1',
    'ashrae6-4.php' => 'Ashrae 62.1 Table 6-4',
    'buildings.php' => 'Buildings',
    'campuses.php' => 'Campuses',
    'configuration.php' => 'Settings',
    'equipment.php' => 'Devices',
    'floors.php' => 'Floors',
    'import.php' => 'Import',
    'import_log.php' => 'Import Log',
    'log.php' => 'System Log',
    'nces4-2.php' => 'NCES/ASHRAE 62.1',
    'ncesTypes.php' => 'NCES Types',
    'rooms.php' => 'Rooms',
    'sis.php' => 'SIS Data',
    'terms.php' => 'Terms',
    'uncertainty.php' => 'Uncertainty',
    'updates.php' => 'Updates',
    'users.php' => 'Users',
    'xref.php' => 'Space distribution by Zone',
    'zones.php' => 'Zones - VAVs',
    'outdoorAirflowRooms.php' => 'Max Outdoor Airflow Per Room',
    'outdoorAirflowZones.php' => 'Max Outdoor Airflow Per Zone',
    'outdoorAirflowEvents.php' => 'Max Outdoor Airflow Per Event',
    'import-events.php' => 'Import Events',

];

// Get the current page name
$currentPage = basename($_SERVER['PHP_SELF']);
// Start the breadcrumbs
echo '<ol class="breadcrumb">';
// Add the home page link
echo '<li class="breadcrumb-item"><a href="/admin/index.php">Home</a></li>';
// Loop through breadcrumbs array
foreach ($breadcrumbs as $page => $label) {
    if ($page == $currentPage) {
        // Output active class if current page
        $active = ($page == $currentPage) ? ' active' : '';
        // Output breadcrumb item
        echo '<li class="breadcrumb-item' . $active . '">';
        // Link if not active page
        if ($page != $currentPage) {
            echo '<a href="' . $page . '">';
        }
        // Label
        echo $label;
        // Close link
        if ($page != $currentPage) {
            echo '</a>';
        }
        echo '</li>';
        break;
    }
}

// Close breadcrumbs
echo '</ol>';
