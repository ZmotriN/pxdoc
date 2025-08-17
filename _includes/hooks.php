<?php

/******************************************************
 *                 Article Page Type                  *
 ******************************************************/
register_page_type('article', [
    'header' => [
        __DIR__ . '/templates/header.php',
        __DIR__ . '/templates/header_main.php',
        __DIR__ . '/templates/header_article.php',
    ],
    'footer' => [
        __DIR__ . '/templates/footer_article.php',
        __DIR__ . '/templates/footer_main.php',
        __DIR__ . '/templates/footer.php',
    ],
]);


/******************************************************
 *                   List Page Type                   *
 ******************************************************/
register_page_type('list', [
    'header' => [
        __DIR__ . '/templates/header.php',
        __DIR__ . '/templates/header_main.php',
        __DIR__ . '/templates/header_list.php',
    ],
    'footer' => [
        __DIR__ . '/templates/footer_list.php',
        __DIR__ . '/templates/footer_main.php',
        __DIR__ . '/templates/footer.php',
    ],
]);


/******************************************************
 *                   Wiki Page Type                   *
 ******************************************************/
register_page_type('wiki', [
    'header' => [
        __DIR__ . '/templates/header.php',
        __DIR__ . '/templates/header_wiki.php',
    ],
    'footer' => [
        __DIR__ . '/templates/footer_wiki.php',
        __DIR__ . '/templates/footer.php',
    ],
]);


/******************************************************
 *                  Register Plugins                  *
 ******************************************************/
register_hook('pre_render', function($contents) {
    $map = json_decode(file_get_contents(realpath(__DIR__ . '/../jscripts/plugins/pxdoc.plugin.map.json')));
    foreach($map as $plugin) {
        foreach($plugin->tags as $tag) {
            if(preg_match('#<' . preg_quote($tag, '#') . '([^>]*)>(.*?)</' . preg_quote($tag, '#') . '>#msi', $contents)) {
                $this->addPlugin($plugin->file);
            }
        }
    }
});



/******************************************************
 *                Composante Exercice                 *
 ******************************************************/
register_tag('exercice', function($html, $attrs, $data) {
    if(empty($attrs['href'])) return;
    if(!is_file(($file = realpath(pathinfo($this->file, PATHINFO_DIRNAME).S.$attrs['href']).S.'_index.php'))) return;
    if(!$info = php_file_info($file)) return;
    $url = !empty($info->url) ? $info->url : $attrs['href'];
    $thumb = rtrim($attrs['href'], '/') . '/' . (!empty($info->image) ? $info->image : $info->icon);
    $target = !empty($info->url) ? '_blank' : '_self';
    return <<<EOD
        <a class="exercice" target="{$target}" href="{$url}">
            <div class="exercice-container">
                <div class="exercice-thumb" style="background-image: url({$thumb})"></div>
                <div class="exercice-abstract">
                    <em class="exercice-label">EXERCICE</em>
                    <span class="exercice-title">{$info->title}</span>
                    <span class="exercice-description">{$info->abstract}</span>
                </div>
            </div>
        </a>
EOD;
});


/******************************************************
 *                  Composante Tool                   *
 ******************************************************/
register_tag('tool', function($html, $attrs, $data) {
    if(empty($attrs['href'])) return;
    if(!is_file(($file = realpath(pathinfo($this->file, PATHINFO_DIRNAME).S.$attrs['href']).S.'_index.php'))) return;
    if(!$info = php_file_info($file)) return;
    $url = !empty($info->url) ? $info->url : $attrs['href'];
    $thumb = rtrim($attrs['href'], '/') . '/' . (!empty($info->image) ? $info->image : $info->icon);
    $target = !empty($info->url) ? '_blank' : '_self';
    return <<<EOD
        <a class="tool" target="{$target}" href="{$url}">
            <div class="tool-container">
                <div class="tool-abstract">
                    <em class="tool-label">OUTIL</em>
                    <span class="tool-title">{$info->title}</span>
                    <span class="tool-description">{$info->abstract}</span>
                </div>
                <div class="tool-thumb" style="background-image: url({$thumb})"></div>
            </div>
        </a>
EOD;
});


/******************************************************
 *                 Composante Intlink                 *
 ******************************************************/
