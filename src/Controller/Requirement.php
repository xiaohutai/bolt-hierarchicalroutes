<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Controller;

use Bolt\Extension\TwoKings\HierarchicalRoutes\Service\HierarchicalRoutesService;

/**
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class Requirement
{
    /** @var HierarchicalRoutesService $service */
    private $service;

    /**
     * @param HierarchicalRoutesService $service
     */
    public function __construct(HierarchicalRoutesService $service)
    {
        $this->service = $service;
    }

    /**
     * @return string
     */
    public function anyRecordRouteConstraint()
    {
        return $this->createConstraints($this->service->getRecordRoutes());
    }

    /**
     * @return string
     */
    public function anyListingRouteConstraint()
    {
        return $this->createConstraints($this->service->getListingRoutes());
    }

    /**
     * @return string
     */
    public function anyPotentialParentConstraint()
    {
        return $this->createConstraints(array_keys($this->service->getContenttypeRules()));
    }

    /**
     * @return string
     */
    private function createConstraints($array)
    {
        if (empty($array)) {
            return '$.';
        }

        return implode('|', $array);
    }
}
