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
require_once(dirname(__FILE__) . '/pk.php');

class X509
{
	protected $resource;
	public $info;
	public $subjectKey;

	/* Create an instance of an X.509 certificate object from an "armoured"
	 * PEM blob.
	 */
	public static function x509FromPEM($pem)
	{
		$cert = new X509($pem);
		if($cert->info === null)
		{
			return null;
		}
		return $cert;
	}
	
	/* Create an instance of an X.509 certificate object from a DER-encoded
	 * blob.
	 */
	public static function x509FromDER($der)
	{
		$cert = new X509(chunk_split("-----BEGIN CERTIFICATE-----\n" . base64_encode($der), 64, "\n") . "-----END CERTIFICATE-----\n");
		if($cert->info === null)
		{
			return null;
		}
		return $cert;
	}
	
	/* Create an instance of an X.509 certificate object from an OpenSSL
	 * resource.
	 */
	public static function x509FromResource($resource)
	{
		$cert = new X509($resource);
		if($cert->info === null)
		{
			return null;
		}
		return $cert;
	}

	protected function __construct($pem)
	{
		if(is_resource($pem))
		{
			$this->resource = $pem;
		}
		else
		{
			$this->resource = openssl_x509_read($pem);
		}
		if(is_resource($this->resource))
		{
			$this->info = openssl_x509_parse($this->resource);
			$key = openssl_pkey_get_public($this->resource);
			$this->subjectKey = PublicKey::publicKeyFromResource($key);
		}
	}
}