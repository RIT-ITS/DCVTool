<?php

declare(strict_types=1);

namespace App\Utilities;

/**
 * Service for handling URL operations
 */
class UrlService
{
    /**
     * Parse the current URL to extract segments and path variables
     */
    public function parseCurrentUrl(): array
    {
        $currentURL = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $currentURL .= 's';
        }
        $currentURL .= '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $urlSegments = explode('/', $currentURL);
        $secondSegment = $urlSegments[count($urlSegments) - 2];
        $lastSegment = $urlSegments[count($urlSegments) - 1];
        $res = explode('.json', $lastSegment);
        $pathVar = $res[0];

        return [
            'fullUrl' => $currentURL,
            'segments' => $urlSegments,
            'secondSegment' => $secondSegment,
            'lastSegment' => $lastSegment,
            'pathVar' => $pathVar
        ];
    }

    /**
     * Get the base URL of the application
     */
    public function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return "{$protocol}://{$host}";
    }

    /**
     * Build a URL with query parameters
     */
    public function buildUrl(string $path, array $params = []): string
    {
        $url = $this->getBaseUrl() . $path;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Get current route information
     */
    public function getCurrentRoute(): array
    {
        $requestUri = $_SERVER['REQUEST_URI'];
        $path = parse_url($requestUri, PHP_URL_PATH);
        $query = parse_url($requestUri, PHP_URL_QUERY);

        parse_str($query ?? '', $queryParams);

        return [
            'path' => $path,
            'queryParams' => $queryParams
        ];
    }

    /**
     * Validate if a request is coming from an allowed origin
     */
    public function validateOrigin(array $allowedOrigins = []): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

        // If no origin header or no allowed origins specified, default behavior
        if (!$origin || empty($allowedOrigins)) {
            return true;
        }

        return in_array($origin, $allowedOrigins);
    }
}
