<?php

namespace ReactMVC\RequestProcessing;

use React\Stream\ReadableStreamInterface;
use React\Promise\Promise;

/**
 * Description of Result
 *
 * @author livio
 */
class Result
{
    const RESULT_DATA = 0;
    const RESULT_PROMISE = 1;
    const RESULT_STREAM = 2;

    private $content;
    private $contentType;
    private $statusCode;
    private $headers;

    public function __construct($content, $contentType = null, $headers = array(), $statusCode = StatusCodes::HTTP_OK)
    {
        if ($content instanceof ReadableStreamInterface && $contentType === null) {
            throw new \Exception('Stream result requires a content type');
        }

        $this->content = $content;
        $this->contentType = $contentType;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }
    
    public static function redirect($location, $statusCode = StatusCodes::HTTP_SEE_OTHER)
    {
        return new Result(null, null, ['Location' => $location], $statusCode);
    }
    
    public static function notFound()
    {
        return new Result(null, null, array(), StatusCodes::HTTP_NOT_FOUND);
    }
    
    public static function error($statusCode)
    {
        return new Result(null, null, array(), $statusCode);
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getContentType()
    {
        return $this->contentType;
    }
    
    public function getStatusCode()
    {
        return $this->statusCode;
    }
    
    public function getHeaders()
    {
        return $this->headers;
    }

    public function isData()
    {
        return !($this->isPromise() || $this->isStream());
    }

    public function isPromise()
    {
        return $this->content instanceof Promise;
    }

    public function isStream()
    {
        return $this->content instanceof ReadableStreamInterface;
    }

    public function isRedirect()
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    public function getResultType()
    {
        if ($this->content instanceof Promise) {
            return self::RESULT_PROMISE;
        }
        if ($this->content instanceof ReadableStreamInterface) {
            return self::RESULT_STREAM;
        }
        return self::RESULT_DATA;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

}
