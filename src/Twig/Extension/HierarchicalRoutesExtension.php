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
            new \Twig_SimpleFunction('getParent'   , [HierarchicalRoutesRuntime::class, 'getParent'  ], $safe),
            new \Twig_SimpleFunction('getParents'  , [HierarchicalRoutesRuntime::class, 'getParents' ], $safe),
            new \Twig_SimpleFunction('getChildren' , [HierarchicalRoutesRuntime::class, 'getChildren'], $safe),
            new \Twig_SimpleFunction('getSiblings' , [HierarchicalRoutesRuntime::class, 'getSiblings'], $safe),
        ];
    }
}
