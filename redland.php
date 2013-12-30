<?php

/* Copyright 2010-2013 Mo McRoberts.
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

require_once(dirname(__FILE__) . '/uri.php');

abstract class RedlandBase
{
	protected $resource;
	protected $resourceDestructor = null;
	protected $weak = false;
	protected $dependents;

	public function __construct($resource, $dependents = null)
	{
		if(!is_resource($resource))
		{
			throw new Exception('Argument 1 passed to RedlandBase::__construct() must be a resource');
		}
		$this->resource = $resource;
		$this->dependents = $dependents;
	}
	
	public function __destruct()
	{
		if(!$this->weak && is_resource($this->resource) && isset($this->resourceDestructor))
		{
			call_user_func($this->resourceDestructor, $this->resource);
		}
		$this->resource = null;
	}

	protected static function checkNullOrInstance($value, $className, $pos, $method, $defVal = null)
	{
		if($value === null)
		{
			return $defVal;
		}
		if(!is_object($value) || !($value instanceof $className))
		{
			throw new Exception('Argument ' . $pos . ' passed to ' . $method . '() must be null or a ' . $type . ' instance');
		}
		return $value;
	}
}

class RedlandWorld extends RedlandBase
{
	protected static $sharedWorld = null;

	protected $resourceDestructor = 'librdf_free_world';

	public static function create()
	{
		$res = librdf_new_world();
		if(!is_resource($res))
		{
			return null;
		}
		librdf_world_open($res);
		return new RedlandWorld($res);
	}		
	
	public static function get($world = null)
	{
		if($world !== null)
		{
			if(!($world instanceof RedlandWorld))
			{
				throw new Exception('Argument 1 passed to RedlandWorld::get() must be null or a RedlandWorld instance');
			}
			return $world;
		}
		if(self::$sharedWorld !== null)
		{
			return self::$sharedWorld;
		}
		/* Obtain the shared world instance */
		$res = librdf_php_get_world();
		if(!is_resource($res))
		{
			return null;
		}
		self::$sharedWorld = new RedlandWorld($res);
		self::$sharedWorld->weak = true;
		return self::$sharedWorld;
	}
}

class RedlandStorage extends RedlandBase
{
	protected $resourceDestructor = 'librdf_free_storage';
	protected $world;

	public static function create($storageName = null, $name = null, $options = null, $world = null)
	{
		$world = RedlandWorld::get($world);
		$res = librdf_new_storage($world->resource, $storageName = null, $name, $options);
		if(!is_resource($res))
		{
			return null;
		}
		return new RedlandStorage($res, $world);
	}

	public static function get($storage = null, $world = null)
	{
		if($storage !== null)
		{
			if(!($storage instanceof RedlandStorage))
			{
				throw new Exception('Argument 1 passed to RedlandStorage::get() must be null or a RedlandStorage instance');
			}
			return $world;
		}
		/* Create a new storage instance with defaults */
		return self::create(null, null, null, $world);
	}
}

class RedlandURI extends RedlandBase
{
	protected $resourceDestructor = 'librdf_free_uri';
	
	public static function create($uristr, $world = null)
	{
		$world = RedlandWorld::get($world);
		$res = librdf_new_uri($world->resource, $uristr);
		if(!is_resource($res))
		{
			return null;
		}
		return new URI($res, $world);
	}
	
	public static function createFromFilename($filename, $world = null)
	{
		$world = RedlandWorld::get($world);
		$res = librdf_new_uri_from_filename($world->resource, $filename);
		if(!is_resource($res))
		{
			return null;
		}
		return new URI($res, $world);
	}
	
	public static function copy(URI $uri)
	{
		$res = librdf_new_uri_from_uri($uri->resource);
		if(!is_resource($res))
		{
			return null;
		}
		return new URI($res);
	}
	
	public static function createWithBase(URI $base, $local)
	{
		$res = librdf_new_uri_from_uri_local_name($base->resource, $local);
		if(!is_resource($res))
		{
			return null;
		}
		return new URI($res);
	}
	
