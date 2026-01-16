<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case with helper methods
 */
abstract class TestCase extends BaseTestCase
{
    protected array $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = TEST_CONFIG;
    }

    /**
     * Get test config
     */
    protected function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Create a temporary file with content
     */
    protected function createTempFile(string $content): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, $content);
        return $tmpFile;
    }

    /**
     * Clean up temporary files
     */
    protected function cleanupTempFiles(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Mock a class method
     */
    protected function mockMethod(string $className, string $methodName, $returnValue)
    {
        $mock = $this->createMock($className);
        $mock->method($methodName)->willReturn($returnValue);
        return $mock;
    }

    /**
     * Assert array has keys
     */
    protected function assertArrayHasKeys(array $keys, array $array, string $message = ''): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message);
        }
    }

    /**
     * Capture output from a callable
     */
    protected function captureOutput(callable $fn): string
    {
        ob_start();
        $fn();
        return ob_get_clean();
    }
}
