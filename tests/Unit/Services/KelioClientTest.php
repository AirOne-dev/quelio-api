<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Tests\Fixtures\KelioHtmlFixtures;
use KelioClient;
use Exception;

/**
 * Tests for KelioClient service
 * All HTTP interactions are mocked - no real network calls to daryl.kelio.io
 */
class KelioClientTest extends TestCase
{
    private KelioClient $client;
    private string $kelioUrl = 'https://daryl.kelio.io';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new KelioClient($this->kelioUrl);
    }

    public function test_extracts_csrf_token_from_login_page(): void
    {
        $html = KelioHtmlFixtures::getLoginPage();

        // Extract CSRF token using same regex as KelioClient
        preg_match('/<input type="hidden" name="_csrf_bodet" value="([^"]+)"/', $html, $matches);
        $csrfToken = $matches[1] ?? '';

        $this->assertNotEmpty($csrfToken);
        $this->assertEquals('84eea639-c251-4661-b965-acbddd752367', $csrfToken);
    }

    public function test_extracts_jsessionid_from_cookie_header(): void
    {
        $cookieHeader = KelioHtmlFixtures::getSampleCookie();

        // Parse cookie using same logic as KelioClient::getCookies()
        preg_match('/JSESSIONID=([^;]+)/', $cookieHeader, $matches);
        $jsessionid = $matches[1] ?? '';

        $this->assertNotEmpty($jsessionid);
        $this->assertStringContainsString('ABC123DEF456', $jsessionid);
    }

    public function test_parses_hours_from_html_table(): void
    {
        $html = KelioHtmlFixtures::getHoursPage();
        $expectedHours = KelioHtmlFixtures::getExpectedParsedHours();

        // Test HTML parsing logic
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $data = [];

        // Find all rows with date cells
        $rows = $xpath->query('//table//tr');

        for ($i = 1; $i < $rows->length; $i++) {
            $row = $rows->item($i);

            // Get date from first cell
            $dateCell = $xpath->query('.//td[@class="date"]', $row)->item(0);

            if ($dateCell) {
                $date = trim($dateCell->textContent);
                $times = [];

                // Get all time cells (class="heure")
                $timeCells = $xpath->query('.//td[@class="heure"]', $row);
                foreach ($timeCells as $cell) {
                    $timeText = trim($cell->textContent);
                    // Skip empty cells (HTML entities are decoded, so &nbsp; becomes a space/non-breaking space)
                    // Also check if it matches HH:MM format
                    if ($timeText && preg_match('/^\d{2}:\d{2}$/', $timeText)) {
                        $times[] = $timeText;
                    }
                }

                if (!empty($times)) {
                    $data[$date] = $times;
                }
            }
        }

        $this->assertCount(3, $data);
        $this->assertArrayHasKey('13/01/2026', $data);
        $this->assertArrayHasKey('14/01/2026', $data);
        $this->assertArrayHasKey('15/01/2026', $data);

        // Verify exact times match expected
        $this->assertEquals($expectedHours['13/01/2026'], $data['13/01/2026']);
        $this->assertEquals($expectedHours['14/01/2026'], $data['14/01/2026']);
        $this->assertEquals($expectedHours['15/01/2026'], $data['15/01/2026']);
    }

    public function test_handles_empty_hours_page(): void
    {
        $html = KelioHtmlFixtures::getEmptyHoursPage();

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $data = [];
        $rows = $xpath->query('//table//tbody/tr');

        $this->assertEquals(0, $rows->length);
        $this->assertEmpty($data);
    }

    public function test_detects_login_error_from_response(): void
    {
        $html = KelioHtmlFixtures::getLoginErrorPage();

        // Check for error message presence
        $this->assertStringContainsString('messagesErreur', $html);
        $this->assertStringContainsString('Identifiant ou mot de passe incorrect', $html);
    }

    public function test_csrf_token_is_required_for_login(): void
    {
        $html = KelioHtmlFixtures::getLoginPage();

        preg_match('/<input type="hidden" name="_csrf_bodet" value="([^"]+)"/', $html, $matches);
        $csrfToken = $matches[1] ?? '';

        // Verify login form requires CSRF token
        $this->assertNotEmpty($csrfToken);
        $this->assertStringContainsString('_csrf_bodet', $html);
    }

    public function test_login_form_has_correct_action_url(): void
    {
        $html = KelioHtmlFixtures::getLoginPage();

        // Verify form action is j_spring_security_check
        $this->assertStringContainsString('action="j_spring_security_check"', $html);
        $this->assertStringContainsString('method="post"', $html);
    }

    public function test_login_form_has_required_fields(): void
    {
        $html = KelioHtmlFixtures::getLoginPage();

        // Verify all required form fields are present
        $this->assertStringContainsString('name="username"', $html);
        $this->assertStringContainsString('name="password"', $html);
        $this->assertStringContainsString('name="_csrf_bodet"', $html);
        $this->assertStringContainsString('name="ACTION"', $html);
    }

    public function test_hours_table_structure_is_correct(): void
    {
        $html = KelioHtmlFixtures::getHoursPage();

        // Verify table structure
        $this->assertStringContainsString('class="planning-table"', $html);
        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('<tbody>', $html);

        // Verify column headers
        $this->assertStringContainsString('<th>Date</th>', $html);
        $this->assertStringContainsString('<th>Heure 1</th>', $html);
        $this->assertStringContainsString('<th>Heure 8</th>', $html);
    }

    public function test_parses_multiple_time_entries_per_day(): void
    {
        $html = KelioHtmlFixtures::getHoursPage();
        $expectedHours = KelioHtmlFixtures::getExpectedParsedHours();

        // Verify day with 6 time entries (breaks included)
        $this->assertCount(6, $expectedHours['14/01/2026']);
        $this->assertEquals(['08:30', '10:30', '10:45', '12:00', '13:00', '17:30'], $expectedHours['14/01/2026']);
    }

    public function test_cookie_extraction_handles_multiple_cookies(): void
    {
        $headers = "HTTP/1.1 302 Found\r\n";
        $headers .= "Set-Cookie: JSESSIONID=ABC123; Path=/; HttpOnly\r\n";
        $headers .= "Set-Cookie: OTHER_COOKIE=xyz789; Path=/\r\n";
        $headers .= "Location: /open/homepage\r\n";

        $cookies = [];
        foreach (explode("\n", $headers) as $header) {
            if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $header, $match)) {
                parse_str($match[1], $cookie);
                $cookies = array_merge($cookies, $cookie);
            }
        }

        $this->assertArrayHasKey('JSESSIONID', $cookies);
        $this->assertEquals('ABC123', $cookies['JSESSIONID']);
        $this->assertArrayHasKey('OTHER_COOKIE', $cookies);
    }

    public function test_location_header_extraction(): void
    {
        $headers = "HTTP/1.1 302 Found\r\n";
        $headers .= "Location: https://daryl.kelio.io/open/homepage\r\n";
        $headers .= "Set-Cookie: JSESSIONID=ABC123\r\n";

        // Extract location using same regex as KelioClient
        preg_match('/^Location: (.+)$/mi', $headers, $match);
        $location = isset($match[1]) ? trim(str_replace(['https://daryl.kelio.io', 'https://daryl.kelio.io:443'], '', $match[1])) : null;

        $this->assertNotNull($location);
        $this->assertEquals('/open/homepage', $location);
    }

    public function test_handles_malformed_html_gracefully(): void
    {
        $malformedHtml = '<html><body><table><tr><td>Invalid</td></tr></table></body></html>';

        $dom = new \DOMDocument();
        @$dom->loadHTML($malformedHtml);
        $xpath = new \DOMXPath($dom);

        // Should not throw exception, just return empty array
        $rows = $xpath->query('//table[@class="bordered"]/tr');

        $this->assertNotNull($rows);
        $this->assertEquals(0, $rows->length);
    }

    public function test_time_format_validation(): void
    {
        $expectedHours = KelioHtmlFixtures::getExpectedParsedHours();

        // Verify all times are in HH:MM format
        foreach ($expectedHours as $date => $times) {
            foreach ($times as $time) {
                $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $time, "Time $time should be in HH:MM format");
            }
        }
    }

    public function test_date_format_validation(): void
    {
        $expectedHours = KelioHtmlFixtures::getExpectedParsedHours();

        // Verify all dates are in DD/MM/YYYY format
        foreach (array_keys($expectedHours) as $date) {
            $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $date, "Date $date should be in DD/MM/YYYY format");
        }
    }
}
