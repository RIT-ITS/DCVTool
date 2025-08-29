<?php

namespace App\Services\Trends;

use App\Database\DatabaseManager;
use App\Utilities\Utilities;
use PDO;
use DateTime;
use Exception;

class TrendsService
{
    private PDO $webCtrlMainConnection;
    private Utilities $utilities;
    private string $logTag = 'TrendsService';

    /**
     * TrendsService constructor
     */
    public function __construct(DatabaseManager $databaseManager, Utilities $utilities)
    {
        $this->webCtrlMainConnection = $databaseManager->getWebCtrlMainConnection();
        $this->utilities = $utilities;
    }

    /**
     * Refresh all trends for the last 90 minutes
     *
     * @param int|null $minutes Number of minutes to look back (defaults to 90)
     * @return bool Success status
     */
    public function refreshAllTrends(?int $minutes = 90): bool
    {
        $this->utilities->dataLog("Refreshing all trends for the last {$minutes} minutes", $this->logTag);

        try {
            $sql = "CALL refresh_all_trends(LOCALTIMESTAMP - INTERVAL '{$minutes} minutes', LOCALTIMESTAMP);";

            $stmt = $this->webCtrlMainConnection->prepare($sql);
            $result = $stmt->execute();

            if ($result) {
                $this->utilities->dataLog("Successfully refreshed all trends", $this->logTag);
            } else {
                $this->utilities->dataLog("Failed to refresh all trends", $this->logTag);
            }

            return $result;
        } catch (Exception $e) {
            $this->utilities->dataLog("Error refreshing all trends: " . $e->getMessage(), $this->logTag);
            return false;
        }
    }

    /**
     * Retrieve trend data for a specific point within a date range
     *
     * @param string $pointId The identifier for the trend point
     * @param DateTime $startDate Start date for trend data
     * @param DateTime $endDate End date for trend data
     * @return array The trend data points
     */
    public function getTrendData(string $pointId, DateTime $startDate, DateTime $endDate): array
    {
        $this->utilities->dataLog("Retrieving trend data for point: {$pointId}", $this->logTag);

        $sanitizedPointId = $this->utilities->sanitizeStringInput($pointId);
        $startDateFormatted = $startDate->format('Y-m-d H:i:s');
        $endDateFormatted = $endDate->format('Y-m-d H:i:s');

        try {
            $query = "SELECT timestamp, value 
                     FROM trend_data 
                     WHERE point_id = :pointId 
                     AND timestamp BETWEEN :startDate AND :endDate 
                     ORDER BY timestamp ASC";

            $stmt = $this->webCtrlMainConnection->prepare($query);
            $stmt->bindParam(':pointId', $sanitizedPointId, PDO::PARAM_STR);
            $stmt->bindParam(':startDate', $startDateFormatted, PDO::PARAM_STR);
            $stmt->bindParam(':endDate', $endDateFormatted, PDO::PARAM_STR);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->utilities->dataLog("Retrieved " . count($results) . " trend data points", $this->logTag);
            return $results;
        } catch (Exception $e) {
            $this->utilities->dataLog("Error retrieving trend data: " . $e->getMessage(), $this->logTag);
            return [];
        }
    }

    /**
     * Calculate statistics for trend data
     *
     * @param array $trendData Array of trend data points
     * @return array Statistics including min, max, avg, etc.
     */
    public function calculateTrendStatistics(array $trendData): array
    {
        if (empty($trendData)) {
            return [
                'min' => null,
                'max' => null,
                'avg' => null,
                'count' => 0,
                'stdDev' => null
            ];
        }

        $values = array_column($trendData, 'value');
        $numericValues = array_filter($values, 'is_numeric');

        if (empty($numericValues)) {
            return [
                'min' => null,
                'max' => null,
                'avg' => null,
                'count' => count($trendData),
                'stdDev' => null
            ];
        }

        $min = min($numericValues);
        $max = max($numericValues);
        $avg = array_sum($numericValues) / count($numericValues);
        $count = count($trendData);

        // Calculate standard deviation
        $variance = 0;
        foreach ($numericValues as $value) {
            $variance += pow(($value - $avg), 2);
        }
        $stdDev = sqrt($variance / count($numericValues));

        return [
            'min' => $min,
            'max' => $max,
            'avg' => $avg,
            'count' => $count,
            'stdDev' => $stdDev
        ];
    }

    /**
     * Save trend data to the database
     *
     * @param string $pointId The identifier for the trend point
     * @param float $value The value to save
     * @param DateTime|null $timestamp The timestamp (defaults to current time)
     * @return bool Success status
     */
    public function saveTrendData(string $pointId, float $value, ?DateTime $timestamp = null): bool
    {
        $sanitizedPointId = $this->utilities->sanitizeStringInput($pointId);
        $sanitizedValue = $this->utilities->sanitizeFloatInput($value);
        $timestampFormatted = ($timestamp ?? new DateTime())->format('Y-m-d H:i:s');

        try {
            $query = "INSERT INTO trend_data (point_id, value, timestamp) 
                     VALUES (:pointId, :value, :timestamp)";

            $stmt = $this->webCtrlMainConnection->prepare($query);
            $stmt->bindParam(':pointId', $sanitizedPointId, PDO::PARAM_STR);
            $stmt->bindParam(':value', $sanitizedValue, PDO::PARAM_STR);
            $stmt->bindParam(':timestamp', $timestampFormatted, PDO::PARAM_STR);

            $result = $stmt->execute();

            if ($result) {
                $this->utilities->dataLog("Successfully saved trend data for point: {$pointId}", $this->logTag);
            } else {
                $this->utilities->dataLog("Failed to save trend data for point: {$pointId}", $this->logTag);
            }

            return $result;
        } catch (Exception $e) {
            $this->utilities->dataLog("Error saving trend data: " . $e->getMessage(), $this->logTag);
            return false;
        }
    }

    /**
     * Get available trend points
     *
     * @param string|null $filter Optional filter string
     * @return array List of available trend points
     */
    public function getAvailableTrendPoints(?string $filter = null): array
    {
        $sanitizedFilter = $filter ? $this->utilities->sanitizeStringInput($filter) : null;

        try {
            $query = "SELECT point_id, description, units 
                     FROM trend_points";

            $params = [];

            if ($sanitizedFilter) {
                $query .= " WHERE point_id LIKE :filter OR description LIKE :filter";
                $filterParam = "%{$sanitizedFilter}%";
                $params[':filter'] = $filterParam;
            }

            $stmt = $this->webCtrlMainConnection->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->utilities->dataLog("Retrieved " . count($results) . " trend points", $this->logTag);
            return $results;
        } catch (Exception $e) {
            $this->utilities->dataLog("Error retrieving trend points: " . $e->getMessage(), $this->logTag);
            return [];
        }
    }
}
