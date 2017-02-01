<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\BitBucket;

use Nice\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Controller
{
    public function newAction(Application $app, Request $request)
    {
        return new Response($app->get('twig')->render('Plugin/BitBucket/new.html.twig'));
    }

    public function createAction(Application $app, Request $request)
    {
        $remote = $request->get('remote');
        if ($remote->getAdapter() !== 'BitBucket') {
            return new Response();
        }

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $app->get('doctrine.orm.entity_manager');
        $config = new RemoteConfiguration();
        $config->setRemote($remote);
        $config->setPassword($request->get('bitbucket_password'));
        $config->setUser($request->get('bitbucket_user'));
        $config->setUrl($request->get('bitbucket_url'));
        $config->setEnabled($remote->isEnabled());
        $config->setRemoteWebHookKey($request->get('bitbucket_webhook_key'));
        $config->setRemoteWebHook($request->query->has('bitbucket_webhook'));
        $entityManager->persist($config);

        return new Response();
    }

    public function editAction(Application $app, Request $request, $id)
    {
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $app->get('doctrine.orm.entity_manager');
        $config = $entityManager->getRepository('Terramar\Packages\Plugin\BitBucket\RemoteConfiguration')->findOneBy(array(
            'remote' => $id,
        ));
        return new Response($app->get('twig')->render('Plugin/BitBucket/edit.html.twig', array(
            'config' => $config ?: new RemoteConfiguration(),
        )));
    }

    public function updateAction(Application $app, Request $request, $id)
    {
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $app->get('doctrine.orm.entity_manager');
        $config = $entityManager->getRepository('Terramar\Packages\Plugin\BitBucket\RemoteConfiguration')->findOneBy(array(
            'remote' => $id,
        ));

        if (!$config) {
            return new Response();
        }

        $config->setPassword($request->get('bitbucket_password'));
        $config->setUser($request->get('bitbucket_user'));
        $config->setUrl($request->get('bitbucket_url'));
        $config->setEnabled($request->query->has('enabled'));
        $config->setRemoteWebHookKey($request->get('bitbucket_webhook_key'));
        $config->setRemoteWebHook($request->query->has('bitbucket_webhook'));

        $entityManager->persist($config);

        return new Response();
    }
}
