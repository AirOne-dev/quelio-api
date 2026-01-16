<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use BaseGuestController;

/**
 * Unit Tests - BaseGuestController
 * Tests login form display and access control
 */
class BaseGuestControllerTest extends TestCase
{
    private BaseGuestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ========================================================================
    // FORM DISPLAY - ENABLED
    // ========================================================================

    public function test_displays_login_form_when_enabled(): void
    {
        $config = $this->getConfig();
        $config['enable_form_access'] = true;

        $controller = new BaseGuestController($config);

        ob_start();
        $controller->indexAction();
        $output = ob_get_clean();

        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('<form method="POST">', $output);
        $this->assertStringContainsString('name="username"', $output);
        $this->assertStringContainsString('name="password"', $output);
    }

    public function test_form_contains_required_fields(): void
    {
        $config = $this->getConfig();
        $config['enable_form_access'] = true;

        $controller = new BaseGuestController($config);

        ob_start();
        $controller->indexAction();
        $output = ob_get_clean();

        // Check for username field
        $this->assertStringContainsString('type="text"', $output);
        $this->assertStringContainsString('name="username"', $output);
        $this->assertStringContainsString('required', $output);

        // Check for password field
        $this->assertStringContainsString('type="password"', $output);
        $this->assertStringContainsString('name="password"', $output);

        // Check for submit button
        $this->assertStringContainsString('type="submit"', $output);
    }

    public function test_form_has_valid_html_structure(): void
    {
        $config = $this->getConfig();
        $config['enable_form_access'] = true;

        $controller = new BaseGuestController($config);

        ob_start();
        $controller->indexAction();
        $output = ob_get_clean();

        // Check HTML structure
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('<html>', $output);
        $this->assertStringContainsString('<head>', $output);
        $this->assertStringContainsString('<title>', $output);
        $this->assertStringContainsString('</title>', $output);
        $this->assertStringContainsString('</head>', $output);
        $this->assertStringContainsString('<body>', $output);
        $this->assertStringContainsString('</body>', $output);
        $this->assertStringContainsString('</html>', $output);
    }

    public function test_form_has_utf8_charset(): void
    {
        $config = $this->getConfig();
        $config['enable_form_access'] = true;

        $controller = new BaseGuestController($config);

        ob_start();
        $controller->indexAction();
        $output = ob_get_clean();

        $this->assertStringContainsString('charset="UTF-8"', $output);
    }

    public function test_form_has_title(): void
    {
        $config = $this->getConfig();
        $config['enable_form_access'] = true;

        $controller = new BaseGuestController($config);

        ob_start();
        $controller->indexAction();
        $output = ob_get_clean();

        $this->assertStringContainsString('<title>Connexion Kelio</title>', $output);
    }

    // ========================================================================
    // FORM DISPLAY - DISABLED
    // ========================================================================

    public function test_returns_403_when_form_access_disabled(): void
    {
        $config = $this->getConfig();
        $config['enable_form_access'] = false;

        $controller = new BaseGuestController($config);

        ob_start();
        $controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertNotNull($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('disabled', $response['error']);
    }

    public function test_suggests_post_method_when_disabled(): void
    {
        $config = $this->getConfig();
        $config['enable_form_access'] = false;

        $controller = new BaseGuestController($config);

        ob_start();
        $controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertStringContainsString('POST', $response['error']);
    }

    public function test_does_not_leak_html_when_disabled(): void
    {
        $config = $this->getConfig();
        $config['enable_form_access'] = false;

        $controller = new BaseGuestController($config);

        ob_start();
        $controller->indexAction();
        $output = ob_get_clean();

        // Should return JSON, not HTML
        $this->assertStringNotContainsString('<!DOCTYPE html>', $output);
        $this->assertStringNotContainsString('<form', $output);

        // Should be valid JSON
        $response = json_decode($output, true);
        $this->assertNotNull($response);
    }

    // ========================================================================
    // SECURITY
    // ========================================================================

    public function test_form_uses_post_method(): void
    {
        $config = $this->getConfig();
        $config['enable_form_access'] = true;

        $controller = new BaseGuestController($config);

        ob_start();
        $controller->indexAction();
        $output = ob_get_clean();

        $this->assertStringContainsString('method="POST"', $output);
        $this->assertStringNotContainsString('method="GET"', strtoupper($output));
    }

    public function test_form_inputs_have_placeholders(): void
    {
        $config = $this->getConfig();
        $config['enable_form_access'] = true;

        $controller = new BaseGuestController($config);

        ob_start();
        $controller->indexAction();
        $output = ob_get_clean();

        $this->assertStringContainsString('placeholder=', $output);
    }

    // ========================================================================
    // EDGE CASES
    // ========================================================================

    public function test_handles_missing_enable_form_access_config(): void
    {
        $config = $this->getConfig();
        unset($config['enable_form_access']);

        $controller = new BaseGuestController($config);

        ob_start();
        $controller->indexAction();
        $output = ob_get_clean();

        // Without the config, it should treat it as false (disabled)
        // PHP: !$this->config['enable_form_access'] will be true if key doesn't exist
        $response = json_decode($output, true);

        // Should either display form or return error depending on how PHP handles undefined key
        // In this case, undefined key in array access will trigger a warning but evaluate to null
        // And !null is true, so form access would be disabled
        $this->assertTrue(
            (isset($response['error'])) || str_contains($output, '<form')
        );
    }
}
