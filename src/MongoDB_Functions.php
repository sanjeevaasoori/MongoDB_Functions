<?php

namespace Sanjeev\Custom;

use Exception;

class MongoDB_Functions
{
	public
		$message,
		$insert_id,
		$insert_count,
		$matched_count,
		$modified_count,
		$total_count,
		$deleted_count;
	protected
		$_debug = false,
		$_default_dbname = 'undefined',
		$_collection,
		$_client,
		$_db,
		$_data;

	protected function _result_to_response($result)
	{
		$response = [];
		foreach ($result as $res) {
			$construct = [];
			foreach ($res as $key => $value) {
				$construct[$key] = $value;
			}
			$response[] = $construct;
		}
		return $response;
	}
	public static function dump(string $k, mixed $v)
	{
		echo $k, "<pre>", json_encode($v, JSON_PRETTY_PRINT|JSON_INVALID_UTF8_SUBSTITUTE|JSON_PARTIAL_OUTPUT_ON_ERROR), "</pre>";
	}
	public function log(bool $debug)
	{
		$this->_debug = $debug;
	}

	public function connect(?string $db_name = null): \MongoDB\Database
	{
		$this->_client = new \MongoDB\Client;
		$this->_db = $this->_client->{$db_name ?? $this->default_dbname};
		return $this->_db;
	}

	public function setCollection(string $collectionName): \MongoDB\Collection
	{
		$this->_collection = $this->_db->{$collectionName};
		return $this->_collection;
	}
	public function createIndex(string $name, $indexType)
	{
		return $this->_collection->createIndex([$name => $indexType]);
	}
	public function insert(array ...$data): \MongoDB\InsertManyResult | \MongoDB\InsertOneResult | null
	{
		$count = count($data);
		$this->_data = ($count > 1) ? $data : $data[0];
		$result = null;
		try {
			$result = ($count > 1) ? $this->_collection->insertMany($this->_data) : $this->_collection->insertOne($this->_data);
			$this->insert_count = $result->getInsertedCount();
			if ($this->insert_count > 0) {
				$this->insert_id = ($count > 1) ? implode(',', $result->getInsertedIds()) : $result->getInsertedId();
			} else {
				$this->insert_id = null;
			}
			return $result;
		} catch (Exception $e) {
			$this->message = $e->getMessage();
			return null;
		}
	}
	protected function _make_sorting(?array $projection = null, ?int $limit = null, ?int $skip = null, ?array $sort = null): ?array
	{
		$sorting = [];
		if ($projection !== null) {
			$sorting['projection'] = $projection;
		}
		if ($limit !== null) {
			$sorting['limit'] = $limit;
		}
		if ($skip !== null) {
			$sorting['skip'] = $skip;
		}
		if ($sort !== null) {
			$sorting['sort'] = $sort;
		}
		if (count($sorting) === 0) {
			return null;
		}
		return $sorting;
	}
	public function fetch(array $arg = [], ?array $projection = null, ?int $limit = null, ?int $skip = null, ?array $sort = null): ?array
	{
		try {
			if ($this->_debug) {
				static::dump('MongoDB Fetch Args', $arg);
			}
			$sorting = $this->_make_sorting($projection, $limit, $skip, $sort);
			$result = null;
			if ($sorting === null) {
				$result = $this->_collection->find($arg);
			} else {
				$result = $this->_collection->find($arg, $sorting);
			}
			$response = $this->_result_to_response($result);
			$this->total_count = $this->_collection->count();
			return $response;
		} catch (Exception $e) {
			$this->message = $e->getMessage();
			return null;
		}
	}
	public function aggregate(array $arg = []): ?array
	{
		try {
			if ($this->_debug) {
				static::dump('MongoDB Aggregate Args', $arg);
			}
			$response = $this->_result_to_response($this->_collection->aggregate($arg));
			$this->total_count = $this->_collection->count();
			return $response;
		} catch (Exception $e) {
			$this->message = $e->getMessage();
			return null;
		}
	}
	public function fetchOne($arg, ?array $projection = null, ?int $limit = null, ?int $skip = null, ?array $sort = null): ?array
	{
		try {
			if ($this->_debug) {
				static::dump('MongoDB FetchOne Args', $arg);
			}
			$sorting = $this->_make_sorting($projection, $limit, $skip, $sort);
			$result = null;
			if ($sorting === null) {
				$result = $this->_collection->findOne($arg);
			} else {
				$result = $this->_collection->findOne($arg, $sorting);
			}
			return $result;
		} catch (Exception $e) {
			$this->message = $e->getMessage();
			return null;
		}
	}
	public function update($cond = [], $args): ?int
	{
		try {
			if ($this->_debug) {
				static::dump('MongoDB Update', [
					'cond' => $cond,
					'args' => $args
				]);
			}
			$result = $this->_collection->updateMany($cond, ['$set' => $args]);
			$this->matched_count = $result->getMatchedCount();
			$this->modified_count = $result->getModifiedCount();
			if ($this->modified_count) {
				return $this->modified_count;
			} else {
				$this->message = "MongoDB Client : No Data Modified";
				return null;
			}
		} catch (Exception $e) {
			$this->message = $e->getMessage();
			return null;
		}
	}
	public function delete($arg): ?int
	{
		try {
			if ($this->_debug) {
				static::dump('MongoDB Delete Args', $arg);
			}
			$result = $this->_collection->deleteMany($arg);
			$this->deleted_count = $result->getDeletedCount();
			if ($this->deleted_count) {
				return $this->deleted_count;
			} else {
				$this->message = "No Data Modified";
				return null;
			}
		} catch (Exception $e) {
			$this->message = $e->getMessage();
			return null;
		}
	}
	public function drop()
	{
		if ($this->_debug) {
			static::dump('MongoDB Drop Args', $this->_collection);
		}
		$result = $this->_collection->drop();
		return $result->ok;
	}
}
