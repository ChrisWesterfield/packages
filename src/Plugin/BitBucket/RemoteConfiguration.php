<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\BitBucket;

use Doctrine\ORM\Mapping as ORM;
use Terramar\Packages\Entity\Remote;

/**
 * @ORM\Entity
 * @ORM\Table(name="packages_bitbucket_remotes")
 */
class RemoteConfiguration
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(name="id", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="enabled", type="boolean")
     */
    private $enabled = false;

    /**
     * @ORM\Column(name="user", type="string")
     */
    private $user;

    /**
     * @ORM\Column(name="password", type="string")
     */
    private $password;

    /**
     * @ORM\Column(name="url", type="string")
     */
    private $url;

    /**
     * @var bool
     * @ORM\Column(name="remote_web_hook", type="boolean")
     */
    private $remoteWebHook;

    /**
     * @var string
     * @ORM\Column(name="remote_web_hook_key", type="string")
     */
    private $remoteWebHookKey;

    /**
     * @ORM\ManyToOne(targetEntity="Terramar\Packages\Entity\Remote")
     * @ORM\JoinColumn(name="remote_id", referencedColumnName="id")
     */
    private $remote;

    /**
     * @param mixed $enabled
     */
    public function setEnabled($enabled)
    {
        $this->enabled = (bool) $enabled;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Remote
     */
    public function getRemote()
    {
        return $this->remote;
    }

    /**
     * @param Remote $remote
     */
    public function setRemote(Remote $remote)
    {
        $this->remote = $remote;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param mixed $url
     */
    public function setUrl($url)
    {
        $this->url = (string) $url;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     *
     * @return RemoteConfiguration
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     *
     * @return RemoteConfiguration
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getRemoteWebHook()
    {
        return $this->remoteWebHook;
    }

    /**
     * @param mixed $remoteWebHook
     *
     * @return RemoteConfiguration
     */
    public function setRemoteWebHook($remoteWebHook)
    {
        $this->remoteWebHook = $remoteWebHook;

        return $this;
    }

    /**
     * @return string
     */
    public function getRemoteWebHookKey()
    {
        return $this->remoteWebHookKey;
    }

    /**
     * @param string $remoteWebHookKey
     *
     * @return RemoteConfiguration
     */
    public function setRemoteWebHookKey($remoteWebHookKey)
    {
        $this->remoteWebHookKey = $remoteWebHookKey;

        return $this;
    }
}
