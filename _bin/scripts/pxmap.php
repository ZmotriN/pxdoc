<?php
require_once(__DIR__ . '/utils.php');

$pluginDir = realpath(__DIR__ . '../../../jscripts/plugins/') . S;

$map = [];
foreach(glob($pluginDir . '*.min.js') as $file) {
    if(empty(($info = js_file_info($file)))) continue;
    $info->tags = explode(',', $info->tags);
    $info->file = pathinfo($file, PATHINFO_BASENAME);
    $map[] = $info;
}

file_put_contents($pluginDir . 'pxdoc.plugin.map.json' , json_encode($map, JSON_PRETTY_PRINT));
