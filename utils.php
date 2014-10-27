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


/* The ISerialisable interface is implemented by classes which can serialise
 * themselves.
 */
interface ISerialisable
{
	public function serialise(&$mimeType, $returnBuffer = false, $request = null, $sendHeaders = null);
}

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
function eregansu_autoload_handler($name)
{
	global $AUTOLOAD, $AUTOLOAD_SUBST;
	
	if(isset($AUTOLOAD[strtolower($name)]))
	{
		$path = str_replace(array_keys($AUTOLOAD_SUBST), array_values($AUTOLOAD_SUBST), $AUTOLOAD[strtolower($name)]);
		if(defined('EREGANSU_DEBUG_AUTOLOAD') && EREGANSU_DEBUG_AUTOLOAD)
		{
			error_log('Autoloading: ' . $path);
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

function eregansu_error_handler($errno, $errstr, $errfile, $errline)
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

function eregansu_exception_handler($exception)
{
	if(php_sapi_name() != 'cli') echo '<pre>';
	echo "Uncaught exception:\n\n";
	echo $exception . "\n";
	die(1);
}

function eregansu_assertion_handler()
{
	throw new ErrorException('Assertion failed');
	die(1);
}
