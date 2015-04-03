# Helper classes and functions for Laravel 4.2 / 5.0

## class HelpLar\ModelsWriter
### Emulation of Eloquent\Model::firstOrCreate() for multiple models via MYSQL INSERT ON DUPLICATE KEY UPDATE

#### Usage.
Creation of child class with custom model fields check callback.

    <?php namespace Pharm;

    class TripletsWriter extends \HelpLar\ModelsWriter {

            public static function instantiate() {
                    $self = static::create('Pharm\Models\Triplet', 100);
                    $self->setCB('beforeAdd', [$self, 'tripletFieldsConstraints']);
                    return $self;
            }

            public function tripletFieldsConstraints(array &$modelArr) {
                    $modelArr['type'] = trim($modelArr['type']);
                    $modelArr['name'] = trim($modelArr['name']);
                    if (strlen($modelArr['type']) === 0) {
                            throw new \Exception("Too short type attribute value: {$modelArr['type']}");
                    }
                    if (mb_strlen($modelArr['type']) > Models\Triplet::MAX_TYPE_SIZE) {
                            throw new \Exception("Too long type attribute value: {$modelArr['type']}");
                    }
                    // name cannot be zero-length when owner_id === 0 (root triplet node);
                    if (intval($modelArr['owner_id']) === 0 && strlen($modelArr['name']) === 0) {
                            throw new \Exception("Too short name attribute value: {$modelArr['name']}");
                    }
                    if (mb_strlen($modelArr['name']) > Models\Triplet::MAX_NAME_SIZE) {
                            throw new \Exception("Too long name attribute value: {$modelArr['name']}");
                    }
            }

    }


Usage of HelpLar\ModelsWriter for fast bufferized writing of multiple Eloquent models.

    $writer = ModelsWriter::create('App\\Models\\LocalCountry');
            if (count(array_diff_key($this->lang_map, $sitelinks)) === 0) {
                    if ($this->isModern($entity)) {
                            foreach ($this->lang_map as $wiki_lang => $dj_lang) {
                                    $title = $sitelinks[$wiki_lang]->title;
                                    $this->line("\tAdding {$wiki_lang} country: {$title}");
                                    sdv_dbg("Adding {$wiki_lang} title", $title);
                                    $country = \App\Models\Country::cachedUpdate([
                                            'wikidata_id' => intval(ltrim($key, 'Q'))
                                    ]);
                                    $writer->add([
                                            'language' => $dj_lang,
                                            'name' => $title,
                                            'country_id' => $country->id
                                    ]);
                            }
                    } else {
                            foreach ($this->lang_map as $wiki_lang => $dj_lang) {
                                    $title = $sitelinks[$wiki_lang]->title;
                                    $this->line("\tSkipping {$wiki_lang} country: {$title}");
                                    sdv_dbg("Skipping {$wiki_lang} title", $title);
                            }
                    }
            }
            $writer->flush();


## class HelpLar\AbstractModel
Base model with useful scopes ->random() and ->searchLike()

::hashedArray() is a multi-field version of ->lists()

::cachedUpdate() is advanced version of ::firstOrCreate() with optional Redis caching.

## HelpLar\sdv_debug.php
Various functions for debug logging, including custom SQL logger.

sdv_dbg('varname',$var) logs the dump of $var with method name and 'varname' output.
sdv_log_illuminate_query() generates closure for custom SQL logger.

## HelpLar\ExtStdClass.php
\stdClass extended with nested access to \stdClass / array properties via

::hasNested() / ::getNested() / ::setNested() / ::deleteNested() and their

static counterparts with s_ prefix.
