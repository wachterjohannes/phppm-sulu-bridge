<?php

namespace Sulu\Component\PHPPM;

use PHPPM\Bridges\BridgeInterface;
use React\Http\Request as ReactRequest;
use React\Http\Response as ReactResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Implements a bridge to bootstrap sulu application in php-pm
 */
class SuluBridge implements BridgeInterface
{
    /**
     * @var KernelInterface
     */
    private $adminKernel;

    /**
     * @var KernelInterface
     */
    private $websiteKernel;

    /**
     * {@inheritdoc}
     */
    public function bootstrap($appBootstrap, $appenv)
    {
        // include applications autoload
        $autoloader = dirname(realpath($_SERVER['SCRIPT_NAME'])) . '/vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
        }

        $this->websiteKernel = $this->getKernel('website', $appenv);
        $this->adminKernel = $this->getKernel('website', $appenv);
    }

    private function getKernel($name, $appenv)
    {
        $kernelName = '\\' . ucfirst($name) . 'Kernel';

        if (file_exists('./app/' . $kernelName . '.php')) {
            require_once './app/' . $kernelName . '.php';
        }
        $app = new $kernelName($appenv, false);
        $app->loadClassCache();

        return $app;
    }

    /**
     * {@inheritdoc}
     */
    public function onRequest(ReactRequest $request, ReactResponse $response)
    {
        if (null === $this->application) {
            return;
        }
        $content = '';
        $headers = $request->getHeaders();
        $contentLength = isset($headers['Content-Length']) ? (int)$headers['Content-Length'] : 0;
        $request->on(
            'data',
            function ($data)
            use ($request, $response, &$content, $contentLength) {
                // read data (may be empty for GET request)
                $content .= $data;
                // handle request after receive
                if (strlen($content) >= $contentLength) {
                    $syRequest = self::mapRequest($request, $content);
                    try {
                        $syResponse = $this->application->handle($syRequest);
                    } catch (\Exception $exception) {
                        $response->writeHead(500); // internal server error
                        $response->end();
                        return;
                    }
                    self::mapResponse($response, $syResponse);
                }
            }
        );
    }

    /**
     * Convert React\Http\Request to Symfony\Component\HttpFoundation\Request
     *
     * @param ReactRequest $reactRequest
     * @return SymfonyRequest $syRequest
     */
    protected static function mapRequest(ReactRequest $reactRequest, $content)
    {
        $method = $reactRequest->getMethod();
        $headers = $reactRequest->getHeaders();
        $query = $reactRequest->getQuery();
        $post = array();
        // parse body?
        if (
            isset($headers['Content-Type']) &&
            (0 === strpos($headers['Content-Type'], 'application/x-www-form-urlencoded')) &&
            in_array(strtoupper($method), array('POST', 'PUT', 'DELETE', 'PATCH'))
        ) {
            parse_str($content, $post);
        }

        $syRequest = new SymfonyRequest(
        // $query, $request, $attributes, $cookies, $files, $server, $content
            $query, $post, array(), array(), array(), array(), $content
        );
        $syRequest->setMethod($method);
        $syRequest->headers->replace($headers);
        $syRequest->server->set('REQUEST_URI', $reactRequest->getPath());
        $syRequest->server->set('SERVER_NAME', explode(':', $headers['Host'])[0]);
        return $syRequest;
    }

    /**
     * Convert Symfony\Component\HttpFoundation\Response to React\Http\Response
     *
     * @param ReactResponse $reactResponse
     * @param SymfonyResponse $syResponse
     */
    protected static function mapResponse(ReactResponse $reactResponse, SymfonyResponse $syResponse)
    {
        $headers = $syResponse->headers->all();
        $reactResponse->writeHead($syResponse->getStatusCode(), $headers);
        $reactResponse->end($syResponse->getContent());
    }
}
