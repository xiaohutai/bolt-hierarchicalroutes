<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Listener;

use Bolt\Events\StorageEvent;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Service\HierarchicalRoutesService;
use Silex\Application;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event class to handle events early enough to work, but late enough that db
 * and config are initialized.
 *
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class KernelEventListener implements EventSubscriberInterface
{
    /** @var HierarchicalRoutesService $service */
    private $service;

    /**
     * @param HierarchicalRoutesService $service
     */
    public function __construct(HierarchicalRoutesService $service)
    {
        $this->service = $service;
    }

    /**
     * Kernel request listener callback.
     *
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $this->service->build();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 31],
        ];
    }
}
