<?php

declare(strict_types=1);

namespace App\Controllers\Http;

use App\Services\Reference\Academic\AcademicScheduleService;
use App\Services\Reference\Academic\SemesterService;
use App\Services\Reference\Academic\ClassService;
use App\Services\Response\JsonResponseService;
use App\Services\Security\TokenValidator;
use App\Services\Transfer\TransferService;
use App\Utilities\Utilities;
use App\Utilities\UrlService;
use App\Services\Logging\LogService;

/**
 * Controller for handling scheduled synchronization jobs from Kubernetes CronJobs
 */
class CronJobController
{
    private Utilities $utilities;
    private TokenValidator $tokenValidator;
    private SemesterService $semesterService;
    private ClassService $classService;
    private AcademicScheduleService $academicScheduleService;
    private TransferService $transferService;
    private JsonResponseService $responseService;
    private UrlService $urlService;
    private LogService $logService;

    /**
     * Constructor with dependency injection
     */
    public function __construct(
        Utilities $utilities,
        TokenValidator $tokenValidator,
        SemesterService $semesterService,
        AcademicScheduleService $academicScheduleService,
        ClassService $classService,
        TransferService $transferService,
        JsonResponseService $responseService,
        UrlService $urlService,
        LogService $logService
    ) {
        $this->utilities = $utilities;
        $this->tokenValidator = $tokenValidator;
        $this->semesterService = $semesterService;
        $this->academicScheduleService = $academicScheduleService;
        $this->classService = $classService;
        $this->transferService = $transferService;
        $this->responseService = $responseService;
        $this->urlService = $urlService;
        $this->logService = $logService;
    }

    /**
     * Handle incoming synchronization requests
     */
    public function handleRequest(array $request): void
    {
        $tag = 'CronjobRequest';
        $this->logService->dataLog("Checking incoming request for token.", $tag);

        // Get current route information for logging
        $routeInfo = $this->urlService->getCurrentRoute();
        $this->logService->dataLog("Request to path: {$routeInfo['path']}", $tag);

        // Validate request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logService->dataLog("Invalid request method: {$_SERVER['REQUEST_METHOD']}", $tag);
            $this->responseService->error('Invalid request method', 405);
        }

        // Extract and sanitize inputs
        $token = $this->utilities->sanitizeStringInput($request['token'] ?? '');
        $incomingStrm = $this->utilities->sanitizeIntegerInput($request['strm'] ?? '');
        $incomingMin = $this->utilities->sanitizeIntegerInput($request['min'] ?? '');

        // Validate token
        if (empty($token)) {
            $this->logService->dataLog("Missing token", $tag);
            $this->responseService->error('Missing token', 400);
        }

        $this->logService->dataLog("Token received. Verifying.", $tag);
        $tokenSubstr = substr($token, -1);

        // Determine semester if not provided
        $strm = $incomingStrm > 0 ? $incomingStrm : $this->semesterService->determineSemester();
        $minReq = $incomingMin > 0 ? $incomingMin : null;

        // Log the request origin
        $baseUrl = $this->urlService->getBaseUrl();
        $this->logService->dataLog("Request origin: {$baseUrl}", $tag);

