<?php

/* Copyright 2009-2013 Mo McRoberts.
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

define('__EREGANSU__', 'develop'); /* %%version%% */

if(!defined('PHP_VERSION_ID'))
{
    $php_version = explode('.', PHP_VERSION);
    define('PHP_VERSION_ID', ($php_version[0] * 10000 + $php_version[1] * 100 + $php_version[2]));
}

if(defined('WP_CONTENT_URL') && defined('ABSPATH'))
{
	/* If we're being included inside WordPress, just perform
	 * minimal amounts of setup.
	 */
	define('EREGANSU_MINIMAL_CORE', true);
	define('INSTANCE_ROOT', ABSPATH);
	define('PUBLIC_ROOT', ABSPATH);
	define('CONFIG_ROOT', INSTANCE_ROOT . 'config/');
	define('PLATFORM_ROOT', INSTANCE_ROOT . 'eregansu/');
	define('PLATFORM_LIB', PLATFORM_ROOT . 'lib/');
	define('MODULES_ROOT', INSTANCE_ROOT . 'app/');
	
	global $MODULE_ROOT;
	
	if(!isset($MODULE_ROOT))
	{
		$MODULE_ROOT = MODULES_ROOT;	
	}
	return true;
}
if(defined('EREGANSU_MINIMAL_CORE'))
{
	return true;
}

$EREGANSU_MODULE_MAP['url'] = 'uri';
$EREGANSU_MODULE_MAP['xmlns'] = 'uri';
if(!isset($EREGANSU_MODULES)) $EREGANSU_MODULES = array();

if(!defined('PUBLIC_ROOT'))
{
	define('PUBLIC_ROOT', defined('INSTANCE_ROOT') ? INSTANCE_ROOT : ((isset($_SERVER['SCRIPT_FILENAME']) ? dirname(realpath($_SERVER['SCRIPT_FILENAME'])) : realpath(dirname(__FILE__ ) . '/../../')) . '/'));
}
if(!defined('INSTANCE_ROOT'))
{
	define('INSTANCE_ROOT', PUBLIC_ROOT);
}
if(!defined('PLATFORM_ROOT'))
{
	define('PLATFORM_ROOT', realpath(dirname(__FILE__) . '/..') . '/');
} 
if(!defined('PLATFORM_LIB'))
{
	define('PLATFORM_LIB', PLATFORM_ROOT . 'lib/');
}
if(!defined('CONFIG_ROOT'))
{
	define('CONFIG_ROOT', INSTANCE_ROOT . 'config/');
}
if(!defined('MODULES_ROOT'))
{
	if(defined('MODULES_PATH'))
	{
		define('MODULES_ROOT', INSTANCE_ROOT . MODULES_PATH . '/');
	}
	else
	{
		define('MODULES_ROOT', INSTANCE_ROOT . 'app/');
	}
}
if(!defined('PLUGINS_ROOT')) define('PLUGINS_ROOT', INSTANCE_ROOT . 'plugins/');

$MODULE_ROOT = MODULES_ROOT;

/**
 * @var $AUTOLOAD_SUBST
 * @brief Substitutions used by the class auto-loader
 *
 * $AUTOLOAD_SUBST is an associative array of substitutions which are applied to
 * paths in $AUTOLOAD when the class auto-loader is invoked.
 *
 * By default it contains the following substitutions:
 *
 * - \c ${lib} The filesystem path to the Eregansu Core Library
 * - \c ${instance} The value of the INSTANCE_ROOT definition
 * - \c ${platform} The value of the PLATFORM_ROOT definition
 * - \c ${modules} The value of the MODULES_ROOT definition
 * - \c ${module} The filesystem path of the current module
 */
$AUTOLOAD_SUBST = array();
$AUTOLOAD_SUBST['${lib}'] = PLATFORM_LIB;
$AUTOLOAD_SUBST['${instance}'] = INSTANCE_ROOT;
$AUTOLOAD_SUBST['${modules}'] = MODULES_ROOT;
$AUTOLOAD_SUBST['${module}'] =& $MODULE_ROOT;

