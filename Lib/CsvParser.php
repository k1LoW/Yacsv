<?php

class CsvParser {

    /**
     * parseCsvLine
     * @author yossy
     * @see http://yossy.iimp.jp/wp/?p=56
     * @param $line
     */
    public static function parseCsvLine($handle, $d, $e, $length = null){
        if (in_array($e, array(false, null, ''))) {
            $e = '__YACSVENCLOSURE__';
        }
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

}