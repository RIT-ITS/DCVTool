<?php

namespace App\Services\Reference\Hvac;

use App\Services\Reference\BaseReferenceService;
use App\Database\DatabaseManager;
use PDO;
use PDOException;
use App\Services\Logging\LogService;
use App\Services\Configuration\ConfigService;
use App\Utilities\Utilities;
use App\Services\Reference\Academic\UncertaintyService;
use App\Services\Reference\Spaces\ZoneService;

class SetpointService extends BaseReferenceService
{
    protected Utilities $utilities;
    protected ConfigService $configService;
    protected LogService $logService;
    protected UncertaintyService $uncertaintyService;
    protected ZoneService $zoneService;

    /**
     * @param DatabaseManager $dbManager
     * @param Utilities $utilities
     * @param UncertaintyService $uncertaintyService
     * @param ZoneService $zoneService
     * @param ConfigService|null $configService
     * @param LogService|null $logService
     */
    public function __construct(
        DatabaseManager $dbManager,
        Utilities $utilities,
        UncertaintyService $uncertaintyService,
        ZoneService $zoneService,
        ?ConfigService $configService = null,
        ?LogService $logService = null
    ) {
        parent::__construct($dbManager);
        $this->utilities = $utilities;
        $this->uncertaintyService = $uncertaintyService;
        $this->zoneService = $zoneService;

        // Use injected services if provided, otherwise create new instances
        $this->configService = $configService ?? new ConfigService($dbManager);
        $this->logService = $logService ?? new LogService($dbManager, $utilities, $this->configService);
    }

    /**
     * Retrieves all setpoint write data
     *
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The setpoint write data
     * @throws PDOException If database query fails
     */
    public function readSetpointWriteDataAll(bool $isRaw = false): array
    {
        try {
            $connection = $this->dbManager->getWebCtrlConnection();

            $sql = "SELECT * FROM setpoint_write ORDER BY uname ASC, effectivetime ASC";
            $stmt = $connection->prepare($sql);
            $stmt->execute();

            $setpointData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = $stmt->rowCount();

            $result = [];

            $configVars = $this->configService->readConfigVars(3, true);
            $unamePreString = $configVars['unamePreString'] ?? '';
            $unamePostString = $configVars['unamePostString'] ?? '';

            foreach ($setpointData as $row) {
                $zoneName = str_replace($unamePostString, "", str_replace($unamePreString, "", $row["uname"]));
                $res = [
                    "effectivetime" => $row["effectivetime"],
                    "uname" => $row["uname"],
                    "pv" => $row["pv"],
                    "dispatched" => ($row["dispatched"]) ? 1 : 0,
                    "xrefs" => $this->zoneService->getXrefsByZone(null, $zoneName, true)
                ];
                $result[] = $res;
            }
            return $this->formatResponse($result, $count, $isRaw);
        } catch (PDOException $e) {
            $this->logService->logException($e,"Error retrieving setpoint write data");
            throw $e;
        }
    }

