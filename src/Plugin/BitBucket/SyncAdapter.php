<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\BitBucket;

use Doctrine\ORM\EntityManager;
use Httpful\Request;
use Httpful\Response;
use Nice\Router\UrlGeneratorInterface;
use Terramar\Packages\Entity\Remote;
use Terramar\Packages\Entity\Package;
use Terramar\Packages\Helper\SyncAdapterInterface;

class SyncAdapter implements SyncAdapterInterface
{
    const REST_REPOS  = 'rest/api/1.0/repos?limit=1000';
    const REST_BRANC  = 'rest/api/1.0/projects/%s/repos/%s/branches/default';
    const REST_COMIT  = 'rest/api/1.0/projects/%s/repos/%s/commits?limit=1';
    const REST_HOOK   = 'rest/api/1.0/projects/%s/repos/%s/settings/hooks/%s';
    const REST_HOOKS  = 'rest/api/1.0/projects/%s/repos/%s/settings/hooks/%s/settings';
    const REST_HOOKEN = 'rest/api/1.0/projects/%s/repos/%s/settings/hooks/%s/enabled';
    const AVATAR_URL  = '/projects/%s/avatar.png?s=%i&';
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    /**
     * @var \Nice\Router\UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * Constructor.
     * 
     * @param EntityManager         $entityManager
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(EntityManager $entityManager, UrlGeneratorInterface $urlGenerator)
    {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @param Remote $remote
     *
     * @return bool
     */
    public function supports(Remote $remote)
    {
        return $remote->getAdapter() === $this->getName();
    }