	public function __construct($url, $baseOrDependents = null)
	{
		if(is_resource($url))
		{
			parent::__construct($url, $baseOrDependents);
			return;
		}
		$world = RedlandWorld::get();
		$res = librdf_new_uri($world->resource, $url);
		if($res == false)
		{
			throw new Exception('Failed to construct Redland URI resource from <' . $url . '>');
		}
		$this->resource = $res;
		$this->dependents = array($world);
	}
	
	public function __clone()
	{
		if(isset($this->resource))
		{
			$this->resource = librdf_new_uri_from_uri($this->resource);
		}
		$this->weak = false;
	}
	
	public function equals(URI $uri)
	{
		return librdf_uri_equals($this->resource, $uri->resource);
	}
	
	public function isFile()
	{
		return librdf_uri_is_file_uri($this->resource);
	}
	
	public function toFilename()
	{
		return librdf_uri_to_filename($this->resource);
	}   	
	
	public function __toString()
	{
		return librdf_uri_to_string($this->resource);
	}
}

class RedlandParser extends RedlandBase
{
	protected $resourceDestructor = 'librdf_free_parser';

	public static function create($name = null, $mimeType = null, $typeUri = null, $world = null)
	{
		$world = RedlandWorld::get($world);
		$typeUri = self::checkNullOrInstance($typeUri, 'RedlandURI', 3, 'RedlandParser::create');
		$res = librdf_new_parser($world->resource, $name, $mimeType, ($typeUri === null ? null : $typeUri->resource));
		if(!is_resource($res))
		{
			return null;
		}
		$inst = new RedlandParser($res, $world);
		return $inst;
	}
	
	public function parseIntoModel(RedlandURI $uri, $baseUri = null, RDFGraph $model)
	{
		$baseUri = self::checkNullOrInstance($baseUri, 'RedlandURI', 2, 'RedlandParser::parseIntoModel');
		if(librdf_parser_parse_into_model($this->resource, $uri->resource, ($baseUri === null ? null : $baseUri->resource), $model->resource))
		{
			return false;
		}
		return true;
	}

	public function parseStringIntoModel($string, $baseUri = null, RDFGraph $model)
	{
		if($baseUri !== null && !is_object($baseUri))
		{
			$uri = RedlandURI::create($baseUri);
			if($uri === null)
			{
				throw new Exception('Failed to create URI from <' . $baseUri . '>');
			}
			$baseUri = $uri;
		}
		else
		{
			$baseUri = self::checkNullOrInstance($baseUri, 'RedlandURI', 2, 'RedlandParser::parseStringIntoModel');
		}
		if(librdf_parser_parse_string_into_model($this->resource, $string, ($baseUri === null ? null : $baseUri->resource), $model->resource))
		{
			return false;
		}
		return true;
	}
}

class RedlandSerializer extends RedlandBase
{
	protected $resourceDestructor = 'librdf_free_serializer';
	
	public static function create($name = null, $mimeType = null, $typeUri = null, $world = null)
	{
		$world = RedlandWorld::get($world);
		$typeUri = self::checkNullOrInstance($typeUri, 'RedlandURI', 3, 'RedlandSerializer::create');
		$res = librdf_new_serializer($world->resource, $name, $mimeType, ($typeUri === null ? null : $typeUri->resource));
		if(!is_resource($res))
		{
			return null;
		}
		$inst = new RedlandSerializer($res, $world);
		return $inst;
	}
	
	public function serializeModelToString(RDFGraph $model, $baseUri = null)
	{
		$baseUri = self::checkNullOrInstance($baseUri, 'RedlandURI', 2, 'RedlandSerializer::serializeModelToString');
		return librdf_serializer_serialize_model_to_string($this->resource, ($baseUri === null ? null : $baseUri->resource), $model->resource);
	}
}

class RedlandStream extends RedlandBase implements Iterator
{
	protected $resourceDestructor = 'librdf_free_stream';
	protected $count = 0;
	protected $object = null;
	protected $started = false;
	protected $ended = false;

	public static function create($world = null)
	{
		$world = RedlandWorld::get($world);
		$res = librdf_new_empty_stream($world->resource);
		if(!is_resource($res))
		{
			return null;
		}
		return new RedlandStream($res, $world);
	}

