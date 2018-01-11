<?php

/**
 * ownCloud - user_cas
 *
 * @author Sixto Martin <sixto.martin.garcia@gmail.com>
 * @author Felix Rupp <kontakt@felixrupp.com>
 *
 * @copyright Sixto Martin Garcia. 2012
 * @copyright Leonis. 2014 <devteam@leonis.at>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

$app = new \OCA\UserCAS\AppInfo\Application();
$c = $app->getContainer();

$enable = TRUE;

$script = $_SERVER['SCRIPT_FILENAME'];
$requestUri = $_SERVER['REQUEST_URI'];

if (in_array(basename($script), array('console.php', 'cron.php', 'public.php', 'remote.php', 'status.php', 'version.php')) || strpos($script, "ocs") || strpos($requestUri, "oc.js")) {
    $enable = FALSE;
}

if (\OCP\App::isEnabled($c->getAppName()) && !\OC::$CLI && $enable) {

    $enable = TRUE;

    $script = $_SERVER['SCRIPT_FILENAME'];
    $requestUri = $_SERVER['REQUEST_URI'];

    if (in_array(basename($script), array('console.php', 'cron.php', 'public.php', 'remote.php', 'status.php', 'version.php')) || strpos($script, "ocs") || strpos($requestUri, "oc.js")) {
        $enable = FALSE;
    }

    if (!\OC::$CLI && $enable) {

        $appService = $c->query('AppService');
        $userService = $c->query('UserService');
        $loggingService = $c->query("LoggingService");

        // Initialize app
        if (!$appService->isCasInitialized()) {

            try {
                $appService->init();
            } catch (\OCA\UserCAS\Exception\PhpCas\PhpUserCasLibraryNotFoundException $e) {

                $loggingService->write(\OCP\Util::FATAL, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());

                header("Location: " . $appService->getAbsoluteURL('/'));
                die();
            }
        }

        // Register User Backend
        // CAS is initialized here, through registerBackend() call
        $userService->registerBackend();

        // Register UserHooks
        $c->query('UserHooks')->register();

        // URL params
        $urlParams = "";
        if (isset($_GET['redirect_url'])) {

            $urlParams .= "?redirect_url=" . $_GET['redirect_url'];
        }

        // Register alternative LogIn
        $appService->registerLogIn($urlParams);

        // Check for enforced authentication
        if ($appService->isEnforceAuthentication() && (!isset($_GET["cas_enforce_authentication"]) || (isset($_GET["cas_enforce_authentication"]) && $_GET["cas_enforce_authentication"] === '0'))) {

            if (is_string($urlParams) && strlen($urlParams) > 0) {

                $urlParams .= "&cas_enforce_authentication=1";
            } else {
                $urlParams .= "?cas_enforce_authentication=1";
            }

            $loggingService->write(\OCP\Util::DEBUG, 'Enforce Authentication was: ' . $appService->isEnforceAuthentication());

            if (!\phpCAS::isAuthenticated()) {

                $loggingService->write(\OCP\Util::DEBUG, 'Enforce Authentication was on and phpCAS is not authenticated. Redirecting to CAS Server.');

                header("Location: " . $appService->linkToRouteAbsolute($c->getAppName() . '.authentication.casLogin') . $urlParams);
                die();
            }
        }
    }
}