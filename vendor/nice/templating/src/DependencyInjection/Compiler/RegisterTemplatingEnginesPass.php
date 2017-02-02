<?php

/*
 * Copyright (c) Tyler Sommer
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Nice\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RegisterTemplatingEnginesPass implements CompilerPassInterface
{
    /**
     * Registers services tagged with "templating.engine" with Templating
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('templating')) {
            return;
        }

        $definition = $container->getDefinition('templating');
        foreach ($container->findTaggedServiceIds('templating.engine') as $service => $tag) {
            $definition->addMethodCall('addEngine', array(new Reference($service)));
        }
    }
}
