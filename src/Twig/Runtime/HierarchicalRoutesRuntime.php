<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Twig\Runtime;

use Silex\Application;

/**
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class HierarchicalRoutesRuntime
{
    /** @var Application $app */
    private $app;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function getParent($record)
    {
        return $this->app['hierarchicalroutes.service']->getParent($record);
    }

    public function getParents($record)
    {
        return $this->app['hierarchicalroutes.service']->getParents($record);
    }

    public function getChildren($record)
    {
        return $this->app['hierarchicalroutes.service']->getChildren($record);
    }

    public function getSiblings($record)
    {
        return $this->app['hierarchicalroutes.service']->getSiblings($record);
    }
}
