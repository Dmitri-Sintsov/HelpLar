<?php namespace HelpLar;

use Illuminate\Support\Facades\DB;

/**
 * Буферизированная загрузка моделей с помощью многострочного
 * INSERT ON DUPLICATE KEY UPDATE.
 * Аналог Eloquent::firstOrCreate().
 */
class ModelsWriter {

	protected $pdo;
	protected $models = [];
	protected $tableName;
	/**
	 *   Имена ключевых полей модели:
	 *     несуррогатного первичного ключа, напр.
	 *       ['GUID'] или
	 *     уникального составного индекса при наличии суррогатного инкрементного первичного ключа, напр.
	 *       ['login', 'password'];
	 *   как в Eloquent::firstOrCreate(), но без указания значений самих полей;
	 *   Значения всех полей (кроме суррогатного) будут переданы ассоциативным массивом как параметр ->add();
	 */
	protected $uniqueTogether;
	protected $flushLimit;
	protected $cbList = [];

	function __construct() {
		$this->pdo = DB::connection()->getPdo();
	}

	public static function create($modelClassName, $limit = 100, $cbList = []) {
		$self = new static();
		$self->bindToModel($modelClassName);
		$self->setFlushLimit($limit);
		foreach ($cbList as $when => $cb) {
			$self->setCB($when, $cb);
		}
		return $self;
	}

	public function bindToModel($modelClassName) {
		$model = new $modelClassName();
		$this->tableName = $model->getTable();
		$this->uniqueTogether = $model->getUniqueTogether();
	}

	public function setFlushLimit($limit) {
		$this->flush();
		$this->flushLimit = $limit;
	}

	public function setCB($when, $cb) {
		if (!is_callable($cb)) {
			throw new \Exception("Not callable {$when} $cb");
		}
		$this->cbList[$when] = $cb;
	}

	/**
	 * Буферизированная запись модели.
	 * 
	 * @param array $modelArr
	 *   значение всех полей модели (кроме первичного ключа)
	 *   как в Model::toArray();
	 */
	public function add(array $modelArr) {
		if (array_key_exists('beforeAdd', $this->cbList)) {
			call_user_func_array($this->cbList['beforeAdd'], [&$modelArr]);
		}
		$this->models[] = $modelArr;
		if (count($this->models) >= $this->flushLimit) {
			$this->flush();
		}
	}

	public function quoteFieldName( $fieldName ) {
		return '`' . str_replace( '`', '``', $fieldName ) . '`';
	}

	protected function getValuesStatement( $val ) {
		$val = $this->quoteFieldName($val);
		return $val . ' = VALUES(' . $val . ')';
	}

	/**
	 * Не забудьте вызвать вручную после вызова add() для всех моделей
	 * чтобы очистить буфер записи.
	 */
	public function flush() {
		if (count($this->models) === 0) {
			return;
		}
		if (array_key_exists('beforeFlush', $this->cbList)) {
			call_user_func($this->cbList['beforeFlush'], $this->models);
		}
		if (count($this->uniqueTogether) === 0) {
			DB::table($this->tableName)->insert($this->models);
		} else {
			foreach ($this->models as $record) {
				$allCols = array_keys($record);
				$nonUniqueCols = array_diff($allCols, $this->uniqueTogether);
				break;
			}
			$builder = DB::table($this->tableName);
			$grammar = $builder->getGrammar();
			$bindings = array();
			foreach ($this->models as $record) {
				foreach ($allCols as $col) {
					$bindings[] = $record[$col];
				}
			}
			// sdv_dbg('nonUniqueCols',$nonUniqueCols);
			$sql = $grammar->compileInsert($builder, $this->models) .
				' ON DUPLICATE KEY UPDATE ' .
				implode( ',',
					array_map( [$this, 'getValuesStatement'] , $nonUniqueCols )
				);
			// sdv_dbg('sql',$sql);
			$builder->getConnection()->insert($sql, $bindings);
		}
		if (array_key_exists('afterFlush', $this->cbList)) {
			call_user_func($this->cbList['afterFlush'], $this->models);
		}
		$this->models = [];
	}
    
}
