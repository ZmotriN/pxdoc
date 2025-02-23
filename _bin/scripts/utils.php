<?php

const RN = "\r\n";
const S = '/';

function where($file)
{
    static $isWin = null;
    if($isWin === null) $isWin = strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' ? true : false;
    
    if($isWin && strtolower(pathinfo($file, PATHINFO_EXTENSION)) != 'exe') $file = pathinfo($file, PATHINFO_FILENAME) . '.exe';
    if($isWin) $results = shell_exec('where ' . $file . ' 2>&1');
    else $results = shell_exec('which ' . $file);
    
    if(!$exec = trim(current(explode("\n", trim($results))))) return false;
    if(!$exec = realpath($exec)) return false;

    return $exec;
}


function find_root($path)
{
    if (is_file($path)) $path = pathinfo(realpath($path), PATHINFO_DIRNAME);
    elseif (!$path = realpath($path)) return false;
    do {
        $file = $path . S . '_pxpros.json';
        if (is_file($file)) return realpath($file);
        $path = pathinfo($path, PATHINFO_DIRNAME);
    } while ($path != pathinfo($path, PATHINFO_DIRNAME));
    return false;
}



function err($str)
{
    echo 'Error: ' . $str . RN;
    exit(1);
}


function find_colors()
{
    static $colors = null;

    if($colors === null) {
        if(!$root = find_root(__FILE__)) return false;
        if(!$config = json_decode(file_get_contents($root))) return false;
        if(!$cssfiles[0] = realpath(pathinfo($root, PATHINFO_DIRNAME) . '/pxdoc/styles/styles.min.css')) return false;

        if(!empty($config->styles)) {
            if(is_string($config->styles)) $config->styles = [$config->styles];
            foreach($config->styles as $cssfile) {
                if(!$cssfile = realpath(pathinfo($root, PATHINFO_DIRNAME) . S . $cssfile)) continue;
                else $cssfiles[] = $cssfile;
            }
        }

        $colors = new stdClass;
        foreach($cssfiles as $cssfile) {
            if(!$css = @file_get_contents($cssfile)) continue;
            if(preg_match("/--main-color:[^#]*#([0-9a-f]{6})/i", $css, $m)) $colors->main = strtolower($m[1]) . 'ff';
            if(preg_match("/--second-color:[^#]*#([0-9a-f]{6})/i", $css, $m)) $colors->second = strtolower($m[1]) . 'ff';
            if(preg_match("/--color-black:[^#]*#([0-9a-f]{6})/i", $css, $m)) $colors->black = strtolower($m[1]) . 'ff';
            if(preg_match("/--color-white:[^#]*#([0-9a-f]{6})/i", $css, $m)) $colors->white = strtolower($m[1]) . 'ff';
        }
    }

    return $colors;
}