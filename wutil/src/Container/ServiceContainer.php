<?php
// src/Container/ServiceContainer.php

namespace App\Container;

class ServiceContainer
{
    private static $instance;
    private $services = [];

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function set($name, $service)
    {
        $this->services[$name] = $service;
    }

    public function has($name)
    {
        return isset($this->services[$name]);
    }

    public function get($name)
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $this->create($name);
        }
        return $this->services[$name];
    }

    /**
     * @throws \DateInvalidTimeZoneException
     */
    private function create($name)
    {
        global $dbConfig;

        switch ($name) {
            case 'academicScheduleService':
                return new \App\Services\Reference\Academic\AcademicScheduleService(
                    $this->get('utilities'),
                    $this->get('semesterService'),
                    $this->get('dbManager'),
                    $this->get('buildingService'),
                    $this->get('campusService'),
                    $this->get('configService'),
                    $this->get('logService'),
                    'GOL', // Default building code
                    'America/New_York' // Default timezone
                );
            case 'ahuService':
                return new \App\Services\Reference\Spaces\AhuService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'airflowService':
                return new \App\Services\Reference\Hvac\AirflowService(
                    $this->get('dbManager'),
                    $this->get('uncertaintyService'),
                    $this->get('utilities'),
                    $this->get('roomService'),
                    $this->get('zoneService'),
                    $this->get('ashraeService'),
                    $this->get('configService'),
                    $this->get('logService'),
                    $this->get('semesterService')
                );
            case 'ashraeService':
                return new \App\Services\Reference\Standards\AshraeService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'buildingService':
                return new \App\Services\Reference\Spaces\BuildingService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'campusService':
                return new \App\Services\Reference\Spaces\CampusService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'classService':
                return new \App\Services\Reference\Academic\ClassService(
                    $this->get('dbManager'),
                    $this->get('semesterService'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'configService':
                // Create ConfigService without circular dependencies
                $configService = new \App\Services\Configuration\ConfigService(
                    $this->get('dbManager'),
                    $this->get('utilities')
                );

                // Store it so we can set dependencies after creating other services
                $this->services['configService'] = $configService;
                return $configService;
            case 'cronJobController':
                return new \App\Controllers\Http\CronJobController(
                    $this->get('utilities'),
                    $this->get('tokenValidator'),
                    $this->get('semesterService'),
                    $this->get('academicScheduleService'),
                    $this->get('classService'),
                    $this->get('transferService'),
                    $this->get('jsonResponseService'),
                    $this->get('urlService'),
                    $this->get('logService')
                );
            case 'dbManager':
                return \App\Database\DatabaseManager::getInstance($dbConfig);
            case 'equipmentService':
                return new \App\Services\Reference\Equipment\EquipmentService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'importBuildingService':
                return new \App\Services\Imports\ImportBuildingService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'importClassService':
                return new \App\Services\Imports\ImportClassService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'importEventService':
                return new \App\Services\Imports\ImportEventService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'importZoneService':
                return new \App\Services\Imports\ImportZoneService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'jsonResponseService':
                return new \App\Services\Response\JsonResponseService();
            case 'logService':
                $logService = new \App\Services\Logging\LogService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService')
                );

                // Store it
                $this->services['logService'] = $logService;

                // Now we can set the LogService on ConfigService
                if ($this->has('configService')) {
                    $this->get('configService')->setLogService($logService);
                }

                return $logService;
            case 'ncesService':
                return new \App\Services\Reference\Standards\NcesService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'utilities':
                return new \App\Utilities\Utilities($this->get('dbManager'));
            case 'referenceDataController':
                return new \App\Controllers\Api\Reference\ReferenceDataController($this);
            case 'roomService':
                $roomService = new \App\Services\Reference\Spaces\RoomService(
                    $this->get('dbManager'),
                    $this->get('uncertaintyService'),
                    $this->get('semesterService'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );

                // Handle circular dependency with ZoneService if it exists
                if ($this->has('zoneService')) {
                    $roomService->setZoneService($this->get('zoneService'));
                }

                return $roomService;
            case 'security':
                return new \App\Security\Security(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'semesterService':
                $semesterService = new \App\Services\Reference\Academic\SemesterService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );

                // Now we can set the SemesterService on ConfigService
                if ($this->has('configService')) {
                    $this->get('configService')->setSemesterService($semesterService);
                }

                return $semesterService;
            case 'setpointService':
                return new \App\Services\Reference\Hvac\SetpointService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('uncertaintyService'),
                    $this->get('zoneService'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'tokenValidator':
                return new \App\Services\Security\TokenValidator();
            case 'transferService':
                return new \App\Services\Transfer\TransferService(
                    $this->get('utilities'),
                    $this->get('semesterService'),
                    $this->get('buildingService'),
                    $this->get('roomService'),
                    $this->get('classService'),
                    $this->get('dbManager'),
                    $this->get('zoneService'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'uncertaintyService':
                return new \App\Services\Reference\Academic\UncertaintyService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            // URL and Response services
            case 'urlService':
                return new \App\Utilities\UrlService();
            case 'userService':
                return new \App\Services\Reference\User\UserService(
                    $this->get('dbManager'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService')
                );
            case 'zoneService':
                $zoneService = new \App\Services\Reference\Spaces\ZoneService(
                    $this->get('dbManager'),
                    $this->get('uncertaintyService'),
                    $this->get('utilities'),
                    $this->get('configService'),
                    $this->get('logService'),
                    $this->get('semesterService')
                );

                // Handle circular dependency with RoomService if it exists
                if ($this->has('roomService')) {
                    $zoneService->setRoomService($this->get('roomService'));
                }

                return $zoneService;


            // Add any other services your application uses

            default:
                throw new \Exception("Unknown service: $name");
        }
    }
}
