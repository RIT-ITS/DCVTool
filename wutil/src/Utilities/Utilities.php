<?php
// src/Utilities/Utilities.php
namespace App\Utilities;

use App\Database\DatabaseManager;
use DateInterval;
use DateTime;
use DateTimeZone;
use PDO;
use Exception;
use PDOException;
class Utilities
{
    private DatabaseManager $dbManager;

    public function __construct(
        DatabaseManager $dbManager
    )
    {
        $this->dbManager = $dbManager;
    }


    /**
     * Return a formatted JSON response to the client
     *
     * @param string $msg The message to include in the response
     * @param string $status The status of the response (Success/Error/etc.)
     * @param array $result The data to include in the response
     * @param int $count The total number of results (for pagination)
     * @param int $start The starting index (for pagination)
     * @return never This function terminates execution after sending the response
     */
    public function returnMsg(string $msg, string $status = "Success", array $result = [], int $count = 0, int $start = 0): never
    {
        // Build the response array
        $dataArr = [
            "responseHeader" => [
                "status" => $status
            ],
            "response" => [
                "message" => $msg
            ]
        ];

        // Add result data if provided
        if (!empty($result)) {
            $dataArr["response"]["numFound"] = $count;
            $dataArr["response"]["start"] = $start;
            $dataArr["response"]["docs"] = $result;
        }

        // Set content type header
        header("Content-Type: application/json");

        // Output the JSON response
        echo json_encode($dataArr, JSON_PRETTY_PRINT);

        // Terminate execution
        exit;
    }



    // NOTE: this script converts a 'null' to an empty string "" - this may need to be changed if circumstances dictate.
    public static function sanitizeStringInput(?string $input): ?string
    {
        // If the input is null, return null.
        if ($input === null) {
            return null;
        }

        // Trims the string to a max of 1024 characters, and then runs htmlspecialchars on it.
        return htmlspecialchars(substr($input, 0, 1024), ENT_QUOTES);
    }


    // NOTE: this script converts a 'null' to 0 - this may need to be changed if circumstances dictate.
    public function sanitizeIntegerInput(int|string|null $input): ?int
    {
        // If the input is null, return null.
        if ($input === null) {
            return 0;
        }
        // If the input is already an integer, return it
        if (is_int($input)) {
            return $input;
        }
        // If the input is a string, sanitize and validate it
        if (is_string($input)) {
            // First, sanitize the input to ensure it only contains numbers
            $sanitizedInput = filter_var($input, FILTER_SANITIZE_NUMBER_INT);

            // Then, filter the sanitized input to validate it as an integer
            $filteredInput = filter_var($sanitizedInput, FILTER_VALIDATE_INT);

            // If the input is not a valid integer, return 0
            if ($filteredInput === false) {
                return 0;
            }

            return (int)$filteredInput;
        }
        // For any other type, return 0
        return 0;
    }


    // NOTE: this script converts a 'null' to 0 - this may need to be changed if circumstances dictate.
    public function sanitizeFloatingNumberInput(?string $input): ?float
    {
        // If the input is null, return null.
        if ($input === null) {
            return 0;
        }

        // First, sanitize the input to ensure it only contains numbers.
        $sanitizedInput = filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        // Then, filter the sanitized input to validate it as a float.
        $filteredInput = filter_var($sanitizedInput, FILTER_VALIDATE_FLOAT);

        // If the input is not a valid float, return 0.0 or any default value.
        if ($filteredInput === false) {
            return 0.0;
        }
        return (float)$filteredInput;
    }

    public function validateNumericOrNull($input): bool
    {
        // If the input is null or numeric, return true.
        if ($input === null || is_numeric($input)) {
            return true;
        }

        // If the input is not null and not numeric, return false.
        return false;
    }

    // Postgresql specific boolean sanitizer
    public function sanitizeBooleanInput(?bool $input): ?string
    {
        // If the input is null, return null.
        if ($input === null) {
            return 'f';
        }
        return $input ? 't' : 'f';
    }

