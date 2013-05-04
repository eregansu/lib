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

abstract class Cache
{
	abstract public function meta($key);
	abstract public function modified($key);
	abstract public function blob($key);
	abstract public function setMeta($key, $metadata);
	abstract public function setBlob($key, $blob);
}

class FilesystemCache extends Cache
{
	protected $basePath;
	
	public function __construct($path)
	{
		if(substr($path, 0, 1) == '/')		
		{
			$this->basePath = $path;
		}
		else if(defined('CACHE_DIR'))
		{
			$this->basePath = CACHE_DIR . $path;
		}
		else
		{
			$this->basePath = INSTANCE_ROOT . 'cache/' . $path;
		}
		if(substr($this->basePath, -1) !== '/')
		{
			$this->basePath .= '/';
		}
	}
	
	public function blob($key)
	{
		if(file_exists($this->basePath . $key))
		{
			return file_get_contents($this->basePath . $key);
		}
		return null;
	}
	
	public function setBlob($key, $blob)
	{
		return file_put_contents($this->basePath . $key, $blob) === false ? false : true;
	}
	
	public function meta($key)
	{
		if(file_exists($this->basePath . $key . '.json'))
		{
			return json_decode(file_get_contents($this->basePath . $key . '.json'), true);
		}
		return null;
	}
	
	public function setMeta($key, $metadata)
	{
		if(!is_array($metadata) && !is_object($metadata))
		{
			return false;
		}
		return file_put_contents($this->basePath . $key . '.json', json_encode($metadata)) === false ? false : true;
	}

	public function modified($key)
	{
		if(file_exists($this->basePath . $key))
		{
			$info = stat($this->basePath . $key);
			return $info['mtime'];
		}
		return null;
	}
}
