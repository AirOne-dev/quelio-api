<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use IconController;

class IconControllerTest extends TestCase
{
    private IconController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new IconController();
    }

    public function test_generates_svg_icon(): void
    {
        $_GET['primary'] = '0EA5E9';
        $_GET['secondary'] = '38BDF8';

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        $this->assertStringContainsString('<?xml', $output);
        $this->assertStringContainsString('<svg', $output);
        $this->assertStringContainsString('#0EA5E9', $output);
        $this->assertStringContainsString('#38BDF8', $output);
    }

    public function test_uses_default_colors_when_not_provided(): void
    {
        $_GET = [];

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        // Default ocean theme colors
        $this->assertStringContainsString('#0EA5E9', $output);
        $this->assertStringContainsString('#38BDF8', $output);
    }

    public function test_validates_hex_colors(): void
    {
        $_GET['primary'] = 'invalid';
        $_GET['secondary'] = 'GGGGGG';

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        // Should fall back to defaults
        $this->assertStringContainsString('#0EA5E9', $output);
        $this->assertStringContainsString('#38BDF8', $output);
    }

    public function test_strips_hash_prefix_from_colors(): void
    {
        $_GET['primary'] = '#FF0000';
        $_GET['secondary'] = '#00FF00';

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        $this->assertStringContainsString('#FF0000', $output);
        $this->assertStringContainsString('#00FF00', $output);
    }

    public function test_svg_contains_gradient(): void
    {
        $_GET = [];

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        $this->assertStringContainsString('linearGradient', $output);
        $this->assertStringContainsString('id="iconGradient"', $output);
    }

    public function test_svg_contains_clock_icon(): void
    {
        $_GET = [];

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        $this->assertStringContainsString('<circle', $output);
        $this->assertStringContainsString('<line', $output);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }
}
