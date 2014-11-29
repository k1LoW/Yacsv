<?php
App::uses('Hash', 'Utility');
use Ginq\Ginq;
use Ginq\GinqCsv;

class CsvGenerator {

    public static $options = array(
        'csvEncoding' => 'UTF-8',
        'delimiter' => ',',
        'enclosure' => '"',
        'newlineChar' => "\n",
        'forceEnclose' => false,
        'forceOutput' => false,
    );

    /**
     * generate
     *
     */
    public static function generate($data, $fields, $options = array()){
        $options = array_merge(self::$options, $options);
        if ($fields !== array_values($fields)) {
            $header = array_keys($fields);
            array_unshift($data, $header);
        }
        Ginq::register('Ginq\GinqCsv');
        return Ginq::from($data)
            ->select(function($v, $k) use ($fields) {
                    if ($k === 0 && $fields !== array_values($fields)) {
                        return $v;
                    }
                    $line = array();
                    foreach ($fields as $pointer) {
                        $line[] = Hash::get($v, $pointer);
                    }
                    return $line;
                })
            ->toCsv($options);
    }

}
