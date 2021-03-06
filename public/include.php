<?php

// add str_getcsv if it doesn't exist (added in php version 5.3)
if (!function_exists('str_getcsv')) {
    function str_getcsv($input, $delimiter = ',', $enclosure = '"', $escape = null, $eol = null)
    {
        $temp = fopen("php://memory", "rw");
        fwrite($temp, $input);
        fseek($temp, 0);
        $r = fgetcsv($temp, 4096, $delimiter, $enclosure);
        fclose($temp);
        return $r;
    }
}
