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
}


/**
 * The ISerialisable interface is implemented by classes which can serialise
 * themselves.
 */
interface ISerialisable
{
	public function serialise(&$mimeType, $returnBuffer = false, $request = null, $sendHeaders = null /* true if (!$returnBuffer && $request) */);
}

/* EREGANSU_MINIMAL_CORE is defined when the framework is included inside
 * WordPress
 */
if(defined('EREGANSU_MINIMAL_CORE'))
{
	return true;
}

$EREGANSU_MODULE_MAP['url'] = 'uri';
$EREGANSU_MODULE_MAP['xmlns'] = 'uri';
if(!isset($EREGANSU_MODULES)) $EREGANSU_MODULES = array();

/**
 * Determine whether an object or array is traversable as an array
 *
 * The \f{is_arrayish} function is analogous to PHP’s \f{is_array} function, except
 * that it also returns \c{true} if \p{$var} is an instance of a class implementing
 * the \c{Traversable} interface.
 *
 * @type bool
 * @param[in] mixed $var A variable to test
 * @return \c{true} if \p{$var} can be traversed using \f{foreach}, \c{false} otherwise
 */
function is_arrayish($var)
{
	return is_array($var) || (is_object($var) && $var instanceof Traversable);
}

/**
 * Parse a string and return the boolean value it represents
 *
 * @type bool
 * @param[in] string $str a string representation of a boolean value
 * @return The boolean value \p{$str} represents
 */
function parse_bool($str)
{
	$str = trim(strtolower($str));
	if($str == 'yes' || $str == 'on' || $str == 'true') return true;
	if($str == 'no' || $str == 'off' || $str == 'false') return false;
	return !empty($str);
}

/**
 * @brief HTML-escape a string and output it
 *
 * \f{e} accepts a string and outputs it after ensuring any characters which have special meaning
 * in XML or HTML documents are properly escaped.
 *
 * @type void
 * @param[in] string $str The string to HTML-escape
 */
function e($str)
{
	echo _e($str);
}

/**
 * @brief HTML-escape a string and return it.
 *
 * \f{_e} accepts a string and returns it after ensuring any characters which have special meaning
 * in XML or HTML documents are properly escaped. The resultant string is suitable for inclusion
 * in attribute values or element contents.
 *
 * @type string
 * @param[in] string $str The string to HTML-escape
 * @return The escaped version of \p{$str}
 */
 
function _e($str)
{
	return str_replace('&apos;', '&#39;', str_replace('&quot;', '&#34;', htmlspecialchars(strval($str), ENT_QUOTES, 'UTF-8')));
}

/**
 * Write text to the output stream, followed by a newline.
 * @return void
 * @varargs
 */

function writeLn()
{
	$args = func_get_args();
	echo implode(' ', $args) . "\n";
}


/**
 * Include one or more Eregansu modules
 *
 * The \f{uses} function loads one or more Eregansu modules. You can specify as
 * many modules as are needed, each as a separate parameter.
 *
 * @type void
 * @param[in] string $module,... The name of a module to require. For example, \l{base32}.
 */

function uses($module /* ... */)
{
	global $EREGANSU_MODULE_MAP, $EREGANSU_MODULES;

	$_modules = func_get_args();
	foreach($_modules as $_mod)
	{
		$_mod = isset($EREGANSU_MODULE_MAP[$_mod]) ? $EREGANSU_MODULE_MAP[$_mod] : $_mod;
		$_mod = isset($EREGANSU_MODULES[$_mod]) ? $EREGANSU_MODULES[$_mod] : dirname(__FILE__) . '/' . $_mod . '.php';
		require_once($_mod);
	}
}

/**
 * @brief Callback handler invoked by PHP when an undefined classname is referenced
 * @internal
 */
function autoload_handler($name)
{
	global $AUTOLOAD, $AUTOLOAD_SUBST;
	
	if(isset($AUTOLOAD[strtolower($name)]))
	{
		$path = str_replace(array_keys($AUTOLOAD_SUBST), array_values($AUTOLOAD_SUBST), $AUTOLOAD[strtolower($name)]);
		if(defined('EREGANSU_DEBUG_AUTOLOAD') && EREGANSU_DEBUG_AUTOLOAD)
		{
			echo "Autoloading <$path>\n";
		}
		require_once($path);
		return true;
	}
	return false;
}

/**
 * @brief Callback invoked by PHP when an error occurs
 * @internal
 */

function exception_error_handler($errno, $errstr, $errfile, $errline)
{
	$e = error_reporting();
	if(!$errno || ($e & $errno) != $errno) return;
	if(defined('EREGANSU_STRICT_ERROR_HANDLING') &&
	   ($errno & (E_WARNING|E_USER_WARNING|E_NOTICE|E_USER_NOTICE)))
	{
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		exit(1);
	}		
	if($errno & (E_ERROR|E_PARSE|E_CORE_ERROR|E_COMPILE_ERROR|E_USER_ERROR|E_RECOVERABLE_ERROR))
	{
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		exit(1);
	}
	return false;
}

function strict_error_handler($errno, $errstr, $errfile, $errline)
{
	$e = error_reporting();
	if(!$errno || ($e & $errno) != $errno) return;
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
	return false;
}

function exception_handler($exception)
{
	if(php_sapi_name() != 'cli') echo '<pre>';
	echo "Uncaught exception:\n\n";
	echo $exception . "\n";
	die(1);
}

function assertion_handler()
{
	throw new ErrorException('Assertion failed');
	die(1);
}

umask(007);
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
set_error_handler('exception_error_handler');
set_exception_handler('exception_handler');
assert_options(ASSERT_QUIET_EVAL, true);
assert_options(ASSERT_CALLBACK, 'assertion_handler');

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
	'asn1' => dirname(__FILE__) . '/asn1.php',
	'base32' => dirname(__FILE__) . '/base32.php',
	'clirequest' => dirname(__FILE__) . '/cli.php',
	'csvimport' => dirname(__FILE__) . '/csv-import.php',
	'curl' => dirname(__FILE__) . '/curl.php',
	'curlcache' => dirname(__FILE__) . '/curl.php',  
	'dbcore' => dirname(__FILE__) . '/db.php',
	'dbschema' => dirname(__FILE__) . '/dbschema.php',
	'mime' => dirname(__FILE__) . '/mime.php',	
	'rdf' => dirname(__FILE__) . '/rdf.php',
	'rdfxmlstreamparser' => dirname(__FILE__) . '/rdfxmlstream.php',
	'request' => dirname(__FILE__) . '/request.php',
	'httprequest' => dirname(__FILE__) . '/request.php',
	'session' => dirname(__FILE__) . '/session.php',
	'searchengine' => dirname(__FILE__) . '/searchengine.php',
	'searchindexer' => dirname(__FILE__) . '/searchengine.php',
	'uri' => dirname(__FILE__) . '/uri.php',
	'url' => dirname(__FILE__) . '/uri.php',
	'uuid' => dirname(__FILE__) . '/uuid.php',
	'xmlparser' => dirname(__FILE__) . '/xml.php',
	'xmlns' => dirname(__FILE__) . '/uri.php',
	);

if(function_exists('spl_autoload_register'))
{
	spl_autoload_register('autoload_handler');
}
else
{
	function __autoload($name)
	{
		return autoload_handler($name);
	}
}

$VFS = array();
