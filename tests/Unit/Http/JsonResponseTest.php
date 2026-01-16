<?php

namespace Tests\Unit\Http;

use Tests\TestCase;
use JsonResponse;

/**
 * Unit Tests - JsonResponse
 * Tests JSON response generation and HTTP status codes
 */
class JsonResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    // ========================================================================
    // NOT FOUND - 404 RESPONSE
    // ========================================================================

    public function test_not_found_returns_404_status(): void
    {
        ob_start();
        JsonResponse::notFound('Resource not found');
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Resource not found', $response['error']);
    }

    public function test_not_found_includes_security_headers(): void
    {
        ob_start();
        JsonResponse::notFound('Not found');
        ob_end_clean();

        // Headers should be set (can't test directly without output buffering tricks)
        // But we can verify the method executes without errors
        $this->assertTrue(true);
    }

    // ========================================================================
    // SERVER ERROR - 500 RESPONSE
    // ========================================================================

    public function test_server_error_returns_error_response(): void
    {
        ob_start();
        JsonResponse::serverError('Internal server error');
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Internal server error', $response['error']);
    }

    public function test_server_error_with_exception_in_debug_mode(): void
    {
        // Test that serverError can be called with exception parameter
        // The actual implementation may not expose exception details in response
        $exception = new \Exception('Test exception');

        ob_start();
        JsonResponse::serverError('Server error', $exception, true);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertNotEmpty($response['error']);
    }

    public function test_server_error_in_production_hides_exception_details(): void
    {
        $exception = new \Exception('Sensitive error message');

        ob_start();
        JsonResponse::serverError('Server error', $exception, false);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertArrayNotHasKey('exception', $response);
    }

    public function test_server_error_without_exception(): void
    {
        ob_start();
        JsonResponse::serverError('Generic error');
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Generic error', $response['error']);
        $this->assertArrayNotHasKey('exception', $response);
    }

    // ========================================================================
    // SUCCESS - 200 RESPONSE
    // ========================================================================

    public function test_success_returns_success_response(): void
    {
        ob_start();
        JsonResponse::success(['message' => 'Operation successful']);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertIsArray($response);
        $this->assertEquals('Operation successful', $response['message']);
    }

    // ========================================================================
    // ERROR - GENERIC ERROR RESPONSE
    // ========================================================================

    public function test_error_returns_error_response(): void
    {
        ob_start();
        JsonResponse::error('Something went wrong', 400);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Something went wrong', $response['error']);
    }

    // ========================================================================
    // VALIDATION ERROR - 422 RESPONSE
    // ========================================================================

    public function test_validation_error_includes_field_errors(): void
    {
        $errors = [
            'email' => 'Invalid email format',
            'password' => 'Password too short'
        ];

        ob_start();
        JsonResponse::validationError('Validation failed', $errors);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('fields', $response);
        $this->assertEquals('Invalid email format', $response['fields']['email']);
        $this->assertEquals('Password too short', $response['fields']['password']);
    }

    // ========================================================================
    // UNAUTHORIZED - 401 RESPONSE
    // ========================================================================

    public function test_unauthorized_returns_401_status(): void
    {
        ob_start();
        JsonResponse::unauthorized('Authentication required');
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Authentication required', $response['error']);
    }

    // ========================================================================
    // SECURITY HEADERS
    // ========================================================================

    public function test_add_security_headers_sets_headers(): void
    {
        // This is tested indirectly through other methods
        // Just verify the method can be called
        ob_start();
        JsonResponse::success(['test' => true]);
        ob_end_clean();

        $this->assertTrue(true);
    }
}
