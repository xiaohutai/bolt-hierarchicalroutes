<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Config;

use Symfony\Component\HttpFoundation\ParameterBag;

/**
 *
 *
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
}
