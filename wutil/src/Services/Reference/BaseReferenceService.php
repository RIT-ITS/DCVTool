<?php

declare(strict_types=1);

namespace App\Services\Reference;

use App\Database\DatabaseManager;
use PDO;
use PDOException;

/**
 * Base abstract class for reference table services
 */
abstract class BaseReferenceService
{
    public function __construct(
        protected DatabaseManager $dbManager
    ) {
    }

    /**
     * Get a database connection
     */
    protected function getConnection(): PDO
    {
        return $this->dbManager->getDefaultConnection();
    }

    /**
     * Format response in standard format or return raw data
     */
    protected function formatResponse(array $result, int $count, bool $isRaw = false, ?string $startDate = '', ?string $endDate = ''): array
    {
        if ($isRaw) {
            return $result;
        }
        $startDt = ($startDate !== '') ? $startDate : '';
        $endDt = ($endDate !== '') ? $endDate : '';
        if ($startDt !== '' && $endDt !== '') {
            return [
                "responseHeader" => [
                    "status" => 0
                ],
                "response" => [
                    "numFound" => $count,
                    "startdt"=>$startDate,
                    "enddt"=>$endDate,
                    "start" => 0,
                    "docs" => [
                        $result
                    ]
                ]
            ];
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

    }
}