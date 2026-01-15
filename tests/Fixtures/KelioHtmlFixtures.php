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
     * Captured from: https://daryl.kelio.io/open/login (January 15, 2026)
     */
    public static function getLoginPage(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Kelio</title>
    <meta name="theme-color" content="#1BA4D9">
    <link rel="manifest" href="/open/manifest/manifest.1761908880000.json">
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noodp">
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
                    <input type="hidden" name="_csrf_bodet" value="4a24e2fc-dea8-46ab-a9de-23b45eda7474" />
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
     * Real HTML structure from daryl.kelio.io (January 15, 2026)
     * Captured with martin's account showing real work hours
     */
    public static function getHoursPage(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Visualiser la présence</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="robots" content="noindex, nofollow, noarchive,nosnippet, noodp">
</head>
<body>
    <table width="100%" class="bordered">
        <tr align="center">
            <th class="tabTitre tabTitreFirst" width="3%">Jour</th>
            <th class="tabTitre" width="1%"><img border="0" src="/open/img/vide.1761908880000.gif" height="1" width="13" alt=""></th>
            <th class="tabTitre" width="17%">1</th>
            <th class="tabTitre" width="17%">2</th>
            <th class="tabTitre" width="17%">3</th>
            <th class="tabTitre" width="17%">4</th>
            <th class="tabTitre" width="1%"><img border="0" src="/open/img/vide.1761908880000.gif" height="1" width="13" alt=""></th>
        </tr>

        <tr align="center">
            <td height="20" class="tabPair">
                <a href="javascript:void(0)" onclick="javascript:fcAfficherBadgeagesJour('12/01/2026')" class="lien12">12/01/2026</a>
            </td>
            <td class="tabPair"><a href="javascript:void(0)" onclick="javascript:fcChangerOffset(-4)"><img name="imageMoins" id="imageMoins" src="/open/img/vide.1761908880000.gif" border="0"  alt="" title=""  style='padding-right: 3px'></a></td>

            <td class="tabPair" colspan="0 %>">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tabPair" width="*" align="center" title="">08:30&nbsp;</td>
                        <td class="tabPair" width="15"><img name="" id="" src="/open/images/badgeage/icn_badgeage_from_terminal.1763364751747.png" border="0"  alt="" title=""  style='padding-right: 3px' ></td>
                    </tr>
                </table>
            </td>

            <td class="tabPair" colspan="0 %>">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tabPair" width="*" align="center" title="">10:43&nbsp;</td>
                        <td class="tabPair" width="15"><img name="" id="" src="/open/images/badgeage/icn_badgeage_from_terminal.1763364751747.png" border="0"  alt="" title=""  style='padding-right: 3px' ></td>
                    </tr>
                </table>
            </td>

            <td class="tabPair" colspan="0 %>">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tabPair" width="*" align="center" title="">10:47&nbsp;</td>
                        <td class="tabPair" width="15"><img name="" id="" src="/open/images/badgeage/icn_badgeage_from_terminal.1763364751747.png" border="0"  alt="" title=""  style='padding-right: 3px' ></td>
                    </tr>
                </table>
            </td>

            <td class="tabPair" colspan="0 %>">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tabPair" width="*" align="center" title="">12:02&nbsp;</td>
                        <td class="tabPair" width="15"><img name="" id="" src="/open/images/badgeage/icn_badgeage_from_terminal.1763364751747.png" border="0"  alt="" title=""  style='padding-right: 3px' ></td>
                    </tr>
                </table>
            </td>

            <td class="tabPair"><a href="javascript:void(0)" onclick="javascript:fcChangerOffset(4)"><img name="imagePlus" id="imagePlus" src="/open/img/navRight.1761908880000.png" border="0"  alt="" title=""  style='padding-right: 3px'></a></td>
        </tr>

        <tr align="center">
            <td height="20" class="tabImpair">
                <a href="javascript:void(0)" onclick="javascript:fcAfficherBadgeagesJour('13/01/2026')" class="lien12">13/01/2026</a>
            </td>
            <td class="tabImpair"><a href="javascript:void(0)" onclick="javascript:fcChangerOffset(-4)"><img name="imageMoins" id="imageMoins" src="/open/img/vide.1761908880000.gif" border="0"  alt="" title=""  style='padding-right: 3px'></a></td>

            <td class="tabImpair" colspan="0 %>">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tabImpair" width="*" align="center" title="">08:30&nbsp;</td>
                        <td class="tabImpair" width="15"><img name="" id="" src="/open/images/badgeage/icn_badgeage_from_terminal.1763364751747.png" border="0"  alt="" title=""  style='padding-right: 3px' ></td>
                    </tr>
                </table>
            </td>

            <td class="tabImpair" colspan="0 %>">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tabImpair" width="*" align="center" title="">10:35&nbsp;</td>
                        <td class="tabImpair" width="15"><img name="" id="" src="/open/images/badgeage/icn_badgeage_from_terminal.1763364751747.png" border="0"  alt="" title=""  style='padding-right: 3px' ></td>
                    </tr>
                </table>
            </td>

            <td class="tabImpair" colspan="0 %>">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tabImpair" width="*" align="center" title="">10:46&nbsp;</td>
                        <td class="tabImpair" width="15"><img name="" id="" src="/open/images/badgeage/icn_badgeage_from_terminal.1763364751747.png" border="0"  alt="" title=""  style='padding-right: 3px' ></td>
                    </tr>
                </table>
            </td>

            <td class="tabImpair" colspan="0 %>">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tabImpair" width="*" align="center" title="">12:16&nbsp;</td>
                        <td class="tabImpair" width="15"><img name="" id="" src="/open/images/badgeage/icn_badgeage_from_terminal.1763364751747.png" border="0"  alt="" title=""  style='padding-right: 3px' ></td>
                    </tr>
                </table>
            </td>

            <td class="tabImpair"><a href="javascript:void(0)" onclick="javascript:fcChangerOffset(4)"><img name="imagePlus" id="imagePlus" src="/open/img/navRight.1761908880000.png" border="0"  alt="" title=""  style='padding-right: 3px'></a></td>
        </tr>

        <tr align="center">
            <td height="20" class="tabPair">
                <a href="javascript:void(0)" onclick="javascript:fcAfficherBadgeagesJour('14/01/2026')" class="lien12">14/01/2026</a>
            </td>
            <td class="tabPair"><a href="javascript:void(0)" onclick="javascript:fcChangerOffset(-4)"><img name="imageMoins" id="imageMoins" src="/open/img/vide.1761908880000.gif" border="0"  alt="" title=""  style='padding-right: 3px'></a></td>

            <td class="tabPair" colspan="0 %>">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tabPair" width="*" align="center" title="">08:30&nbsp;</td>
                        <td class="tabPair" width="15"><img name="" id="" src="/open/images/badgeage/icn_badgeage_from_terminal.1763364751747.png" border="0"  alt="" title=""  style='padding-right: 3px' ></td>
                    </tr>
                </table>
            </td>

            <td class="tabPair" colspan="0 %>">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tabPair" width="*" align="center" title="">10:40&nbsp;</td>
                        <td class="tabPair" width="15"><img name="" id="" src="/open/images/badgeage/icn_badgeage_from_terminal.1763364751747.png" border="0"  alt="" title=""  style='padding-right: 3px' ></td>
                    </tr>
                </table>
            </td>

            <td class="tabPair" colspan="0 %>">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tabPair" width="*" align="center" title="">10:47&nbsp;</td>
                        <td class="tabPair" width="15"><img name="" id="" src="/open/images/badgeage/icn_badgeage_from_terminal.1763364751747.png" border="0"  alt="" title=""  style='padding-right: 3px' ></td>
                    </tr>
                </table>
            </td>

            <td class="tabPair" colspan="0 %>">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tabPair" width="*" align="center" title="">12:02&nbsp;</td>
                        <td class="tabPair" width="15"><img name="" id="" src="/open/images/badgeage/icn_badgeage_from_terminal.1763364751747.png" border="0"  alt="" title=""  style='padding-right: 3px' ></td>
                    </tr>
                </table>
            </td>

            <td class="tabPair"><a href="javascript:void(0)" onclick="javascript:fcChangerOffset(4)"><img name="imagePlus" id="imagePlus" src="/open/img/navRight.1761908880000.png" border="0"  alt="" title=""  style='padding-right: 3px'></a></td>
        </tr>
    </table>
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
     * Get sample JSESSIONID cookie (real format from daryl.kelio.io)
     */
    public static function getSampleCookie(): string
    {
        return 'JSESSIONID=7609FB9D4BA5CBC343C73DA62522BFFE;Path=/open;Secure;HttpOnly';
    }

    /**
     * Get expected parsed hours from getHoursPage()
     * These match the real data from Martin's account (January 12-14, 2026)
     */
    public static function getExpectedParsedHours(): array
    {
        return [
            '12/01/2026' => ['08:30', '10:43', '10:47', '12:02'],
            '13/01/2026' => ['08:30', '10:35', '10:46', '12:16'],
            '14/01/2026' => ['08:30', '10:40', '10:47', '12:02'],
        ];
    }
}
