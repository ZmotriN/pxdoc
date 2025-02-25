<?php

class Media
{

    public static function genWaveForm($file, $force = false, $width = 672, $height = 102, $amplitude = 0.75)
    {
        if(!$file = realpath($file)) throw new Exception("Invalid input file specified.");
        if(is_dir($file)) throw new Exception("Invalid input file specified.");
        if(!$colors = find_colors()) err("Can't find colors.");
        $pngdark = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '-' . $colors->main . '-dark.png', $file);
        $pnglight = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '-' . $colors->main . '-light.png', $file);
        if(!$force && is_file($pngdark) && is_file($pnglight)) return true;

        if(!extension_loaded('gd')) throw new Exception("GD PHP Extension required.");
        if(!where('audiowaveform')) throw new Exception("AudioWaveForm not found.");
        if(!where('mediainfo')) throw new Exception("MediaInfo not found.");

        if(!$json = trim(shell_exec('mediainfo ' . escapeshellarg($file) . ' --output=JSON'))) throw new Exception("Can't extract file info.");;
        if(!$data = json_decode($json)) throw new Exception("Invalid media file.");
        if(empty($data->media)) throw new Exception("Invalid audio file.");
        if(empty($data->media->track)) throw new Exception("Invalid audio file.");
        
        foreach($data->media->track as $track) if($track->{'@type'} == 'Video') throw new Exception("This is not an audio file.");
        foreach($data->media->track as $track) {
            if($track->{'@type'} == 'Audio' && !empty($track->SamplingCount)) {
                $samplingCount = $track->SamplingCount;
                break;
            }
        }
        if(!isset($samplingCount)) err("No audio track found.");
        $zoom = round($samplingCount / $width, 5);

        shell_exec('audiowaveform -i ' . escapeshellarg($file) . ' -o ' . $pngdark . ' --quiet --amplitude-scale ' . $amplitude . ' --border-color ' . $colors->white . ' --axis-label-color ' . $colors->white . ' --background-color 00000000 --waveform-color ' . $colors->main . ' --width ' . $width . ' --height ' . $height . ' --zoom ' . $zoom);
        shell_exec('audiowaveform -i ' . escapeshellarg($file) . ' -o ' . $pnglight . ' --quiet --amplitude-scale ' . $amplitude . ' --border-color ' . $colors->black . ' --axis-label-color ' . $colors->black . ' --background-color ffffff00 --waveform-color ' . $colors->main . ' --width ' . $width . ' --height ' . $height . ' --zoom ' . $zoom);

        foreach([$pngdark, $pnglight] as $png) {
            if(!is_file($png)) throw new Exception("Can't extract waveform.");
            if(!$info = getimagesize($png)) throw new Exception("Invalid image.");
            if($info['mime'] != 'image/png') throw new Exception("Invalid PNG.");
            if(!$im = imagecreatefrompng($png)) throw new Exception("Invalid PNG.");
            $new = imagecreatetruecolor(imagesx($im) - 2, imagesy($im) - 2);
            imagealphablending($new, false);
            imagesavealpha($new, true);
            imagecopy($new, $im, 0, 0, 1, 1, imagesx($im) - 2, imagesy($im) - 2);
            imagepng($new, $png);
            imagedestroy($im);
            imagedestroy($new);
        }

        return true;
    }


    public static function extractClipInfo($file, $force = false)
    {

    }


}