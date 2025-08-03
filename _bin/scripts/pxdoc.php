<?php
require_once(__DIR__ . '/utils.php');

$PAGE = null;

if (!isset($argv[1])) err("Invalid argument.");
if (!$target = realpath($argv[1])) err("Invalid target.");

if (is_dir($target)) {
    if (!$seed = PXPros::findSeed($target)) err("No project configuration found.");
    $prj = new PXPros($seed);
    foreach (dig($target . '/*.php') as $file) {
        $parent = pathinfo(pathinfo($file, PATHINFO_DIRNAME), PATHINFO_BASENAME);
        if (strpos($parent, '_') === 0) continue;
        if (strpos(pathinfo($file, PATHINFO_FILENAME), '_') !== 0) continue;
        echo 'Render: ';
        echo str_replace([pathinfo($seed, PATHINFO_DIRNAME), S, '\\'], ['', '/', '/'], $file) . RN;
        $prj->render($file);
    }
} elseif (preg_match('#^_(.*)\.php$#i', pathinfo($target, PATHINFO_BASENAME), $m)) {
    if (!$seed = PXPros::findSeed($target)) err("No project configuration found.");
    $pxpros = new PXPros($seed);
    echo 'Render: ';
    echo str_replace([pathinfo($seed, PATHINFO_DIRNAME), S, '\\'], ['', '/', '/'], $target) . RN;
    $pxpros->render($target);
} else {
    err("Invalid target.");
}

exit(0);