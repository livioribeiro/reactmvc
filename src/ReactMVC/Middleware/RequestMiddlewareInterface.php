<?php
namespace ReactMVC\Middleware;

use ReactMVC\RequestProcessing\Request;

/**
 *
 * @author livio
 */
interface RequestMiddlewareInterface
{
    public function processRequest(Request $request);
}
