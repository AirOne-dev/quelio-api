<?php

namespace Tests\Fixtures;

/**
 * Real HTML fixtures from daryl.kelio.io
 * Captured via curl, never fetched during tests
 */
class KelioHtmlFixtures
{
    /**
     * Login page with CSRF token
     * Captured from: https://daryl.kelio.io/open/
     */
    public static function getLoginPage(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Kelio</title>
    <meta name="theme-color" content="#1BA4D9">
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
</head>
<body class="fondDefault">
    <div class="poly-background"></div>
    <div id="loginWrap">
        <div id="login-container">
            <div>
                <form name="formLogin" method="post" action="j_spring_security_check" onsubmit="return false;">
                    <input type="hidden" id="ACTION" name="ACTION" value=""/>
                    <div class="small-logo">
                        <img border="0" src="portail/fond/loginSmallLogo.jpg" height="100%" alt="">
                    </div>
                    <p class="login-title">Identifiez-vous</p>
                    <p>Entrez votre identifiant et votre mot de passe</p>
                    <input type="text" name="username" id="username" value="" size='40' autocomplete='off' placeholder='Identifiant'>
                    <input type="password" name="password" id="password" value="" maxlength="32" size='40' autocomplete='current-password' placeholder='Mot de passe'>
                    <input type="button" id="okButton" href="javascript:void(0)" value="Valider" />
                    <br/>
                    <a style='text-decoration: none;color:white;font-weight:bold' href='login?ACTION=actionForgottenPassword'>
                        Mot de passe oublié ?
                    </a>
                    <br/>
                    <input type="hidden" name="_csrf_bodet" value="84eea639-c251-4661-b965-acbddd752367" />
                </form>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Hours page with time entries
     * Simulated based on Kelio table structure
     */
    public static function getHoursPage(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Kelio - Mes horaires</title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
</head>
<body>
    <div id="content">
        <table class="planning-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Heure 1</th>
                    <th>Heure 2</th>
                    <th>Heure 3</th>
                    <th>Heure 4</th>
                    <th>Heure 5</th>
                    <th>Heure 6</th>
                    <th>Heure 7</th>
                    <th>Heure 8</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="date">13/01/2026</td>
                    <td class="heure">08:30</td>
                    <td class="heure">12:00</td>
                    <td class="heure">13:00</td>
                    <td class="heure">18:30</td>
                    <td class="heure">&nbsp;</td>
                    <td class="heure">&nbsp;</td>
                    <td class="heure">&nbsp;</td>
                    <td class="heure">&nbsp;</td>
                </tr>
                <tr>
                    <td class="date">14/01/2026</td>
                    <td class="heure">08:30</td>
                    <td class="heure">10:30</td>
                    <td class="heure">10:45</td>
                    <td class="heure">12:00</td>
                    <td class="heure">13:00</td>
                    <td class="heure">17:30</td>
                    <td class="heure">&nbsp;</td>
                    <td class="heure">&nbsp;</td>
                </tr>
                <tr>
                    <td class="date">15/01/2026</td>
                    <td class="heure">08:30</td>
                    <td class="heure">12:00</td>
                    <td class="heure">12:45</td>
                    <td class="heure">18:00</td>
                    <td class="heure">&nbsp;</td>
                    <td class="heure">&nbsp;</td>
                    <td class="heure">&nbsp;</td>
                    <td class="heure">&nbsp;</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Empty hours page (no hours for period)
     */
    public static function getEmptyHoursPage(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Kelio - Mes horaires</title>
</head>
<body>
    <div id="content">
        <table class="planning-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Heure 1</th>
                    <th>Heure 2</th>
                    <th>Heure 3</th>
                    <th>Heure 4</th>
                </tr>
            </thead>
            <tbody>
                <!-- No data -->
            </tbody>
        </table>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Login error page (invalid credentials)
     */
    public static function getLoginErrorPage(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Kelio</title>
</head>
<body>
    <div id="loginWrap">
        <div class="messagesErreur" style="display:block;">
            <p>Identifiant ou mot de passe incorrect</p>
        </div>
        <form name="formLogin" method="post" action="j_spring_security_check">
            <input type="text" name="username" id="username" placeholder='Identifiant'>
            <input type="password" name="password" id="password" placeholder='Mot de passe'>
            <input type="hidden" name="_csrf_bodet" value="error-token-123" />
        </form>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get sample JSESSIONID cookie
     */
    public static function getSampleCookie(): string
    {
        return 'JSESSIONID=ABC123DEF456GHI789JKL012MNO345PQR678STU901VWX234YZ;Path=/;HttpOnly';
    }

    /**
     * Get expected parsed hours from getHoursPage()
     */
    public static function getExpectedParsedHours(): array
    {
        return [
            '13/01/2026' => ['08:30', '12:00', '13:00', '18:30'],
            '14/01/2026' => ['08:30', '10:30', '10:45', '12:00', '13:00', '17:30'],
            '15/01/2026' => ['08:30', '12:00', '12:45', '18:00'],
        ];
    }
}
