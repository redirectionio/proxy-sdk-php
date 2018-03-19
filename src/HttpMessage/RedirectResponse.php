<?php

namespace RedirectionIO\Client\Sdk\HttpMessage;

class RedirectResponse extends Response
{
    private $location;

    /**
     * @param string $location
     * @param int    $statusCode
     */
    public function __construct($location, $statusCode = 301)
    {
        $this->location = $location;

        parent::__construct($statusCode);
    }

    public function getLocation()
    {
        return $this->location;
    }
}
