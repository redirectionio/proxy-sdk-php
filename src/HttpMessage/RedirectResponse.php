<?php

namespace RedirectionIO\Client\Sdk\HttpMessage;

class RedirectResponse extends Response
{
    private $location;

    /**
     * @param string $location
     * @param int    $statusCode
     * @param string $ruleId
     */
    public function __construct($location, $statusCode = 301, $ruleId = null)
    {
        parent::__construct($statusCode, $ruleId);
        $this->location = $location;
    }

    public function getLocation()
    {
        return $this->location;
    }
}
