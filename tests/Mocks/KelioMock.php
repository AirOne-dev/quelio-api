<?php

namespace Tests\Mocks;

/**
 * Mock Kelio responses for testing
 */
class KelioMock
{
    /**
     * Get mock login page HTML with CSRF token
     */
    public static function getLoginPage(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head><title>Kelio Login</title></head>
<body>
    <form action="/login" method="post">
        <input type="hidden" name="_csrf" value="mock_csrf_token_12345">
        <input type="text" name="username">
        <input type="password" name="password">
        <button type="submit">Login</button>
    </form>
</body>
</html>
HTML;
    }

    /**
     * Get mock hours page HTML
     */
    public static function getHoursPage(array $hours = []): string
    {
        $rows = '';
        foreach ($hours as $date => $times) {
            $dateFormatted = str_replace('-', '/', $date);
            $timesHtml = implode('</td><td>', $times);
            $rows .= "<tr><td>{$dateFormatted}</td><td>{$timesHtml}</td></tr>\n";
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head><title>Kelio Hours</title></head>
<body>
    <table>
        <thead>
            <tr><th>Date</th><th colspan="10">Hours</th></tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Get mock hours data for testing
     */
    public static function getMockHoursData(): array
    {
        return [
            '13/01/2026' => ['08:30', '12:00', '13:00', '18:30'],
            '14/01/2026' => ['08:30', '10:30', '10:45', '12:00', '13:00', '17:30'],
            '15/01/2026' => ['08:30', '12:00', '12:45', '18:00'],
        ];
    }

    /**
     * Get mock session cookie
     */
    public static function getMockSessionCookie(): string
    {
        return 'JSESSIONID=mock_session_' . bin2hex(random_bytes(16));
    }
}
