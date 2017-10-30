<?php

namespace RedirectionIO\Client\HTTPMessage;

/**
 * Server-side HTTP Request.
 *
 * Minimal working server request to interact with the agent
 */
class ServerRequest
{
    /**
     * @var string Host of the URI instance
     */
    private $host;

    /**
     * @var string Path of the URI instance
     */
    private $path;

    /**
     * @var string User-Agent header of the request
     */
    private $userAgent;

    /**
     * @var string Referer header of the request
     */
    private $referer;

    /**
     * @param string $host
     * @param string $path
     * @param string $userAgent
     * @param string $referer
     */
    public function __construct($host = '', $path = '', $userAgent = '', $referer = '')
    {
        $this->host = $host;
        $this->path = $path;
        $this->userAgent = $userAgent;
        $this->referer = $referer;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function getReferer()
    {
        return $this->referer;
    }
}
