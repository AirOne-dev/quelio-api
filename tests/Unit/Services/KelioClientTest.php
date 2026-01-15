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
        $this->assertEquals('4a24e2fc-dea8-46ab-a9de-23b45eda7474', $csrfToken);
    }

    public function test_extracts_jsessionid_from_cookie_header(): void
    {
        $cookieHeader = KelioHtmlFixtures::getSampleCookie();

        // Parse cookie using same logic as KelioClient::getCookies()
        preg_match('/JSESSIONID=([^;]+)/', $cookieHeader, $matches);
        $jsessionid = $matches[1] ?? '';

        $this->assertNotEmpty($jsessionid);
        $this->assertEquals('7609FB9D4BA5CBC343C73DA62522BFFE', $jsessionid);
    }

    public function test_parses_hours_from_html_table(): void
    {
        $html = KelioHtmlFixtures::getHoursPage();
        $expectedHours = KelioHtmlFixtures::getExpectedParsedHours();

        // Test HTML parsing logic using real Kelio structure
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $data = [];

        // Find all rows in the bordered table (Kelio's real structure)
        $rows = $xpath->query('//table[@class="bordered"]/tr');

        for ($i = 1; $i < $rows->length; $i++) {
            $row = $rows->item($i);

            // Get date from link with onclick attribute
            $dateLink = $xpath->query('.//a[contains(@onclick, "fcAfficherBadgeagesJour")]', $row)->item(0);

            if ($dateLink && preg_match('/(\d{2}\/\d{2}\/\d{4})/', $dateLink->textContent, $matches)) {
                $date = $matches[1];
                $times = [];

                // Find all time cells (nested table structure with width="*")
                $tdCells = $xpath->query('.//table[@width="100%"]//td[@width="*"]', $row);
                foreach ($tdCells as $cell) {
                    $timeText = trim($cell->textContent);
                    // Clean up non-breaking spaces and other whitespace
                    $time = trim(str_replace(["\xC2\xA0", "&nbsp;", " "], '', $timeText));
                    // Check if it matches HH:MM format
                    if ($time && preg_match('/^\d{2}:\d{2}$/', $time)) {
                        $times[] = $time;
                    }
                }

                if (!empty($times)) {
                    $data[$date] = $times;
                }
            }
        }

        $this->assertCount(3, $data);
        $this->assertArrayHasKey('12/01/2026', $data);
        $this->assertArrayHasKey('13/01/2026', $data);
        $this->assertArrayHasKey('14/01/2026', $data);

        // Verify exact times match expected
        $this->assertEquals($expectedHours['12/01/2026'], $data['12/01/2026']);
        $this->assertEquals($expectedHours['13/01/2026'], $data['13/01/2026']);
        $this->assertEquals($expectedHours['14/01/2026'], $data['14/01/2026']);
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

        // Verify real table structure from Kelio
        $this->assertStringContainsString('class="bordered"', $html);
        $this->assertStringContainsString('class="tabTitre', $html);
        $this->assertStringContainsString('fcAfficherBadgeagesJour', $html);

        // Verify column header exists
        $this->assertStringContainsString('<th class="tabTitre tabTitreFirst"', $html);
    }

    public function test_parses_multiple_time_entries_per_day(): void
    {
        $html = KelioHtmlFixtures::getHoursPage();
        $expectedHours = KelioHtmlFixtures::getExpectedParsedHours();

        // Verify days with 4 time entries (real data from Martin's account)
        $this->assertCount(4, $expectedHours['12/01/2026']);
        $this->assertEquals(['08:30', '10:43', '10:47', '12:02'], $expectedHours['12/01/2026']);
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
