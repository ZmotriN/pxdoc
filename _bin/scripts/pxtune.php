<?php
require_once(__DIR__ . '/utils.php');

if(empty($argv[1])) err("No input file specified.");
if(!$file = realpath($argv[1])) err("Invalid input file specified.");

try {
    Media::genWaveForm($file, true);
} catch(Exception $e) {
    err($e->getMessage());
    exit(1);
}

echo "Success!".RN;
exit(0);