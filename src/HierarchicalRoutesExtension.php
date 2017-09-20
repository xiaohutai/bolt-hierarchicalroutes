<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes;

use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Controller\Zone;
use Bolt\Events\StorageEvent;
use Bolt\Events\StorageEvents;
use Bolt\Extension\SimpleExtension;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Config\Config;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Controller\HierarchicalRoutesController;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Listener\KernelEventListener;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Listener\StorageEventListener;
use Bolt\Menu\MenuEntry;
use Bolt\Version as BoltVersion;
use Pimple as Container;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * HierarchicalRoutesExtension class
 *
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class HierarchicalRoutesExtension extends SimpleExtension
{
    /** @var string $permission The permission a user needs for interaction with  the back-end */
    private $permission = 'extensions';

    /**
     * {@inheritdoc}
     */
    protected function subscribe(EventDispatcherInterface $dispatcher)
    {
        // https://docs.bolt.cm/extensions/essentials#adding-storage-events
        $storageEventListener = new StorageEventListener($this->getContainer());
        $dispatcher->addListener(StorageEvents::POST_SAVE, [$storageEventListener, 'onPostSave']);
        $dispatcher->addListener(StorageEvents::POST_DELETE, [$storageEventListener, 'onPostDelete']);

        $dispatcher->addSubscriber(new KernelEventListener($this->getContainer()['hierarchicalroutes.service']));
    }

    /**
     * {@inheritdoc}
     */
    protected function registerFrontendControllers()
    {
        return [
            '/' => new HierarchicalRoutesController(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerBackendRoutes(ControllerCollection $collection)
    {
        $prefix = '/extensions/';
        if (version_compare(BoltVersion::forComposer(), '3.3.0', '<')) {
            $prefix = '/extend/';
        }

        $collection->match($prefix . 'hierarchical-routes', [$this, 'tree'])
            ->bind('hierarchicalroutes.tree');
    }

    /**
     * {@inheritdoc}
     */
    protected function registerMenuEntries()
    {
        $menuEntry = (new MenuEntry('my-custom-backend-page', 'hierarchical-routes'))
            ->setLabel('Hierarchial Routes')
            ->setIcon('fa:sitemap')
            ->setPermission($this->permission)
        ;

        return [$menuEntry];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigPaths()
    {
        return [
            'templates' => [
                'position' => 'prepend',
                'namespace' => 'bolt'
            ]
        ];
    }

    /**
     *
     */
    public function tree(Request $request)
    {
        $app = $this->getContainer();

        if (!$app['users']->isAllowed($this->permission)) {
            throw new AccessDeniedException('Logged in user does not have the correct rights to use this route.');
        }

        if ($request->query->get('rebuild', false)) {
            $app['hierarchicalroutes.service']->rebuild();

            // FlashLoggerInterface
            // todo: Translatable string in general

            $app['logger.flash']->success('Ok!');

            return $app->redirect(
                $app['url_generator']->generate('hierarchicalroutes.tree')
            );
        }

        $assets = [
            (new Stylesheet('extension.css')),
            (new JavaScript('extension.js')),
        ];

        foreach ($assets as $asset) {
            $asset->setZone(Zone::BACKEND);

            $file = $this->getWebDirectory()->getFile($asset->getPath());
            $asset->setPackageName('extensions')->setPath($file->getPath());
            $app['asset.queue.file']->add($asset);
        }

        $data = [
            'title' => "Hierarchical Routes Tree",
            'tree'  => $app['hierarchicalroutes.service']->getTree(),
        ];

        $html = $app['twig']->render("@bolt/tree.twig", $data);

        return new Response($html);
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
            new Provider\HierarchicalRoutesProvider(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function registerNutCommands(Container $container)
    {
        return [
            new Nut\BuildHierarchyCommand($container),
            new Nut\ViewHierarchyCommand($container),
        ];
    }
}