    /**
     * @param Remote $remote
     *
     * @return Package[]
     */
    public function synchronizePackages(Remote $remote)
    {
        $existingPackages = $this->entityManager->getRepository('Terramar\Packages\Entity\Package')->findBy(array('remote' => $remote));

        $projects = $this->getAllProjects($remote);

        $packages = array();
        foreach ($projects as $project) {
            if (!$this->packageExists($existingPackages, $project['id'])) {
                $package = new Package();
                $package->setExternalId($project['id']);
                $package->setName($project['name']);
                $package->setDescription($project['description']);
                $package->setFqn($project['path_with_namespace']);
                $package->setWebUrl($project['web_url']);
                $package->setSshUrl($project['ssh_url_to_repo']);
                $package->setHookExternalId('');
                $package->setRemote($remote);
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'BitBucket';
    }

    /**
     * Enable a GitLab webhook for the given Package.
     * 
     * @param Package $package
     *
     * @return bool
     */
    public function enableHook(Package $package)
    {
        /** @var PackageConfiguration $config */
        $config = $this->getConfig($package);
        $fqn = $config->getPackage()->getFqn();
        $fqn = explode('/',$fqn);
        $remote = $this->entityManager->getRepository('Terramar\Packages\Plugin\BitBucket\RemoteConfiguration')->findOneBy(['remote' => $config->getPackage()->getRemote()->getId()]);
        if ($config->isEnabled() && $remote->getRemoteWebHook()!==true) {
            return true;
        }
        $key = $remote->getRemoteWebHookKey();
        /** @var Response $hookPlugin */
        $hookPlugin = $this->request(sprintf(self::REST_HOOK,$fqn[0],$fqn[1],$key),$config->getPackage()->getRemote());
        if($hookPlugin->hasBody() && $hookPlugin->hasErrors())
        {
            return true;
        }
        $url = $this->urlGenerator->generate('webhook_receive', array('id' => $package->getId()), true);
        /** @var Response $hookSettings */
        $hookSettings = $this->request(sprintf(self::REST_HOOKS,$fqn[0],$fqn[1],$key),$config->getPackage()->getRemote());
        $urlCount = 0;
        $skipSettings = false;
        if($hookSettings->hasBody())
        {
            $urlCount = (int)$hookSettings->body->locationCount;
            if($urlCount > 0)
            {
                if($hookSettings->body->url === $url)
                {
                    $skipSettings = true;
                }
                else
                {
                    for($i=2;$i <= $urlCount; $i++)
                    {
                        if(isset($hookSettings->body->{'url'.$i}) && $hookSettings->body->{'url'.$i}===$url)
                        {
                            $skipSettings = true;
                            break;
                        }
                    }
                }
            }
        }
        if(!$skipSettings)
        {
            //no method exists!
            $settings = (array)$hookSettings->body;
            $index = '';
            if($urlCount > 1)
            {
                $index = (string)($urlCount + 1);
            }
            $addedSetting = [
                'httpMethod'.$index=>'POST',
                'url'.$index=>$url,
                'skipSsl'.$index=>true,
                'postContentType'.$index=>'application/x-www-form-urlencoded',
                'branchFilter'.$index=>'',
                'tagFilter'.$index=>'',
                'userFilter'.$index=>'',
                'postData'.$index=>'',
            ];
            $settings = array_merge($settings,$addedSetting);
            if(!isset($settings['locationCount']))
            {
                $settings['locationCount'] = 0;
            }
            $settings['locationCount'] = (int)$settings['locationCount']+1;
            $uri = $remote->getUrl().'/'.sprintf(self::REST_HOOKS,$fqn[0],$fqn[1],$key);
            $response = Request::put($uri)
                ->sendsJson()
                ->authenticateWith($remote->getUser(),$remote->getPassword())
                ->body(json_encode($settings))
                ->send();
        }
        $responseEnable = Request::put($remote->getUrl().'/'.sprintf(self::REST_HOOKEN,$fqn[0],$fqn[1],$key))
                 ->sendsJson()
                 ->authenticateWith($remote->getUser(),$remote->getPassword())
                 ->send();
        return true;
    }

    /**
     * Disable a GitLab webhook for the given Package.
     * 
     * @param Package $package
     *
     * @return bool
     */
    public function disableHook(Package $package)
    {
        /** @var PackageConfiguration $config */
        $config = $this->getConfig($package);
        $fqn = $config->getPackage()->getFqn();
        $fqn = explode('/',$fqn);
        $remote = $this->entityManager->getRepository('Terramar\Packages\Plugin\BitBucket\RemoteConfiguration')->findOneBy(['remote' => $config->getPackage()->getRemote()->getId()]);
        if ($config->isEnabled() && $remote->getRemoteWebHook()!==true) {
            return true;
        }
        $remoteKey = $remote->getRemoteWebHookKey();
        /** @var Response $hookPlugin */
        $hookPlugin = $this->request(sprintf(self::REST_HOOK,$fqn[0],$fqn[1],$remoteKey),$config->getPackage()->getRemote());
        if($hookPlugin->hasBody() && $hookPlugin->hasErrors())
        {
            return true;
        }
        /** @var Response $hookSettings */
        $hookSettings = $this->request(sprintf(self::REST_HOOKS,$fqn[0],$fqn[1],$remoteKey),$config->getPackage()->getRemote());
        if($hookSettings->hasBody() && (int)$hookSettings->body->locationCount > 1)
        {
            $settings = (array)$hookSettings->body;
            $settings['locationCount'] = (int)$settings['locationCount'];
            $newSettings = [
                'version'=>3,
                'locationCount'=>0,
            ];
            $url = $this->urlGenerator->generate('webhook_receive', array('id' => $package->getId()), true);
            $index = [];
            foreach($settings as $key=>$value)
            {
                if(strpos($key,'url')!==false && $value===$url)
                {
                    $indexKey = str_replace('url','',$key);
                    if($settings['httpMethod'.$indexKey]==='POST')
                    {
                        $index[] = $indexKey;
                    }
                }
            }
            $newCount = 1;
            for($i=1;$i<=$settings['locationCount'];$i++)
            {
                $indexSearch = $i>1?$i:'';
                $newIndex = $newCount>1?$newCount:'';
                if(!in_array($indexSearch,$index) )
                {
                    $set = [
                        'httpMethod'.$newIndex=>$settings['httpMethod'.$indexSearch],
                        'url'.$newIndex=>$settings['url'.$indexSearch],
                        'postContentType'.$newIndex=>$settings['postContentType'.$indexSearch],
                        'branchFilter'.$newIndex=>$settings['branchFilter'.$indexSearch],
                        'tagFilter'.$newIndex=>$settings['tagFilter'.$indexSearch],
                        'userFilter'.$newIndex=>$settings['userFilter'.$indexSearch],
                    ];
                    if(isset($settings['skipSsl'.$indexSearch]))
                    {
                        $set['skipSsl'.$newIndex] = $settings['skipSsl'.$indexSearch];
                    }
                    if(isset($settings['post'.$indexSearch]))
                    {
                        $set['postSsl'.$newIndex] = $settings['post'.$indexSearch];
                    }
                    $newSettings = array_merge($newSettings,$set);
                    $newCount++;
                    $newSettings['locationCount']++;
                }
            }
            $uri = $remote->getUrl().'/'.sprintf(self::REST_HOOKS,$fqn[0],$fqn[1],$remoteKey);
            $response = Request::put($uri)
               ->sendsJson()
               ->authenticateWith($remote->getUser(),$remote->getPassword())
               ->body(json_encode($newSettings))
               ->send();
        }
        else
        {
            $responseEnable = Request::delete($remote->getUrl().'/'.sprintf(self::REST_HOOKEN,$fqn[0],$fqn[1],$remoteKey))
                 ->sendsJson()
                 ->authenticateWith($remote->getUser(),$remote->getPassword())
                 ->send();
        }

        return true;
    }

    private function getConfig(Package $package)
    {
        return $this->entityManager->getRepository('Terramar\Packages\Plugin\GitLab\PackageConfiguration')->findOneBy(array('package' => $package));
    }

    /**
     * @param Remote $remote
     *
     * @return RemoteConfiguration
     */
    private function getRemoteConfig(Remote $remote)
    {
        /** @var RemoteConfiguration $object */
        $object = $this->entityManager->getRepository('Terramar\Packages\Plugin\BitBucket\RemoteConfiguration')->findOneBy(array('remote' => $remote));
        return $object;
    }

    private function getAllProjects(Remote $remote)
    {
        /** @var Response $response */
        $response = $this->request(self::REST_REPOS,$remote);
        $projects = [];
        if($response->hasBody() && isset($response->body->values) && !empty($response->body->values))
        {
            foreach($response->body->values as $repository)
            {
                $projectId = $repository->project->id;
                $projectKey = $repository->project->key;
                $projectName = $repository->project->name;
                /** @var Response $defaultBranches */
                $defaultBranches = $this->request(sprintf(self::REST_BRANC,$projectKey,$repository->slug),$remote);
                if($defaultBranches->hasBody() && isset($defaultBranches->body->displayId))
                {
                    $defaultMasterBranch = $defaultBranches->body->displayId;
                }
                else
                {
                    $defaultMasterBranch = 'master';
                }
                $links = $repository->links->clone;
                $ssh = $http = '';
                foreach($links as $link)
                {
                    if($link->name === 'ssh')
                    {
                        $ssh = $link->href;
                    }
                    if($link->name === 'http')
                    {
                        $http = str_replace('master.yoda@','',$link->href);
                    }
                }
                $sPath = $repository->links->self[0]->href;
                $sPath = explode('/',$sPath);
                $browse = array_pop($sPath);
                $path = array_pop($sPath);
                $repos = array_pop($sPath);
                $namespace = array_pop($sPath);
                /** @var Response $latestCommit */
                $latestCommit = $this->request(sprintf(self::REST_COMIT,$projectKey,$repository->slug),$remote);
                if($latestCommit->hasBody() && !empty($latestCommit->body->values))
                {
                    $latestDate = $latestCommit->body->values[0]->authorTimestamp;
                    $latestDate /= 1000;
                    $latestDate = (int)$latestDate;
                    $latestDate = date('Y-m-d',$latestDate).'T'.date('H:i:s',$latestDate).'.000Z';
                }
                else
                {
                    $latestDate = date('Y-m-d').'T'.date('H:i:s').'.000Z';
                }
                $projectEntry = [
                    'id'=>$repository->id,
                    'description'=>'',
                    'default_branch'=>$defaultMasterBranch,
                    'tag_list'=>[],
                    'public'=>(bool)$repository->public,
                    'archived'=>$projectKey === 'ARCH',
                    'visibility_level'=>0,
                    'ssh_url_to_repo'=>$ssh,
                    'http_url_to_repo'=>$http,
                    'web_url'=>$repository->links->self[0]->href,
                    'name'=>$repository->name,
                    'name_with_namespace'=>$projectName.' / '.$repository->name,
                    'path' =>$path,
                    'path_with_namespace'=>$namespace.'/'.$path,
                    'container_registry_enabled'=>true,
                    'issues_enabled'=>false,
                    'merge_requests_enabled'=>true,
                    'wiki_enabled'=>false,
                    'builds_enabled'=>false,
                    'snippets_enabled'=>true,
                    'created_at'=>date('Y-m-d').'T'.date('H:i:s').'Z',
                    'last_activity_at'=>$latestDate,
                    'shared_runners_enabled'=>false,
                    'lfs_enabled'=>false,
                    'creator_id'=>0,
                    'namespace'=>[
                        'id'=>$projectId,
                        'name'=>$projectName,
                        'path'=>$namespace,
                        'kind'=>'group',
                    ],
                    'avatar_url'=>sprintf($this->getRemoteConfig($remote)->getUrl().self::AVATAR_URL,$projectKey,$repository->id),
                    'star_count'=>0,
                    'forks_count'=>0,
                    'open_issues_count'=>0,
                    'public_builds'=>false,
                    'shared_with_groups'=>[],
                    'only_allow_merge_if_build_succeeds'=>false,
                    'request_access_enabled'=>false,
                    'only_allow_merge_if_all_discussions_are_resolved'=>false,
                    'permissions'=>[
                        'project_access'=>null,
                        'group_access'=>[
                            'access_level'=>0,
                            'notification_level'=>0,
                        ],
                    ],
                ];
                $projects[] = $projectEntry;
            }
        }
        return $projects;
    }

    protected function request($url, Remote $remote)
    {
        $config = $this->getRemoteConfig($remote);
        $uri = $config->getUrl().'/'.$url;
        $response = Request::get($uri)
            ->expectsJson()
            ->authenticateWith($config->getUser(),$config->getPassword())
            ->send();
        return $response;
    }

    private function packageExists($existingPackages, $gitlabId)
    {
        return count(array_filter($existingPackages, function (Package $package) use ($gitlabId) {
                    return (string) $package->getExternalId() === (string) $gitlabId;
                })) > 0;
    }
}
