<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Provider;

use Bolt\Extension\TwoKings\HierarchicalRoutes\Controller;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Controller\Requirement;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Routing\HierarchicalUrlGenerator;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Service;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Twig;
use Bolt\Version as BoltVersion;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 *
 *
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 * @author Gawain Lynch <gawain@twokings.nl>
 */
class HierarchicalRoutesProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Application $app)
    {
        $app['hierarchicalroutes.service'] = $app->share(
            function (Application $app) {
                return new Service\HierarchicalRoutesService(
                    $app['hierarchicalroutes.config'],
                    $app['config'],
                    $app['storage.lazy'],
                    $app['query'],
                    $app['cache'],
                    $app['logger.system']
                );
            }
        );

        $app['hierarchicalroutes.controller.requirement'] = $app->share(
            function (Application $app) {
                return new Requirement($app['hierarchicalroutes.service']);
            }
        );

        $app['url_generator'] = $app->extend(
            'url_generator',
            function (UrlGeneratorInterface $urlGenerator) use ($app) {
                return new HierarchicalUrlGenerator($urlGenerator, $app);
            }
        );

        /*
         * Twig
         */
        $this-> deprecatedRuntimeSupport($app);

        $app['twig.runtime.hierarchicalroutes'] = function ($app) {
            return new Twig\Runtime\HierarchicalRoutesRuntime($app);
        };
        $app['twig.runtimes'] = $app->extend(
            'twig.runtimes',
            function (array $runtimes) {
                return $runtimes + [
                        Twig\Runtime\HierarchicalRoutesRuntime::class => 'twig.runtime.hierarchicalroutes',
                    ];
            }
        );

        $app['twig'] = $app->share(
            $app->extend(
                'twig',
                function (\Twig_Environment $twig, $app) {
                    $twig->addExtension(new Twig\Extension\HierarchicalRoutesExtension());

                    if (version_compare(BoltVersion::forComposer(), '3.3.0', '<')) {
                        $twig->addRuntimeLoader($app['twig.runtime_loader']);
                    }

                    return $twig;
                }
            )
        );
    }

    /**
     * @deprecated Supports Bolt < 3.3.0
     *
     * @param Application $app
     */
    private function deprecatedRuntimeSupport(Application $app)
    {
        if (version_compare(BoltVersion::forComposer(), '3.3.0', '<')) {
            if (!isset($app['twig.runtimes'])) {
                $app['twig.runtimes'] = function () {
                    return [];
                };
            }
            if (!isset($app['twig.runtime_loader'])) {
                $app['twig.runtime_loader'] = function ($app) {
                    return new Twig\RuntimeLoader($app, $app['twig.runtimes']);
                };
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
    }
}
