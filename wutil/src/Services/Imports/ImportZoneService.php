<?php

namespace App\Services\Imports;

use App\Database\DatabaseManager;
use App\Utilities\Utilities;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use PDO;
use PDOException;
use DateInterval;
use DateTime;
use DateTimeZone;

class ImportZoneService {
    protected DatabaseManager $dbManager;
    protected Utilities $utilities;
    protected ConfigService $configService;
    protected LogService $logService;

    public function __construct(
        DatabaseManager $dbManager,
        ?Utilities $utilities = null,
        ?ConfigService $configService = null,
        ?LogService $logService = null
    ) {
        $this->dbManager = $dbManager;

        // Use injected services if provided, otherwise create new instances
        $this->utilities = $utilities ?? new Utilities($dbManager);
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $this->utilities, $this->configService);
    }

    public function importZoneData(array $data): array
    {
        // Get PDO connection
        $conn = $this->dbManager->getDefaultConnection();

        $ret = [];
        $campusId = $data['campus_id'];
        $errorArray = [];              // Array of error rows
        $newZones = [];
        $newXrefs = [];
        $rowsProcessed = 0;
        $newZoneCount = 0;             // Tracks number of zones inserted
        $rowsWithNoImport = 0;
        $newXrefCount = 0;

        foreach ($data['data'] as $d) {
            try {
                $result = false;
                $importData = 0;

                $d['ahu_step_complete'] = false;

                // See if the linked AHU exists
                $query = "SELECT ahu_name FROM ahu_data WHERE ahu_name = :ahu_name LIMIT 1";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':ahu_name', $d['ahu_name']);
                $stmt->execute();
                $ahuResult = $stmt->fetch(PDO::FETCH_NUM);

                $d['zone_step_complete'] = false;

                // Check to make sure the building the zone is in exists
                $query = "SELECT id FROM buildings WHERE bldg_num = :bldg_num AND campus_id = :campus LIMIT 1";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':bldg_num', $d['bldg_num']);
                $stmt->bindParam(':campus', $campusId);
                $stmt->execute();
                $bldgResult = $stmt->fetch(PDO::FETCH_NUM);

                if ($bldgResult && $ahuResult) {
                    // Check if zone already exists
                    $query = "SELECT * FROM zones WHERE zone_code = :zone_code AND ahu_name = :ahu_name AND zone_name = :zone_name LIMIT 1";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':zone_code', $d['zone_code']);
                    $stmt->bindParam(':zone_name', $d['zone_name']);
                    $stmt->bindParam(':ahu_name', $ahuResult[0]);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Zone Import
                    // If both the room and building exist, and the zone doesn't already exist, add the zone to the table
                    if ($bldgResult[0] && $ahuResult[0] && $result === false) {
                        $r = [];
                        $query = "INSERT INTO zones (zone_name, zone_code, building_id, ahu_name, occ_sensor, active) 
                                  VALUES (:zone_name, :zone_code, :building_id, :ahu_name, :occ_sensor, :active)";
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':zone_name', $d['zone_name']);
                        $stmt->bindParam(':zone_code', $d['zone_code']);
                        $stmt->bindParam(':building_id', $bldgResult[0]);
                        $stmt->bindParam(':occ_sensor', $d['occ_sensor']);
                        $stmt->bindParam(':ahu_name', $d['ahu_name']);
                        $stmt->bindParam(':active', $d['active']);

                        $stmt->execute();
                        $newZoneCount++;
                        $importData = 1;
                        $r['id'] = $conn->lastInsertId();
                        $r['zone_name'] = $d['zone_name'];
                        $r['zone_code'] = $d['zone_code'];
                        $r['building_id'] = $bldgResult[0];
                        $r['ahu_name'] = $ahuResult[0];
                        $r['campus'] = $campusId;
                        $r["active"] = $d["active"];

                        $newZones[] = $r;
                    }

                    if ($importData == 0) {
                        $rowsWithNoImport++;
                    }
                    $rowsProcessed++;

                    // XREF IMPORT
                    if ($bldgResult && $ahuResult && $result === false) {
                        foreach ($d['xrefs'] as $xref) {
                            $r = [];

                            // Verify that the room actually exists
                            $query = "SELECT id FROM rooms WHERE facility_id = :room_code AND building_id = :bldg_id LIMIT 1";
                            $stmt = $conn->prepare($query);
                            $stmt->bindParam(':room_code', $xref);
                            $stmt->bindParam(':bldg_id', $bldgResult[0]);
                            $stmt->execute();
                            $roomSearch = $stmt->fetch(PDO::FETCH_NUM);

                            // Get the most recent zone added to the zones table
                            $query = "SELECT MAX(id) FROM zones";
                            $stmt = $conn->prepare($query);
                            $stmt->execute();
                            $zoneSearch = $stmt->fetch(PDO::FETCH_NUM);

                            if ($roomSearch && $zoneSearch) {
                                $query = "INSERT INTO room_zone_xref (zone_id, room_id, pr_percent) VALUES (:zone_id, :room_id, :pr_percent)";
                                $stmtInsert = $conn->prepare($query);
                                $stmtInsert->bindParam(':zone_id', $zoneSearch[0]);
                                $stmtInsert->bindParam(':room_id', $roomSearch[0]);
                                $stmtInsert->bindValue(':pr_percent', 0);
                                $stmtInsert->execute();

                                $r['id'] = $conn->lastInsertId();
                                $r["facility_id"] = $xref;
                                $r['zone_name'] = $d["zone_name"];
                                $newXrefs[] = $r;
                                $newXrefCount++;
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                $d['error_message'] = $e->getMessage();
                $errorArray[] = $d;
            }
        }

        // Log imports using LogService
        if (!empty($newZones)) {
            $this->logService->logImports($newZones, 'zones');
        }
        if (!empty($newXrefs)) {
            $this->logService->logImports($newXrefs, 'xrefs');
        }

        $ret["totalRowsProcessed"] = $rowsProcessed;
        $ret["totalRowsNoImport"] = $rowsWithNoImport;
        $ret["totalRowsWithErrors"] = count($errorArray);
        $ret["errorArray"] = $errorArray;
        $ret["totalZonesAdded"] = $newZoneCount;
        $ret["totalXrefsAdded"] = $newXrefCount;

        return $ret;
    }


}

