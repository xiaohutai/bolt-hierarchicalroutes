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
     * HierarchicalUrlGenerator constructor.
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
        $config = $this->app['hierarchicalroutes.config'];
        $enableRouting = $config->get('settings/enable-routing', true);
        $bypassUrlGenerator = $config->get('settings/bypass-url-generator', false);

        if ($enableRouting && $name == 'contentlink') {
            $service = $this->app['hierarchicalroutes.service'];

            /**
             * Since Bolt 3.3. Bolt now strips away all the parameters that are not needed for the
             * generation of routes. So we have `contenttypeslug` and `slug`, and we need to get the
             * `id` separately.
             */
            $singular_slug   = $parameters['contenttypeslug'];
            $slug            = $parameters['slug'];
            $contenttypeslug = $service->singularToPluralContentTypeSlug($singular_slug);
            $recordRoutes    = $service->getRecordRoutes();

            if (isset($recordRoutes["$contenttypeslug/$slug"])) {
                if ($bypassUrlGenerator) {
                    return '/' . $recordRoutes["$contenttypeslug/$slug"];
                }

                $name = 'hierarchicalroutes.record.exact';
                $parameters = [
                    'slug' => $recordRoutes["$contenttypeslug/$slug"],
                ];

                $requirement = $this->app['hierarchicalroutes.controller.requirement'];
                $anyRecordRouteConstraint = $requirement->anyRecordRouteConstraint();
                if (strlen($anyRecordRouteConstraint) > 30000) {
                    $groups = $requirement->anyRecordRouteConstraintSplitted();
                    $index  = 0;
                    foreach ($groups as $group) {
                        $matches = preg_match("($group)", $parameters['slug']);
                        if ($matches === 1) {
                            return $this->wrapped->generate($name . '_' . $index, $parameters, $referenceType);
                        }
                        $index++;
                    }
                }

                return $this->wrapped->generate($name, $parameters, $referenceType);
            }

            // For `contenttype` rules
            $parent = $service->getParentLinkForContentType($contenttypeslug);

            if ($parent) {
                $name = 'hierarchicalroutes.record';
                $parameters = [
                    'parents' => $parent,
                    'slug'    => $slug,
                ];
                return $this->wrapped->generate($name, $parameters, $referenceType);
            }
        }

        return $this->wrapped->generate($name, $parameters, $referenceType);
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