        // Dispatch to appropriate handler based on token
        try {
            if ($this->tokenValidator->validate($token, 'SISCLASSSYNC')) {
                $this->handleSisClassSync($strm, $tokenSubstr);
                return;
            } else if ($this->tokenValidator->validate($token, 'SISCLASSTOEXPANDED')) {
                $this->handleSisClassExpanded($strm, $tokenSubstr);
                return;
            } else if ($this->tokenValidator->validate($token, 'SISEXAMSYNC')) {
                $this->handleSisExamSync($strm, $tokenSubstr);
                return;
            } else if ($this->tokenValidator->validate($token, 'SETPOINTSYNC')) {
                $this->handleSetpointSync($strm, $tokenSubstr);
                return;
            }

            // No matching token found
            $this->logService->dataLog("Invalid token ending in {$tokenSubstr}", $tag);
            $this->responseService->unauthorized('Invalid token');

        } catch (\Exception $e) {
            $this->logService->dataLog("Error processing request: {$e->getMessage()}", $tag);
            $this->responseService->error('Internal server error', 500);
        }
    }

    /**
     * Handle SIS Class Sync operation
     */
    private function handleSisClassSync(int $strm, string $tokenSubstr): void
    {
        $tag = 'CronjobRequest_SISClassSync';
        $this->logService->dataLog("Token ending in {$tokenSubstr} verified. Running SIS Class Data Sync.", $tag);

        try {
            $classRetrieval = $this->academicScheduleService->addUpdateClasses($strm);

            if ($classRetrieval) {
                $this->logService->dataLog("Class Data retrieval from SIS successful.", $tag);

                // Build a status URL that could be used for monitoring
                $statusUrl = $this->urlService->buildUrl('/api/status/class-sync', ['strm' => $strm]);
                // TODO: This status URL is for future implementation and doesn't exist yet
                $this->responseService->success([
                    'code' => 1//,
                    //'statusUrl' => $statusUrl
                ], 'Class data sync completed successfully');
            } else {
                $this->logService->dataLog("Class Data retrieval from SIS unsuccessful. See log for details.", $tag);
                $this->responseService->error('Class data sync failed', 500, ['code' => 1]);
            }
        } catch (\Exception $e) {
            $this->logService->dataLog("Exception during class sync: {$e->getMessage()}", $tag);
            $this->responseService->error($e->getMessage(), 500, ['code' => 1]);
        }
    }

    /**
     * Handle SIS Class Expanded operation
     */
    private function handleSisClassExpanded(int $strm, string $tokenSubstr): void
    {
        $tag = 'CronjobRequest_ClassToExpanded';
        $this->logService->dataLog("Token ending in {$tokenSubstr} verified. Initiating Transfer of Class Data to Expanded Table.", $tag);

        try {
            // TODO: Make building ID (56) dynamic or configurable
            $buildingId = 56; // This should be made configurable
            $classTransfer = $this->transferService->convertSemesterScheduleToDaily($buildingId, $strm);

            if ($classTransfer) {
                $this->logService->dataLog("Class Data transfer to expanded successful.", $tag);

                // Build a status URL that could be used for monitoring
                $statusUrl = $this->urlService->buildUrl('/api/status/expanded-data', [
                    'strm' => $strm,
                    'buildingId' => $buildingId
                ]);
                // TODO: This status URL is for future implementation and doesn't exist yet
                $this->responseService->success([
                    'code' => 4//,
                    //'statusUrl' => $statusUrl
                ], 'Class data transfer to expanded table completed successfully');
            } else {
                $this->logService->dataLog("Class Data to expanded unsuccessful. See log for details.", $tag);
                $this->responseService->error('Class data transfer to expanded table failed', 500, ['code' => 4]);
            }
        } catch (\Exception $e) {
            $this->logService->dataLog("Exception during class expansion: {$e->getMessage()}", $tag);
            $this->responseService->error($e->getMessage(), 500, ['code' => 4]);
        }
    }

    /**
     * Handle SIS Exam Sync operation
     */
    private function handleSisExamSync(int $strm, string $tokenSubstr): void
    {
        $tag = 'CronjobRequest_SISExamSync';
        $this->logService->dataLog("Token ending in {$tokenSubstr} verified. Running SIS Exam Data Sync.", $tag);

        try {
            // TODO: Make building ID (56) dynamic or configurable
            $buildingId = 56; // This should be made configurable
            $timezone = "America/New_York"; // This should be made configurable

            // Step 1: Sync exam data from SIS
            $examRetrieval = $this->academicScheduleService->addUpdateExams($buildingId, $timezone, $strm);

            if ($examRetrieval) {
                $this->logService->dataLog("Exam Data retrieval from SIS successful.", $tag);

                // Step 2: Transfer exam data to expanded table
                $this->logService->dataLog("Initiating Transfer of Exam Data to Expanded Table.", $tag);
                $examTransfer = $this->transferService->convertExamScheduleToExpanded($buildingId, $strm);

                if ($examTransfer) {
                    $this->logService->dataLog("Exam Data transfer to expanded successful.", $tag);

                    // Build a status URL that could be used for monitoring
                    $statusUrl = $this->urlService->buildUrl('/api/status/exam-sync', [
                        'strm' => $strm,
                        'buildingId' => $buildingId
                    ]);

                    // TODO: This status URL is for future implementation and doesn't exist yet
                    $this->responseService->success([
                        'code' => 2//,
                        //'statusUrl' => $statusUrl
                    ], 'Exam data sync and transfer completed successfully');
                } else {
                    $this->logService->dataLog("Exam Data transfer to expanded unsuccessful. See log for details.", $tag);
                    $this->responseService->error('Exam data transfer to expanded table failed', 500, ['code' => 2]);
                }
            } else {
                $this->logService->dataLog("Exam Data retrieval from SIS unsuccessful. See log for details.", $tag);
                $this->responseService->error('Exam data sync failed', 500, ['code' => 2]);
            }
        } catch (\Exception $e) {
            $this->logService->dataLog("Exception during exam sync: {$e->getMessage()}", $tag);
            $this->responseService->error($e->getMessage(), 500, ['code' => 2]);
        }
    }

    /**
     * Handle Setpoint Sync operation
     */
    private function handleSetpointSync(int $strm, string $tokenSubstr): void
    {
        $tag = 'CronjobRequest_SetpointSync';
        $this->logService->dataLog("Token ending in {$tokenSubstr} verified. Running Transfer of Data to Setpoint.", $tag);

        try {
            // TODO: Make building ID (56) and mode (2) dynamic or configurable
            $buildingId = 56; // This should be made configurable
            $mode = 2; // This should be made configurable or explained what it means

            $setpointTransfer = $this->transferService->transferToSetpoint($buildingId, $mode);

            if ($setpointTransfer) {
                $this->logService->dataLog("Data transfer to setpoint successful.", $tag);

                // Build a status URL that could be used for monitoring
                $statusUrl = $this->urlService->buildUrl('/api/status/setpoint-sync', [
                    'buildingId' => $buildingId,
                    'mode' => $mode
                ]);

                // TODO: This status URL is for future implementation and doesn't exist yet
                $this->responseService->success([
                    'code' => 3//,
                    //'statusUrl' => $statusUrl
                ], 'Data transfer to setpoint completed successfully');
            } else {
                $this->logService->dataLog("Data transfer to setpoint unsuccessful. See log for details.", $tag);
                $this->responseService->error('Data transfer to setpoint failed', 500, ['code' => 3]);
            }
        } catch (\Exception $e) {
            $this->logService->dataLog("Exception during setpoint sync: {$e->getMessage()}", $tag);
            $this->responseService->error($e->getMessage(), 500, ['code' => 3]);
        }
    }


}
