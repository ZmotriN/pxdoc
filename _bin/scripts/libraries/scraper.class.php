<?php
/**
 * mbstring
 * curl
 * dom
 */

class Scraper
{

    private static function scrape($url) {
        if(!is_url($url)) throw new Exception("Url is not valid.");
        if(!$contents = self::getContents($url)) throw new Exception("Can't get Url contents.");
        if(!$xpath = self::loadHTML($contents)) throw new Exception("Can't parse server response.");
        
        $metas = new stdClass;

        if(($results = $xpath->query('//title')) && $results->length) $metas->title = $results->item(0)->textContent;
        if(($results = $xpath->query('//meta[@name="dc.title"]')) && $results->length) $metas->title = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@name="twitter:title"]')) && $results->length) $metas->title = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@property="og:title"]')) && $results->length) $metas->title = $results->item(0)->getAttribute('content');

        if(($results = $xpath->query('//meta[@name="description"]')) && $results->length) $metas->description = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@name="dc.description"]')) && $results->length) $metas->description = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@name="twitter:description"]')) && $results->length) $metas->description = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@property="og:description"]')) && $results->length) $metas->description = $results->item(0)->getAttribute('content');

        if(($results = $xpath->query('//meta[@name="rc.section"]')) && $results->length) $metas->label = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@property="og:site_name"]')) && $results->length) $metas->label = $results->item(0)->getAttribute('content');

        if(($results = $xpath->query('//meta[@name="twitter:image"]')) && $results->length) $metas->image = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@property="og:image"]')) && $results->length) $metas->image = $results->item(0)->getAttribute('content');

        $metas->url = $url;
        if(($results = $xpath->query('//link[@rel="canonical"]')) && $results->length) $metas->url = $results->item(0)->getAttribute('href');
        if(($results = $xpath->query('//meta[@name="twitter:url"]')) && $results->length) $metas->url = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@property="og:url"]')) && $results->length) $metas->url = $results->item(0)->getAttribute('content');
        
        if(empty($metas->title)) return false;
        else return $metas;
    }


    private static function getContents($url) {
        if(($contents = Cache::get(($key = 'scraper_' . shorthash($url)))) !== null) return $contents;
        $contents = curl_get_contents($url);
        Cache::set($key, $contents);
        return $contents;
    }


	private static function loadHTML($contents) {
		$contents = mb_convert_encoding($contents, 'HTML-ENTITIES', 'UTF-8');
		$dom = new DomDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = false;
		@$dom->loadHTML($contents);
		return new DOMXpath($dom);
	}


    public static function get($url) {
        if(($metas = Cache::get(($key = 'metas_' . shorthash($url)))) !== null) return $metas;
        if(!$metas = self::scrape($url)) throw new Exception("Can't scrape Url.");
        Cache::set($key, $metas);
        return $metas;
    }

}