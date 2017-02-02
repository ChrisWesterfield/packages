<?php

/*
 * Copyright (c) Tyler Sommer
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Nice\Templating\Helper;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Templating\Helper\Helper;

class AssetsHelper extends Helper
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Constructor
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns the public path of an asset
     *
     * @param string $path
     *
     * @return string
     */
    public function getUrl($path)
    {
        if (false !== strpos($path, '://') || 0 === strpos($path, '//')) {
            return $path;
        }

        if (!$this->container->has('request')) {
            return $path;
        }

        if ('/' !== substr($path, 0, 1)) {
            $path = '/' . $path;
        }

        $request = $this->container->get('request');
        $path = $request->getBasePath() . $path;

        return $path;
    }

    /**
     * Returns the name of the helper
     *
     * @return string
     */
    public function getName()
    {
        return 'assets';
    }
}
