<?php
require_once(__DIR__ . '/utils.php');

if(!where('ffmpeg')) err("FFMPEG not found.");
if(!where('mediainfo')) err("MediaInfo not found.");

if(empty($argv[1])) err("No input file specified.");
if(!$file = realpath($argv[1])) err("Invalid input file specified.");

$destjpeg = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '.jpg', $file);
$destjson = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '.json', $file);

if(!$json = trim(shell_exec('mediainfo ' . escapeshellarg($file) . ' --output=JSON'))) err("Can't extract file info.");;
if(!$data = json_decode($json)) err("Invalid media file.");

if(empty($data->media)) err("Invalid video file.");
if(empty($data->media->track)) err("Invalid video file.");

foreach($data->media->track as $track) {
    if($track->{'@type'} == 'Video') {
        $videofound = true;
        break;
    }
}

if(empty($videofound)) err("No video track found on media.");
file_put_contents($destjson, json_encode($data, JSON_PRETTY_PRINT));

shell_exec('ffmpeg -hide_banner -loglevel quiet -ss 1 -y -i ' . escapeshellarg($file) . ' -an -vframes 1 ' . escapeshellarg($destjpeg));
if(!is_file($destjpeg)) err("Can't extract still frame.");

echo 'JSON: ' . $destjson.RN;
echo 'JPEG: ' . $destjpeg.RN;
echo "Success!".RN;