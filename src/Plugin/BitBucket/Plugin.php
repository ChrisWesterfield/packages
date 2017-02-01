<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\BitBucket;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Terramar\Packages\Plugin\Actions;
use Terramar\Packages\Plugin\PluginInterface;

class Plugin implements PluginInterface
{
    /**
     * Configure the given ContainerBuilder.
     *
     * This method allows a plugin to register additional services with the
     * service container.
     *
     * @param ContainerBuilder $container
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function configure(ContainerBuilder $container)
    {
        $container->register('packages.plugin.bitbucket.adapter', 'Terramar\Packages\Plugin\BitBucket\SyncAdapter')
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addArgument(new Reference('router.url_generator'));

        $container->getDefinition('packages.helper.sync')
            ->addMethodCall('registerAdapter', array(new Reference('packages.plugin.bitbucket.adapter')));

        $container->register('packages.plugin.bitbucket.package_subscriber', 'Terramar\Packages\Plugin\BitBucket\PackageSubscriber')
            ->addArgument(new Reference('packages.plugin.bitbucket.adapter'))
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addTag('kernel.event_subscriber');

        $container->register('packages.plugin.bitbucket.remote_subscriber', 'Terramar\Packages\Plugin\BitBucket\RemoteSubscriber')
            ->addArgument(new Reference('packages.plugin.bitbucket.adapter'))
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addTag('kernel.event_subscriber');

        $container->getDefinition('packages.controller_manager')
            ->addMethodCall('registerController', array(Actions::REMOTE_NEW, 'Terramar\Packages\Plugin\BitBucket\Controller::newAction'))
            ->addMethodCall('registerController', array(Actions::REMOTE_CREATE, 'Terramar\Packages\Plugin\BitBucket\Controller::createAction'))
            ->addMethodCall('registerController', array(Actions::REMOTE_EDIT, 'Terramar\Packages\Plugin\BitBucket\Controller::editAction'))
            ->addMethodCall('registerController', array(Actions::REMOTE_UPDATE, 'Terramar\Packages\Plugin\BitBucket\Controller::updateAction'));
    }

    /**
     * Get the plugin name.
     *
     * @return string
     */
    public function getName()
    {
        return 'BitBucket';
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return '1.0.0-omega';
    }
}
