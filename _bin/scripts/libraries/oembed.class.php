<?php

class oEmbed
{

    public static function YouTube($id)
    {
        if(!preg_match('/^[\w\-_]{10,12}$/', $id)) return false;
        if($info = Cache::get('oembed_youtube_' . $id)) return $info;
        if(!$contents = curl_get_contents('https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=' . $id . '&format=json')) return false;
        if(!$info = json_decode($contents)) return false;
        Cache::set('oembed_youtube_' . $id, $info);
        return $info;
    }


    public static function Vimeo($id)
    {
        if(!preg_match('/^[0-9]+$/', $id)) return false;
        if($info = Cache::get('oembed_vimeo_' . $id)) return $info;
        if(!$contents = curl_get_contents('https://vimeo.com/api/oembed.json?url=https://vimeo.com/' . $id)) return false;
        if(!$info = json_decode($contents)) return false;
        Cache::set('oembed_vimeo_' . $id, $info);
        return $info;
    }

}