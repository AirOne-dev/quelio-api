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
        $this->assertStringContainsString('id="paint0_linear_126_88"', $output);
    }

    public function test_svg_contains_clock_icon_design(): void
    {
        $_GET = [];

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        // New icon design contains circle and path elements
        $this->assertStringContainsString('<circle', $output);
        $this->assertStringContainsString('<path', $output);
        $this->assertStringContainsString('<mask', $output);
    }

    public function test_svg_has_correct_dimensions(): void
    {
        $_GET = [];

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        // Check SVG dimensions
        $this->assertStringContainsString('width="581"', $output);
        $this->assertStringContainsString('height="580"', $output);
        $this->assertStringContainsString('viewBox="0 0 581 580"', $output);
    }

    public function test_svg_has_filters_and_effects(): void
    {
        $_GET = [];

        $output = $this->captureOutput(function () {
            $this->controller->indexAction();
        });

        // Check for drop shadow filters
        $this->assertStringContainsString('<filter', $output);
        $this->assertStringContainsString('feGaussianBlur', $output);
        $this->assertStringContainsString('feBlend', $output);
        $this->assertStringContainsString('effect1_dropShadow_126_88', $output, 'Should contain drop shadow effect');
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }
}
