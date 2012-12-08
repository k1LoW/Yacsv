<?php

App::uses('YacsvException', 'Yacsv.Error');
App::uses('CsvParser', 'Yacsv.Lib');

class ImporterBehavior extends ModelBehavior {

    private $model;
    private $options = array('csvEncoding' => 'SJIS-win',
                             'hasHeader' => false,
                             'skipHeaderCount' => 1,
                             'delimiter' => ',',
                             'enclosure' => '"',
                             'forceImport' => false,
                             'saveMethod' => false,
                             'allowExtension' => false,
                             'parseLimit' => false,
                             );

    private $importedCount = 0;
    private $maxColumnCount = 0;
    const AUTO = 'auto';
    const DETECT_SAMPLE_COUNT = 3;

    /**
     * setUp
     *
     */
    public function setUp(Model $model, $settings = array()){
        $defaults = array();
        $settings = array_merge($defaults, $settings);
        $this->settings[$model->alias] = $settings;
    }

    /**
     * setCsvOptions
     *
     */
    public function setCsvOptions(Model $model,  $options = array()){
        $this->options = array_merge($this->options, $options);
    }

    /**
     * importCsv
     *
     */
    public function importCsv(Model $model, $data = null, $options = array()){
        $this->model = $model;
        $this->fields = $model->importFields;
        $this->setOptions($model, $options);

        $importFilterArgs = $model->importFilterArgs;
        if (empty($importFilterArgs)) {
            throw new YacsvException(__d('Yacsv', 'Yacsv: Invalid Settings'));
        }
        foreach ($importFilterArgs as $value) {
            $csvField = $value['name'];
            if (!empty($data[$model->alias][$csvField])) {
                if ($data[$model->alias][$csvField]['error'] !== UPLOAD_ERR_OK) {
                    if ($data[$model->alias][$csvField]['error'] === UPLOAD_ERR_NO_FILE) {
                        $model->invalidate($csvField, __d('Yacsv', 'Yacsv: No Upload CSV'));
                    }
                    throw new YacsvException(__d('Yacsv', 'Yacsv: CSV Import Error'));
                }

                $tmpFile = $data[$model->alias][$csvField]['tmp_name'];

                if (!is_uploaded_file($tmpFile)) {
                    throw new YacsvException(__d('Yacsv', 'Yacsv: CSV Import Error'));
                }

                // check extension
                if (!empty($this->options['allowExtension'])) {
                    $regexp  =  '/\.(' . implode('|', (array) $this->options['allowExtension']) . ')$/i';
                    if (!preg_match($regexp, $data[$model->alias][$csvField]['name'])) {
                        throw new YacsvException(__d('Yacsv', 'Yacsv: Invalid Extension'));
                    }
                }

                $filePath = TMP . uniqid('importer_', true) .'_'. $data[$model->alias][$csvField]['name'];

                move_uploaded_file($tmpFile, $filePath);

                try {
                    $this->model->begin();
                    $result = $this->_importCsv($filePath);
                    if ($result === true) {
                        $this->model->commit();
                        return true;
                    } else {
                        if ($this->options['forceImport']) {
                            $this->model->commit();
                            $this->model->importValidationErrors = $result;
                            return true;
                        }
                        $this->model->rollback();
                        $this->model->importValidationErrors = $result;
                        throw new YacsvException(__d('Yacsv', 'Yacsv: CSV Import Error'));
                    }
                } catch (Exception $e) {
                    $this->model->rollback();
                    throw new YacsvException($e->getMessage());
                }
            }
        }

        return $data;
    }

    /**
     * importCsvFromFile
     *
     */
    public function importCsvFromFile(Model $model, $filePath, $options = array()){
        $this->model = $model;
        $this->fields = $model->importFields;
        $this->setCsvOptions($model, $options);

        try {
            $this->model->begin();
            $result = $this->_importCsv($filePath);
            if ($result === true) {
                $this->model->commit();
                return true;
            } else {
                if ($this->options['forceImport']) {
                    $this->model->commit();
                    $this->model->importValidationErrors = $result;
                    return true;
                }
                $this->model->rollback();
                $this->model->importValidationErrors = $result;
                throw new YacsvException(__d('Yacsv', 'Yacsv: CSV Import Error'));
            }
        } catch (Exception $e) {
            $this->model->rollback();
            throw new YacsvException($e->getMessage());
        }
    }

