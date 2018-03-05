<?php

namespace RedirectionIO\Client\Sdk\HttpMessage;

class Request
{
    private $host;
    private $path;
    private $userAgent;
    private $referer;
    private $scheme;

    /**
     * @param string $host      Host of the URI instance
     * @param string $path      Path of the URI instance
     * @param string $userAgent User-Agent header of the request
     * @param string $referer   Referer header of the request
     */
    public function __construct($host, $path, $userAgent, $referer = '', $scheme = 'http')
    {
        $this->host = $host;
        $this->path = $path;
        $this->userAgent = $userAgent;
        $this->referer = $referer;
        $this->scheme = $scheme;
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

    public function getScheme()
    {
        return $this->scheme;
    }
}
