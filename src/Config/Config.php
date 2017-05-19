<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Config;

use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class Config extends ParameterBag
{
    /**
     * {@inheritdoc}
     */
    public function __construct($parameters = [])
    {
        parent::__construct($parameters);
    }

    /**
     * This function is aken from \Bolt\Config class:
     * @link https://github.com/bolt/bolt/blob/release/3.2/src/Config.php#L202-L239
     *
     * Get a config value, using a path. So the third parameter $deep is ignored.
     *
     * For example:
     * $var = $config->get('general/wysiwyg/ck/contentsCss');
     *
     * @param string               $path
     * @param string|array|boolean $default
     * @param bool                 $deep
     *
     * @return mixed
     */
    public function get($path, $default = null, $deep = false)
    {
        $path = explode('/', $path);

        // Only do something if we get at least one key.
        if (empty($path[0]) || !isset($this->parameters[$path[0]])) {
            return false;
        }

        $part = & $this->parameters;
        $value = null;

        foreach ($path as $key) {
            if (!isset($part[$key])) {
                $value = null;
                break;
            }

            $value = $part[$key];
            $part = & $part[$key];
        }
        if ($value !== null) {
            return $value;
        }

        return $default;
    }
}
