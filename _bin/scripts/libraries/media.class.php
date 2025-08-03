<?php

class Media
{

    public static function genWaveForm($file, $force = false, $width = 672, $height = 102, $amplitude = 0.75)
    {
        if(!$file = realpath($file)) throw new Exception("Invalid input file specified.");
        if(is_dir($file)) throw new Exception("Invalid input file specified.");
        if(!$colors = find_colors()) throw new Exception("Can't find colors.");
        $pngdark = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '-' . $colors->main . '-dark.png', $file);
        $pnglight = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '-' . $colors->main . '-light.png', $file);
        if(!$force && is_file($pngdark) && is_file($pnglight)) return true;

        if(!extension_loaded('gd')) throw new Exception("GD PHP Extension required.");
        
        if(!$data = self::getMediaInfo($file, $force)) throw new Exception("Invalid media file.");
        if(empty($data->media)) throw new Exception("Invalid audio file.");
        if(empty($data->media->track)) throw new Exception("Invalid audio file.");
        
        foreach($data->media->track as $track) if($track->{'@type'} == 'Video') throw new Exception("This is not an audio file.");
        foreach($data->media->track as $track) {
            if($track->{'@type'} == 'Audio' && !empty($track->SamplingCount)) {
                $samplingCount = $track->SamplingCount;
                break;
            }
        }
        if(!isset($samplingCount)) throw new Exception("No audio track found.");
        $zoom = round($samplingCount / $width, 5);

        shell_exec(escapeshellarg(MODULES_PATH . 'audiowaveform') . ' -i ' . escapeshellarg($file) . ' -o ' . $pngdark . ' --quiet --amplitude-scale ' . $amplitude . ' --border-color ' . $colors->white . ' --axis-label-color ' . $colors->white . ' --background-color 00000000 --waveform-color ' . $colors->main . ' --width ' . $width . ' --height ' . $height . ' --zoom ' . $zoom);
        shell_exec(escapeshellarg(MODULES_PATH . 'audiowaveform') . ' -i ' . escapeshellarg($file) . ' -o ' . $pnglight . ' --quiet --amplitude-scale ' . $amplitude . ' --border-color ' . $colors->black . ' --axis-label-color ' . $colors->black . ' --background-color ffffff00 --waveform-color ' . $colors->main . ' --width ' . $width . ' --height ' . $height . ' --zoom ' . $zoom);

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
        if(!$file = realpath($file)) throw new Exception("Invalid input file specified.");
        if(is_dir($file)) throw new Exception("Invalid input file specified.");

        $destjpeg = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '.jpg', $file);
        $destwebp = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '.webp', $file);

        if(!$data = self::getMediaInfo($file, $force)) throw new Exception("Invalid media file.");
        if(empty($data->media)) throw new Exception("Invalid video file.");
        if(empty($data->media->track)) throw new Exception("Invalid video file.");
        
        foreach($data->media->track as $track) {
            if($track->{'@type'} == 'Video') {
                $videotrack = $track;
                break;
            }
        }
        if(empty($videotrack)) throw new Exception("No video track found on media.");
        if(!$force && is_file($destwebp)) return $data;

        shell_exec(escapeshellarg(MODULES_PATH . 'ffmpeg') . ' -hide_banner -loglevel quiet -ss ' . ($videotrack->Duration / 2) . ' -y -i ' . escapeshellarg($file) . ' -an -vframes 1 ' . escapeshellarg($destjpeg));
        if(!is_file($destjpeg)) throw new Exception("Can't extract still frame.");
        $img = imagecreatefromjpeg($destjpeg);
        imagewebp($img, $destwebp, 70);
        imagedestroy($img);
        unlink($destjpeg);

        return $data;
    }


    public static function getMediaInfo(string $file, bool $force = false)
    {
        if(!$file = realpath($file)) throw new Exception("Invalid input file specified.");
        if(is_dir($file)) throw new Exception("Invalid input file specified.");

        $key = 'mediainfo_' . shorthash(filemtime($file) . $file);
        if(!$force && ($info = Cache::get($key))) return $info;

        if(!$json = trim(shell_exec(escapeshellarg(MODULES_PATH . 'mediainfo') .  ' ' . escapeshellarg($file) . ' --Output=JSON'))) throw new Exception("Can't extract file info.");;
        if(!$data = json_decode($json)) throw new Exception("Invalid media file.");

        Cache::set($key, $data);
        return $data;
    }


    public static function downloadImage(string $url, string $dest, int $width, int $height): string | false
    {
        if(!url_exists($url, '^image/')) throw new Exception("Image crawling seems to have been block.");
        if(!$contents = curl_get_contents($url)) throw new Exception("Can't download thumbnail image.");
        if(!$img = @imagecreatefromstring($contents)) throw new Exception("Invalid thumbnail image.");
        if (!$thumb = cropimage($img, $width, $height)) throw new Exception("Can't crop thumbnail image.");
        if (!imagewebp($thumb, $dest, 60)) throw new Exception("Can't save thumbnail image.");
        imagedestroy($img);
        imagedestroy($thumb);
        return realpath($dest);
    }



}