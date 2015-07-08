<?php
namespace ReactMVC\Middleware;

use ReactMVC\RequestProcessing\Request;
use ReactMVC\RequestProcessing\Result;

/**
 *
 * @author livio
 */
interface ResponseMiddlewareInterface
{

    public function processResponse(Request $request, Result $result);
}
