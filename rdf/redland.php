<?php

/* Copyright 2010-2012 Mo McRoberts.
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

/* These classes provide a wrapper around the Redland RDF extension, which
 * must be installed and loaded in order to use them. See
 * http://librdf.org/bindings/INSTALL.html
 */

abstract class RedlandBase
{
	protected static $defaultWorld;

	public $resource;
	protected $world;

	public static function world($world = null)
	{
		if($world !== null)
		{
			return $world;
		}
		if(self::$defaultWorld === null)
		{
			self::$defaultWorld = new RedlandWorld();
		}	   
		return self::$defaultWorld;
	}

	protected function __construct($res = null, $world = null)
	{
		if($res !== null && !is_resource($res))
		{
			trigger_error('Constructing instance of ' . get_class($this) . ' -- resource is invalid', E_USER_ERROR);
		}
		$this->resource = $res;
		$this->world = $world;
	}
	
	protected function parseOptions($options)
	{
		if(is_array($options))
		{
			$opts = array();
			foreach($options as $k => $v)
			{
				if($v === true)
				{
					$v = 'yes';
				}
				else if($v === false)
				{
					$v = 'false';
				}
				else
				{
					$v = "'" . $v . "'";
				}
				$opts[] = $k . '=' . $v;
			}
			return implode(',', $opts);
		}
		return $options;
	}
	
	/* Return a librdf_uri for a given URI string or RDFURI instance */
	protected static function parseURI($uri, $world = null)
	{
		if(is_resource($uri))
		{
			return $uri;
		}
		if(is_object($uri))
		{
			return $uri->resource;
		}
		$world = self::world($world);
		if(is_string($uri))
		{
			return librdf_new_uri($world->resource, $uri);
		}
		return null;
	}

	public function __call($name, $args)
	{
		trigger_error('Call to undefined method ' . get_class($this) . '::' . $name,  E_USER_ERROR);
	}

	/* Return an RDF... instance from a librdf_node resource */
	protected function nodeToObject($node)
	{
		if(librdf_node_is_resource($node))
		{
			return new RDFURI(librdf_node_get_uri($node));
		}
		if(librdf_node_is_blank($node))
		{
			$str = librdf_node_to_string($node);
			if(substr($str, 0, 1) == '(' && substr($str, -1) == ')')
			{
				$str = '_:' . substr($str, 1, -1);
			}			
			return new RDFURI($str);
		}
		return RDFComplexLiteral::literal(null, $node, null);
	}
}

class RedlandWorld extends RedlandBase
{
	const FEATURE_GENID_BASE = 'http://feature.librdf.org/genid-base';
	const FEATURE_GENID_COUNTER = 'http://feature.librdf.org/genid-counter';
	
	public function __construct()
	{
		parent::__construct(librdf_php_get_world());
		$this->world = $this->resource;
		$this->open();
	}
		
	public function open()
	{
		librdf_world_open($this->resource);
	}
	
	public function getFeature($feature)
	{
		return librdf_world_get_feature($this->resource, self::parseURI($feature, $this));
	}
}

class RedlandStorage extends RedlandBase
{
	protected static $storageIndex = 1;
	
	public function __construct($storageName = null, $name = null, $options = null, $world = null)
	{
		$world = RedlandBase::world($world);
		if(!strlen($name))
		{
			$name = get_class($this) . self::$storageIndex;
		}
		self::$storageIndex++;
		$res = librdf_new_storage($world->resource, $storageName, $name, $this->parseOptions($options));
		if(!is_resource($res))
		{
			trigger_error('Failed to construct storage ' . $name . ' (of type ' . $storageName . ')', E_USER_ERROR);
		}
		parent::__construct($res, $world);
	}
}

class RedlandModel extends RedlandBase
{
	protected $storage = null;
	protected $options = null;

	public function __construct($storage = null, $options = null, $world = null)
	{
		$world = RedlandBase::world($world);
		if(!$storage)
		{
			$storage = new RedlandStorage();
		}
		$this->storage = $storage;
		$this->options = $this->parseOptions($options);
		$res = librdf_new_model($world->resource,
								is_object($storage) ? $storage->resource : $storage,
								$this->options);
		if(!is_resource($res))
		{
			trigger_error('Failed to construct new model instance', E_USER_ERROR);
		}
		parent::__construct($res, $world);
	}

	public function addStatement($statement)
	{
		librdf_model_add_statement($this->resource, is_object($statement) ? $statement->resource : $statement);
	}

	public function removeStatement($statement)
	{
		librdf_model_remove_statement($this->resource, is_object($statement) ? $statement->resource : $statement);
	}
		
	public function __get($name)
	{
		if($name == 'size')
		{
			return librdf_model_size($this->resource);
		}
		return null;
	}
	
	public function serialiseToString($serialiser, $baseUri = null)
	{
		return librdf_serializer_serialize_model_to_string($serialiser->resource, $baseUri === null ? null : self::parseURI($baseUri, $this->world), $this->resource);
	}
}

class RedlandParser extends RedlandBase
{
	protected $world;
	
	public function __construct($name = null, $mime = null, $type = null, $world = null)
	{
		$world = RedlandBase::world($world);
		$res = librdf_new_parser($world->resource, $name, $mime, self::parseURI($type, $world));
		if(!is_resource($res))
		{
			trigger_error('Failed to construct a "' . $name . '" parser', E_USER_ERROR);
		}
		parent::__construct($res, $world);
	}

	public function parseFileIntoModel($filename, $baseURI, $model)
	{
		return $this->parseIntoModel('file://' . realpath($filename), $baseURI, $model);
	}
	
	public function parseIntoModel($uri, $baseURI, $model)
	{
		if(0 == librdf_parser_parse_into_model($this->resource, self::parseURI($uri, $this->world), self::parseURI($baseURI, $this->world), $model->resource))
		{
			return true;
		}
		return false;		
	}
	
	public function parseStringIntoModel($string, $baseURI, $model)
	{
		$uri = self::parseURI($baseURI, $this->world);
		if(0 == librdf_parser_parse_string_into_model($this->resource, $string, $uri, $model->resource))
		{
			return true;
		}
		trigger_error('Failed to parse string into model', E_USER_NOTICE);
		return false;
	}
}

class RedlandRDFXMLParser extends RedlandParser
{
	public function __construct($name = 'rdfxml', $mime = null, $type = null, $world = null)
	{
		parent::__construct($name, $mime, $type, $world);
	}
}

class RedlandTurtleParser extends RedlandParser
{
	public function __construct($name = 'turtle', $mime = null, $type = null, $world = null)
	{
		parent::__construct($name, $mime, $type, $world);
	}
}

class RedlandN3Parser extends RedlandParser
{
	public function __construct($name = 'turtle', $mime = null, $type = null, $world = null)
	{
		parent::__construct($name, $mime, $type, $world);
	}
}

class RedlandNTriplesParser extends RedlandParser
{
	public function __construct($name = 'ntriples', $mime = null, $type = null, $world = null)
	{
		parent::__construct($name, $mime, $type, $world);
	}
}

class RedlandRDFaParser extends RedlandParser
{
	public function __construct($name = 'rdfa', $mime = null, $type = null, $world = null)
	{
		parent::__construct($name, $mime, $type, $world);
	}
}

class RedlandTriGParser extends RedlandParser
{
	public function __construct($name = 'trig', $mime = null, $type = null, $world = null)
	{
		parent::__construct($name, $mime, $type, $world);
	}
}

class RedlandNode extends RedlandBase
{
	public static function blank($world = null)
	{
		$world = RedlandBase::world($world);
		return new RedlandNode(librdf_new_node($world->resource), $world);
	}

	public static function blankId($id, $world = null)
	{
		$world = RedlandBase::world($world);
		return new RedlandNode(librdf_new_node_from_blank_identifier($world->resource, $id), $world);
	}

	public static function literal($text, $world = null)
	{
		$world = RedlandBase::world($world);
		return new RedlandNode(librdf_new_node_from_literal($world->resource, $text, null, false), $world);
	}

	public static function uri($uri, $world = null)
	{
		$world = RedlandBase::world($world);
		return new RedlandNode(librdf_new_node_from_uri($world->resource, self::parseURI($uri, $world)), $world);
	}

	public static function node($resource, $world = null)
	{
		if(!is_resource($resource))
		{
			trigger_error('Specified resource is not valid in RedlandNode::node()', E_USER_ERROR);
		}
		if(librdf_node_is_literal($resource))
		{
			return new RDFComplexLiteral(null, $resource, null, $world);
		}
		if(librdf_node_is_resource($resource))
		{
			return new RDFURI(librdf_node_get_uri($resource), $world);
		}
		return new RedlandNode($resource, $world);
	}

	public function __construct($node, $world = null)
	{
		$world = RedlandBase::world($world);
		return parent::__construct($node, $world);
	}

	public function __toString()
	{
		return librdf_node_to_string($this->resource);
	}

	public function isBlank()
	{
		return librdf_node_is_blank($this->resource);
	}

	public function asUri()
	{
		if(librdf_node_is_resource($this->resource))
		{
			$uri = librdf_node_get_uri($this->resource);
			return new RDFURI($uri, $this->world);
		}
		if(librdf_node_is_blank($this->resource))
		{
			return new RDFURI(librdf_node_to_string($this->resource));
		}
		trigger_error('Attempt to obtain URI of node which has no URI: ' . librdf_node_to_string($this->resource), E_USER_NOTICE);
		return null;
	}

