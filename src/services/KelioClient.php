<?php

class KelioClient
{
    private array $defaultHeaders;

    public function __construct(private string $kelioUrl)
    {
        $this->defaultHeaders = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: fr,es;q=0.9,it;q=0.8,en;q=0.7',
            'Cache-Control: no-cache',
            'DNT: 1',
            'Pragma: no-cache',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'sec-ch-ua: "Chromium";v="131", "Not_A Brand";v="24"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "macOS"'
        ];
    }

    /**
     * Login to Kelio and return session ID
     * @param string $username
     * @param string $password
     * @return string JSESSIONID
     * @throws Exception
     */
    public function login(string $username, string $password): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $this->defaultHeaders
        ]);

        // Get CSRF token from login page
        curl_setopt($ch, CURLOPT_URL, $this->kelioUrl . '/open/login');
        $response = curl_exec($ch);
        preg_match('/<input type="hidden" name="_csrf_bodet" value="([^"]+)"/', $response, $matches);
        $csrfToken = $matches[1] ?? '';

        if (empty($csrfToken)) {
            curl_close($ch);
            throw new Exception('Unable to get CSRF token');
        }

        // Login to Kelio
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->kelioUrl . '/open/j_spring_security_check',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'ACTION' => 'ACTION_VALIDER_LOGIN',
                'username' => $username,
                'password' => $password,
                '_csrf_bodet' => $csrfToken
            ]),
            CURLOPT_HTTPHEADER => array_merge($this->defaultHeaders, [
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: ' . $this->kelioUrl,
                'Referer: ' . $this->kelioUrl . '/open/login?logout=1'
            ])
        ]);

        $loginResponse = curl_exec($ch);
        $loginLocation = $this->getLocation($loginResponse);
        $cookies = $this->getCookies($loginResponse);
        $jsessionid = $cookies['JSESSIONID'] ?? '';

        curl_close($ch);

        if (!$jsessionid || !$loginLocation) {
            throw new Exception('Login failed');
        }

        return $jsessionid;
    }

    /**
     * Fetch hours from Kelio
     * @param string $jsessionid
     * @return array All hours merged by day
     */
    public function fetchAllHours(string $jsessionid): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $this->defaultHeaders
        ]);

        // Fetch hours with different offsets (Kelio returns 4 cells per request)
        $hours1 = $this->fetchHours($ch, $jsessionid, 0);
        $hours2 = $this->fetchHours($ch, $jsessionid, 4);
        $hours3 = $this->fetchHours($ch, $jsessionid, 8);

        curl_close($ch);

        return [$hours1, $hours2, $hours3];
    }

    /**
     * Fetch the worked hours from Kelio
     * @param CurlHandle $ch
     * @param string $jsessionid
     * @param int $offset (kelio only return 4 cells of hours per request, so we need to fetch multiple times)
     * @return array
     */
    private function fetchHours(CurlHandle $ch, string $jsessionid, int $offset = 0): array
    {
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->kelioUrl . "/open/homepage" . "?ACTION=intranet&asked=3&header=0&offset=$offset",
            CURLOPT_HTTPHEADER => array_merge($this->defaultHeaders, [
                'Cookie: JSESSIONID=' . $jsessionid
            ])
        ]);

        $portalResponse = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        return $this->parseHoursToArray(substr($portalResponse, $headerSize));
    }

    /**
     * Parse the HTML response from Kelio to extract the worked hours
     * @param string $html
     * @return array
     */
    private function parseHoursToArray(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $data = [];

        // Find all rows in the table
        $rows = $xpath->query('//table[@class="bordered"]/tr');

        if ($rows->length > 1) { // Skip header row
            for ($i = 1; $i < $rows->length; $i++) {
                $row = $rows->item($i);

                // Get the date cell
                $dateCell = $xpath->query('.//a[contains(@onclick, "fcAfficherBadgeagesJour")] | ./td[1][not(./a)]', $row)->item(0);

                if ($dateCell && preg_match('/(\d{2}\/\d{2}\/\d{4})/', $dateCell->textContent, $matches)) {
                    $date = $matches[1];
                    $times = [];

                    // Find all time cells
                    $tdCells = $xpath->query('.//table[@width="100%"]//td[@width="*"]', $row);
                    foreach ($tdCells as $cell) {
                        $timeText = trim($cell->textContent);
                        if ($timeText && $timeText != '&nbsp;') {
                            // Clean the time text
                            $time = trim(str_replace(["\xC2\xA0", "&nbsp;", " "], '', $timeText));
                            if (!empty($time)) {
                                $times[] = $time;
                            }
                        }
                    }

                    if (!empty($times)) {
                        $data[$date] = $times;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Get the cookies from the response
     * @param string $headers
     * @return array
     */
    private function getCookies(string $headers): array
    {
        $cookies = [];
        foreach (explode("\n", $headers) as $header) {
            if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $header, $match)) {
                parse_str($match[1], $cookie);
                $cookies = array_merge($cookies, $cookie);
            }
        }
        return $cookies;
    }

    /**
     * Get the Location header from the response
     * @param string $headers
     * @return string|null
     */
    private function getLocation(string $headers): string|null
    {
        if (preg_match('/^Location: (.+)$/mi', $headers, $match)) {
            return trim(str_replace([$this->kelioUrl, "$this->kelioUrl:443"], '', $match[1]));
        }
        return null;
    }
}
