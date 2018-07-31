<?php

namespace RedirectionIO\Client\Sdk\HttpMessage;

class Response
{
    private $statusCode;
    private $ruleId;
    private $location;

    /**
     * @param int    $statusCode
     * @param string $ruleId
     * @param string $location
     */
    public function __construct($statusCode = 200, $ruleId = null, $location = null)
    {
        $this->statusCode = $statusCode;
        $this->ruleId = $ruleId;
        $this->location = $location;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getRuleId()
    {
        return $this->ruleId;
    }

    public function getLocation()
    {
        return $this->location;
    }
}
