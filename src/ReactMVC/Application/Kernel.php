<?php
namespace ReactMVC\Application;

use DI\Container;
use Evenement\EventEmitter;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Http\Request as ReactRequest;
use React\Http\Response as ReactResponse;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use ReactMVC\RequestProcessing\Request;
use ReactMVC\RequestProcessing\ResponseWriter;
use ReactMVC\RequestProcessing\Result;
use ReactMVC\RequestProcessing\StatusCodes;

/**
 * Description of Application
 *
 * @author livio
 */
class Kernel extends EventEmitter
{

    /**
     *
     * @var \React\EventLoop\LoopInterface
     */
    private $loop;

    /**
     *
     * @var \React\Socket\Server
     */
    private $socket;

    /**
     *
     * @var \React\Http\Server
     */
    private $http;

    /**
     *
     * @var \Interop\Container\ContainerInterface
     */
    private $container;

    /**
     *
     * @var \ReactMVC\RequestProcessing\ResponseWriter
     */
    private $responseWriter;

    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     *
     * @var \Symfony\Component\Routing\RouteCollection
     */
    private $routes;

    /**
     *
     * @var string
     */
    private $host;

    /**
     *
     * @var int
     */
    private $port;

    /**
     *
     * @var bool
     */
    private $debug;
    
    /**
     *
     * @var \Symfony\Component\Serializer\Serializer 
     */
    private $serializer;

    public function __construct(LoopInterface $loop, LoggerInterface $logger, ResponseWriter $reponseWriter, Container $container)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->loop = $loop;
        
        $configDir = $container->get('app.config');
        
        $locator = new FileLocator([$configDir]);
        $loader = new YamlFileLoader($locator);
        $this->routes = $loader->load('routes.yml');
        $this->normalizeRoutes();
        
        $this->socket = new \React\Socket\Server($this->loop);
        $this->http = new \React\Http\Server($this->socket);
        