	public function asArray()
	{
		if(librdf_node_is_resource($this->resource) || librdf_node_is_blank($this->resource))
		{
			$uri = librdf_node_get_uri($this->resource);
			return array('type' => 'uri', 'value' => librdf_uri_to_string($uri));
		}
		if(librdf_node_is_literal($this->resource))
		{			
			$value = array(
				'type' => 'literal',
				'value' => librdf_node_get_literal_value($this->resource),
				);
			$dt = librdf_node_get_literal_value_datatype_uri($this->resource);
			if($dt)
			{
				$value['datatype'] = librdf_uri_to_string($dt);
			}
			$lang = librdf_node_get_literal_value_language($this->resource);
			if($lang !== null)
			{
				$value['lang'] = $lang;
			}
			if(!isset($value['datatype']) && !isset($value['lang']))
			{
				return $value['value'];
			}
			return $value;
		}
		trigger_error('Unable to determine type of node "' . librdf_node_to_string($this->resource) . '"', E_USER_NOTICE);
		return librdf_node_to_string($this->resource);
	}
}

class RDFURI extends RedlandBase
{
	protected $node;
	protected $inner;

	public function __construct($uri, $world = null)
	{
		$world = RedlandBase::world($world);
		if(is_resource($uri))
		{
			$res = $uri;
		}
		else
		{
			if($uri instanceof RDFURI || $uri instanceof URL)
			{
				$uri = strval($uri);
			}
			$res = librdf_new_uri($world->resource, $uri);
		}
		if(!is_resource($res))
		{
			trigger_error('Invalid URI <' . $uri . '> passed to RDFURI::__construct()', E_USER_ERROR);
		}
		parent::__construct($res, $world);
	}

	public static function fromNode($node)
	{
		return new RDFURI(librdf_node_get_uri($node));
	}
	
	public function __toString()
	{
		return strval(librdf_uri_to_string($this->resource));
	}
	
	public function asArray()
	{
		return array('type' => 'uri', 'value' => librdf_uri_to_string($this->resource));
	}

	public function asJSONLD()
	{
		return array('@id' => librdf_uri_to_string($this->resource));
	}

	public function node()
	{
		if($this->node === null)
		{
			$this->node = new RedlandNode(librdf_new_node_from_uri($this->world->resource, $this->resource), $this->world);
		}
		return $this->node;
	}

	public function __get($prop)
	{
		if(!isset($this->internal))
		{
			$this->internal = new URL(librdf_uri_to_string($this->resource));
		}
		return $this->internal->{$prop};
	}

	public function __isset($prop)
	{
		if($prop == 'internal')
		{
			return false;
		}
		if(!isset($this->internal))
		{
			$this->internal = new URL(librdf_uri_to_string($this->resource));
		}
		return isset($this->internal->{$prop});
	}		

	public function __set($prop, $value)
	{
		if($prop == 'internal')
		{
			$this->internal = $value;
			return;
		}
		if(!isset($this->internal))
		{
			$this->internal = new URL(librdf_uri_to_string($this->resource));
		}
		$str = librdf_uri_to_string($this->resource);
		$this->internal->{$prop} = $value;
		$u = strval($this->internal);
		if(strcmp($str, $u))
		{
			$this->node = null;
			$this->resource = librdf_new_uri($this->world->resource, $u);
		}
	}
}

class RedlandSerializer extends RedlandBase
{
	public function __construct($name, $mime = null, $uri = null, $world = null)
	{
		$world = RedlandBase::world($world);	
		$res = librdf_new_serializer($world->resource, $name, $mime, self::parseURI($uri, $world));
		if(!is_resource($res))
		{
			trigger_error('Failed to construct a "' . $name . '" serialiser', E_USER_ERROR);
		}
		parent::__construct($res, $world);
	}
	
	public function serializeModelToString(RedlandModel $model, $baseURI = null)
	{
		$ns = URI::namespaces();
		foreach($ns as $uri => $prefix)
		{
			if($prefix == 'xml' || $prefix == 'xmlns') continue;
			librdf_serializer_set_namespace($this->resource, librdf_new_uri($this->world->resource, $uri), $prefix);
		}
		return librdf_serializer_serialize_model_to_string($this->resource, self::parseURI($baseURI, $this->world), $model->resource);
	}

	public function serializeModelToFile(RedlandModel $model, $fileName, $baseURI = null)
	{
		$ns = URI::namespaces();
		foreach($ns as $uri => $prefix)
		{
			if($prefix == 'xml' || $prefix == 'xmlns') continue;
			librdf_serializer_set_namespace($this->resource, librdf_new_uri($this->world->resource, $uri), $prefix);
		}
		return librdf_serializer_serialize_model_to_string($this->resource, $fileName, self::parseURI($baseURI, $this->world), $model->resource);
	}

	public function serializeStreamToString($stream, $baseURI = null)
	{
		return librdf_serializer_serialize_stream_to_string($this->resource, self::parseURI($baseURI, $this->world), $stream);
	}
}

class RedlandTurtleSerializer extends RedlandSerializer
{
	public function __construct($name = 'turtle', $mime = null, $uri = null, $world = null)
	{
		parent::__construct($name, $mime, $uri, $world);
	}
}

class RedlandN3Serializer extends RedlandSerializer
{
	public function __construct($name = 'turtle', $mime = null, $uri = null, $world = null)
	{
		parent::__construct($name, $mime, $uri, $world);
	}
}

class RedlandRDFXMLSerializer extends RedlandSerializer
{
	public function __construct($name = 'rdfxml-abbrev', $mime = null, $uri = null, $world = null)
	{
		parent::__construct($name, $mime, $uri, $world);
	}
}

class RedlandXMPSerializer extends RedlandSerializer
{
	public function __construct($name = 'rdfxml-xmp', $mime = null, $uri = null, $world = null)
	{
		parent::__construct($name, $mime, $uri, $world);
	}
}

class RedlandJSONSerializer extends RedlandSerializer
{
	public function __construct($name = 'json', $mime = null, $uri = null, $world = null)
	{
		parent::__construct($name, $mime, $uri, $world);
	}
}

class RedlandJSONTriplesSerializer extends RedlandSerializer
{
	public function __construct($name = 'json-triples', $mime = null, $uri = null, $world = null)
	{
		parent::__construct($name, $mime, $uri, $world);
	}
}

class RedlandNTriplesSerializer extends RedlandSerializer
{
	public function __construct($name = 'ntriples', $mime = null, $uri = null, $world = null)
	{
		parent::__construct($name, $mime, $uri, $world);
	}
}


class RedlandNQuadsSerializer extends RedlandSerializer
{
	public function __construct($name = 'nquads', $mime = null, $uri = null, $world = null)
	{
		parent::__construct($name, $mime, $uri, $world);
	}
}

abstract class RDFInstanceBase extends RedlandBase implements ArrayAccess
{
	public $subject;
	public /*internal*/ $model;
	public /*internal*/ $subsidiaries = array();
	
	/* List of known predicates for use with JSON-LD */
	public $knownPredicates = null;
	/* JSON-LD Context URI */
	public $contextUri = null;
	
	public function __construct($uri = null, $type = null, $world = null)
	{
		$this->world = RedlandBase::world($world);	
		if($uri === null)
		{
			$this->subject = RedlandNode::blank($world);
		}
		else
		{
			$uri = new RDFURI($uri);
			$this->subject = $uri->node();
		}
		if($type !== null)
		{
			$this->add(RDF::rdf.'type', new RDFURI($type));
		}
	}

	public /*internal*/ function attachTo(RDFInstanceBase $inst)
	{
		$this->world = $inst->world;
		if($inst->model == null)
		{
			$inst->model = new RedlandModel(null, null, $this->world);
		}
		$this->model = $inst->model;
		$inst->subsidiaries[] = $this;
	}

	public function transform()
	{
		/* Placeholder method */
	}

	public function node()
	{
		return $this->subject;
	}

	public function hasSubject($subject)
	{
		$subj = $this->parseURI($subject);
		$me = librdf_node_get_uri($this->subject->resource);
		if(librdf_uri_equals($subj, $me))
		{
			return true;
		}
		return false;
	}

	public function subject()
	{
		return $this->subject->asUri();
	}

	public function subjects()
	{
		return array($this->subject->asUri());
	}

