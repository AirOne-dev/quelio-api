<?php

/*

This script is used to fetch worked hours from Kelio and calculate the total worked hours.

GET / => login form
POST / => fetch worked hours

curl --location 'http://your_web_server/quel%20io/' \
--form 'username="KELIO_USERNAME"' \
--form 'password="KELIO_PASSWORD"'

*/

// Load configuration
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die(json_encode([
        'error' => 'Configuration file not found. Please copy config.example.php to config.php and configure it.'
    ], JSON_PRETTY_PRINT));
}

$config = require $configFile;

// Validate required configuration
$requiredKeys = ['kelio_url', 'pause_time', 'start_limit_minutes', 'end_limit_minutes', 'morning_break_threshold', 'afternoon_break_threshold', 'enable_form_access'];
foreach ($requiredKeys as $key) {
    if (!isset($config[$key])) {
        die(json_encode([
            'error' => "Missing required configuration key: $key"
        ], JSON_PRETTY_PRINT));
    }
}

$kelioUrl = $config['kelio_url'];
$pauseTime = $config['pause_time'];

// Try multiple possible locations for the data file
$possibleDataPaths = [
    './data.json',
    __DIR__ . '/data.json',
    sys_get_temp_dir() . '/kelio_data.json',
    '/tmp/kelio_data.json'
];

$dataFile = null;
foreach ($possibleDataPaths as $path) {
    $dir = dirname($path);
    if (is_writable($dir)) {
        $dataFile = $path;
        break;
    }
}

// If no writable directory found, use temp directory as fallback
if ($dataFile === null) {
    $dataFile = sys_get_temp_dir() . '/kelio_data.json';
}

