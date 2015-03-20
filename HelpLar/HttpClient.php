<?php namespace HelpLar;

use GuzzleHttp\Stream\Stream;

/**
 * http client with spoofed user agent and fetch delay for anti-anti-bot protection.
 */
class HttpClient extends \GuzzleHttp\Client {

	const BLOCKSIZE = 65536;
	
	protected $configDefaults = [
		'headers' => [
			'User-Agent' => 'Mozilla/5.0 (X11; Linux i586; rv:31.0) Gecko/20100101 Firefox/31.0',
		]
	];
	protected $defaultUrlParts;
	protected $fetchDelay = 0;
	protected $followRedirect = false;
	protected $contentType;
	
	function __construct(array $config = []) {
		if (!array_key_exists('defaults', $config)) {
			$config['defaults'] = [];
		}
		if (array_key_exists('default_url', $config)) {
			$this->setDefaultUrl($config['default_url']);
			unset($config['default_url']);
		}
		if (array_key_exists('delay', $config)) {
			$this->setFetchDelay($config['delay']);
			unset($config['delay']);
		}
		if (array_key_exists('follow_redirect', $config)) {
			$this->setFollowRedirect($config['follow_redirect']);
			unset($config['follow_redirect']);
		}
		$config['defaults'] = array_replace_recursive(
			$this->configDefaults,
			$config['defaults']
		);
		// sdv_dbg('config',$config);
		parent::__construct($config);
	}

	public static function instance(array $config = []) {
		$self = new static($config);
		return $self;
	}
	
	public function setDefaultUrl($url) {
		$this->defaultUrlParts = static::parse_url($url);
	}

	public function setFetchDelay($delay) {
		$this->fetchDelay = abs(intval($delay));
	}

	public function setFollowRedirect($followRedirect) {
		$this->followRedirect = boolval($followRedirect);
	}

	public static function parse_url($url) {
		$urlParts = parse_url($url);
		if (array_key_exists('query', $urlParts)) {
			parse_str($urlParts['query'], $urlParts['query']);
		}
		return $urlParts;
	}
	
	public static function build_url(array $urlParts) {
		if (array_key_exists('query', $urlParts) && is_array($urlParts['query'])) {
			$urlParts['query'] = http_build_query($urlParts['query']);
		}
		$scheme   = isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '';
		$host     = isset($urlParts['host']) ? $urlParts['host'] : '';
		$port     = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';
		$user     = isset($urlParts['user']) ? $urlParts['user'] : '';
		$pass     = isset($urlParts['pass']) ? ':' . $urlParts['pass']  : '';
		$pass     = ($user || $pass) ? "$pass@" : '';
		$path     = isset($urlParts['path']) ? $urlParts['path'] : '';
		$query    = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';
		$fragment = isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '';
		return "$scheme$user$pass$host$port$path$query$fragment";
 	}
	
	public function get($url = null, $options = []) {
		if ($this->fetchDelay > 0) {
			sleep($this->fetchDelay);
		}
		// sdv_dbg('defaultUrlParts',$this->defaultUrlParts);
		if (is_array($this->defaultUrlParts)) {
			if (!is_array($url)) {
				$url = parse_url($url);
			}
			// sdv_dbg('url before defaults',$url);
			$urlParts = array_replace($this->defaultUrlParts, $url);
			$url = static::build_url($urlParts);
			// sdv_dbg('url after defaults',$url);
		}
		$res = parent::get($url, $options);
		$this->contentType = $res->getHeader('content-type');
		$redirect = $res->getHeader('Location');
		if ($this->followRedirect && !empty($redirect)) {
			// sdv_dbg('url', $url);
			// sdv_dbg('Location', $redirect);
			return $this->getRls($redirect);
		}
		if (empty($redirect) && $res->getStatusCode() !== 200) {
			JumpException::raise(
				'Error fetching remote content', [
					'res' => $res,
					'statusCode' => $res->getStatusCode()
				]
			);
		}
		return $res;
	}
	
	public function getBody($url) {
		return $this->get($url)->getBody();
	}

	public function tryContentType($mime) {
		if (!preg_match('#^' . preg_quote($mime, '#') . '#i', $this->contentType)) {
			sdv_dbg('contentType',$this->contentType);
			JumpException::raise("Not {$mime} response", $this->contentType);
		}
	}

