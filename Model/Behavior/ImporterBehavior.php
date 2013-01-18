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
        $this->setCsvOptions($model, $options);

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
        try {
            $csvData = array();
            $handle = fopen($filePath, "r");
            while (($result = CsvParser::parseCsvLine($handle, $d, $e)) !== false) {
                mb_convert_variables(Configure::read('App.encoding'), $this->options['csvEncoding'], $result);
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