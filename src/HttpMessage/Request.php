<?php

namespace RedirectionIO\Client\HttpMessage;

class Request
{
    private $host;
    private $path;
    private $userAgent;
    private $referer;

    /**
     * @param string $host      Host of the URI instance
     * @param string $path      Path of the URI instance
     * @param string $userAgent User-Agent header of the request
     * @param string $referer   Referer header of the request
     */
    public function __construct($host, $path, $userAgent, $referer = '')
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