	public function getCharset() {
		preg_match('/charset=([a-z\-\d]+)/', $this->contentType, $regs);
		$charset = 'utf-8';
		if (count($regs) === 2) {
			$charset = $regs[1];
		}
		return $charset;
	}
	
	public function getJSON($url) {
		$res = $this->get($url);
		$this->tryContentType('application/json');
		/** @var GuzzleHttp\Stream\StreamInterface $body */
		$body = $res->getBody();
		return json_decode($body);
	}

	public function getExtStdClass($url) {
		return new ExtStdClass($this->getJSON($url));
	}
	
	public function getLocalHtml($url) {
		$res = $this->get($url);
		$this->tryContentType('text/html');
		/** @var GuzzleHttp\Stream\StreamInterface $body */
		$body = $res->getBody();
		$charset = $this->getContentCharset($contentType);
		return \QuestPC\XmlTree::replaceHtmlBody(
			($charset !== 'utf-8') ?
				mb_convert_encoding($body, 'utf-8', $charset) :
				strval($body)
		);
	}

	public function copyRemote($fromUrl, $toFile, $expire = 3600) {
		if (is_dir($toFile)) {
			$toFile = rtrim($toFile, '/\\') . '/' . basename($fromUrl);
		}
		$stat = @stat($toFile);
		// sdv_dbg('stat',$stat);
		if (is_array($stat)) {
			$since = time() - $stat['mtime'];
			// sdv_dbg('since', $since);
			if ($since <= $expire) {
				// sdv_dbg('cached', $since);
				return $toFile;
			}
		}
		// sdv_dbg('expired', $since);
		try {
			$tmpFile = tempnam(dirname($toFile), 'copy_remote_');
			$fout = @fopen($tmpFile, 'wb');
			$fin = Stream::factory(fopen($fromUrl, 'r'));
			do {
				$data = $fin->read(self::BLOCKSIZE);
				fwrite($fout, $data);
			} while (strlen($data) > 0);
		} catch (\Exception $e) {
			@fclose($fout);
			@unlink($fout);
			$fin->close();
			// sdv_dbg('e',$e);
			return false;
		}
		@fclose($fout);
		$fin->close();
		@rename($tmpFile, $toFile);
		@chmod($toFile, 0774);
		return $toFile;
	}

	protected function unzipPatterns($zip, $directory, array $patterns) {
		$fileNames = array_fill_keys(array_keys($patterns), false);
		$patternResult = array_fill_keys($patterns, false);
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$filename = $zip->getNameIndex($i);
			foreach ($patterns as $patternIdx => $regexp) {
				if (preg_match($regexp, $filename, $matches)) {
					// sdv_dbg('found file', $filename);
					if (count($matches) > 1) {
						// sdv_dbg('matches',$matches[1]);
						$filename = $matches[1];
						$foutname = "$directory/$filename";
						$outdir = dirname($foutname);
						@mkdir($outdir, 0777, true);
						if (@file_put_contents($foutname, $zip->getFromIndex($i)) !== false) {
							$isFound = $foutname;
							$fileNames[$patternIdx] = $foutname;
						}
						break;
					}
				}
			}
		}
		return $fileNames;
	}
	
	public function unzipRemote($fromUrl, $directory, array $patterns = [], $expire = 3600) {
		// sdv_dbg('fromUrl',$fromUrl);
		// sdv_dbg('directory',$directory);
		// sdv_dbg('patterns',$patterns);
		// sdv_dbg('expire',$expire);
		$directory = rtrim($directory, '/\\');
		$toFile = $this->copyRemote($fromUrl, $directory, $expire);
		if (is_string($toFile)) {
			$zip = new \ZipArchive();
			if ($zip->open($toFile) === true) {
				if (count($patterns) > 0) {
					return $this->unzipPatterns($zip, $directory, $patterns);
				} else {
					$zip->extractTo($directory);
				}
				$zip->close();
				return $toFile;
			}
		}
		return false;
	}
	
	public function tryUnzipRemote($url, $storage_dir, array $filePatterns) {
		$fileNames = $this->unzipRemote(
			$url,
			$storage_dir,
			$filePatterns
		);
		if (is_array($fileNames)) {
			if (in_array(false, $fileNames, true)) {
				foreach ($fileNames as $filePatternsIdx => $fileName) {
					if ($fileName === false) {
						throw new TerminationException('Missing pattern ' . $filePatterns[$filePatternsIdx]);
					}
				}
			}
		} else {
			throw new TerminationException("Error downloading from {$url}");
		}
		return $fileNames;
	}

}
