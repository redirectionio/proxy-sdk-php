<?php

namespace RedirectionIO\Client\Sdk\HttpMessage;

class Request
{
    private $host;
    private $path;
    private $userAgent;
    private $referer;
    private $scheme;
    private $method;

    /**
     * @param string $host      Host of the URI instance
     * @param string $path      Path of the URI instance
     * @param string $userAgent User-Agent header of the request
     * @param string $referer   Referer header of the request
     * @param string $scheme    "http" or "https"
     * @param string $method    GET / POST / PUT / ...
     */
    public function __construct($host, $path, $userAgent = '', $referer = '', $scheme = 'http', $method = '')
    {
        $this->host = $host;
        $this->path = $path;
        $this->userAgent = $userAgent;
        $this->referer = $referer;
        $this->scheme = $scheme;
        $this->method = $method;
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

    public function getMethod()
    {
        return $this->method;
    }
}
