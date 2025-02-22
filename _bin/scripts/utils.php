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