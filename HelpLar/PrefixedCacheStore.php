<?php namespace HelpLar;

use Illuminate\Support\Facades\Redis;

/**
 * General links:
 * http://laravel.com/docs/4.2/cache
 * http://stackoverflow.com/questions/25975794/laravel-4-call-to-undefined-method-redisconnection
 * todo: implement redis commands: http://redis.io/commands/KEYS
 */
class PrefixedCacheStore extends \Illuminate\Cache\RedisStore {

	use DbgTrait;

	// redis
	// Larger keys are inefficient.
	// http://redis.io/topics/data-types-intro
	// http://instagram-engineering.tumblr.com/post/12202313862/storing-hundreds-of-millions-of-simple-key-value
	protected $maxKeyLen = 2048;
	protected $asciiKeys = false;

	// memcached
	// protected $maxKeyLen = 250;
	// protected $asciiKeys = true;

	/**
	 * Генерация ключа memcached для произвольного PHP-объекта данных.
	 * 
	 * @param string $prefix
	 *   префикс ключа memcached для предотвращения конфликтов;
	 *   это не префикс ключа laravel, а дополнительный "подпрефикс" к нему;
	 * @param mixed $obj
	 *   произвольный объект / массив PHP (лучше не использовать реальные классы или ресурсы)
	 * @return string|boolean
	 *   строковый ключ memcached;
	 *   false, если сериализованная структура слишком велика;
	 * @throws \Exception
	 *   префикс ключа должен быть строкой;
	 */
	public function getSerialKey($prefix, $obj) {
		if (!is_string($prefix)) {
			throw new \Exception('prefix must have string type');
		}
		$cacheKey = serialize($obj);
		if ($this->asciiKeys) {
			// Непечатные символы, пробел и UTF8 недопустимы в качестве символов ключа memcached.
			$convmap = array(
				0x80, 0xffff, 0, 0xffff,
				0,    0x20,   0, 0xffff
			);
			$cacheKey = mb_encode_numericentity($cacheKey, $convmap, 'UTF-8');
		}
		$cacheKey = $prefix . $cacheKey;
		if (strlen($cacheKey) > $this->maxKeyLen) {
			// Слишком много данных запроса.
			// Ключ memcached не может содержать более чем 250 байт.
			// Ключ redis не должен быть слишком длинным во избежание падения эффективности доступа.
			// sdv_dbg('too long cache key', $cacheKey);
			return false;
		}
		return $cacheKey;
	}

	public function putSerial($prefix, $obj, $value, $minutes = 60) {
		$cacheKey = $this->getSerialKey($prefix, $obj);
		if ($cacheKey !== false) {
			$this->put($cacheKey, $value, $minutes);
		}
	}

	public function getSerial($prefix, $obj) {
		$cacheKey = $this->getSerialKey($prefix, $obj);
		return ($cacheKey !== false) ? $this->get($cacheKey) : null;
	}


	public function forgetSerial($prefix, $obj) {
		$cacheKey = $this->getSerialKey($prefix, $obj);
		if ($cacheKey !== false) {
			$this->forget($cacheKey);
		}
	}

}
