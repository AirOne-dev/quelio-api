<?php

class JsonResponse
{
    /**
     * Add security headers to the response
     */
    private static function addSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    /**
     * Send a JSON success response
     * @param array $data Response data
     * @param int $statusCode HTTP status code (default: 200)
     */
    public static function success(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        self::addSecurityHeaders();
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Send a JSON error response
     * @param string $message Error message
     * @param int $statusCode HTTP status code (default: 400)
     * @param array $additionalData Additional error data
     */
    public static function error(string $message, int $statusCode = 400, array $additionalData = []): void
    {
        http_response_code($statusCode);
        self::addSecurityHeaders();
        header('Content-Type: application/json');

        $errorData = array_merge(['error' => $message], $additionalData);

        echo json_encode($errorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Send a validation error response
     * @param string $message Error message
     * @param array $fields Field-specific errors
     */
    public static function validationError(string $message, array $fields = []): void
    {
        $data = ['error' => $message];
        if (!empty($fields)) {
            $data['fields'] = $fields;
        }

        self::error($message, 422, empty($fields) ? [] : ['fields' => $fields]);
    }

    /**
     * Send an unauthorized response
     * @param string $message Error message
     * @param array $additionalData Additional error data
     */
    public static function unauthorized(string $message = 'Unauthorized', array $additionalData = []): void
    {
        self::error($message, 401, $additionalData);
    }

    /**
     * Send a not found response
     * @param string $message Error message
     */
    public static function notFound(string $message = 'Not found'): void
    {
        self::error($message, 404);
    }

    /**
     * Send an internal server error response
     * @param string $message Error message
     * @param \Throwable|null $exception Optional exception for debugging
     */
    public static function serverError(string $message = 'Internal server error', ?\Throwable $exception = null): void
    {
        $data = [];

        if ($exception !== null) {
            $data['message'] = $exception->getMessage();
            $data['file'] = $exception->getFile();
            $data['line'] = $exception->getLine();

            // Add trace in development mode
            if (ini_get('display_errors') == '1') {
                $data['trace'] = $exception->getTraceAsString();
            }
        }

        self::error($message, 500, $data);
    }
}
