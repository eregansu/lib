<?php

/* Copyright 2011-2013 Mo McRoberts.
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

require_once(dirname(__FILE__) . '/asn1.php');

abstract class PublicKey
{
	protected $resource;
	protected $info;
	protected $fingerprint;

	public static function publicKeyFromPEM($pem)
	{
		$resource = openssl_pkey_get_public($pem);
		if(is_resource($resource))
		{
			return self::publicKeyFromResource($resource);
		}
	}

	public static function publicKeyFromResource($resource)
	{
		$info = openssl_pkey_get_details($resource);
		switch($info['type'])
		{
		case OPENSSL_KEYTYPE_RSA:
			return new RSAPublicKey($resource, $info);
		case OPENSSL_KEYTYPE_DSA:
			return new DSAPublicKey($resource, $info);
		case OPENSSL_KEYTYPE_DH:
			return new DHPublicKey($resource, $info);
		}
	}

	protected function __construct($resource, $info = null)
	{
		$this->resource = $resource;
		if($info === null && is_resource($resource))
		{
			$info = openssl_pkey_get_details($resource);
		}
		$this->info = $info;
		$this->fingerprint();
	}

	protected function fingerprint($method = 'sha1')
	{
		$this->fingerprint = null;
		if(isset($this->info['key']))
		{
			$decoded = ASN1::decodePEM($this->info['key']);
			if(isset($decoded[0]['sequence']))
			{
				foreach($decoded[0]['sequence'] as $entry)
				{
					if($entry['type'] == 'BIT-STRING')
					{
						$this->fingerprint = hash($method, base64_decode($entry['value']));
						break;
					}
				}
			}
		}
	}

	public function __get($name)
	{
		if(isset($this->info))
		{
			if($name == 'key')
			{
				return $this->info['key'];
			}
			if($name == 'bits')
			{
				return $this->info['bits'];
			}
			if($name == 'type')
			{
				return $this->info['type'];
			}
		}
		if($name == 'fingerprint')
		{
			return $this->fingerprint;
		}
		return null;
	}
	
	public function __set($name, $value)
	{
	}

	public function __isset($name)
	{
		return false;
	}
}

abstract class PrivateKey
{
	public static function privateKeyFromPEM($pem, $passphrase = null)
	{
		$resource = openssl_get_privatekey($pem, $passphrase);
		if(is_resource($resource))
		{
			return self::privateKeyFromResource($resource);
		}
	}
	
	public static function privateKeyFromResource($resource)
	{
		$info = openssl_pkey_get_details($resource);
		switch($info['type'])
		{
		case OPENSSL_KEYTYPE_RSA:
			return new RSAPrivateKey($resource, $info);
		case OPENSSL_KEYTYPE_DSA:
			return new DSAPrivateKey($resource, $info);
		case OPENSSL_KEYTYPE_DH:
			return new DHPrivateKey($resource, $info);
		}
	}
}

class RSAPublicKey extends PublicKey
{
	public function __get($name)
	{
		if(isset($this->info['rsa']))
		{
			switch($name)
			{
			case 'n':
				return $this->info['rsa']['n'];
			case 'e':
				return $this->info['rsa']['e'];
			}
		}
		return parent::__get($name);
	}
}

class RSAPrivateKey extends RSAPublicKey
{
	public $publicKey;

	public static function generate($bits = 1024)
	{
		$resource = openssl_pkey_new(array('private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
		if(is_resource($resource))
		{
			return new RSAPrivateKey($resource);
		}
	}

	public function __construct($resource, $info = null)
	{
		parent::__construct($resource, $info);
		if(isset($this->info['key']))
		{
			$pub = openssl_pkey_get_public($this->info['key']);
			$this->publicKey = PublicKey::publicKeyFromResource($pub);
		}
	}
	
	protected function fingerprint($method = 'sha1')
	{
		unset($this->fingerprint);
	}

	public function __get($name)
	{
		if($name == 'fingerprint')
		{
			if(isset($this->publicKey))
			{
				return $this->publicKey->fingerprint;
			}
		}
		if(isset($this->info['rsa']))
		{
			switch($name)
			{
			case 'd':
			case 'p':
			case 'q':
			case 'dmp1':
			case 'dmq1':
			case 'iqmp':
				return $this->info['rsa'][$name];
			}
		}
		return parent::__get($name);
	}
}