    // Returns the $data if it's valid, or false if the data is not proper format
    public function sanitizeDateInput($date)
    {
        try {
            $dt = new DateTime($date);
            // Checking if the formatted date is same as the provided date, this will help to avoid invalid date like 2023-02-30
            if($dt->format('Y-m-d') === $date) {
                return $date;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    // Returns the $data if it's valid, or false if the data is not proper format
    public function sanitizeOtherDateInput($date)
    {
        try {
            $dt = new DateTime($date);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return false;
        }
    }

    // returnt the time if it's valid, or false if it's not
    function sanitizeTimeWTzoneInput($time)
    {
        try {
            $dt = new DateTime($time);
            // Checking if the formatted time with timezone is same as the provided time with timezone
            if($dt->format('H:i:s.u P') === $time) {
                return $time;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    // return the time if it's valid, or false if it's not
    function sanitizeTimeWOtherTzoneInput($time)
    {
        try {
            $dt = new DateTime($time);

            $timeST = DateTime::createFromFormat('H:i:sP', $time);

            if($dt == $timeST) {
                return $time;
            } else {
                return false;
            }

        } catch (Exception $e) {
            return false;
        }
    }


    // returns the datetime if it's valid, or false if it's not.
    function sanitizeDateTimeInput($dateTime)
    {
        try {
            $dt = new DateTime($dateTime);

            // Checking if the formatted datetime with timezone is same as the provided datetime with timezone
            if($dt->format('Y-m-d H:i:sO') === $dateTime) {
                return $dateTime;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }



    // ########## Function Sanitizers ########## //

    /**
     * Sanitize uncertainty data from form submission
     *
     * @param array $data Raw uncertainty data
     * @return array Sanitized uncertainty data
     */
    public function sanitizeUncertainty(array $data): array
    {
        $saniData = [
            "uncert_amt" => $this->sanitizeIntegerInput($data['uncert_amt'] ?? null),
            "u_desc" => $this->sanitizeStringInput($data['u_desc'] ?? null),
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];

        // Sanitize array of ASHRAE IDs
        $saniData["ashrae_61_ids"] = array_map(
            fn($value) => $this->sanitizeIntegerInput($value),
            $data["ashrae_61_ids"] ?? []
        );

        return $saniData;
    }

    /**
     * Sanitize setpoint data from form submission
     *
     * @param array $data Raw setpoint data
     * @return array Sanitized setpoint data
     */
    public function sanitizeSetpointData(array $data): array
    {
        return [
            "effectivetime" => $data['effectivetime'] ?? null,
            "uname" => $this->sanitizeStringInput($data['uname'] ?? null),
            "pv" => $this->validateNumericOrNull($data['pv'] ?? null) ? $data['pv'] : null,
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null
            // Commented out in original: "delete" => $this->sanitizeBooleanInput($data['delete'] ?? null)
        ];
    }

    /**
     * Sanitize ASHRAE 62.1 data from form submission
     *
     * @param array $data Raw ASHRAE 62.1 data
     * @return array Sanitized ASHRAE 62.1 data
     */
    public function sanitizeAshrae61(array $data): array
    {
        return [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "category" => $this->sanitizeStringInput($data['category'] ?? null),
            "ok" => $this->sanitizeBooleanInput($data['ok'] ?? null),
            "ppl_oa_rate" => $this->sanitizeFloatingNumberInput($data['ppl_oa_rate'] ?? null),
            "area_oa_rate" => $this->sanitizeFloatingNumberInput($data['area_oa_rate'] ?? null),
            "occ_density" => $this->sanitizeIntegerInput($data['occ_density'] ?? null),
            "occ_stdby_allowed" => $this->sanitizeBooleanInput($data['occ_stdby_allowed'] ?? null),
            "type" => $this->sanitizeIntegerInput($data['type'] ?? null),
            "notes" => $this->sanitizeStringInput($data['notes'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];
    }

    /**
     * Sanitize equipment mapping data from form submission
     *
     * @param array $data Raw equipment mapping data
     * @return array Sanitized equipment mapping data
     */
    public function sanitizeEquipmentMap(array $data): array
    {
        return [
            "sysname" => $this->sanitizeStringInput($data['sysname'] ?? null),
            "path" => $this->sanitizeStringInput($data['path'] ?? null),
            "pointtype" => $this->sanitizeStringInput($data['pointtype'] ?? null),
            "uname" => $this->sanitizeStringInput($data['uname'] ?? null),
            "description" => $this->sanitizeStringInput($data['description'] ?? null),
            "units" => $this->sanitizeStringInput($data['units'] ?? null),
            "ptid" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "enabled" => $this->sanitizeBooleanInput($data['enabled'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];
    }

    /**
     * Sanitize configuration variables from form submission
     *
     * @param array $data Raw configuration variables
     * @return array Sanitized configuration variables
     */
    public function sanitizeConfigVars(array $data): array
    {
        return [
            "config_key" => $this->sanitizeStringInput($data['config_key'] ?? ''),
            "config_value" => $this->sanitizeStringInput($data['config_value'] ?? ''),
            "config_scope" => $this->sanitizeStringInput($data['config_scope'] ?? ''),
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];
    }

    /**
     * Sanitize space import data from bulk import
     *
     * @param array $d Raw space import data with nested records
     * @return array Sanitized space import data
     */
    public function sanitizeSpaceImport(array $d): array
    {
        $saniData = [
            "data" => [],
            "campus_id" => $this->sanitizeIntegerInput($d['campus_id'] ?? null)
        ];

        // Process each record in the data array
        foreach ($d['data'] ?? [] as $data) {
            $saniData["data"][] = [
                "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
                "facility_id" => $this->sanitizeStringInput($data['facility_id'] ?? null),
                "room_name" => $this->sanitizeStringInput($data['room_name'] ?? null),
                "building_id" => $this->sanitizeIntegerInput($data['building_id'] ?? null),
                "room_area" => $this->sanitizeFloatingNumberInput($data['room_area'] ?? null),
                "ash61_cat_id" => $this->sanitizeIntegerInput($data['ash61_cat_id'] ?? null),
                "room_population" => $this->sanitizeIntegerInput($data['room_population'] ?? null),
                "room_num" => $this->sanitizeStringInput($data['room_num'] ?? null),
                "floor_id" => $this->sanitizeIntegerInput($data['floor_id'] ?? null),
                "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0,
                "bldg_num" => $this->sanitizeStringInput($data['bldg_num'] ?? null),
                "bldg_name" => $this->sanitizeStringInput($data['bldg_name'] ?? null),
                "rtype_code" => $this->sanitizeStringInput($data['rtype_code'] ?? null),
                "short_desc" => $this->sanitizeStringInput($data['short_desc'] ?? null),
                "facility_code" => $this->sanitizeStringInput($data['facility_code'] ?? null),
                "space_use_name" => $this->sanitizeStringInput($data['space_use_name'] ?? null),
                "floor_designation" => $this->sanitizeStringInput($data['floor_designation'] ?? null),
                "active" => $this->sanitizeBooleanInput($data['active'] ?? null),
                "reservable" => $this->sanitizeBooleanInput($data['reservable'] ?? null),
                "uncert_amt" => $this->sanitizeIntegerInput($data['uncert_amt'] ?? null)
            ];
        }

        return $saniData;
    }


    /**
     * Sanitize SIS import data from bulk import
     *
     * @param array $d Raw SIS import data with nested records
     * @return array Sanitized SIS import data
     */
    public function sanitizeSisImport(array $d): array
    {
        $saniData = [
            "data" => [],
            "campus_id" => $this->sanitizeIntegerInput($d['campus_id'] ?? null)
        ];

        // Process each record in the data array
        foreach ($d['data'] ?? [] as $data) {
            $saniData["data"][] = [
                "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
                "bldg_num" => $this->sanitizeStringInput($data['bldg_num'] ?? null),
                "bldg_code" => $this->sanitizeStringInput($data['bldg_code'] ?? null),
                "facility_id" => $this->sanitizeStringInput($data['facility_id'] ?? null),
                "pp_search_id" => $this->sanitizeStringInput($data['pp_search_id'] ?? null),
                "class_number_code" => $this->sanitizeStringInput($data['class_number_code'] ?? null),
                "coursetitle" => $this->sanitizeStringInput($data['coursetitle'] ?? null),
                "strm" => $this->sanitizeIntegerInput($data['strm'] ?? null),
                "enrl_tot" => $this->sanitizeIntegerInput($data['enrl_tot'] ?? null),
                "room_num" => $this->sanitizeIntegerInput($data['room_num'] ?? null),
                "start_date" => isset($data['start_date']) ? $this->sanitizeOtherDateInput($data['start_date']) : null,
                "end_date" => isset($data['end_date']) ? $this->sanitizeOtherDateInput($data['end_date']) : null,
                "meeting_time_start" => isset($data['meeting_time_start']) ? $this->sanitizeTimeWOtherTzoneInput($data['meeting_time_start']) : null,
                "meeting_time_end" => isset($data['meeting_time_end']) ? $this->sanitizeTimeWOtherTzoneInput($data['meeting_time_end']) : null,
                "monday" => $this->sanitizeIntegerInput($data['monday'] ?? null),
                "tuesday" => $this->sanitizeIntegerInput($data['tuesday'] ?? null),
                "wednesday" => $this->sanitizeIntegerInput($data['wednesday'] ?? null),
                "thursday" => $this->sanitizeIntegerInput($data['thursday'] ?? null),
                "friday" => $this->sanitizeIntegerInput($data['friday'] ?? null),
                "saturday" => $this->sanitizeIntegerInput($data['saturday'] ?? null),
                "sunday" => $this->sanitizeIntegerInput($data['sunday'] ?? null)
            ];
        }

        return $saniData;
    }

    /**
     * Sanitize zone import data from bulk import
     *
     * @param array $d Raw zone import data with nested records
     * @return array Sanitized zone import data
     */
    public function sanitizeZoneImport(array $d): array
    {
        $saniData = [
            "data" => [],
            "campus_id" => $this->sanitizeIntegerInput($d['campus_id'] ?? null)
        ];

        // Process each record in the data array
        foreach ($d['data'] ?? [] as $data) {
            $sreturn = [
                "bldg_num" => $this->sanitizeStringInput($data['bldg_num'] ?? null),
                "ahu_name" => $this->sanitizeStringInput($data['ahu_name'] ?? null),
                "zone_name" => $this->sanitizeStringInput($data['zone_name'] ?? null),
                "zone_code" => $this->sanitizeStringInput($data['zone_code'] ?? null),
                "occ_sensor" => $this->sanitizeIntegerInput($data['occ_sensor'] ?? null),
                "active" => $this->sanitizeBooleanInput($data['active'] ?? null)
            ];

            // Sanitize cross-references array
            $sreturn['xrefs'] = array_map(
                fn($value) => $this->sanitizeStringInput($value),
                $data['xrefs'] ?? []
            );

            $saniData["data"][] = $sreturn;
        }

        return $saniData;
    }

    /**
     * Sanitize room data from form submission
     *
     * @param array $data Raw room data
     * @return array Sanitized room data
     */
    public function sanitizeRooms(array $data): array
    {
        $saniData = [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "facility_id" => $this->sanitizeStringInput($data['facility_id'] ?? null),
            "room_name" => $this->sanitizeStringInput($data['room_name'] ?? null),
            "building_id" => $this->sanitizeIntegerInput($data['building_id'] ?? null),
            "room_area" => $this->sanitizeFloatingNumberInput($data['room_area'] ?? null),
            "ash61_cat_id" => $this->sanitizeIntegerInput($data['ash61_cat_id'] ?? null),
            "room_population" => $this->sanitizeIntegerInput($data['room_population'] ?? null),
            "room_num" => $this->sanitizeStringInput($data['room_num'] ?? null),
            "floor_id" => $this->sanitizeIntegerInput($data['floor_id'] ?? null),
            "rtype_code" => $this->sanitizeStringInput($data['rtype_code'] ?? null),
            "space_use_name" => $this->sanitizeStringInput($data['space_use_name'] ?? null),
            "active" => $this->sanitizeBooleanInput($data['active'] ?? null),
            "reservable" => $this->sanitizeBooleanInput($data['reservable'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];

        // Special handling for uncertainty amount
        // The user needs to be able to add 0 as a uncert value
        // meaning it needs to be able see when the value is null so it can default
        $saniData["uncert_amt"] = $data["uncert_amt"] !== null
            ? $this->sanitizeIntegerInput($data['uncert_amt'])
            : null;

        return $saniData;
    }

    /**
     * Sanitize building data from form submission
     *
     * @param array $data Raw building data
     * @return array Sanitized building data
     */
    public function sanitizeBuildings(array $data): array
    {
        return [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "bldg_num" => $this->sanitizeStringInput($data['bldg_num'] ?? null),
            "bldg_name" => $this->sanitizeStringInput($data['bldg_name'] ?? null),
            "campus_id" => $this->sanitizeIntegerInput($data['campus_id'] ?? null),
            "short_desc" => $this->sanitizeStringInput($data['short_desc'] ?? null),
            "facility_code" => $this->sanitizeStringInput($data['facility_code'] ?? null),
            "active" => $this->sanitizeBooleanInput($data['active'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];
    }


    /**
     * Sanitize campus data from form submission
     *
     * @param array $data Raw campus data
     * @return array Sanitized campus data
     */
    public function sanitizeCampus(array $data): array
    {
        return [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "code" => $this->sanitizeStringInput($data['code'] ?? null),
            "campus_name" => $this->sanitizeStringInput($data['campus_name'] ?? null),
            "utc_offset" => $this->sanitizeStringInput($data['utc_offset'] ?? null),
            "campus_num" => $this->sanitizeStringInput($data['campus_num'] ?? null),
            "active" => $this->sanitizeBooleanInput($data['active'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];
    }

    /**
     * Sanitize floor data from form submission
     *
     * @param array $data Raw floor data
     * @return array Sanitized floor data
     */
    public function sanitizeFloors(array $data): array
    {
        return [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "floor_designation" => $this->sanitizeStringInput($data['floor_designation'] ?? null),
            "buildings_id" => $this->sanitizeIntegerInput($data['buildings_id'] ?? null),
            "active" => $this->sanitizeBooleanInput($data['active'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];
    }

    /**
     * Sanitize ASHRAE 62.1-2004 data from form submission
     *
     * @param array $data Raw ASHRAE 62.1-2004 data
     * @return array Sanitized ASHRAE 62.1-2004 data
     */
    public function sanitizeAshrae64(array $data): array
    {
        return [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "cat" => $this->sanitizeIntegerInput($data['cat'] ?? null),
            "ez" => $this->sanitizeFloatingNumberInput($data['ez'] ?? null),
            "configuration" => $this->sanitizeStringInput($data['configuration'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];
    }

    /**
     * Sanitize term data from form submission
     *
     * @param array $data Raw term data
     * @return array Sanitized term data
     */
    public function sanitizeTerm(array $data): array
    {
        return [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "term_name" => $this->sanitizeStringInput($data['term_name'] ?? null),
            "term_code" => $this->sanitizeIntegerInput($data['term_code'] ?? null),
            "term_start" => isset($data['term_start']) ? $this->sanitizeDateInput($data['term_start']) : null,
            "term_end" => isset($data['term_end']) ? $this->sanitizeDateInput($data['term_end']) : null,
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];
    }

    /**
     * Sanitize NCES 4.2 space use code data from form submission
     *
     * @param array $data Raw NCES 4.2 data
     * @return array Sanitized NCES 4.2 data
     */
    public function sanitizeNces42(array $data): array
    {
        $saniData = [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "code" => $this->sanitizeStringInput($data['code'] ?? null),
            "space_use_name" => $this->sanitizeStringInput($data['space_use_name'] ?? null),
            "category_id" => $this->sanitizeIntegerInput($data['category_id'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];

        // Sanitize array of ASHRAE IDs
        $saniData['ashrae_61_ids'] = array_map(
            fn($value) => $this->sanitizeIntegerInput($value),
            $data['ashrae_61_ids'] ?? []
        );

        return $saniData;
    }

    /**
     * Sanitize NCES category data from form submission
     *
     * @param array $data Raw NCES category data
     * @return array Sanitized NCES category data
     */
    public function sanitizeNcesCats(array $data): array
    {
        return [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "code" => $this->sanitizeStringInput($data['code'] ?? null),
            "type_name" => $this->sanitizeStringInput($data['type_name'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];
    }

    /**
     * @param array $data Raw xref data
     * @return int[]|null[] Sanitized xref data
     */
    public function sanitizeNcesAshraeXref(array $data): array
    {
        return [
            "ashrae_id" => $this->sanitizeIntegerInput($data['ashrae_id'] ?? null),
            "nces_id" => $this->sanitizeIntegerInput($data['nces_id'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
            ];
    }

    /**
     * Sanitize zone data from form submission
     *
     * @param array $data Raw zone data
     * @return array Sanitized zone data
     */
    public function sanitizeZones(array $data): array
    {
        $saniData = [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "zone_name" => $this->sanitizeStringInput($data['zone_name'] ?? null),
            "zone_code" => $this->sanitizeStringInput($data['zone_code'] ?? null),
            "building_id" => $this->sanitizeIntegerInput($data['building_id'] ?? null),
            "ahu_name" => $this->sanitizeStringInput($data['ahu_name'] ?? null),
            "occ_sensor" => $this->sanitizeBooleanInput($data['occ_sensor'] ?? null),
            "active" => $this->sanitizeBooleanInput($data['active'] ?? null),
            // Only set the auto mode if the data is coming from the airflow table
            "auto_mode" => isset($data['auto_mode']) ? $this->sanitizeBooleanInput($data['auto_mode']) : false,
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];

        // Sanitize array of cross-references
        $saniData['xrefs'] = array_map(
            fn($value) => $this->sanitizeStringInput($value),
            $data['xrefs'] ?? []
        );

        return $saniData;
    }


    /**
     * Sanitize cross-reference data from form submission
     *
     * @param array $data Raw cross-reference data
     * @return array Sanitized cross-reference data
     */
    public function sanitizeXref(array $data): array
    {
        return [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "zone_name" => $this->sanitizeStringInput($data['zone_name'] ?? null),
            "facility_id" => $this->sanitizeStringInput($data['facility_id'] ?? null),
            "xref_area" => $this->sanitizeFloatingNumberInput($data['xref_area'] ?? null),
            "xref_population" => $this->sanitizeFloatingNumberInput($data['xref_population'] ?? null),
            "pr_percent" => $this->sanitizeFloatingNumberInput($data['pr_percent'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];
    }

    /**
     * @param array $data Raw AHU data
     * @return array Sanitized AHU Data
     */
    public function sanitizeAHU(array $data): array
    {
        return [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            "ahu_name" => $this->sanitizeStringInput($data['ahu_name'] ?? null),
            "ahu_code" => $this->sanitizeStringInput($data['ahu_code'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];
    }

    /**
     * Sanitize airflow data from form submission
     *
     * @param array $data Raw airflow data
     * @return array Sanitized airflow data
     */
    public function sanitizeAirflowData(array $data): array
    {
        $saniData = [
            "id" => isset($data['zone_id']) ? $this->sanitizeIntegerInput($data['zone_id']) : null,
            "zone_name" => $this->sanitizeStringInput($data['zone_name'] ?? null),
            "zone_code" => $this->sanitizeStringInput($data['zone_code'] ?? null),
            "building_id" => $this->sanitizeIntegerInput($data['building_id'] ?? null),
            "ahu_name" => $this->sanitizeStringInput($data['ahu_name'] ?? null),
            "occ_sensor" => $this->sanitizeBooleanInput($data['occ_sensor'] ?? null),
            "active" => $this->sanitizeBooleanInput($data['active'] ?? null),
            "auto_mode" => $this->sanitizeBooleanInput($data['auto_mode'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];

        // Sanitize array of cross-references
        $saniData['xrefs'] = array_map(
            fn($value) => $this->sanitizeStringInput($value),
            $data['xrefs'] ?? []
        );

        return $saniData;
    }


    /**
     * Sanitize user data from form submission
     *
     * @param array $data Raw user data
     * @return array Sanitized user data
     */
    public function sanitizeUser(array $data): array
    {
        return [
            "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
            'first_name' => $this->sanitizeStringInput($data['first_name'] ?? null),
            'last_name' => $this->sanitizeStringInput($data['last_name'] ?? null),
            'email' => $this->sanitizeStringInput($data['email'] ?? null),
            'role_name' => $this->sanitizeStringInput($data['role_name'] ?? null),
            'role' => $this->sanitizeIntegerInput($data['role'] ?? null),
            'uid' => $this->sanitizeStringInput($data['uid'] ?? null),
            "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
        ];
    }

    /**
     * Sanitize expanded schedule data from form submission
     *
     * @param array $data Raw expanded schedule data
     * @return array Sanitized expanded schedule data
     */
    public function sanitizeExpandedSchedule(array $data): array
    {
        // Create datetime objects for start and end times
        try {
            // Create a new date using the event's date
            $incomingDT = new \DateTime($data['eventDate'] ?? 'now');

            // Format it
            $incomingDTToExpanded = $incomingDT->format('Y-m-d');

            // Create datetime objects using the formatted date and start/end times
            $datetimeStart = new \DateTime(
                $incomingDTToExpanded . ' ' . ($data['meeting_time_start'] ?? '00:00:00'),
                new \DateTimeZone("America/New_York")
            );

            $datetimeEnd = new \DateTime(
                $incomingDTToExpanded . ' ' . ($data['meeting_time_end'] ?? '00:00:00'),
                new \DateTimeZone("America/New_York")
            );

            // Format the new datetime objects
            $formattedDTStart = $datetimeStart->format('Y-m-d H:i:sO');
            $formattedDTEnd = $datetimeEnd->format('Y-m-d H:i:sO');

            return [
                "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
                'coursetitle' => $this->sanitizeStringInput($data['coursetitle'] ?? null),
                'campus' => $this->sanitizeIntegerInput($data['campus'] ?? null),
                'bldg_num' => $this->sanitizeStringInput($data['bldg_num'] ?? null),
                'room_num' => $this->sanitizeStringInput($data['room_num'] ?? null),
                'datetimeStart' => $this->sanitizeDateTimeInput($formattedDTStart),
                'formattedDate' => $datetimeStart->format('Y-m-d'),
                'formattedStart' => $datetimeStart->format('g:i A'),
                'formattedEnd' => $datetimeEnd->format('g:i A'),
                'datetimeEnd' => $this->sanitizeDateTimeInput($formattedDTEnd),
                'enrl_tot' => $this->sanitizeIntegerInput($data['enrl_tot'] ?? null),
                "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
            ];
        } catch (\Exception $e) {
            // Handle date/time parsing errors
            error_log("Error sanitizing expanded schedule: " . $e->getMessage());

            return [
                "id" => isset($data['id']) ? $this->sanitizeIntegerInput($data['id']) : null,
                'coursetitle' => $this->sanitizeStringInput($data['coursetitle'] ?? null),
                'campus' => $this->sanitizeIntegerInput($data['campus'] ?? null),
                'bldg_num' => $this->sanitizeStringInput($data['bldg_num'] ?? null),
                'room_num' => $this->sanitizeStringInput($data['room_num'] ?? null),
                'datetimeStart' => null,
                'formattedDate' => null,
                'formattedStart' => null,
                'formattedEnd' => null,
                'datetimeEnd' => null,
                'enrl_tot' => $this->sanitizeIntegerInput($data['enrl_tot'] ?? null),
                "delete" => isset($data['delete']) ? $this->sanitizeIntegerInput($data['delete']) : 0
            ];
        }
    }

}