register_tag('intlink', function($html, $attrs, $data) {
    if(empty($attrs['href'])) return;
    $path = $attrs['href'];
    if(strpos($path, '#') !== false) list($path, $anchor) = explode('#', $path);
    $path = rtrim($path,'/\\');
    if(!is_file(($file = realpath(pathinfo($this->file, PATHINFO_DIRNAME).S.$path).S.'_index.php'))) return;
    if(!$info = php_file_info($file)) return;
    $url = !empty($info->url) ? $info->url : $path;
    $url = rtrim($url, '/').'/'.(isset($anchor) ? '#'.$anchor : '');
    $thumb = rtrim($path, '/').'/'.$info->icon;
    $thumb = get_relative_path($this->file, realpath(pathinfo($this->file, PATHINFO_DIRNAME).'/'.$thumb));
    return <<<EOD
        <div class="intlink__item">
            <div class="intlink__item__icon" style="background-image: url({$thumb});"></div>
            <div class="intlink__item__description">
                <span class="intlink__item__title"><a href="{$url}">{$info->title}</a></span>
                <span class="intlink__item__abstract">{$info->abstract}</span>
            </div>
        </div>
EOD;
});


/******************************************************
 *                Composante Listlink                 *
 ******************************************************/
register_tag('listlink', function($html, $attrs, $data) {
    if(empty($attrs['href'])) return;
    $path = $attrs['href'];
    $path = rtrim($path,'/\\');
    if(!is_file(($file = realpath(pathinfo($this->file, PATHINFO_DIRNAME).S.$path).S.'_index.php'))) return;
    if(!$info = php_file_info($file)) return;
    $url = !empty($info->url) ? $info->url : $attrs['href'];
    $thumb = rtrim($path, '/').'/'.$info->icon;
    $thumb = get_relative_path($this->file, realpath(pathinfo($this->file, PATHINFO_DIRNAME).'/'.$thumb));
    return <<<EOD
        <div class="list-grid__item">
            <div class="list-grid__item__icon" style="background-image: url({$thumb});"></div>
            <div class="list-grid__item__description">
                <span class="list-grid__item__title"><a href="{$url}">{$info->title}</a></span>
                <span class="list-grid__item__abstract">{$info->abstract}</span>
            </div>
        </div>
EOD;
});


/******************************************************
 *                Composante Highlight                *
 ******************************************************/
register_tag('highlight', function($html, $attrs, $data) {
    $data = html_entity_decode(trim($data), ENT_QUOTES, 'UTF-8');
    $data = htmlentities($data, ENT_QUOTES, 'UTF-8');
    foreach($attrs as $k => $v) $props[] = $k.'="'.$v.'"';
    return '<highlight'.(!empty($props) ? ' '.join(' ', $props): '').'>'.$data.'</highlight>';
});


/******************************************************
 *                 Composante Incode                  *
 ******************************************************/
register_tag('incode', function($html, $attrs, $data) {
    $data = html_entity_decode(trim($data), ENT_QUOTES, 'UTF-8');
    $data = htmlentities($data, ENT_QUOTES, 'UTF-8');
    return '<span class="inline-code">' . $data . '</span>';
});


/******************************************************
 *                 Composante Children                *
 ******************************************************/
register_tag('children', function($html, $attrs, $data) {
    return print_children($this->file, true);
});


/******************************************************
 *                Composante References               *
 ******************************************************/
register_tag('references', function($html, $attrs, $data) {
    $ref = str_replace(realpath($this->root.'index'), '', pathinfo($this->file, PATHINFO_DIRNAME));
    $ref = strtolower(trim(str_replace('\\', '/', $ref), '/'));
    $str = '';
    foreach(getIndexReferences($ref) as $info) {
        $url = get_relative_path($this->file, pathinfo($info->file, PATHINFO_DIRNAME));
        $thumb = get_relative_path(pathinfo($this->file, PATHINFO_DIRNAME), realpath(pathinfo($this->file, PATHINFO_DIRNAME).'/'.$url.$info->icon));
        $str .= <<<EOD
        <div class="list-grid__item">
            <div class="list-grid__item__icon" style="background-image: url({$thumb});"></div>
            <div class="list-grid__item__description">
                <span class="list-grid__item__title"><a href="{$url}">{$info->title}</a></span>
                <span class="list-grid__item__abstract">{$info->abstract}</span>
            </div>
        </div>
EOD;
    }
    return $str;
});


/******************************************************
 *                   Composante Tune                  *
 ******************************************************/
register_tag('tune', function($html, $attrs, $data) {
    if(empty($attrs['src'])) return errcomp("Missing SRC attribute.", "Tune Component Error");;
    if(!$file = realpath(pathinfo($this->file, PATHINFO_DIRNAME) . S . $attrs['src'])) return errcomp("File not found.", "Tune Component Error");

    try {
        if(!Media::genWaveForm($file)) return errcomp("Invalid audio file.", "Tune Component Error");
    } catch(Exception $e) {
        return errcomp($e->getMessage(), "Tune Component Error");;
    }

    return $html;
});


/******************************************************
 *                   Composante Clip                  *
 ******************************************************/