	public function end()
	{
		return librdf_stream_end($this->resource) ? true : false;
	}
	
	public function rewind()
	{
		if($this->started)
		{
			throw new Exception('RedlandStream iterators cannot be rewound');
		}
		$this->started = true;
	}

	public function next()
	{
		$this->started = true;
		$this->object = null;
		if(librdf_stream_next($this->resource))
		{
			$this->ended = true;
			return false;
		}
		$this->count++;
		return true;
	}

	public function current()
	{
		if(!isset($this->object))
		{
			$this->object = $this->object();
		}
		return $this->object;
	}

	public function key()
	{
		return $this->count;
	}

	public function valid()
	{
		if($this->ended)
		{
			return false;
		}
		return true;
	}

	public function object()
	{
		$res = librdf_stream_get_object($this->resource);
		if($res === null)
		{
			return null;
		}
		/* librdf_stream_get_object() returns a shared pointer */
		$st = RDFTriple::alias($res);
		return $st;
	}
}

class RDFGraph extends RedlandBase implements Iterator, ArrayAccess
{
	protected $resourceDestructor = 'librdf_free_model';
	protected $iterator = null;
	protected $subjectFilter = null;
	protected $subjectNode = null;
	protected $predicateFilter = null;
	protected $predicateNode = null;
	protected $curKey = null;
	protected $curVaue = null;
	protected $prevKey = null;
	protected $curObj = null;
	protected $ended = false;

	public static function create($options = null, $storage = null, $world = null)
	{
		$world = RedlandWorld::get($world);
		$storage = RedlandStorage::get($storage, $world);
		$res = librdf_new_model($world->resource, $storage->resource, $options);
		if(!is_resource($res))
		{
			return null;
		}
		$inst = new RDFGraph($res, array($world, $storage));
		return $inst;
	}

	public function __destruct()
	{
		if(isset($this->iterator))
		{
			librdf_free_stream($this->iterator);
			$this->iterator = null;
		}
		if(is_resource($this->subjectNode))
		{
			librdf_free_node($this->subjectNode);
		}
		if(is_resource($this->predicateNode))
		{
			librdf_free_node($this->predicateNode);
		}
		parent::__destruct();
	}

	public function __clone()
	{
		$this->resource = librdf_new_model_from_model($this->resource);
		$this->weak = false;
		$this->subjectNode = null;
		$this->predicateNode = null;
	}

	public function __toString()
	{
		return librdf_model_to_string($this->resource, null, 'ntriples', null, null);
	}

	public function parse($string, $baseUri = null, $mimeType = 'text/turtle', $parserName = null, $typeUri = null)
	{
		$parser = RedlandParser::create($parserName, $mimeType, $typeUri, $this->dependents[0]);
		if($parser === null)
		{
			throw new Exception('Failed to create parser');
		}
		return $parser->parseStringIntoModel($string, $baseUri, $this);
	}
	
	public function serializeToString($baseUri = null, $mimeType = 'text/turtle', $serializerName = null, $typeUri = null)
	{
		$serializer = RedlandSerializer::create($serializerName, $mimeType, $typeUri, $this->dependents[0]);
		if($serializer === null)
		{
			throw new Exception('Failed to create serializer')				
		}
		return $serializer->serializeModelToString($this, $baseUri);
	}

	public function asStream()
	{
		$res = librdf_model_as_stream($this->resource);
		if(!is_resource($res))
		{
			return null;
		}
		return new RedlandStream($res, $this);
	}

