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
    if(empty($attrs['src'])) return $html;
    if(!$colors = find_colors()) return $html;
    if(!$file = realpath(pathinfo($this->file, PATHINFO_DIRNAME) . S . $attrs['src'])) return $html;
    $pngdark = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '-' . $colors->main . '-dark.png', $file);
    $pnglight = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '-' . $colors->main . '-light.png', $file);
    if(!is_file($pngdark) || !is_file($pnglight)) {
        if(!$script = realpath(__DIR__ . '/../_bin/scripts/pxtune.php')) return $html;
        shell_exec('php ' . escapeshellarg($script) . ' ' . escapeshellarg($file));
    }
    return $html;
});


/******************************************************
 *                   Composante Clip                  *
 ******************************************************/
register_tag('clip', function($html, $attrs, $data) {

    if(empty($attrs['src'])) return $html;
    if(!$colors = find_colors()) return $html;
    if(!$file = realpath(pathinfo($this->file, PATHINFO_DIRNAME) . S . $attrs['src'])) return $html;
    
    $jsonfile = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '.json', $file);
    $jpegfile = preg_replace('#\.' . pathinfo($file, PATHINFO_EXTENSION) . '$#i', '.jpg', $file);

    if(!is_file($jsonfile) || !is_file($jpegfile)) {
        if(!$script = realpath(__DIR__ . '/../_bin/scripts/pxclip.php')) return $html;
        shell_exec('php ' . escapeshellarg($script) . ' ' . escapeshellarg($file));
    }
    
    if(!is_file($jsonfile)) return $html;
    if(!$data = json_decode(file_get_contents($jsonfile))) return $html;
    if(empty($data->media)) err("Invalid video file.");
    if(empty($data->media->track)) err("Invalid video file.");
    
    foreach($data->media->track as $track) {
        if($track->{'@type'} == 'Video') {
            $aspect = gcd_reduce($track->Width, $track->Height);
            break;
        }
    }

    if(!isset($aspect)) return $html;
    $attrs['aspect'] = join('/', $aspect);
    if(!empty($attrs['title'])) {
        $attrs['title'] = html_entity_decode(trim($attrs['title']), ENT_QUOTES, 'UTF-8');
        $attrs['title'] = htmlentities($attrs['title'], ENT_QUOTES, 'UTF-8');
    }
    foreach($attrs as $k => $v) $props[] = $k.'="'.$v.'"';
    return '<clip'.(!empty($props) ? ' '.join(' ', $props): '').'></clip>';
});