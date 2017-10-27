<?php

use MatthiasMullie\Minify;

class PHPackage
{
    private $css   = [];
    private $js    = [];
    private $fonts = [];
    private $node_modules;
    private $exclude = ['index.js'];

    /**
     * Bundle & minify javascript & stylesheets + copy fonts.
     * Reads from Yarn / NPM package.json -> dependencies
     *
     * @param String $package      Location of package.json
     * @param String $node_modules Location of node_modules directory
     * @param Array  $custom_css   Array of extra stylesheets and directories (/Users/username/css/*)
     * @param Array  $custom_js    Array of extra javascript files and directories (/Users/username/js/*)
     * @param Array  $exclude      Array of excluded filenames (css & js)
     */
    function __construct($package, $node_modules, $custom_css = [], $custom_js = [], $exclude = [])
    {
        // save node modules location
        $this->node_modules = $node_modules;

        // merge user exclude
        $this->exclude = array_merge($this->exclude, $exclude);

        // yarn available
        $this->package($package);

        // reverse package so dependency tree matches
        $this->css = array_reverse($this->css);
        $this->js  = array_reverse($this->js);

        // prioritize jquery
        // within found values of package.json
        // temporary fix as bootstrap 3.x doesn't have
        // jquery as a dependency in it's package.json
        // bootstrap 4.x works (peerDependencies)
        $endwith = '/jquery.min.js';
        foreach ($this->js as $index => $value)
            if (substr($value, (0 - strlen($endwith))) === $endwith)
                array_unshift($this->js, $value);

        // custom css
        foreach ($custom_css as $css) {
            if (strpos($css, '/*') !== false) {
                foreach (glob($css) as $c)
                    $this->css[] = $c;
            } elseif (file_exists($css)) {
                $this->css[] = $css;
            }
        }

        // custom js
        foreach ($custom_js as $js) {
            if (strpos($js, '/*') !== false) {
                foreach (glob($js) as $j)
                    $this->js[] = $j;
            } elseif (file_exists($js)) {
                $this->js[] = $js;
            }
        }

        // realpath
        foreach ($this->css as $key => $value)
            $this->css[$key] = realpath($value);

        foreach ($this->js as $key => $value)
            $this->js[$key] = realpath($value);

        foreach ($this->fonts as $key => $value)
            $this->fonts[$key] = realpath($value);

        // unique
        $this->css   = array_values(array_unique($this->css));
        $this->js    = array_values(array_unique($this->js));
        $this->fonts = array_values(array_unique($this->fonts));
    }

    private function package($package)
    {
        if (file_exists($package) && is_dir($this->node_modules)) {
            $deps = file_get_contents($package);
            $deps = json_decode($deps, true);

            $deps = $deps['dependencies'] ?? false;
            $this->analyzer($deps);

            $deps = $deps['peerDependencies'] ?? false;
            $this->analyzer($deps);
        }
    }

    private function analyzer($dependencies)
    {
        if (!$dependencies)
            return;

        foreach ($dependencies as $plugin => $version) {
            $plugin  = realpath($this->node_modules . '/' . $plugin);
            $package = $plugin . '/package.json';

            // find plugin dependencies
            $this->package($package);

            // search in dist/umd folder (popper.js)
            $found = $this->analyzeFolder($plugin . '/dist/umd');

            // search in dist/ folder
            if (!$found)
                $found = $this->analyzeFolder($plugin . '/dist');

            // search in lib/ folder
            if (!$found)
                $found = $this->analyzeFolder($plugin . '/lib');

            // search in plugin folder
            if (!$found)
                $this->analyzeFolder($plugin, true);
        }
    }

    private function analyzeFolder($path, $reverse = false)
    {
        $found = 0;

        if (is_dir($path)) {
            // search root path first
            if ($reverse && ($found = $this->search($path . '/')))
                return $found;

            // search folders
            $css   = $path . '/css/';
            $js    = $path . '/js/';
            $fonts = $path . '/fonts/';

            // search in css folder
            if (is_dir($css)) {
                $found = $this->search($css);

                // add everything in fonts folder
                // fonts only when css?
                if ($found && is_dir($fonts))
                    foreach (glob($fonts . '*') as $f)
                        $this->fonts[] = $f;
            }

            // search in js folder
            !is_dir($js) || $found = $this->search($js);

            // nothing found -> search in $path
            if (!$reverse && !$found)
                $found = $this->search($path . '/');
        }

        return $found;
    }

    private function search($path)
    {
        $found = 0;
        $path .= '*';

        if (($candidate = $this->candidate($path, '.min.css'))
            || ($candidate = $this->candidate($path, '.css')))
        {
            $this->css[] = $candidate;
            $found++;
        }

        if (($candidate = $this->candidate($path, '.min.js'))
            || ($candidate = $this->candidate($path, '.js')))
        {
            $this->js[] = $candidate;
            $found++;
        }

        return $found;
    }

    private function candidate($dir, $ext)
    {
        $candidate = false;
        $offset    = 0 - strlen($ext);

        // keep smallest filename
        foreach (glob($dir) as $a)
            if (substr($a, $offset) === $ext)
                if (!$candidate || (strlen($a) < strlen($candidate)))
                    if (!in_array(basename($a), $this->exclude)) // skip excluded filename
                        $candidate = $a;

        return $candidate;
    }

    // -

    public function css($absolute, $relative)
    {
        $path = $absolute . '/' . $relative;
        $this->bundle(new Minify\CSS, $this->css, $path);

        return $relative;
    }

    public function js($absolute, $relative)
    {
        $path = $absolute . '/' . $relative;
        $this->bundle(new Minify\JS, $this->js, $path);

        return $relative;
    }

    public function fonts($dir)
    {
        is_dir($dir) || mkdir($dir);

        foreach ($this->fonts as $font)
            copy($font, $dir . '/' . basename($font));
    }

    // -

    private function bundle($minifier, $bundle, $path)
    {
        foreach ($bundle as $src)
            $minifier->add(file_get_contents($src));

        $dir = dirname($path);
        is_dir($dir) || mkdir($dir);
        file_put_contents($dir . '/.bundle.json', json_encode($bundle, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));

        touch($path);
        $minifier->minify($path);
    }
}
