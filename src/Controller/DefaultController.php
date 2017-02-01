<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Controller;

use Nice\Application;
use Nice\Security\AuthenticationFailureSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

class DefaultController
{
    public function indexAction(Application $app, Request $request)
    {
        $rootDir = $app->getRootDir();
        $packagesJson = $rootDir . '/web/packages.json';
        $mtime = null;
        if (file_exists($packagesJson))
        {
            $mtime = new \DateTime('@' . filemtime($packagesJson));
        }
        if (file_exists(__DIR__ . '/../../site.yml'))
        {
            $config = Yaml::parse(file_get_contents(__DIR__ . '/../../site.yml'));
        }
        else
        {
            $config = [
                'frontend' => [
                    'repository_title'   => 'Private Composer Repository',
                    'repository_for'     => 'MJR.ONE',
                    'repository_contact' => 'admin@mjr.one',
                ],
            ];
        }

        return new Response(
            $app->get('templating')->render(
                'Default/index.html.twig', array(
                'updatedAt' => $mtime,
                'config'    => $config['frontend'],
            )
            )
        );
    }

    public function loginAction(Application $app, Request $request)
    {
        if (file_exists(__DIR__ . '/../../site.yml'))
        {
            $config = Yaml::parse(file_get_contents(__DIR__ . '/../../site.yml'));
        }
        else
        {
            $config = [
                'frontend' => [
                    'repository_title'   => 'Private Composer Repository',
                    'repository_for'     => 'MJR.ONE',
                    'repository_contact' => 'admin@mjr.one',
                ],
            ];
        }
        $error = false;
        $session = $request->getSession();
        if ($session->get(AuthenticationFailureSubscriber::AUTHENTICATION_ERROR))
        {
            $error = true;
            $session->remove(AuthenticationFailureSubscriber::AUTHENTICATION_ERROR);
        }

        return new Response(
            $app->get('templating')->render(
                'Default/login.html.twig', ['error' => $error, 'config' => $config['frontend'],]
            )
        );
    }
}