	public function add(RDFNode $subject, RDFNode $predicate, RDFNode $object)
	{
		if(isset($this->subjectFilter))
		{
			if(!isset($this->subjectNode))
			{
				$this->subjectNode = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->subjectFilter);
			}
			if(!rdf_node_equals($subject->resource, $this->subjectNode))
			{
				throw new Exception('cannot add triple to filtered model because the subject does not match');
			}
		}
		if(isset($this->predicateFilter))
		{
			if(!isset($this->predicateNode))
			{
				$this->predicateNode = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->subjectFilter);
			}
			if(!rdf_node_equals($predicate->resource, $this->predicateNode))
			{
				throw new Exception('cannot add triple to filtered model because the predicate does not match');
			}
		}
		/* Adding nodes to a model alters the ownership of the nodes, so
		 * we must duplicate them first.
		 */
		$subj = librdf_new_node_from_node($subject->resource);
		$pred = librdf_new_node_from_node($predicate->resource);
		$obj = librdf_new_node_from_node($obj->resource);
		return (librdf_model_add($this->resource, $subj, $pred, $obj) == 0) ? true : false;
	}

	public function addStatement(RDFStatement $statement)
	{
		/* Check that the subject of the statement matches */
		if(isset($this->subjectFilter))
		{
			if(!isset($this->subjectNode))
			{
				$this->subjectNode = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->subjectFilter);
			}
			if(!librdf_node_equals(librdf_statement_get_subject($statement->resource), $this->subjectNode))
			{
				throw new Exception('cannot add statement to filtered model because the subject does not match');
			}
		}
		if(isset($this->predicateFilter))
		{
			if(!isset($this->predicateNode))
			{
				$this->predicateNode = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->predicateFilter);
			}
			if(!librdf_node_equals(librdf_statement_get_predicate($statement->resource), $this->predicateNode))
			{
				throw new Exception('cannot add statement to filtered model because the predicate does not match');
			}
		}
		return (librdf_model_add_statement($this->resource, $statement->resource) == 0) ? true : false;
	}

	/* Iterator methods */

	public function rewind()
	{
		if(isset($this->iterator))
		{
			librdf_free_stream($this->iterator);
		}
		$this->curObj = null;
		$this->curKey = null;
		$this->curValue = null;
		$this->lastKey = null;
		$this->ended = false;
		if(isset($this->subjectFilter))
		{
			/* Ownership of the nodes is transferred to the statement */
			$subj = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->subjectFilter);
			$pred = null;
			if(isset($this->predicateFilter))
			{
				$pred = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->predicateFilter);
			}
			$query = librdf_new_statement_from_nodes($this->dependents[0]->resource, $subj, $pred, null);
			/* Ownership of the query statement is transferred to the
			 * stream; we must not free it ourselves.
			 */
			$this->iterator = librdf_model_find_statements($this->resource, $query);
		}
		else
		{
			$this->iterator = librdf_model_as_stream($this->resource);
		}
		$statement = librdf_stream_get_object($this->iterator);
		if(isset($this->predicateFilter))
		{		   
			$this->curObj = librdf_statement_get_object($statement);
		}
		else if(isset($this->subjectFilter))
		{
			$this->curObj = librdf_statement_get_predicate($statement);
		}
		else
		{
			$this->curObj = librdf_statement_get_subject($statement);
		}
		if(isset($this->predicateFilter))
		{
			$this->curKey = 0;
		}
		else
		{
			$this->curKey = librdf_uri_to_string(librdf_node_get_uri($this->curObj));
		}
		$this->curValue = null;
	}

	public function key()
	{
		return $this->curKey;
	}
	
	public function current()
	{
		if(!isset($this->curValue))
		{
			if(isset($this->predicateFilter))
			{
				/* If we're iterating the values of a predicate, just return
				 * the nodes
				 */
				$this->curValue = RDFNode::alias($this->curObj, $this);
			}
			else
			{
				/* Otherwise, return a RDFGraph instance which applies a
				 * filter to this one
				 */
				$deps = $this->dependents;
				$deps[] = $this;
				$inst = new RDFGraph($this->resource, $deps);
				$inst->weak = true;
				if(isset($this->subjectFilter))
				{
					$inst->subjectFilter = $this->subjectFilter;
					$inst->predicateFilter = $this->curKey;
				}
				else
				{
					$inst->subjectFilter = $this->curKey;
				}
				$this->curValue = $inst;
			}
		}
		return $this->curValue;
	}

	public function valid()
	{
		return $this->ended ? false : true;
	}
	
	public function next()
	{
		$this->lastKey = $this->curKey;
		$this->curObj = null;
		$this->curKey = null;
		$this->curValue = null;
		do
		{
			if(librdf_stream_next($this->iterator))
			{
				$this->ended = true;
				return false;
			}
			$statement = librdf_stream_get_object($this->iterator);
			if(isset($this->predicateFilter))
			{
				$obj = librdf_statement_get_object($statement);
				$key = $this->lastKey + 1;
			}
			else if(isset($this->subjectFilter))
			{
				$obj = librdf_statement_get_predicate($statement);
				$key = librdf_uri_to_string(librdf_node_get_uri($obj));
			}
			else
			{
				$obj = librdf_statement_get_subject($statement);
				$key = librdf_uri_to_string(librdf_node_get_uri($obj));
			}
		}
		while(!strcmp($key, $this->lastKey));
		$this->curObj = $obj;
		$this->curKey = $key;
		$this->curValue = null;
		return true;
	}

	/* ArrayAccess methods */

	public function offsetGet($key)
	{
		/* If this is a predicate-filtered model, indices are numeric keys
		 * into an array of nodes.
		 */
		if(isset($this->predicateFilter))
		{
			if(!is_numeric($key))
			{
				return null;
			}
			/* Locate the <key>th item in the list of nodes */
			$subj = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->subjectFilter);
			$pred = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->predicateFilter);
			$query = librdf_new_statement_from_nodes($this->dependents[0]->resource, $subj, $pred, null);
			$iterator = librdf_model_find_statements($this->resource, $query);
			$node = null;
			for($c = 0; !librdf_stream_end($iterator); $c++)
			{
				if($c == $key)
				{
					$st = librdf_stream_get_object($iterator);
					$node = RDFNode::alias(librdf_statement_get_object($st), $this);
					break;
				}
				librdf_stream_next($iterator);
			}
			librdf_free_stream($iterator);
			return $node;
		}
		/* Otherwise, provided the key parses as a URI, apply it as a filter. The filter may not
		 * match any existing triples, but the resulting filtered model will allow new triples
		 * to be added.
		 */
		$node = librdf_new_node_from_uri_string($this->dependents[0]->resource, $key);
		if(!is_resource($node))
		{
			return null;
		}
		$deps = $this->dependents;
		$deps[] = $this;
		$inst = new RDFGraph($this->resource, $deps);
		$inst->weak = true;
		if(isset($this->subjectFilter))
		{
			echo "Creating graph filtered on predicate <$key>\n";
			$inst->subjectFilter = $this->subjectFilter;
			$inst->predicateFilter = $key;
			$inst->predicateNode = $node;
		}
		else
		{
			echo "Creating graph filtered on subject <$key>\n";
			$inst->subjectFilter = $key;
			$inst->subjectNode = $node;
		}
		return $inst;
	}

	/* New statements can be added through several means:
	 *
	 * $model[] = $statement;     // Adds the statement
	 * $model[$subjectUri] = $statement; // Adds the statement provided
	 *                                   // $subjectUri matches
	 * $subjectFilteredModel[$predicateUri] = $node; // Add a triple
	 * $predicateFilteredModel[] = $node;	
	 *
	 * Replacement occurs when $value is an array instead of a single
	 * statement or node
	 */

	public function offsetSet($key, $value)
	{
		if(is_array($value))
		{
			$this->offsetUnset($key);
			foreach($value as $item)
			{
				if(is_array($item))
				{
					throw new Exception('cannot specify arrays-of-arrays in order to add to a model');
				}
				$this->offsetSet($key, $item);
			}
			return;
		}
		if(isset($this->predicateFilter) && $key !== null)
		{
			/* $predicateModel[$uri] = $value is nonsensical, throw an exception */
			throw new Exception('Specifying a subscript when adding something to a predicate-filtered model is nonsensical');
		}
		/* Convert literal values to RDFNode objects */
		if(!is_object($value) && !is_resource($value))
		{
			$value = RDFNode::createFromLiteral($value);
		}
		if($value instanceof RedlandURI)
		{
			$value = RDFNode::createUri($value);
		}
		if($value instanceof RDFNode)
		{
			/* Adding a node: requires either that this is a predicate-filtered
			 * model or it's a subject-filtered model with the predicate URI
			 * specified as the key.
			 */
			if(isset($this->predicateFilter))
			{
				/* $predicateModel[] = $node; */
				$subj = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->subjectFilter);
				$pred = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->predicateFilter);
				$obj = librdf_new_node_from_node($value->resource);
				/* The created nodes become owned by the model */
				librdf_model_add($this->resource, $subj, $pred, $obj);
				return;
			}
			if(isset($this->subjectFilter))
			{
				if($key === null)
				{
					/* $subjectModel[] = $node lacks required information; throw an exception */
					throw new Exception('cannot add a node to a subject-filtered model without specifying a predicate');
				}
				/* $subjectModel[$predicateUri] = $node; */
				$subj = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->subjectFilter);
				$pred = librdf_new_node_from_uri_string($this->dependents[0]->resource, $key);
				$obj = librdf_new_node_from_node($value->resource);
				/* The created nodes become owned by the model */
				librdf_model_add($this->resource, $subj, $pred, $obj);
				return;				
			}
			throw new Exception('Cannot add a node to an unfiltered model');
		}
		if($value instanceof RDFTriple)
		{
			if($key === null)
			{
				$this->addStatement($value);
				return;
			}
			if(isset($this->subjectFilter))
			{
				/* Check that the subject of the statement matches */
				if(!isset($this->subjectNode))
				{
					$this->subjectNode = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->subjectFilter);
				}
				if(!librdf_node_equals(librdf_statement_get_subject($value->resource), $this->subjectNode))
				{
					throw new Exception('cannot add statement to filtered model because the subject does not match');		
				}
				/* Check that the predicate of the statement matches the key */
				$uri = librdf_new_node_from_uri_string($this->dependents[0]->resource, $key);
				if(!librdf_node_equals(librdf_statement_get_predicate($value->resource), $uri))
				{
					librdf_free_node($uri);
					throw new Exception('cannot add statement to subscripted subject-filtered model because the predicate in the statement does not match the subscript');
				}
				librdf_free_node($uri);
			}
			else
			{
				/* Check that the subject of the statement matches the key */
				$uri = librdf_new_node_from_uri_string($this->dependents[0]->resource, $key);
				if(!librdf_node_equals(librdf_statement_get_subject($value->resource), $uri))
				{
					librdf_free_node($uri);
					throw new Exception('cannot add statement to subscripted unfiltered model because the subject in the statement does not match the subscript');
				}
				librdf_free_node($uri);				
			}		   
			librdf_model_add_statement($value->resource);
			return;
		}
		if(is_resource($value))
		{
			throw new Exception('unable to add a ' . get_resource_type($value) . ' to a model');
		}
		throw new Exception('unable to add a ' . get_class($value) . ' to a model');
	}

	public function offsetExists($key)
	{
		$found = false;
		/* If this is a predicate-filtered model, indices are numeric keys
		 * into an array of nodes.
		 */
		if(isset($this->predicateFilter))
		{
			if(!is_numeric($key))
			{
				return $found;
			}
			/* Locate the <key>th item in the list of nodes */
			$subj = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->subjectFilter);
			$pred = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->predicateFilter);
			$query = librdf_new_statement_from_nodes($this->dependents[0]->resource, $subj, $pred, null);
			$iterator = librdf_model_find_statements($this->resource, $query);
			for($c = 0; !librdf_stream_end($iterator); $c++)
			{
				if($c == $key)
				{
					$found = true;
					break;
				}
				librdf_stream_next($iterator);
			}
			librdf_free_stream($iterator);
		}
		else if(isset($this->subjectFilter))
		{
			/* If this is a subject-filtered model, indices are predicate URIs */
			$subj = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->subjectFilter);
			$pred = librdf_new_node_from_uri_string($this->dependents[0]->resource, $key);
			$query = librdf_new_statement_from_nodes($this->dependents[0]->resource, $subj, $pred, null);
			$iterator = librdf_model_find_statements($this->resource, $query);
			if(!librdf_stream_end($iterator))
			{
				$found = true;
			}
			librdf_free_stream($iterator);
		}
		else
		{
			/* Not a filtered model, so indices are subject URIs */
			$subj = librdf_new_node_from_uri_string($this->dependents[0]->resource, $key);
			$query = librdf_new_statement_from_nodes($this->dependents[0]->resource, $subj, null, null);
			$iterator = librdf_model_find_statements($this->resource, $query);
			if(!librdf_stream_end($iterator))
			{
				$found = true;
			}
			librdf_free_stream($iterator);
		}
		return $found;
	}
	
	public function offsetUnset($key)
	{
		/* If this is a predicate-filtered model, indices are numeric keys
		 * into an array of nodes.
		 */
		if(isset($this->predicateFilter))
		{
			if(!is_numeric($key))
			{
				return;
			}
			/* Locate the <key>th item in the list of nodes */
			$subj = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->subjectFilter);
			$pred = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->predicateFilter);
			$query = librdf_new_statement_from_nodes($this->dependents[0]->resource, $subj, $pred, null);
			$iterator = librdf_model_find_statements($this->resource, $query);
			for($c = 0; !librdf_stream_end($iterator); $c++)
			{
				if($c == $key)
				{
					$statement = librdf_stream_get_object($iterator);
					librdf_model_remove_statement($this->resource, $statement);
					break;
				}
				librdf_stream_next($iterator);
			}
			librdf_free_stream($iterator);
		}
		else if(isset($this->subjectFilter))
		{
			/* If this is a subject-filtered model, indices are predicate URIs */
			$subj = librdf_new_node_from_uri_string($this->dependents[0]->resource, $this->subjectFilter);
			$pred = librdf_new_node_from_uri_string($this->dependents[0]->resource, $key);
			$query = librdf_new_statement_from_nodes($this->dependents[0]->resource, $subj, $pred, null);
			$iterator = librdf_model_find_statements($this->resource, $query);
			while(!librdf_stream_end($iterator))
			{
				$statement = librdf_stream_get_object($iterator);
				librdf_model_remove_statement($this->resource, $statement);				
				librdf_stream_next($iterator);
			}
			librdf_free_stream($iterator);
		}
		else
		{
			/* Not a filtered model, so indices are subject URIs */
			$subj = librdf_new_node_from_uri_string($this->dependents[0]->resource, $key);
			$query = librdf_new_statement_from_nodes($this->dependents[0]->resource, $subj, null, null);
			$iterator = librdf_model_find_statements($this->resource, $query);
			while(!librdf_stream_end($iterator))
			{
				$statement = librdf_stream_get_object($iterator);
				librdf_model_remove_statement($this->resource, $statement);
				librdf_stream_next($iterator);
			}
			librdf_free_stream($iterator);
		}
	}
}

