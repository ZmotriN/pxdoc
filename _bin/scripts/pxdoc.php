<?php
/**
 * MIT License
 * 
 * Copyright (c) 2025 Maxime Larrivée-Roy
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author     Maxime Larrivée-Roy <zmotrin@gmail.com>
 * @copyright  2025 Maxime Larrivée-Roy
 * @license    https://github.com/ZmotriN/pxpros/raw/refs/heads/main/LICENSE
 * @link       https://github.com/ZmotriN/pxpros
 */


require_once(__DIR__ . '/utils.php');

/**
 * Declare constants & globals
 */
$PAGE = null;

date_default_timezone_set('America/Toronto');

/**
 * PXPros Class
 */
final class PXPros
{

    private $root;
    private $file;
    private $page;
    private $config;
    private $vars = [];
    private $tags = [];
    private $hooks = [];


    /**
     * __construct
     *
     * @param  mixed $prjfile Project configuration file (_pxprox.json)
     * @return void
     */
    public function __construct($prjfile)
    {
        if (!is_file($prjfile)) return false; //throw error
        if (!$json = file_get_contents($prjfile)) return false; //throw error
        if (!$this->config = json_decode($json)) return false; //throw error
        $this->root = pathinfo($prjfile, PATHINFO_DIRNAME) . S;
        $GLOBALS['PAGE'] = $this;
        $this->includes();
    }


    /**
     * Project and page data getter
     *
     * @param  mixed $name Variable name
     * @return void
     */
    public function __get($name)
    {
        switch ($name) {
            case 'root':
                return $this->root;
            case 'file':
                return $this->file;
            default:
                if (!empty($this->vars[$name])) return $this->vars[$name];
                elseif (!empty($this->page->{$name})) return $this->page->{$name};
                elseif (!empty($this->config->{$name})) return $this->config->{$name};
                elseif (!empty($this->config->data->{$name})) return $this->config->data->{$name};
        }
    }


    /**
     * Project and page data setter
     *
     * @param  mixed $name
     * @param  mixed $val
     * @return void
     */
    public function __set($name, $val)
    {
        $this->vars[$name] = $val;
    }


    /**
     * Includes base .php files
     *
     * @return void
     */
    private function includes()
    {
        if (!empty($this->config->includes)) foreach ($this->config->includes as $path) {
            if (!is_file(realpath($this->root . $path))) continue;
            else include_once(realpath($this->root . $path));
        }
    }


    /**
     * Render a page
     *
     * @param  mixed $file File to render
     * @return void
     */
    public function render($file)
    {
        global $PAGE;
        $PAGE = $this;
        $dir = pathinfo($file, PATHINFO_DIRNAME) . S;
        $this->page = php_file_info($file);
        $target = $dir . ltrim(pathinfo($file, PATHINFO_FILENAME), '_') . '.html';
        $this->file = realpath($file);
        ob_start();
        if ($this->before) include(realpath($this->root . $this->before));
        include($file);
        if ($this->after) include(realpath($this->root . $this->after));
        $contents = ob_get_clean();
        $contents = $this->processTags($contents);
        $contents = $this->processHook('post_render', $contents);
        file_put_contents($target, $contents);
    }



    /**
     * registerTag
     *
     * @param  mixed $tag
     * @param  mixed $clb
     * @return void
     */
    public function registerTag($tag, $clb)
    {
        $this->tags[$tag] = $clb;
    }


    /**
     * processTags
     *
     * @return void
     */
    public function processTags($contents)
    {
        foreach ($this->tags as $tag => $clb) {
            $contents = replace_tags($tag, $contents, $clb);
        }
        return $contents;
    }


    /**
     * registerHook
     *
     * @param  string $hook Name of the hook
     * @param  callable $clb The callback
     * @return void
     */
    public function registerHook($hook, $clb)
    {
        $this->hooks[$hook][] = $clb;
    }


    /**
     * processHook
     *
     * @param  string $hook Name of the hook
     * @param  mixed $data The data to be returned by the callback
     * @return mixed
     */
    public function processHook($hook, $data = null)
    {
        if (!empty($this->hooks[$hook])) {
            foreach ($this->hooks[$hook] as $clb) {
                $data = call_user_func($clb, $data);
            }
        }
        return $data;
    }


    /**
     * Find the currect project configuration file
     *
     * @param  mixed $path Current path
     * @return mixed Returns the project configuration file if exists, otherwise false.
     */
    public static function findRoot($path)
    {
        if (is_file($path)) $path = pathinfo(realpath($path), PATHINFO_DIRNAME);
        elseif (!$path = realpath($path)) return false;
        do {
            $file = $path . S . '_pxpros.json';
            if (is_file($file)) return $file;
            $path = pathinfo($path, PATHINFO_DIRNAME);
        } while ($path != pathinfo($path, PATHINFO_DIRNAME));
        return false;
    }
}


/**
 * register_tag
 *
 * @param  mixed $tag
 * @param  mixed $clb
 * @return void
 */
function register_tag($tag, $clb) {
    global $PAGE;
    return $PAGE->registerTag($tag, $clb);
}


/**
 * register_hook
 *
 * @param  mixed $name
 * @param  mixed $clb
 * @return void
 */
function register_hook($name, $clb) {
    global $PAGE;
    return $PAGE->registerHook($name, $clb);
}


/**
 * Parse arguments and render the specified templates
 */
if (!isset($argv[1])) err("Invalid argument.");
if (!$target = realpath($argv[1])) err("Invalid target.");

if (is_dir($target)) {
    if (!$root = PXPros::findRoot($target)) err("No project configuration found.");
    $prj = new PXPros($root);
    foreach (dig($target . '/*.php') as $file) {
        $parent = pathinfo(pathinfo($file, PATHINFO_DIRNAME), PATHINFO_BASENAME);
        if (strpos($parent, '_') === 0) continue;
        if (strpos(pathinfo($file, PATHINFO_FILENAME), '_') !== 0) continue;
        echo 'Render: ';
        echo str_replace([pathinfo($root, PATHINFO_DIRNAME), S, '\\'], ['', '/', '/'], $file) . RN;
        $prj->render($file);
    }
} elseif (preg_match('#^_(.*)\.php$#i', pathinfo($target, PATHINFO_BASENAME), $m)) {
    if (!$root = PXPros::findRoot($target)) err("No project configuration found.");
    $pxpros = new PXPros($root);
    echo 'Render: ';
    echo str_replace([pathinfo($root, PATHINFO_DIRNAME), S, '\\'], ['', '/', '/'], $target) . RN;
    $pxpros->render($target);
} else {
    err("Invalid target.");
}

exit(0);