        $this->responseWriter = $reponseWriter;
        
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new GetSetMethodNormalizer()];

        $this->serializer = new Serializer($normalizers, $encoders);
    }
    
    private function normalizeRoutes()
    {
        foreach ($this->routes as $name => $route) {
            $controllerName = $route->getDefault('_controller');
            
            if ($controllerName === null) {
                throw new \Exception("Missing controller for route '$name'");
            }

            if ($controllerName[0] === '@') {
                $controllerName = substr($controllerName, 1);
                $namespaces = $this->container->get('controller.namespaces');
                foreach ($namespaces as $n) {
                    if ($n[0] !== '\\') {
                        $n = '\\'.$n;
                    }

                    if (class_exists($class = $n.'\\'.$controllerName, true)) {
                        $controllerClass = $class;
                        break;
                    }
                }
            } elseif (class_exists($controllerName)) {
                $controllerClass = $controllerName;
            }
            
            if (!isset($controllerClass)) {
                throw new \Exception("Controller class for route '$name' does not exist");
            }

            $route->setDefault('_controller', $controllerClass);
        }
    }

    public function run($host, $port, $debug)
    {
        $this->host = $host;
        $this->port = $port;
        $this->debug = $debug;
        $this->http->on('request', function (ReactRequest $request, ReactResponse $response) {
            $this->emit('request.started', [$request]);
            
            if (array_search($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
                $this->onRequestWithBody($request, $response);
            } else {
                $this->onRequest($request, $response);
            }
        });
        
        if ($this->debug) {
            $this->on('request.ended', function (ReactRequest $request, $status) {
                $this->debugRequest($request, $status);
            });
        }
        
        $this->http->on('end', function () {
            $this->logger->notice('Application terminated');
        });
        
        $this->socket->listen($this->port, $this->host);
        $this->logger->notice("Application running on {$this->host}:{$this->port}");
        $this->loop->run();
    }

    public function stop()
    {
        $this->loop->stop();
        $this->socket->shutdown();
        $this->http->removeAllListeners();
    }

    private function onRequestWithBody(ReactRequest $request, ReactResponse $response)
    {
        $request->on('data', function ($data) use ($request, $response) {
            $contentType = $request->getHeaders()['Content-Type'];
            if ($contentType === 'application/json') {
                $content = $this->serializer->decode($data, 'json');
            }
            elseif ($contentType === 'text/xml' || $contentType === 'application/xml') {
                $content = $this->serializer->decode($data, 'xml');
            }
            elseif ($contentType === 'application/x-www-form-urlencoded') {
                $content = array();
                parse_str($data, $content);
            }
            else {
                $content = $data;
            }

            $this->onRequest($request, $response, $content);
        });
    }
    
    private function onRequest(ReactRequest $req, ReactResponse $resp, $content = array())
    {
        $status = StatusCodes::HTTP_OK;
        
        try {
            $routeContext = $this->matchRoute($req);
            $applicationRequest = new Request($req, $routeContext, $content);
            
            $this->emit('request.routed', [$applicationRequest]);
            
            $requestMiddlewareResult = $this->processRequestMiddleware($applicationRequest);
            if ($requestMiddlewareResult instanceof Result) {
                $result = $requestMiddlewareResult;
            } else {
                $result = $this->processRequest($applicationRequest);
            }
            
            $this->processResponse($resp, $applicationRequest, $result, $status);
            
        } catch (ResourceNotFoundException $e) {
            $status = StatusCodes::HTTP_NOT_FOUND;
        } catch (MethodNotAllowedException $e) {
            $status = StatusCodes::HTTP_METHOD_NOT_ALLOWED;
        } catch (\Exception $e) {
            $status = StatusCodes::HTTP_INTERNAL_SERVER_ERROR;
            $this->logException($e);
        }
        
        if ($status >= 400) {
            $this->handleError($req, $resp, $status);
        }

        $this->emit('request.ended', [$req, $status]);
        
    }

    public function matchRoute(ReactRequest $request)
    {
        $host = $request->getHeaders()['Host'];
        $path = $request->getPath();
        $baseUrl = $host.$path;
        $method = $request->getMethod();
        
        $query = '';
        if (count($request->getQuery()) > 0) {
            $query = http_build_query($request->getQuery());
        }
        
        $requestContext = new RequestContext($baseUrl, $method, $host);
        $requestContext->setPathInfo($path);
        $requestContext->setQueryString($query);
        
        $matcher = new UrlMatcher($this->routes, $requestContext);
        $match = $matcher->match($request->getPath());
        return $match;
    }
    
    private function processRequestMiddleware(Request $request)
    {
        if (!$this->container->has('middleware.request')) {
            return;
        }
        
        $requestMiddleware = $this->container->get('middleware.request');
        
        foreach ($requestMiddleware as $middleware) {
            $result = $middleware->processRequest($request);
            if ($result !== null) {
                return $result;
            }
        }
    }
    
    /**
     *
     * @param Request $request            
     * @return Result
     */
    private function processRequest(Request $request)
    {
        $routeParams = $request->getRouteParams();
        
        $controllerClass = $routeParams['_controller'];
        
        $controller = $this->container->get($controllerClass);
        
        if (isset($routeParams['_action'])) {
            $action = $routeParams['_action'];
        } else {
            $action = strtoupper($request->getMethod());
            $routeParams['_action'] = strtolower($action); //= array_merge($routeParams, ['_action' => strtolower($action)]);
        }
        
        $controllerResult = call_user_func_array([$controller, $action], [$request]);
        
        if (!($controllerResult instanceof Result)) {
            throw new \Exception("$controllerClass::$action must return an instance of " . Result::class);
        }
        
        return $controllerResult;
    }
    
    private function processResponse(ReactResponse $resp, Request $request, Result $result, &$status)
    {
        if ($result->getStatusCode() >= 400) {
            $status = $result->getStatusCode();
            $this->handleErrorResult($result);
            return;
        }
        
        if ($result->isRedirect()) {
            $status = $result->getStatusCode();
            $resp->writeHead($status, $result->getHeaders());
            $resp->end();
            return;
        }
        
        if ($result->isStream()) {
            $headers = array_merge($result->getHeaders(), ['Content-Type' => $result->getContentType()]);
            $resp->writeHead(StatusCodes::HTTP_OK, $headers);
            $result->getContent()->pipe($resp);
            return;
        }
        
        $processedResult = $this->processResponseMiddleware($request, $result);
        $this->responseWriter->writeResponse($resp, $request, $processedResult);
    }

    private function processResponseMiddleware(Request $request, Result $result)
    {
        if (!$this->container->has('middleware.response')) {
            return $result;
        }
        
        $processed = $result;
        
        $responseMiddleware = $this->container->get('middleware.response');
        foreach ($responseMiddleware as $middleware) {
            $processed = $middleware->processResponse($request, $result);
        }
        
        return $processed;
    }

    private function debugRequest(ReactRequest $request, $status)
    {
        $url = $request->getPath();
        if (count($request->getQuery()) > 0) {
            $qs = http_build_query($request->getQuery());
            $url .= '?' . $qs;
        }
        
        $httpVersion = $request->getHttpVersion();
        $this->logger->debug("$status HTTP/$httpVersion $url");
    }

    private function handleError(ReactRequest $request, ReactResponse $response, $status)
    {
        $response->writeHead($status);
        $response->end(\React\Http\ResponseCodes::$statusTexts[$status]);
    }
    
    private function handleErrorResult(Result $result)
    {
        // TODO
    }

    private function logException($exception)
    {
        if ($this->debug) {
            $app = $this->container->get('application');
            $out = new ConsoleOutput(OutputInterface::VERBOSITY_DEBUG, true, new OutputFormatter(true));
            $app->renderException($exception, $out);
        } else {
            $this->logger->error($exception);
        }
    }
}
