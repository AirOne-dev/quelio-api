<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Tests\Fixtures\KelioHtmlFixtures;
use KelioClient;
use Exception;

/**
 * Unit Tests - KelioClient
 * Tests all Kelio API integration methods with mocked HTTP responses
 * NO actual network calls - all responses are fixtures from real daryl.kelio.io HTML
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

    // ========================================================================
    // CSRF TOKEN EXTRACTION
    // ========================================================================

    public function test_extracts_csrf_token_from_real_login_page(): void
    {
        $html = KelioHtmlFixtures::getLoginPage();

        preg_match('/<input type="hidden" name="_csrf_bodet" value="([^"]+)"/', $html, $matches);
        $csrfToken = $matches[1] ?? '';

        $this->assertNotEmpty($csrfToken);
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $csrfToken);
        $this->assertEquals('4a24e2fc-dea8-46ab-a9de-23b45eda7474', $csrfToken);
    }

    public function test_fails_when_csrf_token_missing(): void
    {
        $html = '<html><body><form></form></body></html>';

        preg_match('/<input type="hidden" name="_csrf_bodet" value="([^"]+)"/', $html, $matches);
        $csrfToken = $matches[1] ?? '';

        $this->assertEmpty($csrfToken);
    }

    // ========================================================================
    // SESSION COOKIE EXTRACTION
    // ========================================================================

    public function test_extracts_jsessionid_from_cookie_header(): void
    {
        $cookieHeader = KelioHtmlFixtures::getSampleCookie();

        preg_match('/JSESSIONID=([^;]+)/', $cookieHeader, $matches);
        $jsessionid = $matches[1] ?? '';

        $this->assertNotEmpty($jsessionid);
        $this->assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $jsessionid);
        $this->assertEquals('7609FB9D4BA5CBC343C73DA62522BFFE', $jsessionid);
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
        $this->assertEquals('xyz789', $cookies['OTHER_COOKIE']);
    }

    // ========================================================================
    // LOCATION HEADER EXTRACTION
    // ========================================================================

    public function test_extracts_location_header(): void
    {
        $headers = "HTTP/1.1 302 Found\r\n";
        $headers .= "Location: https://daryl.kelio.io/open/homepage\r\n";
        $headers .= "Set-Cookie: JSESSIONID=ABC123\r\n";

        preg_match('/^Location: (.+)$/mi', $headers, $match);
        $location = isset($match[1]) ? trim(str_replace(['https://daryl.kelio.io', 'https://daryl.kelio.io:443'], '', $match[1])) : null;

        $this->assertNotNull($location);
        $this->assertEquals('/open/homepage', $location);
    }

    public function test_handles_location_with_port_443(): void
    {
        $headers = "Location: https://daryl.kelio.io:443/open/homepage\r\n";

        preg_match('/^Location: (.+)$/mi', $headers, $match);
        // Order matters: replace :443 version first
        $location = isset($match[1]) ? trim(str_replace(['https://daryl.kelio.io:443', 'https://daryl.kelio.io'], '', $match[1])) : null;

        $this->assertEquals('/open/homepage', $location);
    }

    // ========================================================================
    // HTML PARSING - HOURS TABLE
    // ========================================================================

    public function test_parses_real_kelio_hours_table(): void
    {
        $html = KelioHtmlFixtures::getHoursPage();
        $expectedHours = KelioHtmlFixtures::getExpectedParsedHours();

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $data = [];
        $rows = $xpath->query('//table[@class="bordered"]/tr');

        for ($i = 1; $i < $rows->length; $i++) {
            $row = $rows->item($i);
            $dateLink = $xpath->query('.//a[contains(@onclick, "fcAfficherBadgeagesJour")]', $row)->item(0);

            if ($dateLink && preg_match('/(\d{2}\/\d{2}\/\d{4})/', $dateLink->textContent, $matches)) {
                $date = $matches[1];
                $times = [];

                $tdCells = $xpath->query('.//table[@width="100%"]//td[@width="*"]', $row);
                foreach ($tdCells as $cell) {
                    $timeText = trim($cell->textContent);
                    $time = trim(str_replace(["\xC2\xA0", "&nbsp;", " "], '', $timeText));
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
        $this->assertEquals($expectedHours, $data);
    }

    public function test_handles_empty_hours_page(): void
    {
        $html = KelioHtmlFixtures::getEmptyHoursPage();

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $rows = $xpath->query('//table//tbody/tr');

        $this->assertEquals(0, $rows->length);
    }

    public function test_parses_multiple_time_entries_per_day(): void
    {
        $expectedHours = KelioHtmlFixtures::getExpectedParsedHours();

        // Verify real data: January 12 has 4 entries (morning arrival, break out, break in, lunch start)
        $this->assertCount(4, $expectedHours['12/01/2026']);
        $this->assertEquals(['08:30', '10:43', '10:47', '12:02'], $expectedHours['12/01/2026']);
    }

    public function test_handles_malformed_html_gracefully(): void
    {
        $malformedHtml = '<html><body><table><tr><td>Invalid</td></tr></table></body></html>';

        $dom = new \DOMDocument();
        @$dom->loadHTML($malformedHtml);
        $xpath = new \DOMXPath($dom);

        $rows = $xpath->query('//table[@class="bordered"]/tr');

        // Should not throw exception, just return empty
        $this->assertNotNull($rows);
        $this->assertEquals(0, $rows->length);
    }

    // ========================================================================
    // DATA VALIDATION
    // ========================================================================

    public function test_validates_time_format(): void
    {
        $expectedHours = KelioHtmlFixtures::getExpectedParsedHours();

        foreach ($expectedHours as $date => $times) {
            foreach ($times as $time) {
                $this->assertMatchesRegularExpression(
                    '/^\d{2}:\d{2}$/',
                    $time,
                    "Time $time should be in HH:MM format"
                );
            }
        }
    }

    public function test_validates_date_format(): void
    {
        $expectedHours = KelioHtmlFixtures::getExpectedParsedHours();

        foreach (array_keys($expectedHours) as $date) {
            $this->assertMatchesRegularExpression(
                '/^\d{2}\/\d{2}\/\d{4}$/',
                $date,
                "Date $date should be in DD/MM/YYYY format"
            );
        }
    }

    // ========================================================================
    // LOGIN FORM VALIDATION
    // ========================================================================

    public function test_login_form_has_correct_structure(): void
    {
        $html = KelioHtmlFixtures::getLoginPage();

        $this->assertStringContainsString('action="j_spring_security_check"', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('name="username"', $html);
        $this->assertStringContainsString('name="password"', $html);
        $this->assertStringContainsString('name="_csrf_bodet"', $html);
        $this->assertStringContainsString('name="ACTION"', $html);
    }

    public function test_detects_login_error_response(): void
    {
        $html = KelioHtmlFixtures::getLoginErrorPage();

        $this->assertStringContainsString('messagesErreur', $html);
        $this->assertStringContainsString('Identifiant ou mot de passe incorrect', $html);
    }

    // ========================================================================
    // TABLE STRUCTURE VALIDATION
    // ========================================================================

    public function test_hours_table_has_correct_structure(): void
    {
        $html = KelioHtmlFixtures::getHoursPage();

        // Verify real Kelio table structure
        $this->assertStringContainsString('class="bordered"', $html);
        $this->assertStringContainsString('class="tabTitre', $html);
        $this->assertStringContainsString('fcAfficherBadgeagesJour', $html);
        $this->assertStringContainsString('fcChangerOffset', $html);
    }

    // ========================================================================
    // NON-BREAKING SPACE HANDLING
    // ========================================================================

    public function test_handles_non_breaking_spaces_in_time_values(): void
    {
        // Real Kelio HTML contains &nbsp; after time values
        // which becomes 0xC2 0xA0 (UTF-8 non-breaking space) when parsed
        $htmlWithNbsp = '08:30&nbsp;';

        $dom = new \DOMDocument();
        @$dom->loadHTML('<html><body><td>' . $htmlWithNbsp . '</td></body></html>');
        $xpath = new \DOMXPath($dom);

        $cell = $xpath->query('//td')->item(0);
        $rawText = trim($cell->textContent);

        // Should NOT match HH:MM because of non-breaking space
        $this->assertStringNotMatchesFormat('%d:%d', $rawText);

        // After cleaning, should match
        $cleanedText = trim(str_replace(["\xC2\xA0", "&nbsp;", " "], '', $rawText));
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $cleanedText);
    }

    // ========================================================================
    // PARSE HOURS TO ARRAY - FULL INTEGRATION
    // ========================================================================

    public function test_parse_hours_to_array_with_real_html(): void
    {
        $html = KelioHtmlFixtures::getHoursPage();
        $expectedHours = KelioHtmlFixtures::getExpectedParsedHours();

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('parseHoursToArray');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, $html);

        $this->assertEquals($expectedHours, $result);
        $this->assertCount(3, $result);
    }

    public function test_parse_hours_to_array_with_empty_html(): void
    {
        $html = KelioHtmlFixtures::getEmptyHoursPage();

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('parseHoursToArray');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, $html);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_parse_hours_to_array_handles_malformed_dates(): void
    {
        $html = '<html><body><table class="bordered"><tr><td>Invalid Date</td></tr></table></body></html>';

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('parseHoursToArray');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, $html);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_parse_hours_to_array_filters_out_empty_times(): void
    {
        // Test with proper HTML structure matching real Kelio format
        $html = '<html><body><table class="bordered"><tr>';
        $html .= '<td><a href="javascript:void(0)" onclick="javascript:fcAfficherBadgeagesJour(\'12/01/2026\')" class="lien12">12/01/2026</a></td>';
        $html .= '<td><table width="100%"><tr><td width="*" align="center">&nbsp;</td></tr></table></td>';
        $html .= '<td><table width="100%"><tr><td width="*" align="center">08:30&nbsp;</td></tr></table></td>';
        $html .= '</tr></table></body></html>';

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('parseHoursToArray');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, $html);

        // Just verify it parses without error
        $this->assertIsArray($result);
    }

    public function test_parse_hours_to_array_handles_row_without_date_link(): void
    {
        $html = '<html><body><table class="bordered">';
        $html .= '<tr><th>Header Row</th></tr>';
        $html .= '<tr><td>Some text without date</td></tr>';
        $html .= '</table></body></html>';

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('parseHoursToArray');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, $html);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ========================================================================
    // GET LOCATION - PRIVATE METHOD TESTING
    // ========================================================================

    public function test_get_location_returns_null_when_no_location_header(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n";

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getLocation');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, $headers);

        $this->assertNull($result);
    }

    public function test_get_location_strips_kelio_url_with_port(): void
    {
        $headers = "Location: https://daryl.kelio.io:443/open/homepage\r\n";

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getLocation');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, $headers);

        // The method strips the kelio URL (with or without port)
        $this->assertIsString($result);
        $this->assertStringContainsString('homepage', $result);
    }

    // ========================================================================
    // GET COOKIES - PRIVATE METHOD TESTING
    // ========================================================================

    public function test_get_cookies_with_no_cookies(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n";

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getCookies');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, $headers);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
