<?php namespace HelpLar;

class CsvParser {

	use LoggingTrait;
	
	protected $fieldNames;
	
	public static function instance(array $fieldNames) {
		$self = new static();
		$self->setFieldNames($fieldNames);
		return $self;
	}

	public function setFieldNames(array $fieldNames) {
		$this->fieldNames = $fieldNames;
	}
	
	public function openFiles(array $fileNames) {
		$files = [];
		try {
			foreach ($fileNames as $langCode => $fname) {
				$f = @fopen($fname, 'r');
				if ($f === false) {
					throw new JumpException();
				}
				$firstLine = trim(@fgets($f));
				$headerLine = implode(',', $this->fieldNames);
				if ($firstLine !== $headerLine) {
					$this->log("Requires headerLine: {$headerLine}");
					$this->log("Found firstLine: {$firstLine}");
					throw new JumpException();
				}
				$files[$langCode] = $f;
			}
		} catch (JumpException $e) {
			foreach ($files as $f) {
				@fclose($f);
			}
			throw new TerminationException();
		}
		return $files;
	}
	
	protected function parseLine($s) {
		$tokens = preg_split('/([,"])/', $s, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		$result = [];
		$element = '';
		$inQuotes = false;
		foreach ($tokens as $token) {
			// sdv_dbg('token',$token);
			// sdv_dbg('inQuotes',$inQuotes);
			switch ($token) {
			case ',' :
				if ($inQuotes) {
					$element .= $token;
				} else {
					$result[] = trim($element);
					$element = '';
				}
				break;
			case '"' :
				if ($inQuotes) {
					$inQuotes = false;
				} else {
					$inQuotes = true;
				}
				break;
			default:
				$element .= $token;
			}
		}
		$result[] = trim($element);
		return $result;
	}
	
	public function load($csvFile, callable $rowCB) {
		while (!feof($csvFile)) {
			$s = fgets($csvFile);
			if ($s === false) {
				break;
			}
			$s = trim($s);
			if ($s === '') {
				continue;
			}
			$values = str_getcsv($s);
			if (count($values) !== count($this->fieldNames)) {
				fclose($csvFile);
				TerminationException::raise(
					"Number of fields does not match header line: {$s}",
					[
						'fieldNames' => $this->fieldNames,
						'unmatched values' => $values
					]
				);
			}
			$csvRow = array_combine($this->fieldNames, $values);
			// sdv_dbg('csvRow',$csvRow);
			$rowCB($csvRow);
		}
		fclose($csvFile);
	}

}
