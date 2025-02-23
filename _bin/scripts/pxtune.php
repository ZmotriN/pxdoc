<?php
require_once(__DIR__ . '/utils.php');

const WIDTH = 672;
const HEIGHT = 102;
const AMPLITUDE = 0.75;

if(!extension_loaded('gd')) err("GD PHP Extension required.");
if(!where('audiowaveform')) err("AudioWaveForm not found.");
if(!where('mediainfo')) err("MediaInfo not found.");

if(!$colors = find_colors()) err("Can't find colors.");

if(empty($argv[1])) err("No input file specified.");
if(!$file = realpath($argv[1])) err("Invalid input file specified.");

if(!$json = trim(shell_exec('mediainfo ' . escapeshellarg($file) . ' --output=JSON'))) err("Can't extract file info.");;
if(!$data = json_decode($json)) err("Invalid media file.");

if(empty($data->media)) err("Invalid audio file.");
if(empty($data->media->track)) err("Invalid audio file.");

foreach($data->media->track as $track) if($track->{'@type'} == 'Video') err("This is not an audio file.");

foreach($data->media->track as $track) {
    if($track->{'@type'} == 'Audio' && !empty($track->SamplingCount)) {
        $samplingCount = $track->SamplingCount;
        break;
    }
}

if(!isset($samplingCount)) err("No audio track found.");

$zoom = round($samplingCount / WIDTH, 5);
$pngdark = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '-' . $colors->main . '-dark.png', $file);
$pnglight = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '-' . $colors->main . '-light.png', $file);

shell_exec('audiowaveform -i ' . escapeshellarg($file) . ' -o ' . $pngdark . ' --quiet --amplitude-scale ' . AMPLITUDE . ' --border-color ' . $colors->white . ' --axis-label-color ' . $colors->white . ' --background-color 00000000 --waveform-color ' . $colors->main . ' --width ' . WIDTH . ' --height ' . HEIGHT . ' --zoom ' . $zoom);
shell_exec('audiowaveform -i ' . escapeshellarg($file) . ' -o ' . $pnglight . ' --quiet --amplitude-scale ' . AMPLITUDE . ' --border-color ' . $colors->black . ' --axis-label-color ' . $colors->black . ' --background-color ffffff00 --waveform-color ' . $colors->main . ' --width ' . WIDTH . ' --height ' . HEIGHT . ' --zoom ' . $zoom);

foreach([$pngdark, $pnglight] as $png) {
    if(!is_file($png)) err("Can't extract waveform.");
    if(!$info = getimagesize($png)) err("Invalid image.");
    if($info['mime'] != 'image/png') err("Invalid PNG.");
    if(!$im = imagecreatefrompng($png)) err("Invalid PNG.");
    $new = imagecreatetruecolor(imagesx($im) - 2, imagesy($im) - 2);
    imagealphablending($new, false);
    imagesavealpha($new, true);
    imagecopy($new, $im, 0, 0, 1, 1, imagesx($im) - 2, imagesy($im) - 2);
    imagepng($new, $png);
    imagedestroy($im);
    imagedestroy($new);
}

echo 'Waveform Dark: ' . $pngdark.RN;
echo 'Waveform Light: ' . $pnglight.RN;
echo "Success!".RN;