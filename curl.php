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

if(function_exists('curl_init'))
{
	if(!defined('CURLOPT_PROTOCOLS')) define('CURLOPT_PROTOCOLS', null);
	if(!defined('CURLOPT_REDIR_PROTOCOLS')) define('CURLOPT_REDIR_PROTOCOLS', null);
	
	if(!defined('CURLPROTO_HTTP')) define('CURLPROTO_HTTP', null);
	if(!defined('CURLPROTO_HTTPS')) define('CURLPROTO_HTTPS', null);
	if(!defined('CURLPROTO_FTP')) define('CURLPROTO_FTP', null);
	if(!defined('CURLPROTO_FTPS')) define('CURLPROTO_FTPS', null);
	if(!defined('CURLPROTO_SCP')) define('CURLPROTO_SCP', null);
	if(!defined('CURLPROTO_SFTP')) define('CURLPROTO_SFTP', null);
	if(!defined('CURLPROTO_TELNET')) define('CURLPROTO_TELNET', null);
	if(!defined('CURLPROTO_LDAP')) define('CURLPROTO_LDAP', null);
	if(!defined('CURLPROTO_LDAPS')) define('CURLPROTO_LDAPS', null);
	if(!defined('CURLPROTO_DICT')) define('CURLPROTO_DICT', null);
	if(!defined('CURLPROTO_FILE')) define('CURLPROTO_FILE', null);
	if(!defined('CURLPROTO_TFTP')) define('CURLPROTO_TFTP', null);
	if(!defined('CURLPROTO_ALL')) define('CURLPROTO_ALL', null);

	class Curl
	{
		const CLOSEPOLICY_LEAST_RECENTLY_USED = CURLCLOSEPOLICY_LEAST_RECENTLY_USED;
		const CLOSEPOLICY_OLDEST = CURLCLOSEPOLICY_OLDEST;
		
		const FTPAUTH_SSL = CURLFTPAUTH_SSL;
		const FTPAUTH_TLS = CURLFTPAUTH_TLS;
		const FTPAUTH_DEFAULT = CURLFTPAUTH_DEFAULT;
		
		const HTTP_VERSION_NONE = CURL_HTTP_VERSION_NONE;
		const HTTP_VERSION_1_0 = CURL_HTTP_VERSION_1_0;
		const HTTP_VERSION_1_1 = CURL_HTTP_VERSION_1_1;
		
		const AUTH_BASIC = CURLAUTH_BASIC;
		const AUTH_DIGEST = CURLAUTH_DIGEST;
		const AUTH_GSSNEGOTIATE = CURLAUTH_GSSNEGOTIATE;
		const AUTH_NTLM = CURLAUTH_NTLM;
		const AUTH_ANY = CURLAUTH_ANY;
		const AUTH_ANYSAFE = CURLAUTH_ANYSAFE;
		
		const PROTO_HTTP = CURLPROTO_HTTP;
		const PROTO_HTTPS = CURLPROTO_HTTPS;
		const PROTO_FTP = CURLPROTO_FTP;
		const PROTO_FTPS = CURLPROTO_FTPS;
		const PROTO_SCP = CURLPROTO_SCP;
		const PROTO_SFTP = CURLPROTO_SFTP;
		const PROTO_TELNET = CURLPROTO_TELNET;
		const PROTO_LDAP = CURLPROTO_LDAP;
		const PROTO_LDAPS = CURLPROTO_LDAPS;
		const PROTO_DICT = CURLPROTO_DICT;
		const PROTO_FILE = CURLPROTO_FILE;
		const PROTO_TFTP = CURLPROTO_TFTP;
		const PROTO_ALL = CURLPROTO_ALL;
		
		const PROXY_HTTP = CURLPROXY_HTTP;
		const PROXY_SOCKS5 = CURLPROXY_SOCKS5;
		
		const TIMECOND_IFMODSINCE = CURL_TIMECOND_IFMODSINCE;
		const TIMECOND_IFUNMODSINCE = CURL_TIMECOND_IFUNMODSINCE;
		
		/* Associative array of authentication details per URL base.
		 * e.g., 'http://example.com/' => 'user:secret'
		 */
		public static $authData = array();
		
		protected static $boolProps = array(
			'autoReferer' => CURLOPT_AUTOREFERER,
			'autoReferrer' => CURLOPT_AUTOREFERER,
			'cookieSession' => CURLOPT_COOKIESESSION,
			'crlf' => CURLOPT_CRLF,
			'dnsUseGlobalCache' => CURLOPT_DNS_USE_GLOBAL_CACHE,
			'failOnError' => CURLOPT_FAILONERROR,
			'fetchFileTime' => CURLOPT_FILETIME,
			'followLocation' => CURLOPT_FOLLOWLOCATION,
			'forbidReuse' => CURLOPT_FORBID_REUSE,
			'freshConnect' => CURLOPT_FRESH_CONNECT,
			'ftpUseEPRT' => CURLOPT_FTP_USE_EPRT,
			'ftpUseEPSV' => CURLOPT_FTP_USE_EPSV,
			'ftpAppend' => CURLOPT_FTPAPPEND,
			'ftpASCII' => CURLOPT_TRANSFERTEXT,
			'ftpListOnly' => CURLOPT_FTPLISTONLY,
			'fetchHeaders' => CURLOPT_HEADER,
	/*		'trackRequestString' => CURLINFO_HEADER_OUT, */
			'httpGET' => CURLOPT_HTTPGET,
			'httpProxyTunnel' => CURLOPT_HTTPPROXYTUNNEL,
	//		'mute' => CURLOPT_MUTE,
			'netrc' => CURLOPT_NETRC,
			'noBody' => CURLOPT_NOBODY,
			'noProgress' => CURLOPT_NOPROGRESS,
			'noSignal' => CURLOPT_NOSIGNAL,
			'httpPOST' => CURLOPT_POST,
			'httpPUT' => CURLOPT_PUT,
			'returnTransfer' => CURLOPT_RETURNTRANSFER,
			'sslVerifyPeer' => CURLOPT_SSL_VERIFYPEER,
			'transferText' => CURLOPT_TRANSFERTEXT,
			'unrestrictedAuth' => CURLOPT_UNRESTRICTED_AUTH,
			'upload' => CURLOPT_UPLOAD,
			'verbose' => CURLOPT_VERBOSE,
			'trackRequestString' => CURLINFO_HEADER_OUT,
		);
		
		protected static $intProps = array(
			'bufferSize' => CURLOPT_BUFFERSIZE,
			'closePolicy' => CURLOPT_CLOSEPOLICY,
			'connectTimeout' => CURLOPT_CONNECTTIMEOUT,
			'connectTimeoutMS' => CURLOPT_CONNECTTIMEOUT_MS,
			'dnsCacheTimeout' => CURLOPT_DNS_CACHE_TIMEOUT,
			'ftpSSLAuth' => CURLOPT_FTPSSLAUTH,
			'httpVersion' => CURLOPT_HTTP_VERSION,
			'httpAuth' => CURLOPT_HTTPAUTH,
			'inFileSize' => CURLOPT_INFILESIZE,
			'lowSpeedLimit' => CURLOPT_LOW_SPEED_LIMIT,
			'lowSpeedTime' => CURLOPT_LOW_SPEED_TIME,
			'maxConnects' => CURLOPT_MAXCONNECTS,
			'maxRedirs' => CURLOPT_MAXREDIRS,
			'port' => CURLOPT_PORT,
			'protocols' => CURLOPT_PROTOCOLS,
			'proxyAuth' => CURLOPT_PROXYAUTH,
			'proxyPort' => CURLOPT_PROXYPORT,
			'proxyType' => CURLOPT_PROXYTYPE,
			'redirProtocols' => CURLOPT_REDIR_PROTOCOLS,
			'resumeFrom' => CURLOPT_RESUME_FROM,
			'sslVerifyHost' => CURLOPT_SSL_VERIFYHOST,
			'sslVersion' => CURLOPT_SSLVERSION,
			'timeCondition' => CURLOPT_TIMECONDITION,
			'timeout' => CURLOPT_TIMEOUT,
			'timeoutMs' => CURLOPT_TIMEOUT_MS,
			'timeValue' => CURLOPT_TIMEVALUE,
		);
		
		protected static $strProps = array(
			'caInfo' => CURLOPT_CAINFO,
			'caPath' => CURLOPT_CAPATH,
			'cookie' => CURLOPT_COOKIE,
			'cookieFile' => CURLOPT_COOKIEFILE,
			'cookieJar' => CURLOPT_COOKIEJAR,
			'customRequest' => CURLOPT_CUSTOMREQUEST,
			'egdSocket' => CURLOPT_EGDSOCKET,
			'encoding' => CURLOPT_ENCODING,
			'ftpPort' => CURLOPT_FTPPORT,
			'interface' => CURLOPT_INTERFACE,
			'krb4Level' => CURLOPT_KRB4LEVEL,
			'postFields' => CURLOPT_POSTFIELDS,
			'proxy' => CURLOPT_PROXY,
			'proxyAuthData' => CURLOPT_PROXYUSERPWD,
			'randomFile' => CURLOPT_RANDOM_FILE,
			'range' => CURLOPT_RANGE,
			'referer' => CURLOPT_REFERER,
			'referrer' => CURLOPT_REFERER,
			'sslCipherList' => CURLOPT_SSL_CIPHER_LIST,
			'sslCert' => CURLOPT_SSLCERT,
			'sslCertPassword' => CURLOPT_SSLCERTPASSWD,
			'sslCertType' => CURLOPT_SSLCERTTYPE,
			'sslEngine' => CURLOPT_SSLENGINE,
			'sslEngineDefault' => CURLOPT_SSLENGINE_DEFAULT,
			'sslKey' => CURLOPT_SSLKEY,
			'sslKeyPassword' => CURLOPT_SSLKEYPASSWD,
			'sslKeyType' => CURLOPT_SSLKEYTYPE,
			'url' => CURLOPT_URL,
			'userAgent' => CURLOPT_USERAGENT,
			'authData' => CURLOPT_USERPWD,
		);
		
		protected static $arrayProps = array(
			'http200Aliases' => CURLOPT_HTTP200ALIASES,
			'headers' => CURLOPT_HTTPHEADER,
			'postQuote' => CURLOPT_POSTQUOTE,
			'quote' => CURLOPT_QUOTE,
		);
		
		protected $handle;
		protected $options = array();
		protected $info = null;

		protected $cachedInfo = null;

		/* The ICache instance to use, if any */
		public $cache;
		/* Whether error responses should be cached or not */
		public $cacheErrors = false;
		public $headers;
		public $receivedHeaders = array();

		public static function version()
		{
			return curl_version();
		}
		
		public function __construct($url = null)
		{
			global $CURL_AUTH;

			if($url !== null)
			{
				$url = strval($url);
			}
			if(isset($CURL_AUTH) && is_array($CURL_AUTH))
			{
				foreach($CURL_AUTH as $domain => $auth)
				{
					if(!isset(self::$authData[$domain]))
					{
						self::$authData[$domain] = $auth;
					}
				}
			}
			$CURL_AUTH = null;
			$this->handle = curl_init($url);
			$this->options['url'] = $url;
			$this->options['http200Aliases'] = $this->options['headers'] = $this->options['postQuote'] = $this->options['quote'] = array();
			foreach(self::$boolProps as $k => $i)
			{
				$this->options[$k] = false;
			}
			$this->options['dnsUseGlobalCache'] = true;
			$this->options['httpGET'] = true;
			if(defined('CURL_ALWAYS_VERBOSE') && CURL_ALWAYS_VERBOSE)
			{
				$this->__set('verbose', true);
			}
			$this->headers = new CurlHeaders();
			curl_setopt($this->handle, CURLOPT_HEADERFUNCTION, array($this, 'headerFunction'));
		}

		public function __destruct()
		{
			if(isset($this->handle))
			{
				curl_close($this->handle);
			}
		}

		public function headerFunction($curl, $data)
		{
			$lines = explode("\n", $data);
			foreach($lines as $line)
			{
				$line = rtrim($line, "\r");
				if(!count($this->receivedHeaders))
				{
					if(strncmp($line, 'HTTP/', 5))
					{
						error_log("Received headers don't begin with HTTP response: $line");
					}
					$this->receivedHeaders['status'] = $line;
				}
				else
				{
					$header = explode(':', $line, 2);
					$this->receivedHeaders[$header[0]] = ltrim(@$header[1]);
				}
			}
			return strlen($data);
		}
		
		public function close()
		{
			if(isset($this->handle))
			{
				curl_close($this->handle);
			}
			$this->handle = null;
		}
		
		protected function authDataForURL($url)
		{
			$len = 0;
			$string = null;
			$slen = strlen($url);
			foreach(self::$authData as $base => $authData)
			{
				$l = strlen($base);
				if($l > $len && $l <= $slen && !strncmp($base, $url, $l))
				{
					$len = $l;
					$string = $authData;
				}
			}
			return $string;
		}
					
		/* Force a fetch without use of the cache */
		public function execNoCache()
		{
			if(!$this->handle)
			{
				trigger_error('Curl::exec() - cannot execute a request which has been closed', E_USER_ERROR);
				return false;
			}
			$this->receivedHeaders = array();
			if(!isset($this->options['authData']))
			{
				if(null !== ($auth = $this->authDataForURL($this->options['url'])))
				{
					$this->__set('authData', $auth);
				}
			}
			if(is_object($this->headers))
			{
				$this->headers->apply($this->handle);
			}
			else if(is_array($this->headers))
			{
				curl_setopt($this->handle, CURLOPT_HTTPHEADER, $this->headers);
			}
			else if($this->headers !== null)
			{
				trigger_error('Curl::exec() - $this->headers is non-null but is not an array or a CurlHeaders instance', E_USER_NOTICE);
			}
			$r = curl_exec($this->handle);
			$this->info = curl_getinfo($this->handle);
			$this->info['content_location'] = null;
			foreach($this->receivedHeaders as $k => $v)
			{
				if(!strcasecmp($k, 'Content-Location'))
				{
					$this->info['content_location'] = $v;
				}
			}
			return $r;
		}
		
		public function exec()
		{
			$this->info = null;
			$fetch = true;
			$store = true;
			$info = null;
			$modifiedSince = null;
			if(!$this->handle)
			{
				trigger_error('Curl::exec() - cannot execute a request which has been closed', E_USER_ERROR);
				return false;
			}
			$this->receivedHeaders = array();
			if(!isset($this->options['authData']))
			{
				if(null !== ($auth = $this->authDataForURL($this->options['url'])))
				{
					$this->__set('authData', $auth);
				}
			}
			if(is_object($this->headers))
			{
				$this->headers->apply($this->handle);
			}
			else if(is_array($this->headers))
			{
				curl_setopt($this->handle, CURLOPT_HTTPHEADER, $this->headers);
			}
			else if($this->headers !== null)
			{
				trigger_error('CurlCache::exec() - $this->headers is non-null but is not an array or a CurlHeaders instance', E_USER_NOTICE);
			}
			/* Generate the hash which is used for fingerprinting from a subset of
			 * the curl options (which includes the verb and URL)
			 */
			$hashedOptions = $this->options;
			/* Exclude some options which don't affect the transfer itself
			 * from the request fingerprinting.
			 */
			$hashedOptions['forbidReuse'] = false;
			$hashedOptions['dnsUseGlobalCache'] = true;
			$hashedOptions['verbose'] = false;
			$hash = md5(json_encode($hashedOptions));
			if($this->cache === null)
			{
				/* If there's no cache file, there's nowhere to cache the
				 * response.
				 */
				$store = false;
				$mtime = null;
			}
			else
			{
				$mtime = $this->cache->modified($hash);
			}
			if(empty($this->options['httpGET']))
			{
				/* If it's not a GET request, always fetch */
				$fetch = true;
				$store = false;
			}
			if($mtime !== null)
			{
				/* If the cache file exists, check the timestamp exceeds
				 * the mininum cache time and determine what the
				 * If-Modified-Since header would be.
				 */
				$modifiedSince = strftime('%a, %e %b %Y %H:%M:%S UTC', $mtime);
				if($this->cache->min > 0)
				{
					if($mtime + $this->cache->min > time())
					{
						/* The resource hasn't reached its minimum cache-time
						 * threshold yet.
						 */
						$fetch = false;
					}
					if($this->cache->max !== null && $mtime + $this->cache->min < time())
					{
						/* We've exceeded the maximum cache time, and so
						   must refresh
						*/
						$modifiedSince = null;
						$fetch = true;
					}
				}
			}
			if($modifiedSince !== null)
			{
				if(!isset($this->headers['If-Modified-Since']))
				{
					$this->headers['If-Modified-Since'] = $modifiedSince;
					if(is_object($this->headers))
					{
						$this->headers->apply($this->handle);
					}
					else if(is_array($this->headers))
					{
						curl_setopt($this->handle, CURLOPT_HTTPHEADER, $this->headers);
					}
				}
			}
			if($fetch)
			{
				$buf = curl_exec($this->handle);
				$this->info = curl_getinfo($this->handle);
				$this->info['content_location'] = null;
				foreach($this->receivedHeaders as $k => $v)
				{
					if(!strcasecmp($k, 'Content-Location'))
					{
						$this->info['content_location'] = $v;
					}
				}
				$info = $this->info;
				if($info['http_code'] == 304 && $modifiedSince !== null)
				{
					/* The document is unmodified */
					$buf = $this->cache->payload($hash);
					$info = $this->cache->meta($hash);
					$info['hash'] = $hash;
				}
				else if($store && ($buf !== false || $this->cacheErrors))
				{
					/* Store the document in the cache file */
					$f = $this->cache->stream($hash, 'wb');
					fwrite($f, $buf);
					fclose($f);
					$this->cache->setMeta($hash, $info);
					$info['fetched'] = true;
					$info['hash'] = $hash;
				}
			}
			else if($mtime !== null)
			{
				/* We didn't need to fetch to begin with, just return the
				 * cached resource.
				 */
				$buf = $this->cache->payload($hash);
				$info = $this->cache->meta($hash);
				$info['hash'] = $hash;
			}
			else
			{
				/* If all else fails, bail */
				$buf = $info = null;
			}
			$this->info = $info;
			return $buf;
		}
		
		public function __get($name)
		{
			if($name == 'version')
			{
				return curl_version();
			}
			if(!$this->handle)
			{
				trigger_error('Curl - cannot retrieve information for a request which has been closed', E_USER_NOTICE);
				return null;
			}
			if($name == 'errno')
			{
				return curl_errno($this->handle);
			}
			if($name == 'error')
			{
				return curl_error($this->handle);
			}
			if($name == 'info')
			{
				return $this->info;
			}
			if($name == 'headers')
			{
				return $this->headers;
			}
			if(isset($this->options[$name]))
			{
				return $this->options[$name];
			}
			return null;
		}
		
		public function __set($name, $value)
		{
			if($name == 'version' || $name == 'errno' || $name == 'error' || $name == 'info')
			{
				trigger_error('Curl - attempt to set read-only property "' . $name . '"', E_USER_NOTICE);
				return;
			}
			if(!$this->handle)
			{
				trigger_error('Curl - cannot set information for a request which has been closed', E_USER_NOTICE);
				return null;
			}
			if($name == 'headers')
			{
				$this->headers = $value;
			}
			/* Deal with mutually-exclusive options */
			if($name == 'httpGET' || $name == 'httpPOST' || $name == 'httpPUT' || $name == 'customRequest')
			{
				$this->options['httpGET'] = $this->options['httpPOST'] = $this->options['httpPUT'] = false;
				$this->options['customRequest'] = null;
			}
			if(is_object($value))
			{
				$value = strval($value);
			}
			$this->options[$name] = $value;
			if(isset(self::$boolProps[$name]))
			{
				curl_setopt($this->handle, self::$boolProps[$name], $value);
				return;
			}
			if(isset(self::$intProps[$name]))
			{
				curl_setopt($this->handle, self::$intProps[$name], $value);
				return;
			}
			if(isset(self::$strProps[$name]))
			{
				curl_setopt($this->handle, self::$strProps[$name], $value);
				return;
			}
			if(isset(self::$arrayProps[$name]))
			{
				curl_setopt($this->handle, self::$arrayProps[$name], $value);
				return;
			}
			trigger_error('attempt to set undefined Curl option ' . $name, E_USER_WARNING);
		}		
	}

	class CurlCache extends Curl
	{
		public function __construct($url = null)
		{		
			parent::__construct($url);
			require_once(dirname(__FILE__) . '/cache.php');
			if($this->cache === null)
			{
				$this->cache = new DiskCache('curl');
			}
		}
	}
}

