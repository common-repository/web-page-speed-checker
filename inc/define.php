<?php
define('SC_WPS_VERSION', '1.0.1');
define('SC_WPS_PLUGIN_NAME', 'WebPageSpeed Checker' );
define('SC_WPS_PLUGIN_SHORTNAME', 'WPS Checker' );
define('SC_WPS_APPLICATION_NAME', 'WEBPAGESPEED_CHECKER');
define('SC_WPS_QUERYVAR_RUNPROCESS', 'wps_start');

define('SC_WPS_DIR', realpath(dirname(__FILE__) . '/..'));
define('SC_WPS_INC_DIR', SC_WPS_DIR . '/inc');
define('SC_WPS_TEMPLATES_DIR', SC_WPS_DIR . '/templates');
define('SC_WPS_CLASSES_DIR', SC_WPS_DIR . '/classes');
define('SC_WPS_GOOGLE_API_DIR', SC_WPS_CLASSES_DIR . '/google-api-php-client/src');

define('SC_WPS_HIGH_PRIORITY_LIMIT', 10);
define('SC_WPS_HIGH_MEDIUM_LIMIT', 4);

define('SC_WPS_CRON_HOOK', 'wps_cron_hook' );
define('SC_WPS_OPTIONS', 'WebPageSpeedChecker_Options' );
define('SC_WPS_ADMIN_PAGE', 'webpagespeed-checker.php' );

define('SC_WPS_HOME_WEBSITE', 'http://www.seocom.es/blog/webpage-speed-checker/' );

define('SC_WPS_GOOGLE_LOCALE', 'es_ES' );

define('SC_WPS_BAD_SCORE_STYLE', 'background:red;color:white;padding:2px;' );
define('SC_WPS_GOOD_SCORE_STYLE', 'background:green;color:white;padding:2px;' );
define('SC_WPS_EQUAL_SCORE_STYLE', 'padding:2px;' );

?>