<?php namespace HelpLar;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * Two-level prefixed redis cache storage.
 */
class PrefixedStorage {

	protected static $noRedis = false;
	protected $data = [];
	protected $history = [];
	protected $cache;

	public function __construct($cachePrefix) {
		// Set current instance cache prefix.
		$this->cachePrefix = $cachePrefix;
		if (class_exists('Predis\\Client')) {
			try {
				$this->cache = new \Illuminate\Cache\Repository(
					// Set global app cache prefix.
					new PrefixedCacheStore(app('redis'), Config::get('cache.prefix'))
				);
			} catch (\Exception $e) {
				static::$noRedis = true;
			}
		} else {
			static::$noRedis = true;
		}
	}

	public function get($objKey) {
		if (static::$noRedis) {
			return null;
		}
		return $this->cache->getSerial($this->cachePrefix, $objKey);
	}

	public function put($objKey, $value, $minutes = 60) {
		if (static::$noRedis) {
			return;
		}
		$this->cache->putSerial($this->cachePrefix, $objKey, $value, $minutes);
	}

	public function forget($objKey) {
		if (static::$noRedis) {
			return;
		}
		$this->cache->forgetSerial($this->cachePrefix, $objKey);
	}

	public static function flush() {
		if (static::$noRedis) {
			return false;
		}
		try {
			$redis = Redis::connection();
			$redis->flushdb();
		} catch (\Exception $e) {
			static::$noRedis = true;
			return false;
		}
		return true;
	}
	
}
