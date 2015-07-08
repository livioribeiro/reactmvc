<?php
namespace ReactMVC\RequestProcessing;

use React\Http\Response as ReactResponse;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use ReactMVC\RequestProcessing\Request;
use ReactMVC\RequestProcessing\Result;
use ReactMVC\RequestProcessing\StatusCodes;

/**
 * Description of ResponseWriter
 *
 * @author livio
 */
class ResponseWriter
{

    private $serializer;

    public function __construct()
    {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new GetSetMethodNormalizer()];
        $this->serializer = new Serializer($normalizers, $encoders);
    }

    public function writeResponse(ReactResponse $response, Request $request, Result $result)
    {
        if ($result->isPromise()) {
            $result->getContent()->done(
                function ($data) use($response, $request, $result) {
                    $newResult = new Result(
                        $data,
                        $result->getContentType(),
                        $result->getHeaders(),
                        $result->getStatusCode()
                    );
                    $this->doWrite($response, $request, $newResult);
                },
                function ($reason) {
                    throw new \Exception($reason);
                }
            );
        } else {
            $this->doWrite($response, $request, $result);
        }
    }

    private function doWrite(ReactResponse $response, Request $request, Result $result)
    {
        $accept = explode(',', $request->getHeaders()['Accept']);
        
        $contentType = $result->getContentType();
        
        if ($contentType === null) {
            if (array_search('application/json', $accept) > - 1) {
                $contentType = 'application/json';
            } elseif ((array_search('application/xml', $accept) > - 1) || (array_search('text/xml', $accept) > - 1)) {
                $contentType = 'text/xml';
            } else {
                $contentType = 'text/plain';
            }
        }
        
        $content = $result->getContent();
        if (!is_string($content)) {
            if (array_search($contentType, ['application/json','text/plain'])) {
                $stringResult = $this->serializer->encode($content, 'json');
            } elseif (strpos($contentType, 'xml') > - 1) {
                $stringResult = $this->serializer->encode($content, 'xml');
            } else {
                $stringResult = $this->serializer->encode($content, 'json');
            }
        } else {
            $stringResult = $content;
        }
        
        $response->writeHead(StatusCodes::HTTP_OK, array_merge($result->getHeaders(), ['Content-Type' => $contentType]));
        $response->end($stringResult);
    }
}