	public function predicates()
	{
		if(!isset($this->model))
		{
			return array();
		}
		$list = array();
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, null, null);
		$rs = librdf_model_find_statements($this->model->resource, $query);
		if(!librdf_node_is_blank($this->subject->resource))
		{
			$list[] = RDF::rdf.'about';
		}
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$predicate = librdf_statement_get_predicate($statement);
			$pred = librdf_uri_to_string(librdf_node_get_uri($predicate));
			if(!in_array($pred, $list))
			{
				$list[] = $pred;
			}
			librdf_stream_next($rs);
		}
		return $list;
	}

	public function add($predicate, $object)
	{
		if(!is_object($object))
		{
			$object = RedlandNode::literal($object, $this->world);
		}
		else if($object instanceof RDFSet)
		{
			if(is_string($predicate))
			{
				$predicate = new RDFURI($predicate);
			}
			if(!($predicate instanceof RedlandNode))
			{
				$predicate = $predicate->node();
			}
			if($this->model === null)
			{
				$this->model = new RedlandModel(null, null, $this->world);
			}			
			$rs = librdf_model_as_stream($object->resource);
			while(!librdf_stream_end($rs))
			{
				$statement = librdf_stream_get_object($rs);
				if(isset($object->sourceModel))
				{
					$stobj = librdf_statement_get_object($statement);
					if(librdf_node_is_blank($stobj))
					{
						$this->mergeSubject($stobj, $object->sourceModel->resource);
					}
				}
				$new = librdf_new_statement_from_statement($statement);
				librdf_statement_set_subject($new, $this->subject->resource);
				librdf_statement_set_predicate($new, $predicate->resource);
				librdf_model_add_statement($this->model->resource, $new);
				librdf_stream_next($rs);
			}
			return;
		}
		else if(!($object instanceof RedlandNode))
		{
			$object = $object->node();
		}
		if(!strcmp($predicate, RDF::rdf.'about'))
		{
			if($this->model === null)
			{
				$this->subject = $object;
			}
			else
			{
				$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, null, null);
				$stream = librdf_model_find_statements($this->model->resource, $query);
				$this->subject = $object;
				$list = array();
				while(!librdf_stream_end($stream))
				{
					$statement = librdf_stream_get_object($stream);
					$list[] = librdf_new_statement_from_statement($statement);
					$this->model->removeStatement($statement);
					librdf_stream_next($stream);
				}
				foreach($list as $statement)
				{
					librdf_statement_set_subject($statement, $this->subject->resource);
					$this->model->addStatement($statement);
				}
			}
			return;
		}
		if(is_string($predicate))
		{
			$predicate = new RDFURI($predicate);
		}
		if(!($predicate instanceof RedlandNode))
		{
			$predicate = $predicate->node();
		}
		if($this->model === null)
		{
			$this->model = new RedlandModel(null, null, $this->world);
		}
		$statement = librdf_new_statement_from_nodes(
			$this->world->resource,
			$this->subject->resource, $predicate->resource, $object->resource);
		$this->model->addStatement($statement);
	}

	protected function mergeSubject($subject, $model)
	{
		$query = librdf_new_statement_from_nodes($this->world->resource, $subject, null, null);
		$statements = librdf_model_find_statements($model, $query);
		librdf_model_add_statements($this->model->resource, $statements);
		/* Add any associated bnodes */
		$rs = librdf_model_find_statements($model, $query);
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$obj = librdf_statement_get_object($statement);
			if(librdf_node_is_blank($obj))
			{
				$this->mergeSubject($obj, $model);
			}
			librdf_stream_next($rs);
		}
	}

	public function remove($predicate)
	{
		if($this->model == null)
		{
			return;
		}
		if(is_string($predicate))
		{
			$predicate = new RDFURI($predicate);
		}
		if(!($predicate instanceof RedlandNode))
		{
			$predicate = $predicate->node();
		}
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, $predicate->resource, null);
		$stream = librdf_model_find_statements($this->model->resource, $query);
		while(!librdf_stream_end($stream))
		{
			$statement = librdf_stream_get_object($stream);
			$this->model->removeStatement($statement);
			librdf_stream_next($stream);
		}
	}

	public function __set($key, $value)
	{
		if(strpos($key, ':') === false)
		{
			$this->{$key} = $value;
			return;
		}
		if(is_array($value))
		{
			$this->remove($key);
			foreach($value as $v)
			{
				$this->add($key, $v);
			}
		}
		else
		{
			$this->add($key, $value);
		}
	}

	public function __get($predicate)
	{
		if(strpos($predicate, ':') === false)
		{
			return null;
		}
		if(!strcmp($predicate, RDF::rdf.'about'))
		{
			return array($this->subject->asUri());
		}
		if(is_string($predicate))
		{
			$predicate = new RDFURI($predicate);
		}
		if(!($predicate instanceof RedlandNode))
		{
			$predicate = $predicate->node();
		}
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, $predicate->resource, null);
		$stream = librdf_model_find_statements($this->model->resource, $query);
		$nodes = array();
		while(!librdf_stream_end($stream))
		{
			$statement = librdf_stream_get_object($stream);
			$nodes[]= RedlandNode::node(librdf_statement_get_object($statement));
			librdf_stream_next($stream);
		}
		return $nodes;
	}

	public function __isset($predicate)
	{
		if(strpos($predicate, ':') === false)
		{
			return false;
		}
		if(!strcmp($predicate, RDF::rdf.'about'))
		{
			if($this->subject->isBlank())
			{
				return false;
			}
			return true;
		}
		if($this->model === null)
		{
			return false;
		}
		if(!$this->model->resource)
		{
			trigger_error('Model is NULL but model resource is not', E_USER_ERROR);
		}
		if(is_string($predicate))
		{
			$predicate = new RDFURI($predicate);
		}
		if(!($predicate instanceof RedlandNode))
		{
			$predicate = $predicate->node();
		}
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, $predicate->resource, null);
		$stream = librdf_model_find_statements($this->model->resource, $query);
		$nodes = array();
		while(!librdf_stream_end($stream))
		{
			$statement = librdf_stream_get_object($stream);
			if($statement !== null)
			{
				return true;
			}
			break;
		}
		return false;
	}


	/* Return the first value for the given predicate */
	public function first($key)
	{
		$key = $this->translateQName($key);
		if(!strcmp($key, RDF::rdf.'about'))
		{
			return $this->subject->asUri();
		}
		$predicate = new RDFURI($key);
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, $predicate->node()->resource, null);
		$stream = librdf_model_find_statements($this->model->resource, $query);
		while(!librdf_stream_end($stream))
		{
			$statement = librdf_stream_get_object($stream);
			return new RDFComplexLiteral(null, librdf_statement_get_object($statement), null, $this->world);
		}
		return null;
	}

	public function predicateObjectList()
	{
		$list = array();
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, null, null);
		$stream = librdf_model_find_statements($this->model->resource, $query);
		while(!librdf_stream_end($stream))
		{
			$statement = librdf_stream_get_object($stream);
			$predicate = librdf_statement_get_predicate($statement);
			$uri = librdf_node_get_uri($predicate);
			$k = librdf_uri_to_string($uri);				
			$list[$k][] = $this->nodeToObject(librdf_statement_get_object($statement));
			librdf_stream_next($stream);
		}
		return $list;
	}
	
	/* Return the values of a given predicate */
	public function all($key, $nullOnEmpty = false)
	{
		if(!is_array($key)) $key = array($key);
		$set = new RDFSet(null, null, $this->model);
		foreach($key as $k)
		{
			$k = $this->translateQName($k);
			if(!strcmp($k, RDF::rdf.'about'))
			{
				$set->add($this->subject);
				continue;
			}
			$predicate = librdf_new_node_from_uri($this->world->resource, librdf_new_uri($this->world->resource, $k));
			$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, $predicate, null);
			$stream = librdf_model_find_statements($this->model->resource, $query);
			$set->addStream($stream);
		}
		if($nullOnEmpty && !count($set))
		{
			return null;
		}
		$set->matchAlt($this->model);
		return $set;
	}

	public function offsetExists($offset)
	{
		if(!isset($this->model))
		{
			return false;
		}
		$predicate = new RDFURI($this->translateQName($offset));
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, $predicate->node()->resource, null);
		$stream = librdf_model_find_statements($this->model->resource, $query);
		while(!librdf_stream_end($stream))
		{
			$statement = librdf_stream_get_object($stream);
			if($statement !== null)
			{
				return true;
			}
			break;
		}
		return false;
	}
	
	public function offsetGet($offset)
	{
		if(strpos($offset, ':') === false)
		{
			return $this->{$offset};
		}
		return $this->all($offset);
	}
	
	public function offsetSet($offset, $value)
	{
		if($offset === null)
		{
			/* $inst[] = $value; -- meaningless unless $value is a triple */
			if($value instanceof RDFTriple)
			{
				$this->model->addStatement($value);
			}
			else
			{
				return;
			}
		}
		else
		{
			$offset = $this->translateQName($offset);
		}
		if(is_array($value))
		{
			$this->remove($offset);
			foreach($value as $v)
			{
				$this->add($offset, $v);
			}
		}
		else
		{
			$this->add($offset, $value);
		}
	}

	public function offsetUnset($offset)
	{		
		$offset = $this->translateQName($offset);
		$this->remove($offset);
	}

	protected function idForNode($node, $doc)
	{
		if($node instanceof RedlandNode)
		{
			$node = $node->resource;
		}
		$localId = 'local-' . md5(librdf_node_to_string($node));
		if(librdf_node_is_resource($node))
		{
			$subj = librdf_uri_to_string(librdf_node_get_uri($node));
			if(strlen($doc->fileURI) && !strncmp($subj, $doc->fileURI, strlen($doc->fileURI)))
			{
				$sub = substr($subj, strlen($doc->fileURI));
				if(substr($sub, 0, 1) == '/')
				{
					$sub = substr($sub, 1);
				}
				if(substr($sub, 0, 1) == '#')
				{
					return substr($sub, 1);
				}
			}
		}
		return $localId;
	}

	protected function generateLink($target, $text, $predicate, $doc)
	{
		if(isset($doc->linkFilter))
		{
			$r = call_user_func($doc->linkFilter, $target, $text, $predicate, $doc, $this);
			if($r !== null)
			{
				return $r;
			}
		}
		if($predicate === null && !strcmp($target, RDF::rdf.'about'))
		{
			return '@';
		}
		if($predicate === null && !strcmp($target, RDF::rdf.'type'))
		{
			return 'a';
		}
		return '<a href="' . _e($target) . '">' . _e($text) . '</a>';
	}

	public function htmlLinkId()
	{
		return 'local-' . md5(librdf_node_to_string($this->subject->resource));
	}

	protected function generateCaption($link, $subject)
	{
		$title = $this->title();
		if(!strlen($title))
		{
			$title = $subject;
		}
		if($link !== null)
		{
			return '<a title="' . _e($subject) . '" href="' . _e($link) . '">' . _e($title) . '</a>';
		}
		return '<span title="' . _e($subject) . '">' . _e($title) . '</span>';
	}

	public function asHTML($doc)
	{
		$buf = array();
		$buf[] = '<table id="' . $this->idForNode($this->subject, $doc) . '">';
		$subj = $this->subject();
		if(librdf_node_is_resource($this->subject->resource))
		{
			$link = $subj;
		}
		else
		{
			$link = null;
		}
		$buf[] = '<caption>' . $this->generateCaption($link, $subj) . '</caption>';
		$buf[] = '<thead>';
		$buf[] = '<tr>';
		$buf[] = '<th class="predicate" scope="col">Property</th>';
		$buf[] = '<th class="object" scope="col">Value</th>';
		$buf[] = '</tr>';
		$buf[] = '</thead>';
		$buf[] = '<tbody>';
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, null, null);
		$rs = librdf_model_find_statements($this->model->resource, $query);
		$values = array();
		$prev = null;
		if(librdf_node_is_resource($this->subject->resource))
		{
			$buf[] = '<tr><td>' . $this->generateLink(RDF::rdf.'about', '@', null, $doc) . '</td><td><p>' . $this->generateLink($subj, $subj, URI::rdf.'about', $doc) . '</p></td></tr>';
		}
		else
		{
			$buf[] = '<tr><td>' . $this->generateLink(RDF::rdf.'about', '@', null, $doc) . '</td><td><p>' . _e($subj) . '</p></td>';
		}

		while(!librdf_stream_end($rs))
		{			
			$st = librdf_stream_get_object($rs);
			$predicate = librdf_uri_to_string(librdf_node_get_uri(librdf_statement_get_predicate($st)));
			if($predicate !== $prev && count($values))
			{
				$this->writeHTMLRow($buf, $doc, $prev, $values);
				$values = array();
			}
			$prev = $predicate;
			$row = array();
			$object = librdf_statement_get_object($st);			
			$row[] = '<td class="object">';
			if(librdf_node_is_literal($object))
			{
				$row[] = '<p><q class="literal">' . str_replace('<p><q class="literal"></q></p>', '', str_replace("\n", '</q></p><p><q class="literal">', _e(librdf_node_get_literal_value($object)))) . '</q>';
				$lang = librdf_node_get_literal_value_language($object);
				if(strlen($lang))
				{
					$row[] = '<span class="lang">[' . _e($lang) . ']</span>';
				}
				$dt = librdf_node_get_literal_value_datatype_uri($object);
				if(is_resource($dt))
				{
					$dt = librdf_uri_to_string($dt);
					$short = $doc->namespacedName($dt, false);
					$row[] = '(' . $this->generateLink($dt, $short, URI::rdf.'datatype', $doc) . ')';
				}
				$row[] = '</p>';
			}
			else if(librdf_node_is_resource($object))
			{
				$target = $link = librdf_uri_to_string(librdf_node_get_uri($object));
				if(isset($doc[$target]))
				{
					$link = '#' . $this->idForNode($object, $doc);
				}
				$short = $doc->namespacedName($target, false);
				$row[] = $this->generateLink($link, $short, $predicate, $doc);
			}
			else if(librdf_node_is_blank($object))
			{
				$link = '#' . $this->idForNode($object, $doc);
				$row[] = $this->generateLink($link, librdf_node_to_string($object), $predicate, $doc);
			}				
			$row[] = '</td>';
			$values[] = implode("\n", $row);
			librdf_stream_next($rs);
		}
		if(count($values))
		{
			$this->writeHTMLRow($buf, $doc, $prev, $values);
		}
		$buf[] = '</tbody>';
		$buf[] = '</table>';
		return implode("\n", $buf);
	}

	protected function writeHTMLRow(&$buf, $doc, $predicate, $values)
	{
		if(!strcmp($predicate, RDF::rdf.'about'))
		{
			$short = '@';
		}
		else if(!strcmp($predicate, RDF::rdf.'type'))
		{
			$short = 'is a';
		}
		else
		{
			$short = $doc->namespacedName($predicate, true);
		}
		$link = $this->generateLink($predicate, $short, null, $doc);
		$buf[] = '<tr>';
		$count = count($values);
		if($count > 1)
		{
			$span = ' rowspan="' . $count . '"';
		}
		else
		{
			$span = '';
		}
		$buf[] = '<td class="predicate"' . $span . '>' . $link . '</td>';
		foreach($values as $val)
		{
			$buf[] = $val;
			$buf[] = '</tr>';
			$buf[] = '<tr>';
		}
		array_pop($buf);
	}

	public function asTurtle()
	{
		$ser = new RedlandTurtleSerializer();
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, null, null);
		$rs = librdf_model_find_statements($this->model->resource, $query);
		return $ser->serializeStreamToString($rs, $this);
	}

	public function asArray()
	{
		$ser = new RedlandJSONSerializer();
		$query = librdf_new_statement_from_nodes($this->world->resource, $this->subject->resource, null, null);
		$rs = librdf_model_find_statements($this->model->resource, $query);
		$json = $ser->serializeStreamToString($rs, $this);
		if(!strlen($json))
		{
			return null;
		}
		$list = json_decode($json, true);
		foreach($list as $subject => $predicates)
		{
			$predicates[RDF::rdf.'about'] = array(array('type' => 'uri', 'value' => $subject));
			return array('type' => 'node', 'value' => $predicates);
		}
	}
	
	public function context($doc)
	{
		$predicates = $this->knownPredicates();
		$context = array();
		foreach($predicates as $full => $info)
		{
			if(isset($info['@@as']))
			{
				$key = $info['@@as'];
				unset($info['@@as']);				
				if(isset($context[$key]))
				{
					continue;
				}
				$nn = $doc->namespacedName($full, false);
				if($nn !== null)
				{
					$info['@id'] = $nn;
				}
				if(count($info) == 1 && isset($info['@id']))
				{
					$context[$key] = $info['@id'];
				}
				else
				{
					$context[$key] = $info;
				}
				continue;
			}
			$nn = $doc->namespacedName($full, false);
			if($nn === null || isset($context[$nn]))
			{
				if(count($info) == 1 && isset($info['@id']))
				{
					/* No point in having a <full> => <full> entry */
					continue;
				}
				$context[$full] = $info;
				continue;
			}
			$context[$nn] = $info;
		}
		$nslist = URI::namespaces();
		foreach($nslist as $uri => $prefix)
		{
			if(!strcmp($uri, 'http://www.w3.org/XML/1998/namespace') ||
				!strcmp($uri, 'http://www.w3.org/2000/xmlns/') ||
				!strcmp($uri, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#'))
			{
				continue;
			}
			$context[$prefix] = $uri;
		}
		return $context;
	}

	protected function knownPredicates()
	{
		$predicateList = $this->knownPredicates;
		if(!is_array($predicateList))
		{
			$predicateList = array();
		}
		foreach($predicateList as $full => $info)
		{
			if(isset($info['@@as']) && !isset($info['@id']))
			{
				$predicateList[$full]['@id'] = $full;
			}
		}
		$bareProps = RDF::barePredicates();
		$uriProps = RDF::uriPredicates();
		foreach($bareProps as $short => $full)
		{
			if(is_array($full))
			{
				$info = $full;
				$full = URI::expandUri($info['@id'], true);
			}
			else
			{
				$info = array('@id' => $full);
			}
			$info['@@as'] = $short;
			if(isset($predicateList[$full]))			
			{
				continue;
			}			
			$predicateList[$full] = $info;
		}
		foreach($uriProps as $full)
		{
			$info['@type'] = '@id';
			if(isset($predicateList[$full]))			
			{
				continue;
			}			
			$predicateList[$full] = $info;
		}
		return $predicateList;
	}

	/* Transform this instance into a native array which can itself be
	 * serialised as JSON to result in JSON-LD.
	 */
	public function asJSONLD($doc)
	{
		$array = array('@context' => array(), '@id' => null, '@type' => null);
		$isArray = array();
		$up = array();
		$predicates = $this->knownPredicates();
		$props = $this->predicateObjectList();
		$array['@id'] = strval($this->subject->asUri());
		foreach($props as $name => $values)
		{
			if(strpos($name, ':') === false) continue;
			if(!is_array($values)) continue;
			/* Special-case: @id */
			if(!strcmp($name, RDF::rdf.'about'))
			{
				$name = $kn = '@id';
				foreach($values as $v)
				{
					$array[$kn] = $v;
					break;
				}
				continue;
			}
			/* Special-case: @type */
			if(!strcmp($name, RDF::rdf.'type'))
			{
				$name = $kn = '@type';
				foreach($values as $v)
				{
					$nn = $doc->namespacedName($v, true);
					$nslist = URI::namespaces();
					$x = explode(':', $nn, 2);
					if(count($x) == 2)
					{
						if(!isset($array['@context'][$x[0]]))
						{
							$ns = array_search($x[0], $nslist);
							$array['@context'][$x[0]] = $ns;
						}
						$array[$kn][] = $nn;
					}
					else
					{
						$array[$kn] = $v;
					}
				}
				continue;
			}
			/* Handle known predicates */
			if(isset($predicates[$name]) && isset($predicates[$name]['@@as']))			
			{
				$kn = $predicates[$name]['@@as'];
				if(!isset($array['@context'][$kn]))
				{
					$info = $predicates[$name];
					unset($info['@@as']);
					if(!count($info) || (count($info) == 1 && isset($info['@id'])))
					{
						$array['@context'][$kn] = $name;
					}
					else
					{
						$array['@context'][$kn] = $info;
					}
				}
			}
			/* Handle predicates in known namespaces */
			else
			{
				$kn = $doc->namespacedName($name, true);
				$nslist = URI::namespaces();
				$x = explode(':', $kn, 2);
				if(count($x) == 2 && !isset($array['@context'][$x[0]]))
				{
					$ns = array_search($x[0], $nslist);
					$array['@context'][$x[0]] = $ns;
				}
				/* Handle known predicates */			
				if(isset($predicates[$name]) && !isset($array['@context'][$kn]))
				{
					$array['@context'][$kn] = $predicates[$name];
				}
			}
			foreach($values as $v)
			{				
				if($v instanceof RDFURI)
				{
					$vn = $doc->namespacedName($v, false);				
				}
				if(is_object($v))
				{
					$value = $v->asJSONLD($doc);
				}
				else
				{
					$value = strval($v);
				}
				if($kn == '@' || $kn == 'a' ||
					(is_array($value) && isset($predicates[$name]) && isset($predicates[$name]['@type']) && $predicates[$name]['@type'] == '@id')) 
				{
					if($kn != '@' && $kn != 'a')
					{
						$up[$name] = $kn;
					}
					if(isset($value['@id']))
					{
						$value = $value['@id'];
					}
				}
				else if(is_array($value) && isset($predicates[$name]['@type']))
				{
					/* If this is a known predicate with a specified type and the type of
					 * the value matches, we can reduce the value to simply a literal.
					 */
					if(isset($value['@type']) && !strcmp($value['@type'], $predicates[$name]['@type']))
					{
						unset($value['@type']);
						if(count($value) == 1 && isset($value['@value']))
						{
							$value = $value['@value'];
						}
					}
				}
				if(isset($array[$kn]))
				{
					if(empty($isArray[$kn]))
					{
						$array[$kn] = array($array[$kn]);
						$isArray[$kn] = true;
					}
					$array[$kn][] = $value;
				}
				else
				{
					$array[$kn] = $value;
				}
			}
		}
		if(count($array['@type']) == 1)
		{
			$array['@type'] = $array['@type'][0];
		}
		if(!isset($array['@']))
		{
			unset($array['@']);
		}
		if(!isset($array['a']))
		{
			unset($array['a']);
		}
		if(isset($this->contextUri))
		{
			$array['@context'] = strval($this->contextUri);
		}
		return $array;
	}
	
	public function __toString()
	{
		assert($this->subject !== null);
		assert($this->subject->resource !== null);
		if(librdf_node_is_blank($this->subject->resource))
		{
			return '_:' . librdf_node_get_blank_identifier($this->subject->resource);
		}
		$uri = librdf_node_get_uri($this->subject->resource);
		return librdf_uri_to_string($uri);
	}

	public function isA($type = null)
	{
		$list = array();
		if($type === null)
		{
			$query = librdf_new_statement_from_nodes($this->world->resource,
													 $this->subject->resource,
													 librdf_new_node_from_uri($this->world->resource,
																			  librdf_new_uri($this->world->resource, RDF::rdf.'type')),
													 null);
			$rs = librdf_model_find_statements($this->model->resource, $query);
			while(!librdf_stream_end($rs))
			{
				$list[] = RDFURI::fromNode(librdf_statement_get_object(librdf_stream_get_object($rs)));
				librdf_stream_next($rs);
			}
			return $list;
		}
		$type = $this->translateQName($type);
		if(!strcmp($type, RDF::rdf.'Description'))
		{
			return true;
		}
		if($this->model === null)
		{
			return false;
		}
		$query = librdf_new_statement_from_nodes($this->world->resource,
												 $this->subject->resource,
												 librdf_new_node_from_uri($this->world->resource,
																		  librdf_new_uri($this->world->resource, RDF::rdf.'type')),
												 librdf_new_node_from_uri($this->world->resource,
																		  librdf_new_uri($this->world->resource, $type)));
		$rs = librdf_model_find_statements($this->model->resource, $query);
		if(librdf_stream_end($rs))
		{
			return false;
		}
		return true;												 
	}
		
}

class RDFComplexLiteral extends RedlandNode
{
	public $value;
	public $type = null;
	public $lang = null;

	public static function literal($type = null, $value = null, $lang = null, $world = null)
	{
		if($value instanceof RedlandNode)
		{
			$value = $value->resource;
		}
		if(is_resource($value))
		{
			if($type === null)
			{
				$typeuri = librdf_node_get_literal_value_datatype_uri($value);
				if(is_resource($typeuri))
				{
					$type = librdf_uri_to_string($typeuri);
				}
			}
			if($lang === null)
			{
				$lang = librdf_node_get_literal_value_language($value);
			}
		}
		if(!strcmp($type, 'http://www.w3.org/2001/XMLSchema#dateTime'))
		{
			return new RDFDatetime($value, $world);
		}
		return new RDFComplexLiteral($type, $value, $lang, $world);
	}

	public function __construct($type = null, $value = null, $lang = null, $world = null)
	{
		if($lang !== null && !is_string($lang))
		{
			trigger_error('RDFComplexLiteral::__construct(): specified is not a string (' . $lang . ')', E_USER_ERROR);
		}
		$world = RedlandBase::world($world);
		if($type !== null)
		{
			$this->type = new RDFURI($type);
		}
		if($lang !== null)
		{
			$this->lang = $lang;
		}
		$this->world = $world;
		$this->setValue($value);
	}

	protected function setValue($value)
	{
		if(is_resource($value))
		{
			$this->resource = $value;
			$this->type = $this->type();
			$this->lang = $this->lang();
		}
		else if($this->type !== null)
		{
			$this->resource = librdf_new_node_from_typed_literal($this->world->resource, $value, null, $this->type === null ? null : $this->type->resource);
		}
		else
		{
			$this->resource = librdf_new_node_from_literal($this->world->resource, $value, $this->lang, false);
		}
		$this->value = librdf_node_get_literal_value($this->resource);
	}

	public function lang()
	{
		return librdf_node_get_literal_value_language($this->resource);
	}
	
	public function type()
	{
		$u = librdf_node_get_literal_value_datatype_uri($this->resource);
		if($u === null)
		{
			return null;
		}
		return librdf_uri_to_string($u);
	}

	public function __toString()
	{
		return strval($this->value);
	}

	public function asJSONLD()
	{
		$l = $this->lang();
		$t = $this->type();
		if(strlen($l) || strlen($t))
		{
			$a = array('@value' => $this->value);
			if(strlen($l))
			{
				$a['@language'] = $l;
			}
			if(strlen($t))
			{
				$a['@type'] = $t;
			}
			return $a;
		}
		return strval($this->value);
	}
}

class RDFXMLLiteral extends RDFComplexLiteral
{
	public function __construct($value, $world = null)
	{
		$world = RedlandBase::world($world);		
		$res = librdf_new_node_from_literal($world->resource, $value, null, true);
		return parent::__construct(null, $res, null, $world);
	}
}

class RDFDocument extends RedlandModel implements ArrayAccess, ISerialisable
{
	public static $parseableTypes = array(
		'text/turtle',
		'application/x-turtle',
		'text/n3',
		'text/rdf+n3',
		'application/n3',
		'application/rdf+xml',
		'text/html',
		'application/xhtml+xml',
		'application/ntriples',
		);

	protected static $serialisations = array(
		'text/turtle' => array('title' => 'Turtle'),
		'application/rdf+xml' => array('title' => 'RDF/XML'),
		'application/n3' => array('title' => 'N3'),
		'application/n-triples' => array('title' => 'N-Triples', 'alt' => array('text/plain' => 'Text')),
		'text/n3' => array('title' => 'N3', 'hide' => true),
		'text/plain' => array('title' => 'N-Triples', 'hide' => true),
		'text/rdf+n3' => array('title' => 'N3', 'hide' => true),
		'application/ld+json' => array('title' => 'JSON-LD', 'hide' => true),
		'application/json' => array('title' => 'JSON', 'alt' => array('application/ld+json' => 'JSON-LD')),
		'application/x-xmp+xml' => array('title' => 'Adobe XMP', 'hide' => true),
		);

	public $fileURI;
	public $primaryTopic;
	public $rdfInstanceClass = 'RDFInstance';
	public $namespaces = array();
	public $xmlStylesheet = null;

	public $htmlHead = null;
	public $htmlPreBody = null;
	public $htmlPostBody = null;
	public $htmlLinks = array();
	public $htmlTitle = null;

	public $knownPredicates = array();
	public $contextUri = null;

	protected $qnames = array();
	protected $positions = array();

	/* Callback for generating links in HTML views; invoked as
	 * filter($target, $predicate, $text, $subj, $doc)
	 * $predicate is null if the link is to a predicate itself
	 */
	public $linkFilter = null;

	public function __construct($fileURI = null, $primaryTopic = null, $storage = null, $options = null, $world = null)
	{
		$this->fileURI = $fileURI;
		$this->primaryTopic = $primaryTopic;
		parent::__construct($storage, $options, $world);
	}

	public function parse($type, $content)
	{
		if($content instanceof DOMNode)
		{
			/* XXX Assumes the whole document; will this ever not be true? */
			$content = $content->ownerDocument->saveXML();
		}
		switch($type)
		{
		case 'application/rdf+xml':
			$parser = new RedlandRDFXMLParser();
			break;
		case 'text/turtle':
		case 'application/x-turtle':
			$parser = new RedlandTurtleParser();
			break;
		case 'text/n3':
		case 'application/n3':
		case 'text/rdf+n3':
			$parser = new RedlandN3Parser();
			break;
		case 'text/plain':
		case 'application/n-triples':
			$parser = new RedlandNTriplesParser();
			break;
		case 'text/html':
		case 'application/xhtml+xml':
			$parser = new RedlandRDFaParser();
			break;
		default:
			return false;
		}
		return $parser->parseStringIntoModel($content, $this->fileURI, $this);
	}

	public function add(RDFInstance $inst, $pos = null)
	{
		if(($inst = $this->merge($inst, $pos)))
		{
			$this->promote($inst);
			if($pos !== null)
			{
				$s = strval($inst->subject());
				array_splice($this->positions, $pos, 0, array($s));
			}
		}
		return $inst;		
	}

	public function serialisations()
	{
		return self::$serialisations;
	}

	/* ISerialisable::serialise */
	public function serialise(&$type, $returnBuffer = false, $request = null, $sendHeaders = null)
	{
		if(!isset($request) || $returnBuffer)
		{
			$sendHeaders = false;
		}
		else if($sendHeaders === null)
		{
			$sendHeaders = true;
		}
		if($returnBuffer)
		{
			ob_start();
		}
		if($type == 'text/html')
		{
			if($sendHeaders)
			{
				$request->header('Content-type', 'text/html; charset=UTF-8');
			}
			$output = $this->asHTML();
		}
		else if($type == 'text/turtle')
		{
			if($sendHeaders)
			{
				$request->header('Content-type', $type);
			}			
			$output = $this->asTurtle();
		}
		else if($type == 'application/rdf+xml')
		{
			if($sendHeaders)
			{
				$request->header('Content-type', $type);
			}			
			$output = $this->asXML();
		}
		else if($type == 'application/json' || $type == 'text/javascript')
		{
			if($sendHeaders)
			{
				$request->header('Content-type', $type);
			}
			$output = $this->asJSONLD($type, $request, $sendHeaders);
		}
		else if($type == 'application/ld+json')
		{
			if($sendHeaders)
			{
				$request->header('Content-type', $type);
			}
			$output = $this->asJSONLD($type, $request, $sendHeaders);
		}			
		else if($type == 'application/rdf+json' || $type == 'application/x-rdf+json')
		{
			$type = 'application/json';
			if($sendHeaders)
			{
				$request->header('Content-type', $type);
			}
			$output = $this->asJSON();
		}
		else if($type == 'text/n3' || $type == 'application/n3' || $type == 'text/rdf+n3')
		{
			if($sendHeaders)
			{
				$request->header('Content-type', $type);
			}
			$output = $this->asN3();
		}
		else if($type == 'application/n-triples' || $type == 'text/plain')
		{
			if($sendHeaders)
			{
				$request->header('Content-type', $type);
			}
			$output = $this->asNTriples();
		}
		else if($type == 'text/x-nquads')
		{
			if($sendHeaders)
			{
				$request->header('Content-type', $type);
			}
			$output = $this->asNQuads();
		}
		else if($type == 'application/x-xmp+xml')
		{
			if($sendHeaders)
			{
				$request->header('Content-type', 'text/xml');
			}
			$output = $this->asXMP();
		}
		else
		{
			if($returnBuffer)
			{
				ob_end_clean();
			}
			return false;
		}		
		echo is_array($output) ? implode("\n", $output) : $output;
		if($returnBuffer)
		{
			return ob_get_clean();
		}
		return true;
	}

	public function merge(RDFInstance $inst, $post = null)
	{
		if($inst->model === null)
		{
			/* Doesn't contain anything yet */
			return $inst;
		}
		if($inst->model === $this)
		{
			/* Already exists in this graph */
			return $inst;
		}
		foreach($inst->subsidiaries as $sub)
		{
			$this->merge($sub);
		}
		$subject = $inst->subject->resource;
		$this->mergeSubject($subject, $inst->model->resource);		
		$inst->model = $this;
		librdf_model_sync($this->resource);
		if(!isset($inst->knownPredicates))
		{
			$inst->knownPredicates = $this->knownPredicates;
		}
		if(!isset($inst->contextUri))
		{
			$inst->contextUri = $this->contextUri;
		}
		return $inst;
	}
		
	protected function mergeSubject($subject, $model)
	{
		$query = librdf_new_statement_from_nodes($this->world->resource, $subject, null, null);
		$statements = librdf_model_find_statements($model, $query);
		librdf_model_add_statements($this->resource, $statements);
		/* Add any associated bnodes */
		$rs = librdf_model_find_statements($model, $query);
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$obj = librdf_statement_get_object($statement);
			if(librdf_node_is_blank($obj))
			{
				$this->mergeSubject($obj, $model);
			}
			librdf_stream_next($rs);
		}
	}

	/* ArrayAccess::offsetGet() */
	public function offsetGet($key)
	{
		if(!strcasecmp($key, 'primaryTopic'))
		{
			return $this->primaryTopic();
		}
		if(!strcasecmp($key, 'resourceTopic'))
		{
			return $this->resourceTopic();
		}
		$key = strval($key);
		if(!strlen($key))
		{
			return null;
		}
		return $this->subject($key, null, false);
	}

	/* ArrayAccess::offsetSet() */
	public function offsetSet($key, $what)
	{
		if($key !== null)
		{
			throw new Exception('Explicit keys cannot be specified in RDFDocument::offsetSet');
		}
		if(($what instanceof RDFInstance))
		{				
			$this->add($what);
			return true;
		}
		throw new Exception('Only RDFInstance instances may be assigned via RDFDocument::offsetSet');
	}

	/* ArrayAccess::offsetExists() */
	public function offsetExists($key)
	{
		if($this->offsetGet($key) !== null)
		{
			return true;
		}
		return false;
	}

	/* ArrayAccess::offsetUnset() */
	public function offsetUnset($key)
	{
		throw new Exception('Subjects may not be unset via RDFDocument::offsetUnset');
	}

	public function asXML($leader = null)
	{
		$ser = new RedlandRDFXMLSerializer();
		$s = $ser->serializeModelToString($this);
		$preamble = '<?xml version="1.0" encoding="utf-8"?>';
		if(!strlen($leader) && isset($this->xmlStylesheet))
		{
			$leader = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
				'<?xml-stylesheet type="' . _e($this->xmlStylesheet['type']) . '" href="' . _e($this->xmlStylesheet['href']) . '" ?>' . "\n";
		}
		if($leader !== null && !strncmp($s, $preamble, strlen($preamble)))
		{
			return $leader . ltrim(substr($s, strlen($preamble)));
		}
		return $s;
	}
	
	public function asHTML()
	{		
		$buf = array();
		$buf[] = '<!DOCTYPE html>';
		$buf[] = '<html>';
		$buf[] = '<head>';
		$buf[] = '<meta charset="UTF-8">';
		if(isset($this->htmlTitle))
		{
			$buf[] = '<title>' . _e($this->htmlTitle) . '</title>';
		}
		if(isset($this->htmlLinks))
		{
			foreach($this->htmlLinks as $link)
			{
				$t = '<link';
				foreach($link as $k => $v)
				{
					$t .= ' ' . $k . '="' . _e($v) . '"';
				}
				$t .= '>';
				$buf[] = $t;
			}
		}
		if(isset($this->htmlHead))
		{
			$buf[] = $this->htmlHead;
		}
		$buf[] = '</head>';
		$buf[] = '<body>';
		if(isset($this->htmlPreBody))
		{
			$buf[] = $this->htmlPreBody;
		}
		$array = array();
		$subjects = array();
		$rs = librdf_model_as_stream($this->resource);		
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$subject = librdf_statement_get_subject($statement);
			if(($u = librdf_node_get_uri($subject)) !== null)
			{
				$k = librdf_uri_to_string($u);
			}
			else
			{
				$k = librdf_node_to_string($subject);
			}
			$subjects[$k] = $subject;
			librdf_stream_next($rs);
		}
		$positions = $this->positions;
		$done = array();
		$i = 0;
		while(true)
		{
			if(isset($positions[$i]))
			{
				$done[$positions[$i]] = true;
				if(($obj = $this->subject($positions[$i], null, false, true)) !== null)
				{
					$buf[] = $obj->asHTML($this);
				}
				unset($positions[$i]);
				$i++;
				continue;
			}
			$subj = array_shift($subjects);
			if($subj === null)
			{
				break;
			}
			if(isset($done[$subj]))
			{
				continue;
			}
			if(($obj = $this->subject($subj, null, false, true)) !== null)
			{
				$buf[] = $obj->asHTML($this);
			}		
			$i++;
		}
		foreach($positions as $subj)
		{
			if(($obj = $this->subject($subj, null, false, true)) !== null)
			{
				$buf[] = $obj->asHTML($this);
			}
		}
		if(isset($this->htmlPostBody))
		{
			$buf[] = $this->htmlPostBody;
		}
		$buf[] = '</body>';
		$buf[] = '</html>';
		return $buf;
	}
	

	public function asTurtle()
	{
		$ser = new RedlandTurtleSerializer();
		return $ser->serializeModelToString($this);
	}

	public function asN3()
	{
		$ser = new RedlandN3Serializer();
		return $ser->serializeModelToString($this);
	}

	public function asNQuads()
	{
		$ser = new RedlandNQuadsSerializer();
		return $ser->serializeModelToString($this);
	}

	public function asNTriples()
	{
		$ser = new RedlandNTriplesSerializer();
		return $ser->serializeModelToString($this);
	}

	public function asXMP()
	{
		$ser = new RedlandXMPSerializer();
		return $ser->serializeModelToString($this);
	}

	public function asJSON()
	{
		$ser = new RedlandJSONSerializer();
		return $ser->serializeModelToString($this);
	}

	public function asJSONTriples()
	{
		$ser = new RedlandJSONSerializer('json-triples');
		return $ser->serializeModelToString($this);
	}

	public function asJSONLD($type = 'application/ld+json', $request, $sendHeaders)
	{
		$array = array();
		$subjects = array();
		$rs = librdf_model_as_stream($this->resource);		
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$subject = librdf_node_get_uri(librdf_statement_get_subject($statement));
			$k = librdf_uri_to_string($subject);
			$subjects[$k] = $subject;
			librdf_stream_next($rs);
		}
		$positions = $this->positions;
		$done = array();
		$i = 0;
		while(true)
		{
			if(isset($positions[$i]))
			{
				$done[$positions[$i]] = true;
				if(($obj = $this->subject($positions[$i], null, false)) !== null)
				{
					$array[] = $obj->asJSONLD($this);
				}
				unset($positions[$i]);
				$i++;
				continue;
			}
			$subj = array_shift($subjects);
			if($subj === null)
			{
				break;
			}
			if(isset($done[$subj]))
			{
				continue;
			}
			if(($obj = $this->subject($subj, null, false)) !== null)
			{
				$array[] = $obj->asJSONLD($this);
			}		
			$i++;
		}
		foreach($positions as $subj)
		{
			if(($obj = $this->subject($subj, null, false)) !== null)
			{
				$array[] = $obj->asJSONLD($this);
			}			
		}
		if(!strcmp($type, 'application/json') && isset($this->contextUri))
		{
			if($sendHeaders)
			{
				$request->header('Link', '<' . $this->contextUri . '>; rel="http://www.w3.org/ns/json-ld#context"; type="application/ld+json"', false);
			}
			foreach($array as $k => $subj)
			{
				unset($array[$k]['@context']);				
			}
		}
		if(count($array) == 1)
		{
			foreach($array as $subj)
			{
				$array = $subj;
				break;
			}
		}
		return str_replace('\/', '/', json_encode($array));		
	}
	
	public function promote($subject)
	{
		/* No-op */
	}

	/* Given a URI, generate a prefix:short form name; return $uri if unable to contract */
	public function namespacedName($uri, $generate = true)
	{
		$result = URI::contractUri($uri, $generate);
		if(!strlen($result))
		{
			return $uri;
		}
		return $result;
	}

	/* Return the RDFInstance which is either explicitly or implicitly the
	 * resource topic of this document.
	 */
	public function resourceTopic()
	{
		$top = $file = null;
		if(isset($this->fileURI))
		{
			$top = $file = $this->subject($this->fileURI, null, false);
			if($file)
			{
				return $file;
			}
		}
		$rs = librdf_model_as_stream($this->resource);
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$subject = librdf_statement_get_subject($statement);
			return $this->subject(librdf_node_get_uri($subject), null, false);
		}
		return null;
	}		

	/* Return the RDFInstance which is either explicitly or implicitly the
	 * primary topic of this document.
	 */
	public function primaryTopic()
	{
		if(isset($this->primaryTopic))
		{
			return $this->subject($this->primaryTopic, null, false);
		}
		$top = $file = null;
		if(isset($this->fileURI))
		{
			$top = $file = $this->subject($this->fileURI, null, false);			
			if(!isset($top->{RDF::foaf . 'primaryTopic'}))
			{
				$top = null;
			}
		}
/*		if(!$top)
		{
			foreach($this->subjects as $g)
			{
				if(isset($g->{RDF::rdf . 'type'}[0]) && !strcmp($g->{RDF::rdf . 'type'}[0], RDF::rdf . 'Description'))
				{
					$top = $g;
					break;
				}
			}			
			} */
		if(!$top)
		{
			$rs = librdf_model_as_stream($this->resource);
			while(!librdf_stream_end($rs))
			{
				$statement = librdf_stream_get_object($rs);
				$subject = librdf_statement_get_subject($statement);
				$top = $this->subject(librdf_node_get_uri($subject), null, false);
				break;
			}
		}
		if(!$top)
		{
			return null;
		}
		if(isset($top->{RDF::foaf . 'primaryTopic'}[0]))
		{			
			if($top->{RDF::foaf . 'primaryTopic'}[0] instanceof RDFInstance)
			{
				return $top->{RDF::foaf . 'primaryTopic'}[0];
			}
			$uri = strval($top->{RDF::foaf . 'primaryTopic'}[0]);
			$g = $this->subject($uri, null, false);
			if($g)
			{
				return $g;
			}
		}
		if($file)
		{
			return $file;
		}
		return $top;
	}

	public function subject($uri, $type = null, $create = true, $isNode = false)
	{
		if($isNode)
		{
			$res = $uri;
		}
		else if(is_resource($uri))
		{
			$res = librdf_new_node_from_uri($this->world->resource, $uri);
		}
		else if(!strncmp($uri, "_:", 2))
		{
			$res = librdf_new_node_from_blank_identifier($this->world->resource, substr($uri, 2));
		}
		else
		{
			$res = librdf_new_node_from_uri_string($this->world->resource, $uri);
		}
		$node = new RedlandNode($res, $this->world);
		$setType = false;
		if(!$create || $type !== null)
		{
			$query = librdf_new_statement_from_nodes($this->world->resource, $res, null, null);
			$rs = librdf_model_find_statements($this->resource, $query);
			if(librdf_stream_end($rs))
			{
				if(!$create)
				{
					return null;
				}
				$setType = true;
			}
		}
		$query = librdf_new_statement_from_nodes($this->world->resource, $res, librdf_new_node_from_uri_string($this->world->resource, RDF::rdf.'type'), null);
		$rs = librdf_model_find_statements($this->resource, $query);
		$types = array();
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$object = librdf_statement_get_object($statement);
			if(librdf_node_is_resource($object))
			{
				$types[] = librdf_uri_to_string(librdf_node_get_uri($object));
			}
			librdf_stream_next($rs);
		}
		$inst = RDF::instanceForClass($types, null);
		if($inst === null)
		{
			$class = $this->rdfInstanceClass;
			$inst = new $class();
		}
		$inst->model = $this;
		if(!$node)
		{
			$node = new RedlandNode($res, $this->world);
		}
		$inst->subject = $node;
		if($setType)
		{
			$inst->{RDF::rdf.'type'} = new RDFURI($type);
		}
		$inst->transform();
		if(!isset($inst->knownPredicates))
		{
			$inst->knownPredicates = $this->knownPredicates;
		}
		if(!isset($inst->contextUri))
		{
			$inst->contextUri = $this->contextUri;
		}			
		return $inst;
	}

	public function subjectUris()
	{
		$subjects = array();
		$rs = librdf_model_as_stream($this->resource);		
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$subj = librdf_statement_get_subject($statement);
			if(librdf_node_is_blank($subj))
			{
				$k = '_:' . librdf_node_get_blank_identifier($subj);
			}
			else
			{
				$subject = librdf_node_get_uri($subj);
				$k = librdf_uri_to_string($subject);
			}
			$subjects[$k] = $k;
			librdf_stream_next($rs);
		}		
		return array_values($subjects);
	}


	public function subjects()
	{
		$list = array();
		$subjects = array();
		$rs = librdf_model_as_stream($this->resource);		
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$subj = librdf_statement_get_subject($statement);
			if(librdf_node_is_blank($subj))
			{
				$id = librdf_node_get_blank_identifier($subj);
				$k = '_:' . $id;
				$subject = $k;
			}
			else
			{
				$subject = librdf_node_get_uri($subj);
				$k = librdf_uri_to_string($subject);
			}
			$subjects[$k] = $subject;
			librdf_stream_next($rs);
		}
		foreach($subjects as $subj)
		{
			$list[] = $this->subject($subj, null, false);
		}
		return $list;
	}

	public function subjectsReferencing($target)
	{
		$list = array();
		$subjects = array();
		$query = librdf_new_statement_from_nodes($this->world->resource, null, null, $target->subject->resource);
		$rs = librdf_model_find_statements($this->resource, $query);
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$subject = librdf_statement_get_subject($statement);
			if(!in_array($subject, $subjects))
			{
				$subjects[] = $subject;
			}
			librdf_stream_next($rs);
		}
		foreach($subjects as $subj)
		{
			$list[] = $this->subject(librdf_node_get_uri($subj), null, false);
		}
		return $list;
	}
}

