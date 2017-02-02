<?php

/*
 * Copyright (c) Tyler Sommer
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Nice\Extension;

use Nice\DependencyInjection\Compiler\RegisterTemplatingEnginesPass;
use Nice\DependencyInjection\CompilerAwareExtensionInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Sets up Templating services
 */
class TemplatingExtension extends Extension implements CompilerAwareExtensionInterface
{
    /**
     * @var array
     */
    private $options = array();

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->options = $options;
    }

    /**
     * Returns extension configuration
     *
     * @param array            $config    An array of configuration values
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @return TemplatingConfiguration
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new TemplatingConfiguration();
    }

    /**
     * Loads a specific configuration
     *
     * @param array            $configs    An array of configuration values
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configs[] = $this->options;
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $container->register('templating', 'Symfony\Component\Templating\DelegatingEngine');
        $container->register('templating.template_name_parser', 'Symfony\Component\Templating\TemplateNameParser');

        if (true === $config['enable_php_engine']) {
            $container->setParameter('php.template_dir', $config['template_dir']);

            $container->register('templating.engine.php.loader', 'Symfony\Component\Templating\Loader\FilesystemLoader')
                ->setPublic(false)
                ->addArgument('%php.template_dir%/%%name%%');
            $container->register('templating.engine.php.helper.slots', 'Symfony\Component\Templating\Helper\SlotsHelper');
            $container->register('templating.engine.php.helper.assets', 'Nice\Templating\Helper\AssetsHelper')
                ->addArgument(new Reference('service_container'));
            $container->register('templating.engine.php.helper.router', 'Nice\Templating\Helper\RouterHelper')
                ->addArgument(new Reference('service_container'));
            $container->register('templating.engine.php', 'Symfony\Component\Templating\PhpEngine')
                ->addArgument(new Reference('templating.template_name_parser'))
                ->addArgument(new Reference('templating.engine.php.loader'))
                ->addMethodCall('set', array(new Reference('templating.engine.php.helper.slots')))
                ->addMethodCall('set', array(new Reference('templating.engine.php.helper.assets')))
                ->addMethodCall('set', array(new Reference('templating.engine.php.helper.router')))
                ->addTag('templating.engine');
        }
    }

    /**
     * Gets the CompilerPasses this extension requires.
     *
     * @return array|CompilerPassInterface[]
     */
    public function getCompilerPasses()
    {
        return array(
            new RegisterTemplatingEnginesPass()
        );
    }
}
