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
        if(!where('audiowaveform')) throw new Exception("AudioWaveForm not found.");
        
        if(!$data = self::getMediaInfo($file)) throw new Exception("Invalid media file.");
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
        if(!$file = realpath($file)) throw new Exception("Invalid input file specified.");
        if(is_dir($file)) throw new Exception("Invalid input file specified.");

        $destjpeg = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '.jpg', $file);
        $destjson = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '.json', $file);

        if(!$force && is_file($destjpeg) && is_file($destjson)) return json_decode(file_get_contents($destjson));

        if(!where('ffmpeg')) throw new Exception("FFMPEG not found.");

        if(!$data = self::getMediaInfo($file)) throw new Exception("Invalid media file.");
        if(empty($data->media)) throw new Exception("Invalid video file.");
        if(empty($data->media->track)) throw new Exception("Invalid video file.");
        
        foreach($data->media->track as $track) {
            if($track->{'@type'} == 'Video') {
                $videofound = true;
                break;
            }
        }
        if(empty($videofound)) throw new Exception("No video track found on media.");
        file_put_contents($destjson, json_encode($data, JSON_PRETTY_PRINT));

        shell_exec('ffmpeg -hide_banner -loglevel quiet -ss 1 -y -i ' . escapeshellarg($file) . ' -an -vframes 1 ' . escapeshellarg($destjpeg));
        if(!is_file($destjpeg)) throw new Exception("Can't extract still frame.");

        return $data;
    }


    public static function getMediaInfo($file)
    {
        if(!$file = realpath($file)) throw new Exception("Invalid input file specified.");
        if(is_dir($file)) throw new Exception("Invalid input file specified.");

        $key = 'mediainfo_' . shorthash(filemtime($file) . $file);
        if($info = Cache::get($key)) return $info;

        if(!where('mediainfo')) throw new Exception("MediaInfo not found.");
        if(!$json = trim(shell_exec('mediainfo ' . escapeshellarg($file) . ' --output=JSON'))) throw new Exception("Can't extract file info.");;
        if(!$data = json_decode($json)) throw new Exception("Invalid media file.");

        Cache::set($key, $data);
        return $data;
    }


    public static function downloadImage($url, $dest, $width, $height)
    {
        if(!url_exists($url, '^image/')) throw new Exception("Image crawling seems to have been block.");
        if(!$contents = curl_get_contents($url)) throw new Exception("Can't download thumbnail image.");

        if(IMAGICK_SUPPORT) {
            $im = new Imagick;
            $im->readImageBlob($contents);

            $originalWidth = $im->getImageWidth();
            $originalHeight = $im->getImageHeight();
            $aspectRatio = $originalWidth / $originalHeight;
            $targetAspectRatio = $width / $height;
            
            if ($aspectRatio > $targetAspectRatio) {
                $newHeight = $originalHeight;
                $newWidth = (int)($originalHeight * $targetAspectRatio);
            } else {
                $newWidth = $originalWidth;
                $newHeight = (int)($originalWidth / $targetAspectRatio);
            }
            
            $cropX = (int)(($originalWidth - $newWidth) / 2);
            $cropY = (int)(($originalHeight - $newHeight) / 2);
            
            $im->cropImage($newWidth, $newHeight, $cropX, $cropY);
            $im->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
            $im->setImageFormat('webp');
            $im->setOption('webp:method', '6'); 
            $im->writeImage($dest);
            $im->destroy();
        } else {
            if(!$img = @imagecreatefromstring($contents)) throw new Exception("Invalid thumbnail image.");
            if(!$thumb = cropimage($img, 480, 252)) throw new Exception("Can't crop thumbnail image.");
            if(IMG_EXT == '.webp' && !imagewebp($thumb, $dest)) throw new Exception("Can't save thumbnail image.");
            elseif(!imagejpeg($thumb, $dest)) throw new Exception("Can't save thumbnail image.");
            imagedestroy($img);
            imagedestroy($thumb);
        }
        return realpath($dest);
    }



}