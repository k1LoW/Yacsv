<?php
App::uses('Hash', 'Utility');

class CsvGenerator {

    public static $options = array(
        'csvEncoding' => 'UTF-8',
        'delimiter' => ',',
        'enclosure' => '"',
        'forceOutput' => false,
        'newlineChar' => "\n",
    );

    /**
     * generate
     *
     */
    public static function generate($data, $fields, $options = array()){
        $options = array_merge(self::$options, $options);
        $d = $options['delimiter'];
        $e = $options['enclosure'];
        $nc = $options['newlineChar'];
        if ($options['forceOutput']) {
            $fp = fopen('php://output','w');
            if ($fields !== array_values($fields)) {
                $header = array_map(function($v) use ($e) { return $e . $v . $e;}, array_keys($fields));
                if ($options['csvEncoding'] !== 'UTF-8') {
                    fwrite($fp, mb_convert_encoding(implode($d, $header) . $nc, $options['csvEncoding']));
                } else {
                    fwrite($fp, implode($d, $header) . $nc);
                }
            }
            foreach ($data as $line) {
                $tmp = array();
                foreach ($fields as $key => $pointer) {
                    $tmp[] = $e . Hash::get($line, $pointer) . $e;
                }
                if ($options['csvEncoding'] !== 'UTF-8') {
                    fwrite($fp, mb_convert_encoding(implode($d, $tmp) . $nc, $options['csvEncoding']));
                } else {
                    fwrite($fp, implode($d, $tmp) . $nc);
                }
            }
            fclose($fp);
        } else {
            $out = '';
            if ($fields !== array_values($fields)) {
                $header = array_map(function($v) use ($e) { return $e . $v . $e;}, array_keys($fields));
                $out .= implode($d, $header) . $nc;
            }
            foreach ($data as $line) {
                $tmp = array();
                foreach ($fields as $key => $pointer) {
                    $tmp[] = $e . Hash::get($line, $pointer) . $e;
                }
                $out .= implode($d, $tmp) . $nc;
            }
            if ($options['csvEncoding'] !== 'UTF-8') {
                return mb_convert_encoding($out, $options['csvEncoding']);
            }
            return $out;
        }
    }

}
