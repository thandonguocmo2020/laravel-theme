<?php namespace Teepluss\Theme;

use Illuminate\Support\Facades\HTML;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class AssetQueue extends AssetContainer {

    /**
     * Create a new asset container instance.
     *
     * @param  string  $name
     * @return void
     */
    public function __construct($name, $path)
    {
        parent::__construct($name, $path);
    }

    /**
     * Call compress asset.
     *
     * @return void
     */
    public function compress()
    {
        // Compress scripts.
        $this->doCompress('script', true);

        // Compress styles.
        $this->doCompress('style', true);
    }

    /**
     * Compress asset by group.
     *
     * @param  string      $group
     * @param  boolean     $force
     * @return AssetQueue
     */
    protected function doCompress($group, $force = true)
    {
        if ( ! isset($this->assets[$group]) or count($this->assets[$group]) == 0) return '';

        $anames = '';

        $contents = '';

        foreach ($this->arrange($this->assets[$group]) as $name => $data)
        {
            $anames .= $data['source'];

            $contents .= $this->content($group, $name);
        }

        // Get hashed name with path location.
        $hashed = $this->hashed($group, $anames);

        // Compress on file not exists or $force is true.
        if ( ! File::exists($hashed) or $force === true)
        {
            $dir = dirname($hashed);

            // Create dir if not exists.
            if ( ! File::isDirectory($dir))
            {
                File::makeDirectory($dir, 0777, true);
            }

            // Write asset content to cache path.
            File::put($hashed, $contents);
        }

        return $this;
    }

    /**
     * Get cache path.
     *
     * @param  string $hashed
     * @return string
     */
    protected function getCachePath($hashed)
    {
        // Get cache path from cofig.
        if (Config::has('theme::compressDir'))
        {
            $path = Config::get('theme::compressDir');
        }
        // If not exists uses "cache".
        else
        {
            $path = 'cache';
        }

        return $path.'/'.$hashed;
    }

    /**
     * Hash name and return with location.
     *
     * @param  string $group
     * @param  string $anames
     * @return string
     */
    protected function hashed($group, $anames)
    {
        $extension = ($group == 'script') ? 'js' : 'css';

        // Hashing name and concat with extension.
        $hashed = md5($anames).'.'.$extension;

        // Finding location from config.
        $location = $this->getCachePath($hashed);

        return app()->make('path.public').'/'.$location;
    }

    /**
     * Get compress asset with HTML tag.
     *
     * @param  string $group
     * @param  string $anames
     * @return string
     */
    protected function getCompressed($group, $anames)
    {
        if ( ! in_array($group, array('script', 'style'))) return '';

        // Hashed with location.
        $hashed = $this->hashed($group, $anames);

        // Forces compile or not?
        $forceCompress = (bool) Config::get('theme::forceCompress');

        // Compress.
        $this->doCompress($group, $forceCompress);

        // Return file path with HTML.
        $file = basename($hashed);
        $location = $this->getCachePath($file);

        return HTML::$group($location);
    }



    /**
     * Get the HTML link to a registered asset.
     *
     * @param  string  $group
     * @param  string  $name
     * @return string
     */
    protected function content($group, $name)
    {
        if ( ! isset($this->assets[$group][$name])) return '';

        $asset = $this->assets[$group][$name];

        // If the bundle source is not a complete URL, we will go ahead and prepend
        // the bundle's asset path to the source provided with the asset. This will
        // ensure that we attach the correct path to the asset.
        if (filter_var($asset['source'], FILTER_VALIDATE_URL) === false)
        {
            $asset['source'] = $this->path($asset['source']);
        }

        $pathinfo = pathinfo($asset['source']);

        // If cannot find an extension in source, we will return as inline source.
        if ( ! isset($pathinfo['extension']))
        {
            return $asset['source'];
        }

        $path = app()->make('path.public').'/'.$asset['source'];

        return File::get($path);
    }

    /**
     * Get all of the registered assets for a given type / group.
     *
     * @param  string  $group
     * @return string
     */
    protected function group($group)
    {
        if ( ! isset($this->assets[$group]) or count($this->assets[$group]) == 0) return '';

        $anames = '';

        $assets = '';

        foreach ($this->arrange($this->assets[$group]) as $name => $data)
        {
            $anames .= $data['source'];

            $assets .= $this->asset($group, $name);
        }

        $compressed = $this->getCompressed($group, $anames);

        return $compressed;
    }

}