class RDFSet extends RedlandModel implements Countable
{
	protected $blank;
	public $sourceModel;
	
	public static function setFromInstances($keys, $instances /* ... */)
	{
		$set = new RDFSet();
		$instances = func_get_args();
		array_shift($instances);
		foreach($instances as $list)
		{
			if($list instanceof RDFInstance)
			{
				$list = array($list);
			}
			foreach($list as $instance)
			{
				if(is_array($instance) && isset($instance[0]))
				{
					$instance = $instance[0];
				}
				if(!is_object($instance))
				{
					throw new Exception('RDFSet::setFromInstances() invoked with a non-object instance');
				}
				if(!($instance instanceof RDFInstance))
				{
					throw new Exception('RDFSet::setFromInstances() invoked with a non-RDF instance');
				}
				$set->addInstance($keys, $instance);
			}
		}
		return $set;
	}

	public function __construct($values = null, $world = null, $sourceModel = null)
	{
		parent::__construct(null, null, $world);
		$this->sourceModel = $sourceModel;
		$this->blank = librdf_new_node($this->world->resource);
		$this->blankPredicate = librdf_new_node_from_uri_string($this->world->resource, 'http://example.com/blankPredicate');
		if($values === null) return;
		if(!is_array($values))
		{
			$values = array($values);
		}
		$this->add($values);
	}

