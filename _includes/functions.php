<?php
const BR = '<br>';


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
 * Get relative shared path
 *
 * @param  string $file The current project file
 * @return string The relative shared path
 */
function get_shared($file){
    global $PAGE;
    $root = str_replace('\\', '/', realpath($PAGE->root));
    $target = str_replace('\\', '/', pathinfo($file,PATHINFO_DIRNAME));
    if(realpath($root) == realpath(pathinfo($file, PATHINFO_DIRNAME)))  return 'pxdoc/';
    $backwards = count(explode('/', ltrim(str_replace($root, '', $target), '/')));
    return join('/', array_fill(0, $backwards, '..')).'/pxdoc/';
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
 * getProjectRoot
 *
 * @return void
 */
function getProjectRoot() {
    global $PAGE;
    $path = get_relative_path($PAGE->file, $PAGE->root);
    if(!$path) $path = './';
    return $path;
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
        $icon = $child->href . $child->icon;
        $str .= <<<EOD
                        <div class="list-grid__item">
                            <div class="list-grid__item__icon" style="background-image: url({$icon});"></div>
                            <div class="list-grid__item__description">
                                <span class="list-grid__item__title"><a href="{$child->href}">{$child->title}</a></span>
                                <span class="list-grid__item__abstract">{$child->abstract}</span>
                            </div>
                        </div>

EOD;
    }
    if($return) return $str;
    else echo $str;
}


/**
 * Print internal link like listing
 *
 * @param  mixed $path Internal relative path
 * @return void
 */
function intlink($path=null){
    if(!$path) return;
    if(strpos($path, '#') !== false) list($path, $anchor) = explode('#', $path);
    $path = rtrim($path,'/\\');
    $parent = pathinfo(current(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,1))['file'], PATHINFO_DIRNAME);
    if(!$target = realpath($parent.'/'.$path)) return;
    if(!$info = php_file_info($target.'/_index.php')) return;
        ?>
            <div class="intlink__item">
                <div class="intlink__item__icon" style="background-image: url('<?php echo $path . '/' . $info->icon; ?>');"></div>
                <div class="intlink__item__description">
                    <span class="intlink__item__title"><a href="<?php echo  !empty($info->url) ? $info->url : $path; ?>/<?php echo (!empty($anchor) ? '#'.$anchor : ''); ?>"><?php echo $info->title; ?></a></span>
                    <span class="intlink__item__abstract"><?php echo $info->abstract; ?></span>
                </div>
            </div>
        <?php
    return true;
}


/**
 * getIndexReferences
 *
 * @return void
 */
function getIndexReferences($name=null) {
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
 * get_relative_path
 *
 * @param  mixed $from
 * @param  mixed $to
 * @return void
 */
function get_relative_path($from, $to) {
    $from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
    $to   = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
    $from = str_replace('\\', '/', $from);
    $to   = str_replace('\\', '/', $to);
    $from     = explode('/', $from);
    $to       = explode('/', $to);
    $relPath  = $to;

    foreach($from as $depth => $dir) {
        if($dir === $to[$depth]) {
            array_shift($relPath);
        } else {
            $remaining = count($from) - $depth;
            if($remaining > 1) {
                $padLength = (count($relPath) + $remaining - 1) * -1;
                $relPath = array_pad($relPath, $padLength, '..');
                break;
            } else {
                $relPath[0] = './' . $relPath[0];
            }
        }
    }
    return implode('/', $relPath);
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



// temporarairement là
function find_colors()
{
    static $colors = null;

    if($colors === null) {
        if(!$root = find_root(__FILE__)) return false;
        if(!$config = json_decode(file_get_contents($root))) return false;
        if(!$cssfiles[0] = realpath(pathinfo($root, PATHINFO_DIRNAME) . '/pxdoc/styles/styles.min.css')) return false;

        if(!empty($config->styles)) {
            if(is_string($config->styles)) $config->styles = [$config->styles];
            foreach($config->styles as $cssfile) {
                if(!$cssfile = realpath(pathinfo($root, PATHINFO_DIRNAME) . S . $cssfile)) continue;
                else $cssfiles[] = $cssfile;
            }
        }

        $colors = new stdClass;
        foreach($cssfiles as $cssfile) {
            if(!$css = @file_get_contents($cssfile)) continue;
            if(preg_match("/--main-color:[^#]*#([0-9a-f]{6})/i", $css, $m)) $colors->main = strtolower($m[1]) . 'ff';
            if(preg_match("/--second-color:[^#]*#([0-9a-f]{6})/i", $css, $m)) $colors->second = strtolower($m[1]) . 'ff';
            if(preg_match("/--color-black:[^#]*#([0-9a-f]{6})/i", $css, $m)) $colors->black = strtolower($m[1]) . 'ff';
            if(preg_match("/--color-white:[^#]*#([0-9a-f]{6})/i", $css, $m)) $colors->white = strtolower($m[1]) . 'ff';
        }
    }

    return $colors;
}


function find_root($path)
{
    if (is_file($path)) $path = pathinfo(realpath($path), PATHINFO_DIRNAME);
    elseif (!$path = realpath($path)) return false;
    do {
        $file = $path . S . '_pxpros.json';
        if (is_file($file)) return realpath($file);
        $path = pathinfo($path, PATHINFO_DIRNAME);
    } while ($path != pathinfo($path, PATHINFO_DIRNAME));
    return false;
}




function gcd($a, $b=0) {
	return is_array($a) ? array_reduce($a, 'gcd') : ($b ? gcd($b, $a % $b) : $a);
}


function gcd_reduce($a,$b){
	$f = gcd($a,$b);
	return [$a/$f,$b/$f];
}