    /**
     * _importCsv
     *
     */
    private function _importCsv($filePath){
        $csvData = $this->parseCsvFile($this->model, $filePath);
        $this->importedCount = 0;
        $invalidLines = array();
        foreach ($csvData as $key => $value) {
            if (count($value['data']) !== count($this->fields)) {
                $invalidLines[$key + 1] = array('message' => __d('Yacsv', 'Yacsv: Invalid Line Format'),
                                                'validationErrors' => array(),
                                                'line' => $value['line']);
                continue;
            }

            $data = array();
            $data[$this->model->alias] = array_combine($this->fields, $value['data']);
            if (!$this->options['saveMethod']) {
                $this->model->create();
                $result = $this->model->save($data);
            } else {
                try {
                    $result = call_user_func_array($this->options['saveMethod'], array($data));
                } catch (Exception $e) {
                    $invalidLines[$key + 1] = array('message' => __d('Yacsv', 'Yacsv: Invalid Line Format'),
                                                    'validationErrors' => $this->model->validationErrors,
                                                    'line' => $value['line']);
                    $result = false;
                }
            }
            if ($result === false) {
                $invalidLines[$key + 1] = array('message' => __d('Yacsv', 'Yacsv: Invalid Line Format'),
                                                'validationErrors' => $this->model->validationErrors,
                                                'line' => $value['line']);
            } else {
                ++$this->importedCount;
            }
        }
        if (!empty($this->options['removeFile']) && !@unlink($filePath)) {
            throw new YacsvException(__d('Yacsv', 'Yacsv: Temp File Remove Error'));
        }
        if (!empty($invalidLines)) {
            return $invalidLines;
        }
        return true;
    }

    /**
     * parseCsvFile
     *
     */
    public function parseCsvFile(Model $model, $filePath){
        $dataCount = 0;
        $d = preg_quote($this->options['delimiter']);
        $e = preg_quote($this->options['enclosure']);
        $parseLimit = $this->options['parseLimit'];
        $csvEncoding = $this->options['csvEncoding'];

        if ($d === self::AUTO) {
            // detect delimiter
            $d = $this->detectDelimiterFromFile($filePath);
            $this->options['delimiter'] = $d;
        }

        if ($e === self::AUTO) {
            // detect enclosure
            $e = $this->detectEnclosureFromFile($filePath);
            $this->options['enclosure'] = $e;
        }

        if ($csvEncoding === self::AUTO) {
            // detect encoding
            $csvEncoding = $this->detectEncodingFromFile($filePath);
        }

        try {
            $csvData = array();
            $handle = fopen($filePath, "r");
            while (($result = CsvParser::parseCsvLine($handle, $d, $e)) !== false) {
                mb_convert_variables(Configure::read('App.encoding'), $csvEncoding, $result);
                $csvData[] = $result;
                $dataCount++;
                $columnCount = count($result);
                if ($columnCount > $this->maxColumnCount) {
                    // Update maxColumnCount
                    $this->maxColumnCount = $columnCount;
                }
                if ($parseLimit && $dataCount >= $parseLimit) {
                    return $csvData;
                }
            }
            if ($this->options['hasHeader']) {
                for ($i = 0; $i < $this->options['skipHeaderCount']; ++$i) {
                    $header = array_shift($csvData);
                }
            }
            fclose($handle);
        } catch (Exception $e){
            throw new YacsvException($e->getMessage());
        }
        return $csvData;
    }

    /**
     * detectDelimiterFromFile
     *
     * @param $filePath
     */
    public function detectDelimiterFromFile($filePath){
        $dataCount = 0;
        $parseLimit = self::DETECT_SAMPLE_COUNT;
        $candidates = array(',', "\t", ';', ' ');

        // for skip header
        if ($this->options['hasHeader']) {
            $parseLimit += $this->options['skipHeaderCount'];
        }

        $handle = fopen($filePath, "r");
        $results = array();
        while ($line = fgets($handle)) {
            $dataCount++;
            if ($dataCount > $parseLimit) {
                break;
            }
            foreach ($candidates as $candidate) {
                if (empty($results[$candidate])) {
                    $results[$candidate] = array();
                }
                $results[$candidate][] = count(explode($candidate, $line));
            }
        }
        fclose($handle);
        foreach ($results as $candidate => $value) {
            $fliped = array_flip($value);
            if (count($fliped) !== 1) {
                unset($results[$candidate]);
                continue;
            }
            $results[$candidate] = key($fliped);
        }
        arsort($results, SORT_NUMERIC);
        return key($results);
    }