register_tag('clip', function($html, $attrs, $data) {
    if(empty($attrs['src'])) return errcomp("Missing SRC attribute.", "Clip Component Error");
    if(!$file = realpath(pathinfo($this->file, PATHINFO_DIRNAME) . S . $attrs['src'])) return errcomp("File not found.", "Clip Component Error");
    
    try {
        if(!$data = Media::extractClipInfo($file)) return errcomp("Invalid video file.", "Clip Component Error");
    } catch(Exception $e) {
        return errcomp($e->getMessage(), "Clip Component Error");;
    }

    if(empty($data->media)) return errcomp("Invalid video file.", "Clip Component Error");
    if(empty($data->media->track)) return errcomp("Invalid video file.", "Clip Component Error");
    
    foreach($data->media->track as $track) {
        if($track->{'@type'} == 'Video') {
            $aspect = gcd_reduce($track->Width, $track->Height);
            break;
        }
    }

    if(!isset($aspect)) return errcomp("Invalid video file.", "Clip Component Error");
    $attrs['aspect'] = join('/', $aspect);
    foreach($attrs as $k => $v) $props[] = $k . '="' . htmlentities(html_entity_decode(trim($v), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '"';
    return '<clip' . (!empty($props) ? ' ' . join(' ', $props) : '') . '></clip>';
});


/******************************************************
 *                Composante Boxlink                  *
 ******************************************************/
register_tag('boxlink', function($html, $attrs, $data) {
    if(empty($attrs['href'])) return errcomp("Missing HREF attribute.", "Boxlink Component Error");

    if(is_url($attrs['href'])) {
        if(strpos($attrs['href'], '#') !== false) list($attrs['href'], $anchor) = explode('#', $attrs['href']);

        try {
            if(!$metas = Scraper::get($attrs['href'])) throw new Exception("Can't scrape meta informations.");
            // print_r($metas);
// echo mb_detect_encoding($metas->description, 'UTF-8, ISO-8859-15, ISO-8859-1', true).RN;

// echo mb_check_encoding($metas->description, 'UTF-8').RN;
            if(empty($metas->image)) throw new Exception("No thumbnail image found.");
            $destimg = pathinfo($this->file, PATHINFO_DIRNAME) . '/images/' . 'thumb_' . shorthash($metas->image) . IMG_EXT;
            if(!is_file($destimg)) {
                // print_r($metas);
                
                if(!is_dir(pathinfo($destimg, PATHINFO_DIRNAME)) && !@mkdir(pathinfo($destimg, PATHINFO_DIRNAME)))
                    throw new Exception("Can't create subfolder: " . pathinfo($destimg, PATHINFO_DIRNAME));
                if(!$destimg = Media::downloadImage($metas->image, $destimg, 480, 252))
                    throw new Exception("Can't download thumbnail.");
            }

            $url = $metas->url . (isset($anchor) ? '#' . $anchor : '');
            $title = $metas->title;
            $abstract = $metas->description;
            $label = isset($attrs['label']) ? $attrs['label'] : (empty($metas->label) ? 'Lien' : $metas->label);
            $thumb = get_relative_path($this->file, $destimg);
            $target = '_blank';
            $classes = ' extern';
        } catch(Exception $e) {
            return errcomp($e->getMessage() . (empty($metas) ? '' : '<br><pre>' . print_r($metas, true) . '</pre>'), "Boxlink Component Error");
        }
    } else {
        $path = $attrs['href'];
        if(strpos($path, '#') !== false) list($path, $anchor) = explode('#', $path);
        if(!$file = realpath(pathinfo($this->file, PATHINFO_DIRNAME) . S . $path)) return errcomp("Invalid HREF attribute.", "Boxlink Component Error");
        if(is_dir($file) && !is_file(($file = realpath($file . S . '_index.php')))) return errcomp("Invalid HREF attribute.", "Boxlink Component Error");
        if(!$info = php_file_info($file)) return errcomp("Invalid file header: " . $file . ".", "Boxlink Component Error");
        $url = !empty($info->url) ? $info->url : $path;
        $url = rtrim($url, '/').'/'.(isset($anchor) ? '#'.$anchor : '');
        $thumb = rtrim($path, '/') . '/' . (!empty($info->image) ? $info->image : $info->icon);
        $target = !empty($info->url) ? '_blank' : '_self';
        $title = $info->title;
        $abstract = $info->abstract;
        $label = !empty($info->label) ? $info->label : 'Lien';
        $classes = '';
    }

    $atitle = htmlentities($title, ENT_COMPAT);
    return <<<EOD
        <a class="boxlink" rel="noopener noreferrer" target="{$target}" href="{$url}" title="{$atitle}">
            <div class="boxlink-container{$classes}">
                <div class="boxlink-thumb" style="background-image: url({$thumb})"></div>
                <div class="boxlink-abstract">
                    <em class="boxlink-label">{$label}</em>
                    <span class="boxlink-title">{$title}</span>
                    <span class="boxlink-description">{$abstract}</span>
                </div>
            </div>
        </a>
EOD;
});
