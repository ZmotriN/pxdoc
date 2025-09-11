<?php

/**
 * Dump element contents for HTML
 *
 * @param  mixed $elm
 * @return void
 */
function __print_r($elm) {
    echo '<pre>'.print_r($elm, true).'</pre>';
}



/**
 * Generate and print the breadcrumb
 *
 * @return void
 */
function print_breadcrumb() {
    global $PAGE;
    $root = realpath($PAGE->root);
    $parent = pathinfo(pathinfo($PAGE->file, PATHINFO_DIRNAME), PATHINFO_DIRNAME);
    while(realpath(pathinfo($PAGE->file, PATHINFO_DIRNAME)) != $root) {
        if(!is_file(($file = $parent.S.'_index.php'))) break;
        if(!$data = php_file_info($file)) break;
        if($root != $parent) $link = str_replace([$root, '\\'], ['', '/'], $parent)."/";
        else {
            $link = get_relative_path($PAGE->file, $PAGE->root);
            if(!empty($data->icon)) $icon = get_relative_path($PAGE->file, realpath($parent.S.$data->icon));
        }
        $page = str_replace([$root, '\\'], ['', '/'], pathinfo($PAGE->file, PATHINFO_DIRNAME));
        $backwards = count(explode('/',str_replace($link, '', $page)));
        $href = join('/', array_fill(0, $backwards, '..')).'/';
        $nodes[] = '<a href="'.$href.'">'.$data->title.'</a>';
        $parent = pathinfo($parent, PATHINFO_DIRNAME);
    }
    if(!empty($nodes)) echo '<div class="breadcrum-logo"' . (!empty($icon) ? ' style="background-image: url(' . $icon . ');"' : '') . '></div>' . join(' > ', array_reverse($nodes)).' >';
}


/**
 * print_breadcrumb_index
 *
 * @return void
 */
function print_breadcrumb_index() {
    global $PAGE;
    if(!$info = php_file_info($PAGE->file)) return;
    if(empty($info->ref)) return;
    $ref = 'index/'.strtolower(trim(str_replace('\\', '/', $info->ref), '/'));
    $index = $PAGE->root;
    foreach(explode('/', $ref) as $part) {
        $index .= $part.'/';
        if(!$data = php_file_info($index.'_index.php')) continue;
        $url = get_relative_path($PAGE->file, $index);
        echo '<a href="' . $url . '">' . $data->title . '</a>&nbsp;>&nbsp;';
    }
}



/**
 * getIndexPath
 *
 * @return void
 */
function getIndexPath() {
    global $PAGE;
    if(!$PAGE->ref) return;
    $index = $PAGE->root.'index/';
    return get_relative_path($PAGE->file, $index);
}


/**
 * lang
 *
 * @param  mixed $idx
 * @return void
 */
function lang($idx) {
    static $index = null;
    global $PAGE;
    if($index === null) {
        if(!$file = realpath($PAGE->root . 'pxdoc/langs/' . $PAGE->lang . '.json')) return false;
        if(!$data = @file_get_contents($file)) return false;
        if(!$index = @json_decode($data)) return false;
    }
    return empty($index->{$idx}) ? false : $index->{$idx};
}


/**
 * Get children pages
 *
 * @param  string $parent (optional) Parent page
 * @return array Array of children page informations
 */
function get_children($parent = null) {
    if(!$parent) $parent = current(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,1))['file'];
    $folder = pathinfo($parent, PATHINFO_DIRNAME).S;
    foreach(glob($folder.'*', GLOB_ONLYDIR) as $dir) {
        if(!is_file(($file = $dir.S.'_index.php'))) continue;
        if(!$data = php_file_info($file)) continue;
        if(empty($data)) continue;
        if($data->type == 'wiki') continue;
        if(empty($data->index)) $data->index = 0;
        $data->href = pathinfo($dir, PATHINFO_BASENAME).'/';
        $children[$data->index][] = $data;
    }
    if(!isset($children)) return [];
    ksort($children);
    while ($idx = array_pop($children))
        foreach($idx as $elm)
            $elms[] = $elm;
    return $elms;
}


/**
 * Print children page grid for list pages
 *
 * @return void
 */
function print_children($parent=null, $return=false) {
    $str = '';
    if(!$parent) $parent = current(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,1))['file'];
    foreach(get_children($parent) as $child) {
        $abstract = empty($child->abstract) ? '' : $child->abstract;
        $icon = $child->href . $child->icon;
        $radius = pathinfo($icon, PATHINFO_EXTENSION) == 'svg' ? '0' : '50%';
        $str .= <<<EOD
                        <div class="list-grid__item">
                            <div class="list-grid__item__icon" style="background-image: url({$icon}); border-radius: {$radius};"></div>
                            <div class="list-grid__item__description">
                                <span class="list-grid__item__title"><a href="{$child->href}">{$child->title}</a></span>
                                <span class="list-grid__item__abstract">{$abstract}</span>
                            </div>
                        </div>

EOD;
    }
    if($return) return $str;
    else echo $str;
}




/**
 * getIndexReferences
 *
 * @return iterable
 */
function getIndexReferences($name=null): iterable {
    static $references = null;
    global $PAGE;
    if($references === null) {
        $references = [];
        foreach(dig($PAGE->root.'_index.php') as $file) {
            if(!$info = php_file_info($file)) continue;
            if(!empty($info->ref)) {
                $ref = strtolower(trim(str_replace('\\', '/', $info->ref), '/\\'));
                $info->file = $file;
                $references[$ref][] = $info;
            }
        }
        foreach($references as $k => $v) {
            usort($v, function($a, $b) { return strnatcmp($a->title, $b->title); });
            $references[$k] = $v;
        }
    }
    if(!$name) return $references;
    elseif(empty($references[$name])) return [];
    else return $references[$name];
}


/**
 * register_page_type
 *
 * @param  mixed $type
 * @param  mixed $info
 * @return void
 */
function register_page_type($type = null, $info = null) {
    static $types = [];
    if(is_null($type)) return $types;
    if(is_null($info)) return isset($types[$type]) ? $types[$type] : false;
    $types[$type] = $info;
    return true;
}




function errcomp($message, $title = null) {
    if(!$title) $title = 'Error';
    $title = html_entity_decode(trim($title), ENT_QUOTES, 'UTF-8');
    $title = htmlentities($title, ENT_QUOTES, 'UTF-8');
    return '<error title="' . $title . '">' . $message . '</error>';
}