    /**
     * detectEnclosureFromFile
     *
     * @param $filePath
     */
    public function detectEnclosureFromFile($filePath){
        $dataCount = 0;
        $parseLimit = self::DETECT_SAMPLE_COUNT;
        $candidates = array('"', "'");

        // for skip header
        if ($this->options['hasHeader']) {
            $parseLimit += $this->options['skipHeaderCount'];
        }

        $handle = fopen($filePath, "r");
        $results = array();
        while ($line = fgets($handle)) {
            $dataCount++;
            if ($dataCount > $parseLimit) {
                break;
            }
            foreach ($candidates as $candidate) {
                $count = preg_match_all('/' . $candidate . '/', $line, $dummy);
                if ($count === 0 || $count % 2 !== 0) {
                    continue;
                }
                if (empty($results[$candidate])) {
                    $results[$candidate] = 0;
                }
                $results[$candidate]++;
            }
        }
        fclose($handle);

        arsort($results, SORT_NUMERIC);
        return key($results);
    }

    /**
     * detectEncodingFromFile
     *
     * @param $filePath
     */
    public function detectEncodingFromFile($filePath){
        $dataCount = 0;
        $parseLimit = 0;
        $d = preg_quote($this->options['delimiter']);
        $e = preg_quote($this->options['enclosure']);

        // for skip header
        if ($this->options['hasHeader']) {
            $parseLimit = $this->options['skipHeaderCount'];
        }

        $handle = fopen($filePath, "r");
        while (($result = CsvParser::parseCsvLine($handle, $d, $e)) !== false) {
            $dataCount++;
            if ($dataCount > $parseLimit) {
                fclose($handle);
                // @see http://d.hatena.ne.jp/t_komura/20090615/1245078430
                if (preg_replace('/\A([\x00-\x7f]|[\xc0-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xf7][\x80-\xbf]{3}|[\xf8-\xfb][\x80-\xbf]{4}|[\xfc-\xfd][\x80-\xbf]{5})*\z/', '', $result['line']) === '') {
                    return 'UTF-8';
                }
                if (preg_replace('/\A([\x00-\x7f]|[\xa1-\xdf]|[\x81-\x9f\xe0-\xfc][\x40-\x7e\x80-\xfc])*\z/', '', $result['line']) === '') {
                    return 'SJIS-win';
                }
                if (preg_replace('/\A([\x00-\x7f]|[\xa1-\xfe][\xa1-\xfe]|\x8e[\xa1-\xdf]|\x8f[\xa1-\xfe][\xa1-\xfe])*\z/', '', $result['line']) === '') {
                    return 'eucJP-win';
                }
                if (preg_replace('/\A([\x00-\x1a\x1c-\x7f]|\x1b\x24[\x40\x42](?:[\x21-\x7e][\x21-\x7e])+|\x1b\x24\x28[\x40\x42\x44](?:[\x21-\x7e][\x21-\x7e])+|\x1b\x28\x42|\x1b\x28\x4a[\x00-\x1a\x1c-\x7f]+|\x1b\x28\x49[\x00-\x1a\x1c-\x7f]+\x1b\x28\x42)*\z/', '', $result['line']) === '') {
                    return 'ISO-2022-JP-MS';
                }
                return mb_detect_encoding($result['line'], array('UTF-8', 'eucJP-win', 'SJIS-win', 'ISO-2022-JP'));
            }
        }
    }

    /**
     * return import success count
     *
     * @return integer
     */
    public function getImportedCount() {
        return $this->importedCount;
    }

    /**
     * return max cell count
     *
     * @return integer
     */
    public function getMaxColumnCount() {
        return $this->maxColumnCount;
    }
}