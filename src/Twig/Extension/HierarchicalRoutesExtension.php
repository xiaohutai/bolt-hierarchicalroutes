<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Twig\Extension;

use Bolt\Extension\TwoKings\HierarchicalRoutes\Twig\Runtime\HierarchicalRoutesRuntime;
use Twig_Environment as Environment;
use Twig_Extension as Extension;
use Twig_Markup as Markup;

/**
 * .
 *
 * @author Gawain Lynch <gawain@twokings.nl>
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class HierarchicalRoutesExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        $safe = ['is_safe' => ['html', 'is_safe_callback' => true]];
        $env  = ['needs_environment' => true];

        return [
            new \Twig_SimpleFunction('something', [HierarchicalRoutesRuntime::class, 'doSomething'], $safe),
        ];
    }
}