/**
 * @var $AUTOLOAD
 * @brief Mapping of class names to paths used by the class auto-loader
 *
 * $AUTOLOAD is an associative array, where the keys are all-lowercase
 * class names and the values are filesystem paths (which may contain
 * substitutions as per $AUTOLOAD_SUBST).
 *
 * When the class auto-loader is invoked, it checks the contents of
 * $AUTOLOAD and if a match is found the specified file is loaded.
 *
 * The $AUTOLOAD array is initialised with the classes which make up
 * the Eregansu Core Library.
 */
 
$AUTOLOAD = array(
	/* Interfaces */
	'icache' => dirname(__FILE__) . '/cache.php',
	'iobservable' => dirname(__FILE__) . '/observer.php',
	'ivfs' => dirname(__FILE__) . '/uri.php',
	/* Classes */
	'asn1' => dirname(__FILE__) . '/asn1.php',
	'base32' => dirname(__FILE__) . '/base32.php',
	'cache' => dirname(__FILE__) . '/cache.php',
	'clirequest' => dirname(__FILE__) . '/cli.php',
	'csvimport' => dirname(__FILE__) . '/csv-import.php',
	'eregansudatetime' => dirname(__FILE__) . '/date.php',
	'httprequest' => dirname(__FILE__) . '/request.php',
	'mime' => dirname(__FILE__) . '/mime.php',	
	'persistentsession' => dirname(__FILE__) . '/session.php',
	'rdf' => dirname(__FILE__) . '/rdf.php',
	'rdfxmlstreamparser' => dirname(__FILE__) . '/rdfxmlstream.php',
	'request' => dirname(__FILE__) . '/request.php',
	'session' => dirname(__FILE__) . '/session.php',
	'transientsession' => dirname(__FILE__) . '/session.php',
	'uri' => dirname(__FILE__) . '/uri.php',
	'url' => dirname(__FILE__) . '/uri.php',
	'uuid' => dirname(__FILE__) . '/uuid.php',
	'xmlparser' => dirname(__FILE__) . '/xml.php',
	'xmlns' => dirname(__FILE__) . '/uri.php',
	);

if(function_exists('curl_init'))
{
	$AUTOLOAD['curl'] = dirname(__FILE__) . '/curl.php';
	$AUTOLOAD['curlcache'] = dirname(__FILE__) . '/curl.php';
	$AUTOLOAD['curlheaders'] = dirname(__FILE__) . '/curl.php';
}
if(function_exists('openssl_pkey_get_public'))
{
	$AUTOLOAD['publickey'] = dirname(__FILE__) . '/pk.php';
	$AUTOLOAD['privatekey'] = dirname(__FILE__) . '/pk.php';
	$AUTOLOAD['rsapublickey'] = dirname(__FILE__) . '/pk.php';
	$AUTOLOAD['rsaprivatekey'] = dirname(__FILE__) . '/pk.php';
}

/* Configure a consistent environment */
umask(022);
error_reporting(E_ALL|E_STRICT|E_RECOVERABLE_ERROR);
ini_set('display_errors', 'On');
if(function_exists('set_magic_quotes_runtime'))
{
	@set_magic_quotes_runtime(0);
}
ini_set('session.auto_start', 0);
ini_set('default_charset', 'UTF-8');
ini_set('arg_separator.output', ';');
if(function_exists('mb_regex_encoding')) mb_regex_encoding('UTF-8');
if(function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');
putenv('TZ=UTC');
ini_set('date.timezone', 'UTC');

/* Ensure utilities and handlers are available */
require_once(dirname(__FILE__) . '/utils.php');

/* Install Eregansu handlers (see utils.php) for errors, exceptions,
 * assertions and autoloading
 */
set_error_handler('eregansu_error_handler');
set_exception_handler('eregansu_exception_handler');
assert_options(ASSERT_QUIET_EVAL, true);
assert_options(ASSERT_CALLBACK, 'eregansu_assertion_handler');

if(function_exists('spl_autoload_register'))
{
	spl_autoload_register('eregansu_autoload_handler');
}
else
{
	function __autoload($name)
	{
		return eregansu_autoload_handler($name);
	}
}

$VFS = array();
