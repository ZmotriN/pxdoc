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
        if(!$contents = self::getContents($url)) throw new Exception("Url crawling seems to have been block.");
        if(!$xpath = self::loadHTML($contents)) throw new Exception("Can't parse server response.");

        $metas = new stdClass;
        $metas->url = $url;
        $metas->title = '';
        $metas->description = '';
        $metas->image = '';
        $metas->label = '';

        

        if(($results = $xpath->query('//script[@type="application/ld+json"]')) && $results->length) {
            $graphs = [];
            foreach($results as $item) {
                if($ld = json_decode($item->textContent)) {
                    if(empty($ld->{'@graph'})) $graphs[] = $ld;
                    else foreach($ld->{'@graph'} as $item) $graphs[] = $item;
                }
            }

            foreach($graphs as $graph) {
                if(empty($graph->{'@type'})) continue;
                if(is_array($graph->{'@type'}) || strtolower($graph->{'@type'}) != 'imageobject') continue;
                if(!empty($graph->url)) $metas->image = html_entities_decode($graph->url);
            }

            foreach($graphs as $graph) {
                if(empty($graph->{'@type'})) continue;
                if(is_array($graph->{'@type'}) || strtolower($graph->{'@type'}) != 'organization') continue;
                if(!empty($graph->name)) $metas->label = html_entities_decode($graph->name);
            }

            foreach($graphs as $graph) {
                if(empty($graph->{'@type'})) continue;
                if(is_array($graph->{'@type'}) || strtolower($graph->{'@type'}) != 'newsmediaorganization') continue;
                if(!empty($graph->name)) $metas->label = html_entities_decode($graph->name);
            }

            foreach($graphs as $graph) {
                if(empty($graph->{'@type'})) continue;
                if(is_array($graph->{'@type'}) || strtolower($graph->{'@type'}) != 'webpage') continue;
                if(!empty($graph->name)) $metas->title = html_entities_decode($graph->name);
                if(!empty($graph->description)) $metas->description = html_entities_decode($graph->description);
                if(!empty($graph->image->url)) $metas->image = html_entities_decode($graph->image->url);
                if(!empty($graph->thumbnailUrl)) $metas->image = html_entities_decode($graph->thumbnailUrl);
                if(!empty($graph->publisher->name)) $metas->label = html_entities_decode($graph->publisher->name);
                if(!empty($graph->mainEntity->name)) $metas->title = html_entities_decode($graph->mainEntity->name);
                if(!empty($graph->mainEntity->image->url)) $metas->image = html_entities_decode($graph->mainEntity->image->url);
            }
            
            foreach($graphs as $graph) {
                if(empty($graph->{'@type'})) continue;
                if(is_array($graph->{'@type'}) || strtolower($graph->{'@type'}) != 'newsarticle') continue;
                if(!empty($graph->headline)) $metas->title = html_entities_decode($graph->headline);
                if(!empty($graph->description)) $metas->description = html_entities_decode($graph->description);
                if(!empty($graph->publisher->name)) $metas->label = html_entities_decode($graph->publisher->name);
                if(!empty($graph->thumbnailUrl)) $metas->image = html_entities_decode($graph->thumbnailUrl);
                if(!empty($graph->image->url)) $metas->image = html_entities_decode($graph->image->url);
            }

            foreach($graphs as $graph) {
                if(empty($graph->{'@type'})) continue;
                if(is_array($graph->{'@type'}) || strtolower($graph->{'@type'}) != 'opinionnewsarticle') continue;
                if(!empty($graph->headline)) $metas->title = html_entities_decode($graph->headline);
                if(!empty($graph->description)) $metas->description = html_entities_decode($graph->description);
                if(!empty($graph->publisher->name)) $metas->label = html_entities_decode($graph->publisher->name);
                if(!empty($graph->thumbnailUrl)) $metas->image = html_entities_decode($graph->thumbnailUrl);
                if(!empty($graph->image->url)) $metas->image = html_entities_decode($graph->image->url);
            }

            foreach($graphs as $graph) {
                if(empty($graph->{'@type'})) continue;
                if(is_array($graph->{'@type'}) || strtolower($graph->{'@type'}) != 'radioepisode') continue;
                if(!empty($graph->name)) $metas->title = html_entities_decode($graph->name);
                if(!empty($graph->description)) $metas->description = html_entities_decode($graph->description);
                if(!empty($graph->productionCompany->name)) $metas->label = html_entities_decode($graph->productionCompany->name);
                if(!empty($graph->thumbnailUrl)) $metas->image = html_entities_decode($graph->thumbnailUrl);
                if(!empty($graph->image->url)) $metas->image = html_entities_decode($graph->image->url);
            }
        }

        if(($results = $xpath->query('//link[@rel="alternate" and @type="application/json+oembed" and @href]')) && $results->length) {
            if(is_url(($oembedurl = $results->item(0)->getAttribute('href')))) {
                if($json = self::getContents($oembedurl)) {
                    if($oem = json_decode($json)) {
                        if(!empty($oem->title)) $metas->title = $oem->title;
                        if(!empty($oem->summary)) $metas->description = $oem->summary;
                        if(!empty($oem->provider_name)) $metas->label = $oem->provider_name;
                        if(!empty($oem->thumbnail_url)) $metas->image = $oem->thumbnail_url;
                        if(!empty($oem->url)) $metas->image = $oem->url;
                    }
                }
            }
        }
        
        if(($results = $xpath->query('//meta[@name="dc.title"]')) && $results->length) $metas->title = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@name="dcterms.title"]')) && $results->length) $metas->title = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@name="twitter:title"]')) && $results->length) $metas->title = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@property="og:title"]')) && $results->length) $metas->title = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@name="description"]')) && $results->length) $metas->description = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@name="dc.description"]')) && $results->length) $metas->description = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@name="dcterms.description"]')) && $results->length) $metas->description = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@name="twitter:description"]')) && $results->length) $metas->description = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@property="og:description"]')) && $results->length) $metas->description = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@name="twitter:image"]')) && $results->length) $metas->image = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@property="og:image"]')) && $results->length) $metas->image = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//link[@rel="canonical"]')) && $results->length) $metas->url = $results->item(0)->getAttribute('href');
        if(($results = $xpath->query('//meta[@name="twitter:url"]')) && $results->length) $metas->url = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@property="og:url"]')) && $results->length) $metas->url = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@name="og:site_name"]')) && $results->length) $metas->label = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//meta[@property="og:site_name"]')) && $results->length) $metas->label = $results->item(0)->getAttribute('content');
        if(($results = $xpath->query('//shreddit-title')) && $results->length) $metas->title = $results->item(0)->getAttribute('title');


        
        if(empty($metas->label)) {
            if(($results = $xpath->query('//link[@rel="manifest" and @href]')) && $results->length) {
                if($manifesturl = get_absolute_url($url, $results->item(0)->getAttribute('href'))) {
                    if($json = self::getContents($manifesturl)) {
                        if($manifest = json_decode($json)) {
                            if(!empty($manifest->name)) $metas->label = $manifest->name;
                        }
                    }
                }
            }
        }

        if(empty($metas->label)) {
            if(($results = $xpath->query('//meta[@property="fb:page_id"]')) && $results->length) {
                if($contents = self::getContents('https://facebook.com/' . $results->item(0)->getAttribute('content'))) {
                    if($xp = self::loadHTML($contents)) {
                        if(($results = $xp->query('//meta[@property="og:title"]')) && $results->length) {
                            $metas->label = $results->item(0)->getAttribute('content');
                        }
                    }
                }
            }
        }
        
        if(strtolower(pathinfo(parse_url($metas->image, PHP_URL_PATH), PATHINFO_EXTENSION)) == 'svg') $metas->image = '';



        if(empty($metas->image) && ($results = $xpath->query('//link[@rel="apple-touch-icon" and @sizes]')) && $results->length) {
            $size = 0;
            foreach($results as $item) {
                $nsize = current(explode('x', $item->getAttribute('sizes')));
                if($nsize > $size) {
                    $metas->image = $item->getAttribute('href');
                    $size = $nsize;
                }
            }
        }
        

        if(empty($metas->label) && ($results = $xpath->query('//meta[@property="article:author"]')) && $results->length) $metas->label = $results->item(0)->getAttribute('content');
        if(empty($metas->label) && ($results = $xpath->query('//meta[@name="author"]')) && $results->length) $metas->label = $results->item(0)->getAttribute('content');
        if(empty($metas->label) && ($results = $xpath->query('//meta[@name="twitter:creator"]')) && $results->length) $metas->label = $results->item(0)->getAttribute('content');
        if(empty($metas->label) && ($results = $xpath->query('//meta[@name="dcterms.creator"]')) && $results->length) $metas->label = $results->item(0)->getAttribute('content');
        if(empty($metas->label) && ($results = $xpath->query('//meta[@property="al:ios:app_name"]')) && $results->length) $metas->label = $results->item(0)->getAttribute('content');
        if(empty($metas->label) && ($results = $xpath->query('//meta[@property="al:android:app_name"]')) && $results->length) $metas->label = $results->item(0)->getAttribute('content');
        if(empty($metas->label) && ($results = $xpath->query('//span[@class="footer__signature"]')) && $results->length) $metas->label = $results->item(0)->textContent;
        if(empty($metas->label) && ($results = $xpath->query('//p[@class="site-title"]')) && $results->length) $metas->label = trim($results->item(0)->textContent);
        if(empty($metas->label) && ($results = $xpath->query('//meta[@name="copyright"]')) && $results->length) $metas->label = trim($results->item(0)->getAttribute('content'), '© ');


        if(empty($metas->label)) {
            $parts = explode('|', $metas->title);
            if(count($parts) > 1) $metas->label = trim(array_pop($parts));
        }

        if(empty($metas->label)) {
            $parts = explode(' - ', $metas->title);
            if(count($parts) > 1) $metas->label = trim(array_pop($parts));
        }


        if(empty($metas->title) && ($results = $xpath->query('//title')) && $results->length) $metas->title = $results->item(0)->textContent;

        if(empty($metas->description) && ($results = $xpath->query('//p')) && $results->length){
            foreach($results as $item) {
                if(!($desc = trim($item->textContent))) continue;
                if(count(explode(' ', $desc)) < 5) continue;
                $metas->description = $desc;
                break;
            }
        }
        
        
        // print_r($metas);
        
        
        if(strpos($metas->image, '//') === 0) $metas->image = 'https:' . $metas->image;
        if(!empty($metas->image) && !parse_url($metas->image, PHP_URL_SCHEME)) $metas->image = get_absolute_url($url, $metas->image);
        
        if(parse_url($metas->image, PHP_URL_HOST) == 'lookaside.fbsbx.com')
            if($contents = self::getContents($metas->image))
                if(preg_match('#location\.href = "(.*?)";#i', $contents, $m))
                    if($contents = self::getContents(str_replace('\\/', '/', $m[1])))
                        if($xp = self::loadHTML($contents))
                            if(($results = $xp->query('//link[@rel="preload" and @as="image" and contains(@href, ".jpg")]')) && $results->length)
                                $metas->image = $results->item(0)->getAttribute('href');

        if(!url_exists($metas->image, '^image/') && ($results = $xpath->query('//img[@src]')) && $results->length) {
            $metas->image = $results->item(0)->getAttribute('src');
            foreach($results as $item) {
                if(in_array(pathinfo(parse_url($item->getAttribute('src'), PHP_URL_PATH), PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $metas->image = $item->getAttribute('src');
                    if(strpos($metas->image, '//') === 0) $metas->image = 'https:' . $metas->image;
                    if(!empty($metas->image) && !parse_url($metas->image, PHP_URL_SCHEME)) $metas->image = get_absolute_url($url, $metas->image);
                    break;
                }
            }
        }

        
        // if(empty($metas->label)) $metas->label = preg_replace('#^www\.#i', '', parse_url($url, PHP_URL_HOST));

        $metas->url = urldecode($metas->url);
        $metas->title = trim(current(explode(' — ', current(explode(' - ', current(explode(' | ', $metas->title)))))));
        $metas->label = preg_replace('#^www\.#i', '', trim(current(explode(' — ', current(explode(' - ', current(explode(' | ', $metas->label))))))));
        $metas->description = trim($metas->description);
        return $metas;
        return empty($metas->title) ? false : $metas;
    }


    private static function getContents($url) {
        if(($contents = Cache::get(($key = 'scraper_' . shorthash($url)))) !== null) return $contents;
        $contents = curl_get_contents($url);
        Cache::set($key, $contents);
        return $contents;
    }


	private static function loadHTML($contents) {
        $encoding = mb_detect_encoding($contents, 'UTF-8, ISO-8859-1, ISO-8859-15', true);
        if ($encoding !== 'UTF-8') $contents = mb_convert_encoding($contents, 'UTF-8', $encoding);
		$dom = new DomDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = false;
		@$dom->loadHTML($contents);
		return new DOMXpath($dom);
	}


    public static function get($url) {
        // if(($metas = Cache::get(($key = 'metas_' . shorthash($url)))) !== null) return $metas;
        if(!$metas = self::scrape($url)) throw new Exception("Can't crawl Url.");
        $key = 'metas_' . shorthash($url);
        Cache::set($key, $metas);
        // print_r($metas);
        return $metas;
    }

}