$defaultHeaders = [
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

/**
 * Load saved data from JSON file
 * @return array
 */
function loadSavedData(): array
{
    global $dataFile;
    
    if (!file_exists($dataFile)) {
        return [];
    }
    
    $content = file_get_contents($dataFile);
    if ($content === false) {
        return [];
    }
    
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

/**
 * Save data to JSON file
 * @param string $username
 * @param array $hours
 * @param string $totalEffective
 * @param string $totalPaid
 * @return bool
 */
function saveData(string $username, array $hours, string $totalEffective, string $totalPaid): bool
{
    global $dataFile;
    
    try {
        // Ensure directory exists and is writable
        $dir = dirname($dataFile);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("Failed to create directory: $dir");
                return false;
            }
        }
        
        if (!is_writable($dir)) {
            error_log("Directory not writable: $dir");
            return false;
        }
        
        $allData = loadSavedData();
        
        $allData[$username] = [
            'hours' => $hours,
            'total_effective' => $totalEffective,
            'total_paid' => $totalPaid,
            'last_save' => date('d/m/Y H:i:s')
        ];
        
        $jsonData = json_encode($allData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($jsonData === false) {
            error_log("Failed to encode JSON data");
            return false;
        }
        
        $result = file_put_contents($dataFile, $jsonData, LOCK_EX);
        if ($result === false) {
            error_log("Failed to write file: $dataFile");
            return false;
        }
        
        return true;
    } catch (\Throwable $e) {
        error_log("Save data error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get saved data for a specific user
 * @param string $username
 * @return array|null
 */
function getSavedDataForUser(string $username): ?array
{
    $allData = loadSavedData();
    return $allData[$username] ?? null;
}

/**
 * Get the cookies from the response
 * @param string $headers
 * @return array
 */
function getCookies(string $headers): array
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
function getLocation(string $headers): string|null
{
    global $kelioUrl;

    if (preg_match('/^Location: (.+)$/mi', $headers, $match)) {
        return trim(str_replace([$kelioUrl, "$kelioUrl:443"], '', $match[1]));
    }
    return null;
}

/**
 * Parse the HTML response from Kelio to extract the worked hours
 * @param string $html
 * @return array
 */
function parseHoursToArray(string $html): array
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
 * Fetch the worked hours from Kelio
 * @param CurlHandle $ch
 * @param string $jsessionid
 * @param int $offset (kelio only return 4 cells of hours per request, so we need to fetch multiple times)
 * @return array
 */
function fetchHours(CurlHandle $ch, string $jsessionid, int $offset = 0): array
{
    global $kelioUrl, $defaultHeaders;
    curl_setopt_array($ch, [
        CURLOPT_URL => $kelioUrl . "/open/homepage" . "?ACTION=intranet&asked=3&header=0&offset=$offset",
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, [
            'Cookie: JSESSIONID=' . $jsessionid
        ])
    ]);
    
    $portalResponse = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    return parseHoursToArray(substr($portalResponse, $headerSize));
}

/**
 * Merge multiple arrays of hours by day
 * @param array ...$arrays (returned by fetchHours)
 * @return array
 */
function mergeHoursByDay(array ...$arrays): array 
{
    $merged = [];

    foreach ($arrays as $timeSet) {
        if (!$timeSet) {
            continue;
        }
        
        foreach ($timeSet as $date => $hours) {
            // convert date format (23/12/2024 -> 23-12-2024)
            $cleanDate = str_replace('/', '-', $date);
            
            if (!isset($merged[$cleanDate])) {
                $merged[$cleanDate] = [];
            }
            
            // clean hours (remove spaces and \u00a0 character)
            $cleanHours = array_map(function($hour) {
                return trim($hour, " \t\n\r\0\x0B\xC2\xA0");
            }, $hours);
            
            $merged[$cleanDate] = array_merge($merged[$cleanDate], $cleanHours);
            sort($merged[$cleanDate]);
        }
    }
    
    return $merged;
}

/**
 * Calcul of the total worked hours
 * @param array $arrayData (returned by mergeHoursByDay)
 * @param int $pause (in minutes)
 * @return string
 */
function calculateTotalWorkingHours(array $arrayData, int $pause = 0): string
{
    global $config;

    date_default_timezone_set('Europe/Paris');

    $totalMinutes = 0;
    $currentDate = date('d-m-Y');
    $currentTime = date('H:i');

    $startLimit = $config['start_limit_minutes'];
    $endLimit = $config['end_limit_minutes'];
    
    foreach ($arrayData as $date => $hours) {
        $dailyMinutes = 0;
        $nbHours = count($hours);
        $morningPauseAdded = false;
        $afternoonPauseAdded = false;
        
        // Special case for the current day with an odd number of hours
        if ($date === $currentDate && $nbHours % 2 !== 0) {
            $start = explode(':', $hours[$nbHours - 1]);
            $end = explode(':', $currentTime);
            
            $startMinutes = max(min(intval($start[0]) * 60 + intval($start[1]), $endLimit), $startLimit);
            $endMinutes = max(min(intval($end[0]) * 60 + intval($end[1]), $endLimit), $startLimit);

            $plageMinutes = $endMinutes - $startMinutes;
            $dailyMinutes += $plageMinutes;

            // If we are after the morning break threshold, we add the morning break
            if ($endMinutes >= $config['morning_break_threshold'] && !$morningPauseAdded) {
                $dailyMinutes += $pause;
                $morningPauseAdded = true;
            }
            // If we are after the afternoon break threshold, we add the afternoon break
            if ($endMinutes >= $config['afternoon_break_threshold'] && !$afternoonPauseAdded) {
                $dailyMinutes += $pause;
                $afternoonPauseAdded = true;
            }
        }
        
        // Normal case
        for ($i = 0; $i < $nbHours - 1; $i += 2) {
            $start = explode(':', $hours[$i]);
            $end = explode(':', $hours[$i + 1]);
            
            $startMinutes = max(min(intval($start[0]) * 60 + intval($start[1]), $endLimit), $startLimit);
            $endMinutes = max(min(intval($end[0]) * 60 + intval($end[1]), $endLimit), $startLimit);
            
            $plageMinutes = $endMinutes - $startMinutes;
            $dailyMinutes += $plageMinutes;

            // Check for the morning break
            if ($endMinutes >= $config['morning_break_threshold'] && !$morningPauseAdded) {
                $dailyMinutes += $pause;
                $morningPauseAdded = true;
            }

            // Check for the afternoon break
            if ($endMinutes >= $config['afternoon_break_threshold'] && !$afternoonPauseAdded) {
                $dailyMinutes += $pause;
                $afternoonPauseAdded = true;
            }
        }
        
        $totalMinutes += $dailyMinutes;
    }
    
    $h = floor($totalMinutes / 60);
    $m = $totalMinutes % 60;
    return sprintf("%02d:%02d", $h, $m);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        die(json_encode(['error' => 'username and password required'], JSON_PRETTY_PRINT));
    }

    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $defaultHeaders
        ]);
        
        // Get CSRF token from login page
        curl_setopt($ch, CURLOPT_URL, $kelioUrl . '/open/login');
        $response = curl_exec($ch);
        preg_match('/<input type="hidden" name="_csrf_bodet" value="([^"]+)"/', $response, $matches);
        $csrfToken = $matches[1] ?? '';

        if (empty($csrfToken)) {
            throw new Exception('Unable to get CSRF token');
        }

        // Login to Kelio
        curl_setopt_array($ch, [
            CURLOPT_URL => $kelioUrl . '/open/j_spring_security_check',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'ACTION' => 'ACTION_VALIDER_LOGIN',
                'username' => $username,
                'password' => $password,
                '_csrf_bodet' => $csrfToken
            ]),
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, [
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: ' . $kelioUrl,
                'Referer: ' . $kelioUrl . '/open/login?logout=1'
            ])
        ]);
        
        $loginResponse = curl_exec($ch);
        $loginStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $loginLocation = getLocation($loginResponse);
        $cookies = getCookies($loginResponse);
        $jsessionid = $cookies['JSESSIONID'] ?? '';
        
        if (!$jsessionid || !$loginLocation) {
            throw new Exception('Login failed');
        }
        
        // Fetch hours data
        $hours = mergeHoursByDay(
            fetchHours($ch, $jsessionid),
            fetchHours($ch, $jsessionid, 4),
            fetchHours($ch, $jsessionid, 8)
        );
        
        curl_close($ch);
        
        $totalEffective = calculateTotalWorkingHours($hours);
        $totalPaid = calculateTotalWorkingHours($hours, $pauseTime);
        
        // Save the successful result
        $saveSuccess = saveData($username, $hours, $totalEffective, $totalPaid);
        
        $response = [
            'hours' => $hours,
            'total_effective' => $totalEffective,
            'total_paid' => $totalPaid,
            'data_saved' => $saveSuccess,
            'data_file_path' => $dataFile
        ];
        
        die(json_encode($response, JSON_PRETTY_PRINT));
        
    } catch (\Throwable $th) {
        // Try to get fallback data
        $savedData = getSavedDataForUser($username);
        
        if ($savedData !== null) {
            $response = [
                'error' => 'Failed to fetch fresh data, using cached data',
                'fallback' => true,
                'hours' => $savedData['hours'],
                'total_effective' => $savedData['total_effective'],
                'total_paid' => $savedData['total_paid'],
                'last_save' => $savedData['last_save']
            ];
            die(json_encode($response, JSON_PRETTY_PRINT));
        } else {
            die(json_encode(['error' => 'No fresh data available and no cached data found'], JSON_PRETTY_PRINT));
        }
    }
}

// Check if form access is disabled
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$config['enable_form_access']) {
    header('Content-Type: application/json');
    http_response_code(403);
    die(json_encode([
        'error' => 'Form access is disabled. Please use POST method to access the API.'
    ], JSON_PRETTY_PRINT));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Connexion Kelio</title>
    <meta charset="UTF-8">
</head>
<body>
    <form method="POST">
        <input type="text" name="username" placeholder="Identifiant" required><br>
        <input type="password" name="password" placeholder="Mot de passe" required><br>
        <input type="submit" value="Connexion">
    </form>
</body>
</html>
