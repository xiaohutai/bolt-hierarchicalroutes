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
        $storageEventListener = new StorageEventListener($this->getContainer());
        $dispatcher->addListener(StorageEvents::POST_SAVE, [$storageEventListener, 'onPostSave']);
        $dispatcher->addListener(StorageEvents::POST_DELETE, [$storageEventListener, 'onPostDelete']);
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
}
