<?php

// -----------------------------------------------------------------------------
//
// TODO: USE THE NEW `getContent` FUNCTION INSTEAD:
//
//     $app['storage']->getContent => `\Bolt\Legacy\Content` --> to be deprecated, it seems
//     $app['query']->getContent   => `\Bolt\Storage\Entity\Content`
//
// The `setcontent` function (in Twig) currently returns `\Bolt\Legacy\Content`
//
// -----------------------------------------------------------------------------

namespace Bolt\Extension\TwoKings\HierarchicalRoutes;

use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Controller\Zone;
use Bolt\Events\StorageEvent;
use Bolt\Events\StorageEvents;
use Bolt\Extension\SimpleExtension;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Config\Config;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Controller\ExampleController;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Listener\StorageEventListener;
use Bolt\Menu\MenuEntry;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HierarchicalRoutesExtension class
 *
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class HierarchicalRoutesExtension extends SimpleExtension
{
    /**
     * {@inheritdoc}
     */
    protected function subscribe(EventDispatcherInterface $dispatcher)
    {
        // https://docs.bolt.cm/extensions/essentials#adding-storage-events

        $dispatcher->addListener(StorageEvents::PRE_SAVE, [$this, 'onPreSave']);

        $storageEventListener = new StorageEventListener($this->getContainer(), $this->getConfig());
        $dispatcher->addListener(StorageEvents::POST_SAVE, [$storageEventListener, 'onPostSave']);
        $dispatcher->addListener(StorageEvents::PRE_DELETE, [$storageEventListener, 'onPreDelete']);
        $dispatcher->addListener(StorageEvents::POST_DELETE, [$storageEventListener, 'onPostDelete']);
    }

    /**
     * Handles PRE_SAVE storage event
     *
     * @param StorageEvent $event
     */
    public function onPreSave(StorageEvent $event)
    {
        $contenttype = $event->getContentType();
        $record = $event->getContent();
        $created = $event->isCreate();
        // ...
    }

    /**
     * {@inheritdoc}
     */
    protected function registerAssets()
    {
        return [
            // Web assets that will be loaded in the frontend
            new Stylesheet('extension.css'),
            new JavaScript('extension.js'),
            // Web assets that will be loaded in the backend
            (new Stylesheet('clippy.js/clippy.css'))->setZone(Zone::BACKEND),
            (new JavaScript('clippy.js/clippy.min.js'))->setZone(Zone::BACKEND),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigPaths()
    {
        return ['templates'];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigFunctions()
    {
        return [
            'my_twig_function' => 'myTwigFunction',
        ];
    }

    /**
     * The callback function when {{ my_twig_function() }} is used in a template.
     *
     * @return string
     */
    public function myTwigFunction()
    {
        $context = [
            'something' => mt_rand(),
        ];

        return $this->renderTemplate('extension.twig', $context);
    }

    /**
     * {@inheritdoc}
     *
     * Extending the backend menu:
     *
     * You can provide new Backend sites with their own menu option and template.
     *
     * Here we will add a new route to the system and register the menu option in the backend.
     *
     * You'll find the new menu option under "Extras".
     */
    protected function registerMenuEntries()
    {
        /*
         * Define a menu entry object and register it:
         *   - Route http://example.com/bolt/extend/my-custom-backend-page-route
         *   - Menu label 'MyExtension Admin'
         *   - Menu icon a Font Awesome small child
         *   - Required Bolt permissions 'settings'
         */
        $adminMenuEntry = (new MenuEntry('my-custom-backend-page', 'my-custom-backend-page-route'))
            ->setLabel('MyExtension Admin')
            ->setIcon('fa:child')
            ->setPermission('settings')
        ;

        return [$adminMenuEntry];
    }

    /**
     * {@inheritdoc}
     *
     * Mount the ExampleController class to all routes that match '/example/url/*'
     *
     * To see specific bindings between route and controller method see 'connect()'
     * function in the ExampleController class.
     */
    protected function registerFrontendControllers()
    {
        $app = $this->getContainer();
        $config = $this->getConfig();

        return [
            '/example/url' => new ExampleController($config),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * This first route will be handled in this extension class,
     * then we switch to an extra controller class for the routes.
     */
    protected function registerFrontendRoutes(ControllerCollection $collection)
    {
        // cache-able ??
        $this->importMenu();
        $rules = $this->getConfig()['rules'];
        $this->importRules($rules);

        // dump($this->parents);
        // dump($this->children);
        // dump($this->slugs);
        // dump($this->routes);
        // dump($this->contenttypeRules);

        $collection->match('/example/url', [$this, 'routeExampleUrl']);

        // This is an exact match that is takes precendence over all other
        // front-end routes.

        $collection
            ->match("/{slug}", [$this, 'recordExactMatch'])
            ->assert('slug', $this->anyRecordRouteConstraint())
            ->bind('hierarchicalroutes.record.exact')
        ;

        $collection
            ->match("/{slug}", [$this, 'listingExactMatch'])
            ->assert('slug', $this->anyListingRouteConstraint())
            ->bind('hierarchicalroutes.listing.exact')
        ;

        // Note: Would you want this? There's a potential choice for:
        //   - /foo/bar/{taxonomytype}/{slug}
        //   - /foo/bar/{slug}
        //
        // $collection
        //     ->match("/{slug}", [$this, 'taxonomyExactMatch'])
        //     ->assert('slug', $this->anyTaxonomyRouteConstraint())
        //     ->bind('hierarchicalroutes.listing.exact')
        // ;

        // If we allow dynamic content on any node, even leaf nodes.
        $collection
            ->match("/{parents}/{slug}", [$this, 'recordPotentialMatch'])
            ->assert('parents', $this->anyPotentialParentConstraint())
            ->assert('slug', '[a-zA-Z0-9_\-]+') // this may result in a 404
            ->bind('hierarchicalroutes.record.fuzzy')
        ;

    }

    // -------------------------------------------------------------------------

    // todo: Controller\Requirement

    /**
     * @return string
     */
    public function anyRecordRouteConstraint()
    {
        return $this->createConstraints($this->routes);
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
        return $this->createConstraints($this->contenttypeRules);
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

    // todo: Controller

    /**
     *
     */
    public function recordExactMatch($slug)
    {
        $app = $this->getContainer();

        $content = $app['storage']->getContent(
            array_search($slug, $this->routes),
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
    public function listingExactMatch($slug)
    {
        $app = $this->getContainer();

        return $app['controller.frontend']->listing(
            $app['request'],
            array_search($slug, $this->listingRoutes)
        );
    }

    /**
     *
     */
    // public function taxonomyExactMatch($slug)
    // {
    //     $app = $this->getContainer();
    //     $taxonomytype = '';
    //     return $app['controller.frontend']->taxonomy($app['request'], $taxonomytype, $slug);
    // }

    /**
     *
     */
    public function recordPotentialMatch($parents, $slug)
    {
        $app = $this->getContainer();

        // potential 1: contenttype rule match

        $parentsKey = array_search($parents, $this->routes);
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

    // todo: Service

    // Simple lookup tables
    private $parents  = [];
    private $children = [];
    private $slugs    = []; // identifier => slug

    // Carefully divided collections for different routes
    private $routes           = []; // identifier => slug + parents' slug
    private $listingRoutes    = []; // same as $routes, but for listings
    private $taxonomyRoutes   = []; // same as $routes, but for taxonomies
    private $contenttypeRules = []; // parent => contenttypeslug


    /**
     *
     */
    private function importMenu($identifier = 'main')
    {
        $app = $this->getContainer();
        /** @var \Bolt\Menu\Menu $menu */
        $menu = $app['menu']->menu($identifier);

        foreach ($menu->getItems() as $item) {
            $this->importMenuItem($item);
        }
    }

    /**
     *
     */
    private function importMenuItem($item, $parent = null)
    {
        // so... items can be path -> easy to find a record
        // or a link -> possible to find a record
        // or external links -> ignore
        $app     = $this->getContainer();
        $link    = $item['link'];
        $content = false;

        if (isset($item['path'])) {
            /** @var \Bolt\Legacy\Content $content */
            $content = $app['storage']->getContent($item['path'], ['hydrate' => false]);
        }

        // Only items with records can be in a hierarchy, otherwise it doesn't make much sense

        if ($content && !is_array($content)) {
            $id          = $content->id;
            $slug        = $content->values['slug'];
            $contenttype = $content->contenttype['slug']; // $content->contenttype['singular_slug'];

            $this->parents["$contenttype/$id"] = $parent;
            $this->slugs["$contenttype/$id"]   = $slug;
            $this->routes["$contenttype/$id"]  = $parent ? $this->routes[$parent] . '/' . $slug : $slug;

            if ($parent) {
                $this->children[$parent][] = "$contenttype/$id"; // but also add links and other items ???
            }

            if (isset($item['submenu'])) {
                foreach ($item['submenu'] as $subitem) {
                    $this->importMenuItem($subitem, "$contenttype/$id");
                }
            }
        }
        elseif (is_array($content)) {
            $path = $item['path'];
            $path = trim($path, '/');

            $this->parents[$path] = $parent;
            $this->slugs[$path]   = $path;
            // $this->routes[$path]  = $parent ? $this->routes[$parent] . '/' . $path : $path;
            $this->listingRoutes[$path]  = $parent ? $this->routes[$parent] . '/' . $path : $path;

            if (isset($item['submenu'])) {
                foreach ($item['submenu'] as $subitem) {
                    $this->importMenuItem($subitem, $path);
                }
            }
        }
        else {
            // log errors if not found or if skipped (reason)
        }
    }

    /**
     *
     */
    private function importRules($rules)
    {
        foreach ($rules as $rule) {
            $this->importRule($rule['type'], $rule['params']);
        }
    }

    /**
     *
     */
    private function importRule($type, $params)
    {
        $app = $this->getContainer();
        $content = $app['storage']->getContent($params['parent'], ['hydrate' => false]);

        switch ($type) {
            case 'contenttype':
                if ($content && !is_array($content)) {
                    $contenttypeslug = $content->contenttype['slug'];
                    $id = $content->id;
                    $this->contenttypeRules["$contenttypeslug/$id"][] = $params['slug'];
                } elseif (is_array($content)) {
                    $path = $params['parent'];
                    $path = trim($path, '/');
                    $this->contenttypeRules[$path][] = $params['slug'];
                }
                break;

            case 'query':
                if ($content && !is_array($content)) {
                    $contenttypeslug = $content->contenttype['slug'];
                    $id = $content->id;
                    $parent = "$contenttypeslug/$id";
                } elseif (is_array($content)) {
                    $path = $params['parent'];
                    $path = trim($path, '/');
                    $parent = $path;
                }

                if ($parent) {
                    /** @var \Bolt\Storage\Entity\Content[] $items */
                    $items = $app['query']->getContent($params['query'], $params['parameters']);
                    foreach ($items as $item) {
                        $contenttypeslug = $item->getContenttype()['slug'];
                        $id = $item->getId();
                        $slug = $item->getSlug();

                        $this->parents["$contenttypeslug/$id"] = $parent;
                        $this->children[$parent][]             = "$contenttypeslug/$id";
                        $this->slugs["$contenttypeslug/$id"]   = $slug;

                        if (is_array($content)) {
                            $this->listingRoutes["$contenttypeslug/$id"]  = $this->routes[$parent] . '/' . $slug;
                        } else {
                            $this->routes["$contenttypeslug/$id"]  = $this->routes[$parent] . '/' . $slug;
                        }
                    }
                }
                break;

            default:
                // log an error invalid rule type found
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Handles GET requests on the /example/url route.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function routeExampleUrl(Request $request)
    {
        $response = new Response('Hello, Bolt!', Response::HTTP_OK);

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    protected function registerBackendControllers()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerBackendRoutes(ControllerCollection $collection)
    {
        $collection->match('/extend/my-custom-backend-page-route', [$this, 'exampleBackendPage']);
    }

    /**
     * Handles GET requests on /bolt/my-custom-backend-page and return a template.
     *
     * @param Request $request
     *
     * @return string
     */
    public function exampleBackendPage(Request $request)
    {
        return $this->renderTemplate('custom_backend_site.twig', ['title' => 'My Custom Page']);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerServices(Application $app)
    {
        $app['hierarchicalroutes.config'] = $app->share(function () { return new Config($this->getConfig()); });
    }

    /**
     * {@inheritdoc}
     */
    public function getServiceProviders()
    {
        return [
            $this,
            new Provider\HierarchicalRoutesProvider()
        ];
    }
}
