<?php

declare(strict_types=1);

namespace App\Services\Response;

/**
 * Service for handling JSON responses
 */
class JsonResponseService
{
    /**
     * Send a JSON response with appropriate headers
     */
    public function send(array $data, int $statusCode = 200): void
    {
        // Set appropriate headers
        header('Content-Type: application/json');
        http_response_code($statusCode);

        // Encode and output the JSON data
        echo json_encode($data);
        exit;
    }

    /**
     * Send a success response
     */
    public function success(array $data, string $message = 'Success', int $statusCode = 200): void
    {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ];

        $this->send($response, $statusCode);
    }

    /**
     * Send an error response
     */
    public function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        $response = [
            'status' => 'error',
            'message' => $message
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        $this->send($response, $statusCode);
    }

    /**
     * Send a not found response
     */
    public function notFound(string $message = 'Resource not found'): void
    {
        $this->error($message, 404);
    }

    /**
     * Send an unauthorized response
     */
    public function unauthorized(string $message = 'Unauthorized'): void
    {
        $this->error($message, 401);
    }
}
