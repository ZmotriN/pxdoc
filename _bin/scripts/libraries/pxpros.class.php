<?php

final class PXPros
{

    const SEED_FILE = '_pxdoc.json';


    private $root;
    private $file;
    private $page;
    private $config;
    private $vars = [];
    private $tags = [];
    private $hooks = [];
    private $plugins = [];


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
            case 'plugins':
                return $this->plugins;
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
        $this->plugins = [];
        $this->processHook('pre_render', file_get_contents($file));
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
    public static function findSeed($path)
    {
        if (is_file($path)) $path = pathinfo(realpath($path), PATHINFO_DIRNAME);
        elseif (!$path = realpath($path)) return false;
        do {
            $file = $path . S . self::SEED_FILE;
            if (is_file($file)) return realpath($file);
            $path = pathinfo($path, PATHINFO_DIRNAME);
        } while ($path != pathinfo($path, PATHINFO_DIRNAME));
        return false;
    }


    public static function findRoot(string $path, bool $abs = false)
    {
        if(!$seed = self::findSeed($path)) return false;
        if(!$root = pathinfo($seed, PATHINFO_DIRNAME)) return false;
        return $abs ?  $root . S : get_relative_path($path, $root);
    }


    public static function findShared(string $path, bool $abs = false)
    {
        $shared = realpath(__DIR__ . '/../../..');
        return $abs ? $shared . S : get_relative_path($path, $shared);
    }

    public function addPlugin($file) {
        array_push($this->plugins, $file);
    }

}