    /**
     * Retrieves setpoint write data for a specific date range
     *
     * @param string $date1 Start date
     * @param string $date2 End date (optional)
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The setpoint write data for the specified date range
     * @throws PDOException If database query fails
     */
    public function readSetpointWriteData(string $date1, string $date2 = '0', bool $isRaw = false): array
    {
        try {
            $connection = $this->dbManager->getWebCtrlConnection();

            $startDate = (new \DateTime($date1, new \DateTimeZone('America/New_York')))->format('Y-m-d H:i:s.u');

            if ($date2 === "0-0-0" || $date2 === '0') {
                $endDate = (clone new \DateTime($date1, new \DateTimeZone('America/New_York')))
                    ->add(new \DateInterval("P1D"))
                    ->format('Y-m-d H:i:s.u');
            } else {
                $endDate = (clone new \DateTime($date2, new \DateTimeZone('America/New_York')))
                    ->add(new \DateInterval("P1D"))
                    ->format('Y-m-d H:i:s.u');
            }

            $sql = "SELECT * FROM setpoint_write WHERE effectivetime > :dateStart AND effectivetime < :dateEnd ORDER BY uname ASC, effectivetime ASC";
            $stmt = $connection->prepare($sql);
            $stmt->bindValue(":dateStart", $startDate, PDO::PARAM_STR);
            $stmt->bindValue(":dateEnd", $endDate, PDO::PARAM_STR);
            $stmt->execute();

            $setpointData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = $stmt->rowCount();

            $result = [];

            $configVars = $this->configService->readConfigVars(3, true);
            $unamePreString = $configVars['unamePreString'] ?? '';
            $unamePostString = $configVars['unamePostString'] ?? '';

            foreach ($setpointData as $row) {
                $zoneName = str_replace($unamePostString, "", str_replace($unamePreString, "", $row["uname"]));

                $res = [
                    "effectivetime" => $row["effectivetime"],
                    "uname" => $row["uname"],
                    "pv" => $row["pv"],
                    "dispatched" => ($row["dispatched"]) ? 1 : 0,
                    "xrefs" => $this->zoneService->getXrefsByZone(null, $zoneName, true)
                ];

                $result[] = $res;
            }

            return $this->formatResponse($result, $count, $isRaw);
        } catch (PDOException $e) {
            $this->logService->logException($e, "Error retrieving setpoint write data for date range");
            throw $e;
        }
    }

    /**
     * Retrieves all expanded setpoint write data with related information
     *
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The expanded setpoint write data
     * @throws PDOException If database query fails
     */
    public function readExpandedSetPointWriteDataAll(bool $isRaw = false): array
    {
        try {
            $connection = $this->dbManager->getDefaultConnection();
            $webCtrlConnection = $this->dbManager->getWebCtrlConnection();

            $sql = "SELECT * FROM 
            ((((setpoint_expanded se 
            INNER JOIN rooms r ON r.facility_id = se.facility_id)
            INNER JOIN zones z ON z.zone_name = se.zone_name)
            INNER JOIN ashrae_6_1 ash ON r.ash61_cat_id = ash.id)
            INNER JOIN room_zone_xref xyz ON xyz.room_id = r.id AND xyz.zone_id = se.zone_id)
            ORDER BY z.zone_code ASC, se.effectivetime ASC";

            $stmt = $connection->prepare($sql);
            $stmt->execute();

            $setpointData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = $stmt->rowCount();

            $result = [];

            $configVars = $this->configService->readConfigVars(3, true);
            $unamePreString = $configVars['unamePreString'] ?? '';
            $unamePostString = $configVars['unamePostString'] ?? '';

            foreach ($setpointData as $row) {
                $res = [
                    "zone_name" => $row["zone_name"],
                    "facility_id" => $row["facility_id"],
                    "coursetitle" => $row["coursetitle"],
                    "enrl_tot" => $row["enrl_tot"],
                    "pv" => $row["pv"],
                    "id" => $row["id"],
                    "effectivetime" => $row["effectivetime"],
                    "class_number_code" => $row["class_number_code"],
                    "zone_id" => $row["zone_id"],
                    "ppl_oa_rate" => $row["ppl_oa_rate"],
                    "xref_population" => $row["xref_population"],
                    "pr_percent" => $row["pr_percent"],
                    "uncert_amt" => $row["uncert_amt"],
                    "disptached" => $row["zone_code"]
                ];

                // Check if the setpoint was dispatched
                $sql2 = "SELECT dispatched FROM setpoint_write WHERE uname = :uname AND effectivetime = :efftime";
                $stmt2 = $webCtrlConnection->prepare($sql2);
                $stmt2->bindValue(':uname', $unamePreString . $row["zone_code"] . $unamePostString, PDO::PARAM_STR);
                $stmt2->bindValue(':efftime', $row["effectivetime"], PDO::PARAM_STR);
                $stmt2->execute();

                $res["dispatched"] = ($stmt2->fetch(PDO::FETCH_ASSOC)) ? 1 : 0;

                $result[] = $res;
            }

            return $this->formatResponse($result, $count, $isRaw);
        } catch (PDOException $e) {
            $this->logService->logException($e, "Error retrieving expanded setpoint write data");
            throw $e;
        }
    }

