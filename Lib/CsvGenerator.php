<?php
App::uses('Hash', 'Utility');

class CsvGenerator {

    public static $options = array(
        'csvEncoding' => 'SJIS-win',
        'delimiter' => ',',
        'enclosure' => '"',
        'forceOutput' => false,
    );

    /**
     * generate
     *
     */
    public static function generate($data, $fields, $options = array()){
        $options = array_merge(self::$options, $options);
        $d = $options['delimiter'];
        $e = $options['enclosure'];
        if ($options['forceOutput']) {
            $fp = fopen('php://output','w');
            if ($fields !== array_values($fields)) {
                $header = array_map(function($v) use ($e) { return $e . $v . $e;}, array_keys($fields));
                if ($options['csvEncoding'] !== 'UTF-8') {
                    fwrite($fp, mb_convert_encoding(implode($d, $header) . "\n", $options['csvEncoding']));
                } else {
                    fwrite($fp, implode($d, $header) . "\n");
                }
            }
            foreach ($data as $line) {
                $tmp = array();
                foreach ($fields as $key => $pointer) {
                    $tmp[] = $e . Hash::get($line, $pointer) . $e;
                }
                if ($options['csvEncoding'] !== 'UTF-8') {
                    fwrite($fp, mb_convert_encoding(implode($d, $tmp) . "\n", $options['csvEncoding']));
                } else {
                    fwrite($fp, implode($d, $tmp) . "\n");
                }
            }
            fclose($fp);
        } else {
            $out = '';
            if ($fields !== array_values($fields)) {
                $header = array_map(function($v) use ($e) { return $e . $v . $e;}, array_keys($fields));
                $out .= implode($d, $header) . "\n";
            }
            foreach ($data as $line) {
                $tmp = array();
                foreach ($fields as $key => $pointer) {
                    $tmp[] = $e . Hash::get($line, $pointer) . $e;
                }
                $out .= implode($d, $tmp) . "\n";
            }
            if ($options['csvEncoding'] !== 'UTF-8') {
                return mb_convert_encoding($out, $options['csvEncoding']);
            }
            return $out;
        }
    }

}
