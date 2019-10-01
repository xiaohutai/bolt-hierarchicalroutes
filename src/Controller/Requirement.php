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

    private $recordRoutes     = [];
    private $listingRoutes    = [];
    private $potentialParents = [];

    private $chunkSize = 100;

    /**
     * @param HierarchicalRoutesService $service
     */
    public function __construct(HierarchicalRoutesService $service)
    {
        $this->service = $service;

        // This call makes sure we got something to work with, when controllers
        // are connected.
        $this->recordRoutes     = $this->service->getRecordRoutes();
        $this->listingRoutes    = $this->service->getListingRoutes();
        $this->potentialParents = $this->service->getPotentialParents();
    }

    /**
     * @return array
     */
    public function anyRecordRouteConstraintSplitted()
    {
        $result = [];
        $chunks = array_chunk($this->recordRoutes, $this->chunkSize);

        foreach ($chunks as $chunk) {
            $result[] = $this->createConstraints($chunk);
        }

        return $result;
    }

    /**
     * @return string
     */
    public function anyRecordRouteConstraint()
    {
        return $this->createConstraints($this->recordRoutes);
    }

    /**
     * @return string
     */
    public function anyListingRouteConstraint()
    {
        return $this->createConstraints($this->listingRoutes);
    }

    /**
     * @return string
     */
    public function anyPotentialParentConstraint()
    {
        return $this->createConstraints($this->potentialParents);
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