	/* Return a simple human-readable representation of the property values */
	public function __toString()
	{
		return $this->join(', ');
	}

	/* Add one or more arrays-of-properties to the set. Call as, e.g.:
	 *
	 * $set->add($inst->{RDF::dc.'title'}, $inst->{RDF::rdfs.'label'});
	 *
	 * Any of the property arrays passed may already be an RDFSet instance, so that
	 * you can do:
	 *
	 * $foo = $k->all(array(RDF::dc.'title', RDF::rdfs.'label'));
	 * $set->add($foo); 
	 */
	public function add($property)
	{
		$props = func_get_args();
		foreach($props as $list)
		{
			if(is_array($list))
			{
				foreach($list as $value)
				{
					$this->addValue($value);
				}
			}
			else
			{
				$this->addValue($list);
			}
		}
	}

	protected function addValue($value)
	{
		if($value instanceof RDFSet)
		{
			librdf_model_add_statements($this->resource, librdf_model_as_stream($value->resource));
			return;
		}
		if($value instanceof RDFURI)
		{
			die('about to add URI');
			$statement = librdf_new_statement_from_nodes($this->world->resource, $this->blank, $this->blankPredicate, $value->node()->resource);
			die('will add statement ' . librdf_statement_to_string($statement));
			librdf_model_add_statement($this->resource, $statement);
			return;
		}
		if($value instanceof RDFInstance)
		{
			librdf_model_add_statements($this->resource, librdf_model_as_stream($value->model->resource));
			return;
		}
		if($value instanceof RedlandNode)
		{
			$statement = librdf_new_statement_from_nodes($this->world->resource, $this->blank, $this->blankPredicate, $value->resource);
			librdf_model_add_statement($this->resource, $statement);
			return;
		}
		if(is_object($value))
		{
			trigger_error("Don't know how to add a " . get_class($value) . " to an RDFSet", E_USER_ERROR);
		}		
		trigger_error("Don't know how to add a " . get_type($value) . " to an RDFSet", E_USER_ERROR);
	}

