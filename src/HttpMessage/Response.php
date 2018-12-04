<?php

namespace RedirectionIO\Client\Sdk\HttpMessage;

class Response
{
    private $statusCode;
    private $ruleId;
    private $location;
    private $matchOnResponseStatus;

    /**
     * @param int    $statusCode
     * @param string $ruleId
     * @param string $location
     * @param int    $matchOnResponseStatus
     */
    public function __construct($statusCode = 200, $ruleId = null, $location = null, $matchOnResponseStatus = 0)
    {
        $this->statusCode = $statusCode;
        $this->ruleId = $ruleId;
        $this->location = $location;
        $this->matchOnResponseStatus = $matchOnResponseStatus;
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

    public function getMatchOnResponseStatus()
    {
        return $this->matchOnResponseStatus;
    }
}
