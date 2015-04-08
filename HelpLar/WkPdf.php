<?php namespace HelpLar;

/**
 * http://wkhtmltopdf.org/downloads.html
 * 
 * For Ubuntu 14.04 LTS:
 * dpkg -i wkhtmltox-0.12.2.1_linux-trusty-amd64.deb
 * apt-get -f install
 */
class WkPdf {
    protected $options;

    public function __construct(array $options = []) {
        $this->options = $options;
    }

    public static function make($inputFilepath, $outputFilepath, array $options = []) {
		$self = new static($options);
        return $self->wkpdf($inputFilepath, $outputFilepath);
    }

    public function wkpdf($inputFilepath, $outputFilepath) {
        $filesize = false;

        putenv('PATH=/usr/bin:/usr/local/bin:' . getenv('PATH'));

        static::prepareDirectories($outputFilepath);

        $execOutput = [];
        $returnValue = null;

        $options = [];
        foreach ($this->options as $option => $value) {
            $options[] = '--' . $option . ' ' . escapeshellarg($value);
        }
        $options = join(' ', $options);

        $cmd = 'wkhtmltopdf ' . $options . ' ' . escapeshellarg($inputFilepath) . ' ' . escapeshellarg($outputFilepath);
        // sdv_dbg('shellCommand', $cmd);
        exec($cmd, $execOutput, $returnValue);

        if ($returnValue) {
            // sdv_dbg('execOutput', join("\n", $execOutput));
            // sdv_dbg('execError', $returnValue);
        } else if (is_file($outputFilepath)) {
            $filesize = filesize($outputFilepath);
        }

        return $filesize;
    }

    public static function prepareDirectories($path, $mode = 0777) {
        $dirPath = dirname($path);
        if (!is_dir($dirPath)) {
            @mkdir($dirPath, $mode, true);
        }
    }

    public function setOptions($options) {
        $this->options = array_replace_recursive($this->options, $options);
    }
}

