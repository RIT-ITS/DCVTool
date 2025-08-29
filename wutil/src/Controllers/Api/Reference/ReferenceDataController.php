<?php

declare(strict_types=1);

namespace App\Controllers\Api\Reference;

use App\Container\ServiceContainer;

/**
 * Controller for handling reference data API requests
 */
class ReferenceDataController
{
    private $container;

    /**
     * Constructor with dependency injection
     */
    /**
     * Constructor with dependency injection
     */
    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;

        // Set headers for API response
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Methods: POST");
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
    }

    /**
     * Get a service from the container only when needed
     */
    private function service(string $name)
    {
        return $this->container->get($name);
    }

    /**
     * Process incoming API requests
     */
    public function processRequest(): void
    {
        $data = json_decode(file_get_contents("php://input"), true);
        $result = false;

        if (empty($data)) {
            $this->service('jsonResponse')->error('No data provided', 400);
            //$this->responseService->error('No data provided', 400);
            return;
        }

        $section = $this->service('utilities')->sanitizeStringInput($data["dcvsection"] ?? '');

        switch ($section) {
            case "uncertainty":
                $sanitizedData = $this->service('utilities')->sanitizeUncertainty($data);
                $result = $this->service('uncertaintyService')->addUpdUncertainty($sanitizedData);
                break;
            case "ashrae61":
                $sanitizedData = $this->service('utilities')->sanitizeAshrae61($data);
                $result = $this->service('ashraeService')->addUpdAshrae61($sanitizedData);
                break;
            case "rooms":
                $sanitizedData = $this->service('utilities')->sanitizeRooms($data);
                $result = $this->service('roomService')->addUpdRooms($sanitizedData);
                break;
            case "campus":
                $sanitizedData = $this->service('utilities')->sanitizeCampus($data);
                $result = $this->service('campusService')->addUpdCampus($sanitizedData);
                break;
            case "floors":
                $sanitizedData = $this->service('utilities')->sanitizeFloors($data);
                $result = $this->service('buildingService')->addUpdFloors($sanitizedData);
                break;
            case "configuration":
                $sanitizedData = $this->service('utilities')->sanitizeConfigVars($data);
                $result = $this->service('configService')->addUpdConfigVars($sanitizedData);
                break;
            case "buildings":
                $sanitizedData = $this->service('utilities')->sanitizeBuildings($data);
                $result = $this->service('buildingService')->addUpdBuildings($sanitizedData);
                break;
            case 'ashrae64':
                $sanitizedData = $this->service('utilities')->sanitizeAshrae64($data);
                $result = $this->service('ashraeService')->addUpdAshrae64($sanitizedData);
                break;
            case 'terms':
                $sanitizedData = $this->service('utilities')->sanitizeTerm($data);
                $result = $this->service('semesterService')->addUpdTerm($sanitizedData);
                break;
            case 'nces42':
                $sanitizedData = $this->service('utilities')->sanitizeNces42($data);
                $result = $this->service('ncesService')->addUpdNces42($sanitizedData);
                break;
            case 'nces_categories':
                $sanitizedData = $this->service('utilities')->sanitizeNcesCats($data);
                $result = $this->service('ncesService')->addUpdNcesCats($sanitizedData);
                break;
            case 'zones':
                $sanitizedData = $this->service('utilities')->sanitizeZones($data);
                $result = $this->service('zoneService')->addUpdZones($sanitizedData);
                break;
            case 'xref':
                $sanitizedData = $this->service('utilities')->sanitizeXref($data);
                $result = $this->service('roomService')->addUpdXref($sanitizedData);
                break;
            case 'ahu':
                $sanitizedData = $this->service('utilities')->sanitizeAHU($data);
                $result = $this->service('ahuService')->addUpdAHUs($sanitizedData);
                break;
            case 'airflow':
                $sanitizedData = $this->service('utilities')->sanitizeAirflowData($data);
                $returnData = $this->service('zoneService')->addUpdZones($sanitizedData);
                break;
            case 'users':
                $sanitizedData = $this->service('utilities')->sanitizeUser($data);
                $result = $this->service('userService')->addUpdUser($sanitizedData);
                break;
            case 'equipment_map':
                $sanitizedData = $this->service('utilities')->sanitizeEquipmentMap($data);
                $result = $this->service('equipmentService')->addUpdEquipmentMap($sanitizedData);
                break;
            case 'importSpace':
                $sanitizedData = $this->service('utilities')->sanitizeSpaceImport($data);
                $returnData = $this->service('importBuildingService')->importBuildingRoomData($sanitizedData);
                break;
            case 'importSis':
                $sanitizedData = $this->service('utilities')->sanitizeSisImport($data);
                $returnData = $this->service('importClassService')->importSisClassData($sanitizedData);
                break;
            case 'importZone':
                $sanitizedData = $this->service('utilities')->sanitizeZoneImport($data);
                $returnData = $this->service('importZoneService')->importZoneData($sanitizedData);
                break;
            case 'expandedSchedule':
                $sanitizedData = $this->service('utilities')->sanitizeExpandedSchedule($data);
                $returnData = $this->service('importEventService')->importEvent($sanitizedData);
                break;
            // Add other cases as needed

            default:
                $this->service('jsonResponse')->error('Invalid section specified', 400);
                return;
        }
        if ($result) {
            $this->service('jsonResponse')->success([], 'Operation completed successfully');
        } else {
            $this->service('jsonResponse')->error('Operation failed', 500);
        }
    }
}