class RDFTriple extends RedlandBase
{
	protected $resourceDestructor = 'librdf_free_statement';
	
	public static function alias($res, $dependents = null)
	{
		if(!is_resource($res))
		{
			throw new Exception('Argument 1 passed to RDFTriple::alias() must be a resource');
		}
		$inst = new RDFTriple($res, $dependents);
		$inst->weak = true;
		return $inst;
	}

	public static function copy(RDFTriple $st)
	{
		$res = librdf_new_statement_from_statement($st->resource);
		if(!is_resource($res))
		{
			return null;
		}
		return new RDFTriple($res);
	}

	public function __toString()
	{
		return librdf_statement_to_string($this->resource);
	}

	public function __clone()
	{
		$this->resource = librdf_new_statement_from_statement($this->resource);
		$this->weak = false;
	}
	
	public function clear()
	{
		librdf_statement_clear($this->resource);
	}
	
	public function subject()
	{
		$res = librdf_statement_get_subject($this->resource);
		if(!is_resource($res))
		{
			return null;
		
		}
		return RDFNode::alias($res, $this);
	}

	public function predicate()
	{
		$res = librdf_statement_get_predicate($this->resource);
		if(!is_resource($res))
		{
			return null;
		
		}
		return RDFNode::alias($res, $this);
	}

