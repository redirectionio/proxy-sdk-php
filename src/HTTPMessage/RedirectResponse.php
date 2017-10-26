<?php

namespace RedirectionIO\Client\HTTPMessage;

/**
 * Redirect HTTP Response
 * 
 * Minimal working redirect response to interact with the agent
 */
class RedirectResponse {

    /**
     * @var int Status code of the response
     */
    private $statusCode;

    /**
     * @var string Destination location of the response
     */
    private $location;

    /**
     * @param string $location
     * @param int $statusCode
     */
    public function __construct($location = '', $statusCode = 200)
    {
        $this->location = $location;
        $this->statusCode = $statusCode;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getLocation()
    {
        return $this->location;
    }
}
