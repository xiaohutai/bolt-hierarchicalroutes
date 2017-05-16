<?php

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
        $collection->match('/example/url', [$this, 'routeExampleUrl']);

        $collection
            ->match("/{parents}/{slug}", [$this, 'record'])
            ->assert('parents', [$this, 'inHierarchy']) // basically, if the {parents} combination exists in some look-up table
            ->assert('slug', '[a-zA-Z0-9_\-]+')
            ->bind('hierarchicalroutes.record')
        ;

        // is this wanted for (top-level) defined structured items? probably
        // how about undefined items? because "event/foo" and "event/bar" still makes sense, right??
        $collection
            ->match("/{slug}", [$this, 'recordParentless'])
            ->assert('slug', '[a-zA-Z0-9_\-]+[^(sitemap)^(search)]')
            ->bind('hierarchicalroutes.record.parentless')
        ;
    }

// distinction between parents + slug and slug

    public function inHierarchy()
    {
        // oh shit, i dont have the current record lol
        return implode('|', $this->slugs);
    }

    public function record($parents, $slug)
    {
        $this->importMenu();

        dump($parents);
        dump($slug);

        dump($this->parents);
        dump($this->children);
        dump($this->slugs);
        dump($this->routes);

        $response = new Response('Hello, Bolt!', Response::HTTP_OK);
        return $response;
    }

    public function recordParentless($slug)
    {
        $this->importMenu();
        dump($slug);

        $response = new Response('Hello, Bolt!', Response::HTTP_OK);
        return $response;
    }

    private $parents  = [];
    private $children = [];
    private $slugs    = [];
    private $routes   = [];


    private function importMenu($identifier = 'main')
    {
        $app = $this->getContainer();
        /** @var \Bolt\Menu\Menu $menu */
        $menu = $app['menu']->menu($identifier);

        foreach ($menu->getItems() as $item) {
            $this->importMenuItem($item);
        }
    }

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
            // dump($content);
            // $content->link();
            $id          = $content->id;
            $slug        = $content->values['slug'];
            $contenttype = $content->contenttype['slug']; // $content->contenttype['singular_slug'];

            $this->parents["$contenttype/$id"] = $parent;
            $this->slugs["$contenttype/$id"]   = $slug;

            if ($parent) {
                $this->children[$parent][] = "$contenttype/$id"; // but also add links and other items ...
                $this->routes["$contenttype/$id"] = $this->slugs[$parent] . '/' . $slug;
            }

            if (isset($item['submenu'])) {
                foreach ($item['submenu'] as $subitem) {
                    $this->importMenuItem($subitem, "$contenttype/$id");
                }
            }
        }
    }

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