    /**
     * Retrieves expanded setpoint write data for a specific date range
     *
     * @param string $date1 Start date
     * @param string $date2 End date (optional)
     * @param bool $isRaw Whether to return raw data or formatted response
     * @return array The expanded setpoint write data for the specified date range
     * @throws PDOException If database query fails
     */
    public function readExpandedSetPointWriteData(string $date1, string $date2 = '0', bool $isRaw = false): array
    {
        try {
            $connection = $this->dbManager->getDefaultConnection();
            $webCtrlConnection = $this->dbManager->getWebCtrlConnection();

            $startDate = (new \DateTime($date1, new \DateTimeZone('America/New_York')))->format('Y-m-d H:i:s.u');

            if ($date2 === "0-0-0" || $date2 === '0') {
                $endDate = (clone new \DateTime($date1, new \DateTimeZone('America/New_York')))
                    ->add(new \DateInterval("P1D"))
                    ->format('Y-m-d H:i:s.u');
            } else {
                $endDate = (clone new \DateTime($date2, new \DateTimeZone('America/New_York')))
                    ->add(new \DateInterval("P1D"))
                    ->format('Y-m-d H:i:s.u');
            }

            $sql = "SELECT * FROM 
                ((((setpoint_expanded se 
                INNER JOIN rooms r ON r.facility_id = se.facility_id)
                INNER JOIN zones z ON z.zone_name = se.zone_name)
                INNER JOIN ashrae_6_1 ash ON r.ash61_cat_id = ash.id)
                INNER JOIN room_zone_xref xyz ON xyz.room_id = r.id AND xyz.zone_id = se.zone_id)
                WHERE se.effectivetime > :dateStart AND se.effectivetime < :dateEnd
                ORDER BY z.zone_code ASC, se.effectivetime ASC";

            $stmt = $connection->prepare($sql);
            $stmt->bindValue(":dateStart", $startDate, PDO::PARAM_STR);
            $stmt->bindValue(":dateEnd", $endDate, PDO::PARAM_STR);
            $stmt->execute();

            $setpointData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = $stmt->rowCount();

            $result = [];

            $configVars = $this->configService->readConfigVars(3, true);
            $unamePreString = $configVars['unamePreString'] ?? '';
            $unamePostString = $configVars['unamePostString'] ?? '';

            foreach ($setpointData as $row) {
                $res = [
                    "zone_name" => $row["zone_name"],
                    "facility_id" => $row["facility_id"],
                    "coursetitle" => $row["coursetitle"],
                    "enrl_tot" => $row["enrl_tot"],
                    "pv" => $row["pv"],
                    "id" => $row["id"],
                    "effectivetime" => $row["effectivetime"],
                    "class_number_code" => $row["class_number_code"],
                    "zone_id" => $row["zone_id"],
                    "ppl_oa_rate" => $row["ppl_oa_rate"],
                    "xref_population" => $row["xref_population"],
                    "pr_percent" => $row["pr_percent"],
                    "uncert_amt" => $row["uncert_amt"],
                    "disptached" => $row["zone_code"]
                ];

                // Check if the setpoint was dispatched
                $sql2 = "SELECT dispatched FROM setpoint_write WHERE uname = :uname AND effectivetime = :efftime";
                $stmt2 = $webCtrlConnection->prepare($sql2);
                $stmt2->bindValue(':uname', $unamePreString . $row["zone_code"] . $unamePostString, PDO::PARAM_STR);
                $stmt2->bindValue(':efftime', $row["effectivetime"], PDO::PARAM_STR);
                $stmt2->execute();

                $dispatchedResult = $stmt2->fetch(PDO::FETCH_ASSOC);
                $res["dispatched"] = ($dispatchedResult) ? 1 : 0;

                $result[] = $res;
            }

            return $this->formatResponse($result, $count, $isRaw, $startDate, $endDate);
        } catch (PDOException $e) {
            $this->logService->logException($e, "Error retrieving expanded setpoint write data for date range");
            throw $e;
        }
    }

}