<?php
require_once(__DIR__ . '/utils.php');

if(!$seed = PXPros::findSeed(__DIR__)) err("Can't find project root.");
if(!$config = json_decode(file_get_contents($seed))) err("Invalid project seed.");
if(empty($config->dist)) err("No dist path find in project seed.");
$rootPath = pathinfo($seed, PATHINFO_DIRNAME) . S;

if(is_dir(($distPath = $rootPath . $config->dist))) {
    $distPath = realpath($distPath) . S;
    delete_path($distPath, true);
} else {
    if(!@mkdir($distPath, 0777, true)) err("Can't create dist folder");
    $distPath = realpath($distPath) . S;
}

$excludeFiles = ["package-lock.json", "package.json"];

$rit = new RecursiveIteratorIterator((new RecursiveDirectoryIterator($rootPath,
    FilesystemIterator::CURRENT_AS_PATHNAME |    
    FilesystemIterator::FOLLOW_SYMLINKS |
    FilesystemIterator::SKIP_DOTS |
    FilesystemIterator::UNIX_PATHS
)), RecursiveIteratorIterator::SELF_FIRST);

foreach($rit as $file) {
    if(is_dir($file)) continue;
    if(preg_match('#\.map\.json$#i', $file)) continue;
    if(pathinfo($file, PATHINFO_EXTENSION) == 'scss') continue;
    if(pathinfo($file, PATHINFO_EXTENSION) == 'js' && !preg_match('#\.min\.js$#i', $file)) continue;
    if(in_array(pathinfo($file, PATHINFO_BASENAME), $excludeFiles)) continue;
    if(preg_match('#/[_\.]#i', ($path = get_relative_path($rootPath, $file)))) continue;

    $destPath = $distPath . preg_replace('#^\./#i', '', $path);
    $destDir = pathinfo($destPath, PATHINFO_DIRNAME);

    if(!is_dir($destDir) && !@mkdir($destDir, 0777, true)) err("Can't create dir: ". $destDir);
    if(!@copy($file, $destPath)) err("Can't copy file: " . $path);
    
    echo "COPY: " . $path . RN;
}

