<?php

/* Copyright 2013 Mo McRoberts.
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

interface ICache
{
	/* Return the metadata for a key */
	public function meta($key);
	/* Return the last-modified timestamp for a key */
	public function modified($key);
	/* Return the entire payload for a key */
	public function payload($key);
	/* Open the payload as a stream (if possible) */
	public function stream($key, $mode = 'rb');
	/* Set the metadata for a key */
	public function setMeta($key, $metadata);
	/* Set the entire payload for a key */
	public function setBlob($key, $payload);
}

abstract class Cache implements ICache
{
	public $name;
	/* Minimum time objects should be cached for */
	public $min;
	/* Maximum time objects should be cached for */
	public $max;
	
	protected function __construct($name)
	{
		$this->name = $name;
		/* Apply defaults to cacheTime and cacheMax */
		if($this->min === null)
		{
			if(defined('CACHE_TIME'))
			{
				$this->min = intval(CACHE_TIME);
			}
			else
			{
				$this->min = 0;
			}
		}
		if($this->max === null)
		{
			if(defined('CACHE_MAX'))
			{
				$this->max = intval(CACHE_MAX);
			}
			else
			{
				$this->max = 0;
			}
		}
	}
}

class DiskCache extends Cache
{
	const CACHE_INFO_SUFFIX = '.json';
	const CACHE_PAYLOAD_SUFFIX = '.payload';
	
	protected $basePath;
	
	public function __construct($name)
	{
		parent::__construct($name);
		$constant = strtoupper($name) . '_CACHE_DIR';
		if(defined($constant))
		{
			$this->basePath = constant($constant);
		}
		else if(defined('CACHE_DIR'))
		{
			$this->basePath = CACHE_DIR . $name . '/';
		}
		else
		{
			$this->basePath = INSTANCE_ROOT . 'cache/' . $name . '/';
		}
		if(substr($this->basePath, -1) !== '/')
		{
			$this->basePath .= '/';
		}
	}
	
	/* Obtain the path for a key */
	protected function path($key)
	{
		if(!ctype_alnum($key))
		{
			throw new Exception('Cache keys should consist only of alphanumeric characters');
		}
		$key = strtolower($key);
		/* base path + "/" + key[0..1] + "/" + key[2..3] + "/" + key[0..n] */
		$dir0 = substr($key, 0, 2);
		$dir1 = substr($key, 2, 2);
		$base = $this->basePath . $dir0 . '/' . $dir1 . '/';
		if(!file_exists($base))
		{
			mkdir($base, 0777, true);
		}
		return $base . $key;
	}
	
	/* Return the metadata for a key */
	public function meta($key)
	{
		$path = $this->path($key) . self::CACHE_INFO_SUFFIX;
		if(file_exists($path))
		{
			return json_decode(file_get_contents($path), true);
		}
		return null;
	}
	
	/* Return the last-modified timestamp for a key */
	public function modified($key)
	{
		$path = $this->path($key) . self::CACHE_INFO_SUFFIX;
		if(file_exists($path))
		{
			$info = stat($path);
			return $info['mtime'];
		}
		return null;
	}
	
	/* Set the entire payload for a key */
	public function payload($key)
	{
		$path = $this->path($key) . self::CACHE_PAYLOAD_SUFFIX;
		if(file_exists($path))
		{
			return file_get_contents($path);
		}
		return null;
	}
	
	/* Open the payload as a stream (if possible) */
	public function stream($key, $mode = 'rb')
	{
		$path = $this->path($key) . self::CACHE_PAYLOAD_SUFFIX;
		return fopen($path, $mode);
	}
	
	/* Set the metadata for a key */
	public function setMeta($key, $metadata)
	{
		$path = $this->path($key) . self::CACHE_INFO_SUFFIX;
		if(!is_array($metadata) && !is_object($metadata))
		{
			return false;
		}
		return file_put_contents($path, json_encode($metadata)) === false ? false : true;
	}
		
	/* Set the entire payload for a key */
	public function setBlob($key, $payload)
	{
		$path = $this->path($key) . self::CACHE_INFO_SUFFIX;
		return file_put_contents($path, $payload) === false ? false : true;
	}
}
