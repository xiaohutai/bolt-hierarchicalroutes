<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Twig\Runtime;

use Silex\Application;

/**
 * .
 *
 * @author Gawain Lynch <gawain@twokings.nl>
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class HierarchicalRoutesRuntime
{
    /** @var Application */
    private $app;

    /**
     * Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     *
     */
    public function doSomething()
    {
        return "something";
    }
}
