<?php

App::uses('YacsvException', 'Yacsv.Error');

class ImporterBehavior extends ModelBehavior {

    private $model;
    private $options;
    private $importedCount = 0;

    /**
     * setUp
     *
     */
    public function setUp(&$model, $settings = array()){
        $defaults = array();
        $settings = array_merge($defaults, $settings);
        $this->settings[$model->alias] = $settings;
    }

    /**
     * importCsv
     *
     * @param $arg
     */
    public function importCsv(&$model, $data = null, $options = array()){
        $this->model = $model;
        $this->fields = $model->importFields;
        $defaults = array('csvEncoding' => 'SJIS-win',
                          'hasHeader' => false,
                          'skipHeaderCount' => 1,
                          'delimiter' => ',',
                          'enclosure' => '"',
                          'forceImport' => false,
                          'saveMethod' => false,
                          'allowExtension' => false,
                          );
        $this->options = array_merge($defaults, $options);

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
     * @param $filePath
     */
    public function importCsvFromFile(&$model, $filePath, $options = array()){
        $this->model = $model;
        $this->fields = $model->importFields;
        $defaults = array('csvEncoding' => 'SJIS-win',
                          'hasHeader' => false,
                          'skipHeaderCount' => 1,
                          'delimiter' => ',',
                          'enclosure' => '"',
                          'forceImport' => false,
                          'saveMethod' => false,
                          'allowExtension' => false,
                          'removeFile' => true,
                          );
        $this->options = array_merge($defaults, $options);

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
     * @param $filePath
     */
    private function _importCsv($filePath){
        $csvData = $this->parseCsvFile($filePath);

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
     * @param $arg
     */
    public function parseCsvFile($filePath){
        try {
            $csvData = array();
            $handle = fopen($filePath, "r");
            while (($result = $this->_parseCsvLine($handle)) !== false) {
                mb_convert_variables(Configure::read('App.encoding'), $this->options['csvEncoding'], $result);
                $csvData[] = $result;
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
     * _parseCsvLine
     * @author yossy
     * @see http://yossy.iimp.jp/wp/?p=56
     * @param $line
     */
    private function _parseCsvLine($handle){
        $d = preg_quote($this->options['delimiter']);
        $e = preg_quote($this->options['enclosure']);
        $line = "";
        $eof = false;
        while (($eof != true) && (!feof($handle))) {
            $line .= (empty($length) ? fgets($handle) : fgets($handle, $length));
            $itemcnt = preg_match_all('/' . $e . '/', $line, $dummy);
            if ($itemcnt % 2 == 0) $eof = true;
        }
        // for TSV
        if (strlen($d) === strlen(trim($d))) {
            $line = trim($line);
        } else {
            $line = preg_replace('/(\r\n|[\r\n])$/', '', $line);
        }
        $csvLine = preg_replace('/(?:\\r\\n|[\\r\\n])?$/', $d, $line);
        $pattern = '/(' . $e . '[^' . $e . ']*(?:' . $e . $e . '[^' . $e . ']*)*' . $e . '|[^' . $d . ']*)' . $d . '/';
        preg_match_all($pattern, $csvLine, $matches);
        $csvData = $matches[1];
        for($i = 0; $i < count($csvData); $i++){
            $csvData[$i] = preg_replace('/^' . $e . '(.*)' . $e . '$/s','$1', $csvData[$i]);
            $csvData[$i] = str_replace($e . $e, $e, $csvData[$i]);
        }
        return empty($line) ? false : array('data' => $csvData, 'line' => $line);
    }

    /**
     * return import success count
     *
     * @return integer
     */
    public function getImportedCount() {
        return $this->importedCount;
    }
}