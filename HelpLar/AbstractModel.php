<?php namespace HelpLar;

use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Model;

abstract class AbstractModel extends Model {

	protected static $cache;

	/**
	 * Get list of unique fields (single or surrogate) for ModelsWriter.
	 * @return array
	 */
	public function getUniqueTogether() {
		return $this->uniqueTogether;
	}
	
	/*** Scopes ***/
	
	/**
	 * Get random models.
	 * http://www.laravel-tricks.com/tricks/random-model-scope
	 */
	public function scopeRandom($query)	{
		return $query->orderBy(DB::raw('RAND()'));
	}

	public function scopeSearchLike($query, $search, array $searchFields) {
		if ($search !== '') {
			$query->where( function($query) use($search, $searchFields) {
				foreach ($searchFields as $searchField) {
					$query->orWhere($searchField, 'LIKE', "%{$search}%");
				}
			});
		}
	}

	/*** Helpers ***/
	
	public static function hashedArray($field, $collection) {
		$result = [];
		foreach ($collection as $model) {
			if (array_key_exists($model->{$field}, $result)) {
				throw new \Exception("Non unique field {$field} value " . $model->{$field});
			}
			$result[$model->{$field}] = $model;
		}
		return $result;
	}

	/**
	 * When called without second argument, behaviour is similar to Eloquent::firstOrCreate().
	 * @param array $existingFields
	 * @param array $updatedFields
	 * @return \static
	 * @throws \Exception
	 */
	public static function cachedUpdate(array $existingFields, array $updatedFields = []) {
		if (!isset(static::$cache)) {
			$model = new static();
			// sdv_dbg('cache prefix',__METHOD__ . ":{$model->table}:");
			static::$cache = new \HelpLar\PrefixedStorage(__METHOD__ . ":{$model->table}:");
		}
		ksort($existingFields);
		ksort($updatedFields);
		$cachedFields = static::$cache->get($existingFields);
		if (is_array($cachedFields)) {
			// sdv_dbg('found cachedFields',  $cachedFields);
			// sdv_dbg('comparing to updatedFields',  $updatedFields);
			if ($cachedFields === $updatedFields) {
				$allFields = array_replace($existingFields, $updatedFields);
				// sdv_dbg('cached model from allFields',$allFields);
				return new static($allFields);
			}
		}
		$model = static::firstOrNew($existingFields);
		$hasToSave = false;
		if ($model->exists) {
			foreach ($updatedFields as $fieldName => $fieldVal) {
				if ($hasToSave || $model->{$fieldName} !== $fieldVal) {
					$hasToSave = true;
					$model->{$fieldName} = $fieldVal;
				}
			}
		} else {
			$hasToSave = true;
			foreach ($updatedFields as $fieldName => $fieldVal) {
				$model->{$fieldName} = $fieldVal;
			}
		}
		if ($hasToSave) {
			if (!$model->save()) {
				// sdv_dbg('error saving fields',$allFields);
				throw new \Exception("Cannot save current model");
			}
		}
		static::$cache->put($existingFields, $updatedFields);
		return $model;
	}
	
}
