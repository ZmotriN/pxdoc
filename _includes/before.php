<?php

if(!$PAGE->type) return;
if(!$info = register_page_type($PAGE->type)) return;
if(empty($info['header'])) return;
if(is_string($info['header'])) $info['header'] = [$info['header']];

$PAGE->shared = PXPros::findShared($PAGE->file);
$PAGE->rooturl = get_relative_path($PAGE->file, $PAGE->root);
$root = str_replace('\\', '/', realpath($PAGE->root)) . '/';
$image = $PAGE->baseurl . $PAGE->image;
if($PAGE->image) {
    if($img = realpath(pathinfo($PAGE->file, PATHINFO_DIRNAME) . '/' . $PAGE->image)) {
        $image = str_replace($root, $PAGE->baseurl, str_replace('\\', '/', $img));
    }
}

$favicon = get_relative_path($PAGE->file, $PAGE->root . $PAGE->favicon);

$ogtags = new stdClass;
$ogtags->image = $image;
$ogtags->title = strip_tags($PAGE->title) . ' | ' . $PAGE->project;
$ogtags->description  = htmlentities(html_entity_decode(strip_tags(trim(is_null($PAGE->abstract) ? '' : $PAGE->abstract)), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
$ogtags->url = str_replace($root, $PAGE->baseurl, str_replace('\\', '/', pathinfo($PAGE->file, PATHINFO_DIRNAME) . '/'));
$PAGE->ogtags = $ogtags;

$styles[] = $PAGE->shared . 'styles/styles.min.css';
$pstyles = $PAGE->styles;
if(empty($pstyles)) $pstyles = [];
if(is_string($pstyles)) $pstyles = [$pstyles];
foreach($pstyles as $cssfile) {
    if(!$cssfile = realpath($root . $cssfile)) continue;
    $styles[] = get_relative_path($PAGE->file, $cssfile);
}

$scripts[] = $PAGE->shared . 'jscripts/pxdoc.core.min.js';
// insert plugins here
$pscripts = $PAGE->scripts;
if(empty($pscripts)) $pscripts = [];
if(is_string($pscripts)) $pscripts = [$pscripts];
foreach($pscripts as $scriptfile) {
    if(!$scriptfile = realpath($root . $scriptfile)) continue;
    $scripts[] = get_relative_path($PAGE->file, $scriptfile);
}

foreach($info['header'] as $header) {
    if(!$headerfile = realpath($header)) continue;
    include($header);
}

