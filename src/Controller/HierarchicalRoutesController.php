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
            ->bind('hierarchicalroutes.record')
        ;

        return $ctr;
    }

    /**
     *
     */
    public function recordExactMatch(Application $app, $slug)
    {
        $content = $app['storage']->getContent(
            array_search($slug, $app['hierarchicalroutes.service']->getRecordRoutes()),
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
            array_search($slug, $app['hierarchicalroutes.service']->getListingRoutes())
        );
    }

    /**
     *
     */
    public function recordPotentialMatch(Application $app, $parents, $slug)
    {
        // todo: re-write this part, as some parts are better off in Service ??
        $parentsKey = array_search($parents, $app['hierarchicalroutes.service']->getRecordRoutes());
        $rules = $app['hierarchicalroutes.service']->getContentTypeRules();
        if (isset($rules[$parentsKey])) {
            foreach ($rules[$parentsKey] as $contenttypeslug) {
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

        $app->abort(Response::HTTP_NOT_FOUND, "Page $parents/$slug not found.");
    }
}