	public function object()
	{
		$res = librdf_statement_get_object($this->resource);
		if(!is_resource($res))
		{
			return null;
		
		}
		return RDFNode::alias($res, $this);
	}
}

class RDFNode extends RedlandBase
{
	protected static $xsdBool;
	protected static $xsdInteger;
	protected static $xsdDecimal;
	protected static $xsdDouble;

	protected $resourceDestructor = 'librdf_free_node';

	public static function alias($res, $dependents = null)
	{
		if(!is_resource($res))
		{
			throw new Exception('Argument 1 passed to RDFNode::alias() must be a resource');
		}
		$inst = new RDFNode($res, $dependents);
		$inst->weak = true;
		return $inst;
	}

	public static function copy(RDFNode $st)
	{
		$res = librdf_new_node_from_node($st->resource);
		if(!is_resource($res))
		{
			return null;
		}
		return new RDFNode($res);
	}

	/* Create a node from a PHP literal value */
	public static function createFromLiteral($value, $world = null)
	{
		$world = RedlandWorld::get($world);
		if(is_string($value))
		{
			$res = librdf_new_node_from_literal($world->resource, $value, null, 0);
		}
		else if(is_bool($value))
		{
			if(!isset(self::$xsdBool))
			{
				self::$xsdBool = librdf_new_uri($world->resource, 'http://www.w3.org/2001/XMLSchema#boolean');
			}
			$res = librdf_new_node_from_typed_literal($world->resource, $value, null, self::$xsdBool);
		}
		else if(is_int($value))
		{
			if(!isset(self::$xsdInteger))
			{
				self::$xsdInteger = librdf_new_uri($world->resource, 'http://www.w3.org/2001/XMLSchema#integer');
			}
			$res = librdf_new_node_from_typed_literal($world->resource, $value, null, self::$xsdInteger);
		}
		else if(is_double($value))
		{
			if(!isset(self::$xsdDouble))
			{
				self::$xsdDouble = librdf_new_uri($world->resource, 'http://www.w3.org/2001/XMLSchema#double');
			}

			$res = librdf_new_node_from_typed_literal($world->resource, $value, null, self::$xsdDouble);
		}
		else if(is_float($value))
		{
			if(!isset(self::$xsdDecimal))
			{
				self::$xsdDecimal = librdf_new_uri($world->resource, 'http://www.w3.org/2001/XMLSchema#decimal');
			}

			$res = librdf_new_node_from_typed_literal($world->resource, $value, null, self::$xsdDecimal);
		}
		else
		{
			throw new Exception('Cannot convert a ' . gettype($value) . ' to a RDFNode');
		}
		if(!is_resource($res))
		{
			return null;
		}
		return new RDFNode($res);
	}

