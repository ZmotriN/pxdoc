<?php
require_once(__DIR__ . '/utils.php');


// print_r(find_colors());


// var_dump(Cache::set('bleuh', 'caca'));

// var_dump(Cache::get('bleuh'));

// echo date("T").RN;


try {
    print_r(Media::getMediaInfo($argv[1]));
} catch(Exception $e) {
    print_r($e);
    exit(1);
}