/*
 * The internal $headers array looks like this:
 *
 * $headers['content-type']['@name'] = 'Content-type';
 * $headers['content-type'][0] = 'text/plain; charset=UTF-8';
 * ...
 *
 * Note that the value of the @name property is the last name used in a
 * call to CurlHeaders::offsetSet()
 */
 
class CurlHeaders implements ArrayAccess
{
	protected $headers = array();

	public function apply(&$handle)
	{
		curl_setopt($handle, CURLOPT_HTTPHEADER, $this->asList());
	}

	public function __toString()
	{
		return implode("\r\n", $this->list());
	}

	public function asList()
	{
		$list = array();
		
		foreach($this->headers as $values)
		{
			$name = $values['@name'];
			foreach($values as $k => $v)
			{
				if($k === '@name')
				{
					continue;
				}
				$list[] = $name . ': ' . $v;
			}
		}
		return $list;
	}

	/* $inst[] = 'Content-type: text/plain';
	 * $inst['Content-type'] = 'text/plain';
	 * 
	 * The former always appends, the latter always overwrites. The
	 * latter can accept an array of values.
	 *
	 * Keys are case-insensitive. Trailing whitespace will be removed.
	 */
	public function offsetSet($name, $value)
	{
		if($name === null)
		{
			$s = explode(':', $value, 2);
			if(!isset($s[1]))
			{
				trigger_error('Malformed header (' . $value . ') in CurlHeaders::offsetSet()', E_USER_WARNING);
				return;
			}
			$key = strtolower($s[0]);
			$value = $s[1];
		}
		$key = strtolower($name);
		$this->headers[$key]['@name'] = $name;
		$this->headers[$key][] = trim($value);
	}

	/* Note that this function returns an array of values if more than one
	 * header is set for the supplied key.
	 */
	public function offsetGet($name)
	{
		$key = strtolower($name);
		if(!isset($this->headers[$key]) || count($this->headers[$key]) < 2)
		{
			return null;
		}
		$entries = $this->headers[$key];
		unset($entries['@name']);
		if(count($entries) == 1)
		{
			return $entries[0];
		}
		return $entries;
	}
	
	public function offsetUnset($name)
	{
		$key = strtolower($name);
		unset($this->headers[$key]);
	}

	public function offsetExists($name)
	{
		$key = strtolower($name);
		if(isset($this->headers[$key]) && count($this->headers[$key]) > 1)
		{
			return true;
		}
		return false;
	}
}
