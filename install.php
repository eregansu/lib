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

class BuiltinLibModuleInstall extends BuiltinModuleInstaller
{
	public $coexists = true;

	public function writeInstanceConfig($file)
	{
		fwrite($file, "/* Uncomment the below to enable debugging across the framework */\n");
		fwrite($file, "/* define('EREGANSU_DEBUG', true); */\n\n");
		fwrite($file, "/* Uncomment the below to throw exceptions on warnings and notices */\n");
		fwrite($file, "/* define('EREGANSU_STRICT_ERROR_HANDLING', true); */\n\n");
		fwrite($file, "/* Uncomment the below to log when autoloading occurs via error_log() */\n");
		fwrite($file, "/* define('EREGANSU_DEBUG_AUTOLOAD', true); */\n\n");
	}
}
