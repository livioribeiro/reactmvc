<?php

namespace ReactMVC\RequestProcessing;

use React\Http\Request as ReactRequest;

/**
 * Description of Request
 *
 * @author livio
 */
class Request
{
    /**
     * The underlying react request
     * @var \React\Http\Request
     */
    private $request;
    
    /**
     * The parameters from routing
     * @var array
     */
    private $routeParams;
    
    /**
     * Parsed request body
     * @var array
     */
    private $requestBody;

    public function __construct(ReactRequest $request, &$routeParams = array(), $requestBody = array())
    {
        $this->request = $request;
        $this->routeParams = $routeParams;
        $this->requestBody = $requestBody;
    }
    
    public function getRouteParams()
    {
        return $this->routeParams;
    }
    
    public function getRequestBody()
    {
        return $this->requestBody;
    }
    
    public function getMethod()
    {
        return $this->request->getMethod();
    }

    public function getPath()
    {
        return $this->request->getPath();
    }

    public function getQuery()
    {
        return $this->request->getQuery();
    }

    public function getHttpVersion()
    {
        return $this->request->getHttpVersion();
    }

    public function getHeaders()
    {
        return $this->request->getHeaders();
    }

}
