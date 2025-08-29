<?php

namespace App\Services\Reference\Hvac;

use App\Services\Reference\BaseReferenceService;
use App\Services\Reference\Academic\UncertaintyService;
use App\Services\Reference\Academic\SemesterService;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use App\Utilities\Utilities;
use App\Services\Reference\Spaces\RoomService;
use App\Services\Reference\Standards\AshraeService;
use App\Services\Reference\Spaces\ZoneService;
use App\Database\DatabaseManager;
use PDO;
use PDOException;

class AirflowService extends BaseReferenceService
{
    protected UncertaintyService $uncertaintyService;
    protected SemesterService $semesterService;
    protected Utilities $utilities;
    protected ConfigService $configService;
    protected LogService $logService;
    protected RoomService $roomService;
    protected ZoneService $zoneService;
    protected AshraeService $ashraeService;

    /**
     * AirflowService constructor.
     *
     * @param DatabaseManager $dbManager The database manager
     * @param UncertaintyService $uncertaintyService The uncertainty service
     * @param Utilities $utilities
     * @param RoomService $roomService
     * @param ZoneService $zoneService
     * @param AshraeService $ashraeService
     * @param ConfigService|null $configService
     * @param LogService|null $logService
     * @param SemesterService|null $semesterService
     */
    public function __construct(
        DatabaseManager $dbManager,
        UncertaintyService $uncertaintyService,
        Utilities $utilities,
        RoomService $roomService,
        ZoneService $zoneService,
        AshraeService $ashraeService,
        ?ConfigService $configService = null,
        ?LogService $logService = null,
        ?SemesterService $semesterService = null
    ) {
        parent::__construct($dbManager);
        $this->uncertaintyService = $uncertaintyService;
        $this->utilities = $utilities;
        $this->roomService = $roomService;
        $this->zoneService = $zoneService;
        $this->ashraeService = $ashraeService;

        // Use injected services if provided, otherwise create new instances
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $utilities, $this->configService);
        $this->semesterService = $semesterService ?? null; // Initialize if needed
    }

    /**
     * Get airflow cross-reference data for a building
     *
     * @param int|null $id The building ID
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The airflow cross-reference data
     * @throws PDOException If a database error occurs
     */
    public function getAirflowXrefData($id, $isRaw = false): array
    {
        try {
            $connection = $this->dbManager->getDefaultConnection();

            if ($id !== null) {
                $sql = "SELECT r.room_num, r.room_name, r.facility_id, r.uncert_amt, r.room_population, 
                    r.room_area, r.active, r.reservable, z.zone_code, z.zone_name, z.occ_sensor, 
                    z.auto_mode, z.ahu_name, z.active, xyz.room_id, xyz.zone_id, xyz.pr_percent, 
                    xyz.xref_area, xyz.xref_population, xyz.id, ash.occ_stdby_allowed, ash.area_oa_rate, 
                    ash.ppl_oa_rate, ash.category, ash.occ_density
                FROM (((room_zone_xref xyz
                INNER JOIN zones z ON z.id = xyz.zone_id)
                INNER JOIN rooms r ON r.id = xyz.room_id)
                INNER JOIN ashrae_6_1 ash ON ash.id = r.ash61_cat_id) 
                WHERE r.building_id = :id";

                $stmt = $connection->prepare($sql);
                $stmt->bindValue(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                // Handle case where id is null (if needed)
                throw new \InvalidArgumentException("Building ID cannot be null");
            }

            $resStat = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = $stmt->rowCount();
            $result = [];

            foreach ($resStat as $row) {
                $res = [
                    "id" => $row["id"],
                    "xref_area" => $row["xref_area"],
                    "xref_population" => $row["xref_population"],
                    "room_num" => $row["room_num"],
                    "room_name" => $row["room_name"],
                    "facility_id" => $row["facility_id"],
                    "room_id" => $row["room_id"],
                    "room_population" => $row["room_population"],
                    "uncert_amt" => $this->uncertaintyService->updateUncertainty($row["room_id"], $row["uncert_amt"]),
                    "room_area" => $row["room_area"],
                    "zone_id" => $row["zone_id"],
                    "zone_code" => $row["zone_code"],
                    "zone_name" => $row["zone_name"],
                    "active" => ($row["active"]) ? 1 : 0,
                    "ahu_name" => $row["ahu_name"],
                    "pr_percent" => $row["pr_percent"],
                    "category" => $row["category"],
                    "occ_density" => $row["occ_density"],
                    "occ_stdby_allowed" => ($row["occ_stdby_allowed"]) ? 1 : 0,
                    "area_oa_rate" => $row["area_oa_rate"],
                    "ppl_oa_rate" => $row["ppl_oa_rate"],
                    "occ_sensor" => ($row["occ_sensor"]) ? 1 : 0,
                    "reservable" => ($row["reservable"]) ? 1 : 0,
                    "auto_mode" => ($row["auto_mode"]) ? 1 : 0,
                    "xrefs" => $this->zoneService->getRoomsByZone($row['zone_id'], true),
                    "room_xrefs" => $this->zoneService->getZonesByRoom($row["room_id"], true),
                    "zone_xrefs" => $this->zoneService->getXrefsByZone($row["zone_id"], null, true)
                ];

                $result[] = $res;
            }

            if ($isRaw) {
                return $result;
            } else {
                return [
                    "responseHeader" => [
                        "status" => 0
                    ],
                    "response" => [
                        "numFound" => $count,
                        "start" => 0,
                        "docs" => [
                            $result
                        ]
                    ]
                ];
            }
        } catch (PDOException $e) {
            $this->logService->logException($e, "Database error in getAirflowXrefData: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get airflow event data for a building
     *
     * @param int $id The building ID
     * @param int $strm The semester term ID (0 for current term)
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The airflow event data
     * @throws PDOException If a database error occurs
     */
    public function getAirflowEventData($id, $strm = 0, $isRaw = false): array
    {
        try {
            $connection = $this->dbManager->getDefaultConnection();

            if ($strm > 0) {
                $currTerm = $strm;
            } else {
                $currTerm = $this->semesterService->determineSemester();
            }

            $sql = "SELECT r.facility_id, r.room_num, r.room_name, r.room_population, r.uncert_amt, 
                    z.zone_name, z.zone_code, z.ahu_name, ash.ppl_oa_rate, xyz.room_id, xyz.zone_id, 
                    xyz.pr_percent, xyz.xref_area, xyz.xref_population, csd.coursetitle, csd.enrl_tot, 
                    csd.start_date, csd.end_date, csd.meeting_time_start, csd.meeting_time_end, 
                    csd.class_number_code, csd.pp_search_id, csd.monday, csd.tuesday, csd.wednesday, 
                    csd.thursday, csd.friday, csd.saturday, csd.sunday
                    FROM ((((class_schedule_data csd
                    INNER JOIN rooms r ON r.facility_id = csd.facility_id)
                    INNER JOIN room_zone_xref xyz ON xyz.room_id = r.id)
                    INNER JOIN zones z ON z.id = xyz.zone_id)
                    INNER JOIN ashrae_6_1 ash ON ash.id = r.ash61_cat_id) 
                    WHERE r.building_id = :id AND csd.strm = :strm ORDER BY csd.coursetitle";

            $stmt = $connection->prepare($sql);
            $stmt->bindValue(":id", $id, PDO::PARAM_INT);
            $stmt->bindValue(":strm", $currTerm, PDO::PARAM_INT);
            $stmt->execute();

            $resStat = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = $stmt->rowCount();
            $result = [];

            foreach ($resStat as $row) {
                $res = [
                    "facility_id" => $row["facility_id"],
                    "room_num" => $row["room_num"],
                    "room_name" => $row["room_name"],
                    "room_population" => $row["room_population"],
                    "uncert_amt" => $this->uncertaintyService->updateUncertainty($row["room_id"], $row["uncert_amt"]),
                    "coursetitle" => $row["coursetitle"],
                    "class_number_code" => $row["class_number_code"],
                    "pp_search_id" => $row["pp_search_id"],
                    "enrl_tot" => $row["enrl_tot"],
                    "zone_name" => $row["zone_name"],
                    "zone_code" => $row["zone_code"],
                    "ahu_name" => $row["ahu_name"],
                    "ppl_oa_rate" => $row["ppl_oa_rate"],
                    "pr_percent" => $row["pr_percent"],
                    "xref_population" => $row["xref_population"],
                    "xref_area" => $row["xref_area"],
                    "start_date" => $row["start_date"],
                    "end_date" => $row["end_date"],
                    "meeting_time_start" => $row["meeting_time_start"],
                    "meeting_time_end" => $row["meeting_time_end"],
                    "monday" => $row["monday"],
                    "tuesday" => $row["tuesday"],
                    "wednesday" => $row["wednesday"],
                    "thursday" => $row["thursday"],
                    "friday" => $row["friday"],
                    "saturday" => $row["saturday"],
                    "sunday" => $row["sunday"]
                ];

                $result[] = $res;
            }

            if ($isRaw) {
                return $result;
            } else {
                return [
                    "responseHeader" => [
                        "status" => 0
                    ],
                    "response" => [
                        "numFound" => $count,
                        "start" => 0,
                        "docs" => [
                            $result
                        ]
                    ]
                ];
            }
        } catch (PDOException $e) {
            $this->logService->logException($e, "Database error in getAirflowEventData: " . $e->getMessage());
            throw $e;
        }
    }

}