	/* Create a URI node from a URI instance of a URI string */
	public static function createUri($uri, $world = null)
	{
		$world = RedlandWorld::get($world);
		if($uri instanceof RedlandURI)
		{
			$res = librdf_new_node_from_uri($world->resource, $uri->resource);
		}
		else
		{
			$res = librdf_new_node_from_uri_string($world->resource, strval($uri));
		}
		if(!is_resource($res))
		{
			return null;
		}
		return new RDFNode($res);
	}

	public function __toString()
	{
		return librdf_node_to_string($this->resource);
	}

	public function __clone()
	{
		$this->resource = librdf_new_node_from_node($this->resource);
		$this->weak = false;
	}

	public function equals(RDFNode $node)
	{
		return (librdf_node_equals($this->resource, $node->resource) == 0) ? true : false;
	}
	
	public function type()
	{
		return librdf_node_get_type($this->resource);
	}

	public function uri()
	{
		$res = librdf_node_get_uri($this->resource);
		if(is_resource($res))
		{
			$inst = new RedlandURI($res, $this);
			$inst->weak = true;
			return $inst;
		}
		return null;
	}
   
	public function value()
	{
		return librdf_node_get_literal_value($this->resource);
	}

	public function datatype()
	{
		return librdf_node_get_literal_value_datatype_uri($this->resource);
	}
	
	public function language()
	{
		return librdf_node_get_literal_value_language($this->resource);
	}
	
	public function blankId()
	{
		return librdf_node_get_blank_identifier($this->resource);
	}
	
	public function isBlank()
	{
		return librdf_node_is_blank($this->resource);
	}

	public function isLiteral()
	{
		return librdf_node_is_literal($this->resource);
	}

	public function isResource()
	{
		return librdf_node_is_resource($this->resource);
	}
}

