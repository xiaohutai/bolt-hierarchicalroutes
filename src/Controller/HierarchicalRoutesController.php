<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Controller;

use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller
 *
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class HierarchicalRoutesController implements ControllerProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function __construct() { }

    /**
     * {@inheritdoc}
     *
     * @param Application $app
     *
     * @return ControllerCollection A ControllerCollection instance
     */
    public function connect(Application $app)
    {
        /** @var $ctr \Silex\ControllerCollection */
        $ctr = $app['controllers_factory'];

        $requirement = $app['hierarchicalroutes.controller.requirement'];

        $ctr
            ->match("/{slug}", [$this, 'recordExactMatch'])
            ->assert('slug', $requirement->anyRecordRouteConstraint())
            ->bind('hierarchicalroutes.record.exact')
        ;

        $ctr
            ->match("/{slug}", [$this, 'listingExactMatch'])
            ->assert('slug', $requirement->anyListingRouteConstraint())
            ->bind('hierarchicalroutes.listing.exact')
        ;

        // If we allow dynamic content on any node, even leaf nodes.
        $ctr
            ->match("/{parents}/{slug}", [$this, 'recordPotentialMatch'])
            ->assert('parents', $requirement->anyPotentialParentConstraint())
            ->assert('slug', '[a-zA-Z0-9_\-]+') // this may result in a 404
            ->bind('hierarchicalroutes.record.fuzzy')
        ;

        return $ctr;
    }

    /**
     *
     */
    public function recordExactMatch(Application $app, $slug)
    {
        $content = $app['storage']->getContent(
            array_search($slug, $this->recordRoutes),
            ['hydrate' => false]
        );

        return $app['controller.frontend']->record(
            $app['request'],
            $content->contenttype['slug'],
            $content->values['slug']
        );
    }

    /**
     *
     */
    public function listingExactMatch(Application $app, $slug)
    {
        return $app['controller.frontend']->listing(
            $app['request'],
            array_search($slug, $this->listingRoutes)
        );
    }

    /**
     *
     */
    public function recordPotentialMatch(Application $app, $parents, $slug)
    {
        $app = $this->getContainer();

        // potential 1: contenttype rule match

        $parentsKey = array_search($parents, $this->recordRoutes);
        if (isset($this->contenttypeRules[$parentsKey])) {

            foreach ($this->contenttypeRules[$parentsKey] as $contenttypeslug) {
                $content = $app['storage']->getContent("$contenttypeslug/$slug", ['hydrate' => false]);
                if ($content) {
                    return $app['controller.frontend']->record(
                        $app['request'],
                        $content->contenttype['slug'],
                        $content->values['slug']
                    );
                }
            }
        }

        $this->abort(Response::HTTP_NOT_FOUND, "Page $parents/$slug not found.");
    }
}
