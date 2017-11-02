<?php

namespace RedirectionIO\Client\HttpMessage;

class Response
{
    private $statusCode;

    /**
     * @param int $statusCode
     */
    public function __construct($statusCode = 200)
    {
        $this->statusCode = $statusCode;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
