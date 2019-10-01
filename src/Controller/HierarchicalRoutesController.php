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

        // A site with many pages or deeply nested pages (usually a combination of both)
        // results in an error. In this case split into multiple routes. The limit is set
        // around 32767 and requires a re-compile of PHP in order to change this limit.
        // Downside of this method is to that URL generation also becomes more complicated.
        $anyRecordRouteConstraint = $requirement->anyRecordRouteConstraint();
        if (strlen($anyRecordRouteConstraint) > 30000) {
            $index  = 0;
            $groups = $requirement->anyRecordRouteConstraintSplitted();
            foreach ($groups as $group) {
                $ctr
                    ->match("/{slug}", [$this, 'recordExactMatch'])
                    ->assert('slug', $group)
                    ->before('controller.frontend:before')
                    ->after('controller.frontend:after')
                    ->bind('hierarchicalroutes.record.exact_' . $index)
                ;
                $index++;
            }
        } else {
            $ctr
                ->match("/{slug}", [$this, 'recordExactMatch'])
                ->assert('slug', $anyRecordRouteConstraint)
                ->before('controller.frontend:before')
                ->after('controller.frontend:after')
                ->bind('hierarchicalroutes.record.exact')
            ;
        }

        $ctr
            ->match("/{slug}", [$this, 'listingExactMatch'])
            ->assert('slug', $requirement->anyListingRouteConstraint())
            ->before('controller.frontend:before')
            ->after('controller.frontend:after')
            ->bind('hierarchicalroutes.listing.exact')
        ;

        // If we allow dynamic content on any node, even leaf nodes.
        $ctr
            ->match("/{parents}/{slug}", [$this, 'recordPotentialMatch'])
            ->assert('parents', $requirement->anyPotentialParentConstraint())
            ->assert('slug', '[a-zA-Z0-9_\-]+') // this may result in a 404
            ->before('controller.frontend:before')
            ->after('controller.frontend:after')
            ->bind('hierarchicalroutes.record')
        ;

        return $ctr;
    }

    /**
     *
     */
    public function recordExactMatch(Application $app, $slug)
    {
        $key = array_search($slug, $app['hierarchicalroutes.service']->getRecordRoutes());

        /** @var \Bolt\Legacy\Content $content */
        $content = $app['storage']->getContent(
            $key,
            ['hydrate' => false]
        );

        if ($content) {
            return $app['controller.frontend']->record(
                $app['request'],
                $content->contenttype['slug'],
                $content->values['slug']
            );
        }
        else {
            return $app->abort(Response::HTTP_NOT_FOUND, "Page $slug not found.");
        }
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
