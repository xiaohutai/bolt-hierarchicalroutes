<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Routing;

use Silex\Application;
use Symfony\Component\Routing\Generator\ConfigurableRequirementsInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Wraps a UrlGenerator to override URL generation for records.
 *
 * Inspired from: https://github.com/AnimalDesign/bolt-translate/blob/master/src/Routing/LocalizedUrlGenerator.php
 *
 * @author Peter Verraedt <peter@verraedt.be>
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class HierarchicalUrlGenerator implements UrlGeneratorInterface, ConfigurableRequirementsInterface
{
    /** @var UrlGeneratorInterface */
    protected $wrapped;

    /**
     * UrlGeneratorFragmentWrapper constructor.
     *
     * @param UrlGeneratorInterface $wrapped
     */
    public function __construct(UrlGeneratorInterface $wrapped, Application $app)
    {
        $this->wrapped = $wrapped;
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     *
     * Makes sure the _locale parameter is always set.
     */
    public function generate($name, $parameters = [], $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        if ($name == 'contentlink') {
            $singular_slug   = $parameters['contenttypeslug'];
            $contenttypeslug = $this->singularToPluralContentTypeSlug($singular_slug);
            $id              = isset($parameters['id'])? $parameters['id'] : null;
            $slug            = isset($parameters['slug'])? $parameters['slug'] : null;

            $recordRoutes = $this->app['hierarchicalroutes.service']->getRecordRoutes();
            if (isset($recordRoutes["$contenttypeslug/$id"])) {
                return '/' . $recordRoutes["$contenttypeslug/$id"];
            }

            // For `contenttype` rules
            $parent = $this->app['hierarchicalroutes.service']->getParentLinkForContentType($contenttypeslug);

            if ($parent) {
                return "/$parent/$slug";
            }
        }

        return $this->wrapped->generate($name, $parameters, $referenceType);
    }

    /**
     * @param string $singular_slug
     */
    private function singularToPluralContentTypeSlug($singular_slug)
    {
        $contenttypes = $this->app['config']->get('contenttypes');
        foreach ($contenttypes as $key => $type) {
            if ($type['singular_slug'] === $singular_slug) {
                return isset($type['slug']) ? $type['slug'] : $key;
            }
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function setContext(RequestContext $context)
    {
        $this->wrapped->setContext($context);
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->wrapped->getContext();
    }

    /**
     * {@inheritdoc}
     */
    public function setStrictRequirements($enabled)
    {
        if ($this->wrapped instanceof ConfigurableRequirementsInterface) {
            $this->wrapped->setStrictRequirements($enabled);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isStrictRequirements()
    {
        if ($this->wrapped instanceof ConfigurableRequirementsInterface) {
            return $this->wrapped->isStrictRequirements();
        }

        return null; // requirements check is deactivated completely
    }
}
