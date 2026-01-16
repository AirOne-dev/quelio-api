<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use ManifestController;

class ManifestControllerTest extends TestCase
{
    private ManifestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ManifestController();

        // Mock server variables
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    }

    public function test_generates_valid_manifest(): void
    {
        $_GET = [];

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        $manifest = json_decode($output, true);

        $this->assertIsArray($manifest);
        $this->assertArrayHasKeys(['name', 'short_name', 'description', 'start_url', 'display'], $manifest);
    }

    public function test_uses_custom_colors(): void
    {
        $_GET['primary'] = 'FF0000';
        $_GET['secondary'] = '00FF00';
        $_GET['background'] = '0000FF';

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        $manifest = json_decode($output, true);

        $this->assertEquals('#0000FF', $manifest['background_color']);
        $this->assertEquals('#0000FF', $manifest['theme_color']);
    }

    public function test_validates_color_format(): void
    {
        $_GET['primary'] = 'INVALID';
        $_GET['secondary'] = 'GGGGGG';

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        $manifest = json_decode($output, true);

        // Should use defaults
        $this->assertArrayHasKey('background_color', $manifest);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $manifest['background_color']);
    }

    public function test_includes_icon_urls(): void
    {
        $_GET = [];

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        $manifest = json_decode($output, true);

        $this->assertArrayHasKey('icons', $manifest);
        $this->assertIsArray($manifest['icons']);
        $this->assertGreaterThan(0, count($manifest['icons']));

        foreach ($manifest['icons'] as $icon) {
            $this->assertArrayHasKeys(['src', 'sizes', 'type'], $icon);
        }
    }

    public function test_sets_correct_display_mode(): void
    {
        $_GET = [];

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        $manifest = json_decode($output, true);

        $this->assertEquals('standalone', $manifest['display']);
    }

    public function test_sets_portrait_orientation(): void
    {
        $_GET = [];

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        $manifest = json_decode($output, true);

        $this->assertEquals('portrait', $manifest['orientation']);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_SERVER = [];
        parent::tearDown();
    }
}
