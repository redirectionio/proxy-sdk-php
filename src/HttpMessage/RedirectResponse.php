<?php

namespace RedirectionIO\Client\HttpMessage;

class RedirectResponse extends Response
{
    private $location;

    /**
     * @param string $location
     * @param mixed  $statusCode
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