	/* Remove objects matching the specified string from the set */
	public function removeValueString($string)
	{
		$uri = librdf_new_uri($this->world->resource, $string);		
		$rs = librdf_model_as_stream($this->resource);
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$object = librdf_statement_get_object($statement);
			if(librdf_node_is_resource($object))
			{
				$u = librdf_node_get_uri($object);
				if(librdf_uri_equals($u, $uri))
				{
					librdf_model_remove_statement($this->resource, $statement);
				}
			}
			else
			{
				$v = librdf_node_get_literal_value($object);
				if(!strcmp($v, $string))
				{
					librdf_model_remove_statement($this->resource, $statement);
				}
			}
			librdf_stream_next($rs);
		}
	}

	/* Return all of the values as an array */
	public function values()
	{
		$values = array();
		$rs = librdf_model_as_stream($this->resource);
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$object = librdf_statement_get_object($statement);
			$values[] = $this->nodeToObject($object);
			librdf_stream_next($rs);
		}
		return $values;
	}

	/* Return an array containing one value per language */
	public function valuePerLanguage($asSet = false)
	{
		$list = array();
		$rs = librdf_model_as_stream($this->resource);
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$object = librdf_statement_get_object($statement);
			if(librdf_node_is_literal($object))
			{
				$l = librdf_node_get_literal_value_language($object);
				if(!strlen($l))
				{
					$l = '';
				}
				if(!isset($list[$l]))
				{
					if($asSet)
					{
						$list[$l] = new RDFString(librdf_node_get_literal_value($object), $l);
					}
					else
					{
						$list[$l] = librdf_node_get_literal_value($object);
					}
				}
			}
			librdf_stream_next($rs);
		}
		if($asSet)
		{
			return new RDFSet($list);
		}
		return $list;
	}
	
	/* Return a slice of the set */
	public function slice($start, $count)
	{
		trigger_error('RDFSet::slice()', E_USER_ERROR);
		return new RDFSet(array_slice($this->values, $start, $count));
	}

	/* Return all of the values as an array of strings */
	public function strings()
	{
		$list = array();
		$rs = librdf_model_as_stream($this->resource);
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$object = librdf_statement_get_object($statement);
			if(librdf_node_is_literal($object))
			{
				$list[] = librdf_node_get_literal_value($object);
			}
			else if(librdf_node_is_resource($object))
			{
				$u = librdf_node_get_uri($object);
				$list[] = librdf_uri_to_string($u);
			}
			else
			{
				$list[] = librdf_node_to_string($object);
			}
			librdf_stream_next($rs);
		}
		return $list;
	}

	/* Return a string joining the values with the given string */
	public function join($by)
	{
		return implode($by, $this->strings());
	}

	/* Return all of the values which are URIs (or instances) as an array
	 * of RDFURI instances
	 */
	public function uris()
	{
		$list = array();
		$rs = librdf_model_as_stream($this->resource);
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$object = librdf_statement_get_object($statement);
			if(librdf_node_is_resource($object))
			{
				$uri = librdf_node_get_uri($object);
				$list[] = new RDFURI($uri);
			}
			librdf_stream_next($rs);
		}
		return $list;
	}
	
	/* Add the named properties from one or more instances to the set. As with
	 * RDFInstance::all(), $keys may be an array. Multiple instances may be
	 * supplied, either as additional arguments, or as array arguments, or
	 * both.
	 */
	public function addInstance($keys, $instance)
	{
		$instances = func_get_args();
		array_shift($instances);
		if(!is_array($keys)) $keys = array($keys);
		foreach($keys as $k => $key)
		{
			if(!is_resource($key))
			{			   
				$keys[$k] = librdf_new_node_from_uri($this->world->resource, librdf_new_uri($this->world->resource, $key));
			}
		}
		foreach($instances as $list)
		{
			if(!is_array($list))
			{
				$list = array($list);
			}
			foreach($list as $instance)
			{
				foreach($keys as $k)
				{
					if(!strcmp(librdf_node_to_string($k), '<' . RDF::rdf.'about' . '>'))
					{
						$statement = librdf_new_statement_from_nodes($this->world->resource, $this->blank, $this->blankPredicate, librdf_new_node_from_uri_string($this->world->resource, $instance->subject()));
						librdf_model_add_statement($this->resource, $statement);
						continue;
					}
					$query = librdf_new_statement_from_nodes($this->world->resource, $instance->subject->resource, $k, null);
					$rs = librdf_model_find_statements($instance->model->resource, $query);
					librdf_model_add_statements($this->resource, $rs);
				}
			}
		}
	}

	/* Return the first value in the set */
	public function first()
	{
		$rs = librdf_model_as_stream($this->resource);
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$object = librdf_statement_get_object($statement);
			if(librdf_node_is_literal($object))
			{
				return librdf_node_get_literal_value($object);
			}
			if(librdf_node_is_resource($object))
			{
				$u = librdf_node_get_uri($object);
				return librdf_uri_to_string($u);
			}
			return librdf_node_to_string($object);
		}
		return null;
	}
	
	/* Return the number of values held in this set; can be
	 * called as count($set) instead of $set->count().
	 */
	public function count()
	{
		return librdf_model_size($this->resource);
	}
	
	/* Return the value matching the specified language. If $lang
	 * is an array, it specifies a list of languages in order of
	 * preference. if $fallbackFirst is true, return the first
	 * value instead of null if no language match could be found.
	 * $langs may be an array of languages, or a comma- or space-
	 * separated list in a string.
	 */
	public function lang($langs = null, $fallbackFirst = false)
	{
		if($langs === null)
		{
			$langs = RDF::$langs;
		}
		if(!is_array($langs))
		{
			$langs = explode(',', str_replace(' ', ',', $langs));
		}
		foreach($langs as $lang)
		{
			$lang = trim($lang);
			if(!strlen($lang)) continue;
			$rs = librdf_model_as_stream($this->resource);
			while(!librdf_stream_end($rs))
			{
				$statement = librdf_stream_get_object($rs);
				$object = librdf_statement_get_object($statement);
				if(librdf_node_is_literal($object))
				{
					if(!strcmp($lang, librdf_node_get_literal_value_language($object)))
					{
						return librdf_node_get_literal_value($object);
					}
				}
				librdf_stream_next($rs);
			}
		}
		$rs = librdf_model_as_stream($this->resource);
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$object = librdf_statement_get_object($statement);
			if(librdf_node_is_literal($object))
			{
				if(!strlen(librdf_node_get_literal_value_language($object)))
				{
					return librdf_node_get_literal_value($object);
				}
			}
			librdf_stream_next($rs);
		}
		if($fallbackFirst)
		{
			$rs = librdf_model_as_stream($this->resource);
			while(!librdf_stream_end($rs))
			{
				$statement = librdf_stream_get_object($rs);
				$object = librdf_statement_get_object($statement);
				if(librdf_node_is_literal($object))
				{
					return librdf_node_get_literal_value($object);
				}
				librdf_stream_next($rs);
			}
		}
		return null;
	}
	
	/* Return the values as an array of RDF/JSON-structured values */
	public function asArray()
	{
		$list = array();
		$ser = new RedlandJSONSerializer();
		$json = $this->serialiseToString($ser, librdf_node_get_uri($this->blank));
		$subjects = json_decode($json, true);
		foreach($subjects as $predicates)
		{
			foreach($predicates as $predicate => $values)
			{
				foreach($values as $v)
				{
					if(!in_array($v, $list))
					{
						$list[] = $v;
					}
				}
			}
		}
		return $list;
	}

	public /*internal*/ function addStream($stream)
	{
		librdf_model_add_statements($this->resource, $stream);
	}
	
	public /*internal*/ function matchAlt(RedlandModel $doc)
	{
		$list = array();
		$rs = librdf_model_as_stream($this->resource);
		$prefix = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#_';
		while(!librdf_stream_end($rs))
		{
			$statement = librdf_stream_get_object($rs);
			$object = librdf_statement_get_object($statement);			
			if(librdf_node_is_blank($object))
			{
				$query = librdf_new_statement_from_nodes($doc->world->resource, $object, null, null);
				$stream = librdf_model_find_statements($doc->resource, $query);
				while(!librdf_stream_end($stream))
				{
					$st = librdf_stream_get_object($stream);
					$pred = librdf_statement_get_predicate($st);
					$uri = librdf_node_get_uri($pred);
					$u = librdf_uri_to_string($uri);
					if(!strncmp($u, $prefix, strlen($prefix)))
					{
						$suf = substr($u, strlen($prefix));
						if(ctype_digit($suf))
						{
							$stx = librdf_new_statement_from_statement($st);
							librdf_statement_set_predicate($stx, librdf_statement_get_predicate($statement));
							librdf_model_add_statement($this->resource, $stx);
						}
					}
					librdf_stream_next($stream);
				}
			}
			librdf_stream_next($rs);
		}
	}
}

