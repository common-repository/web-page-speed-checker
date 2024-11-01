<?php
/*
Plugin Name: Web PageSpeed Checker
Description: Verifica que el score de pagespeed para las urls especificadas no esté por debajo del límite marcado en configuración.
Version: 1.0.1
Plugin URI: http://www.seocom.es/blog/webpage-speed-checker/
Author: David Garcia
Author URI: http://www.seocom.es/
*/

if (!defined('SC_WPS_PLUGIN_BASENAME'))
{
	define( 'SC_WPS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

    require_once dirname(__FILE__) . '/inc/define.php';

	require_once SC_WPS_GOOGLE_API_DIR . '/apiClient.php';
	require_once SC_WPS_GOOGLE_API_DIR . '/contrib/apiPagespeedonlineService.php';

	require_once SC_WPS_CLASSES_DIR . '/widget.php';	
	require_once SC_WPS_CLASSES_DIR . '/plugin.php';	
}
?>