<?php
/**
 * MIT License
 *
 * Copyright (c) 2017, Pentagonal
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace PentagonalProject\SlimService;

use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Handlers\PhpError;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\XmlResponseHandler;
use Whoops\Run;

/**
 * Class ErrorHandler
 * @package PentagonalProject\SlimService
 */
class ErrorHandler extends PhpError
{
    protected $whoops;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var \Throwable
     */
    protected $exception;

    /**
     * ErrorHandler constructor.
     *
     * @param bool $displayErrorDetails
     */
    public function __construct($displayErrorDetails = false)
    {
        parent::__construct($displayErrorDetails);
        $this->whoops = new Run();
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param \Throwable $exception
     *
     * @return ResponseInterface|static
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, \Throwable $exception)
    {
        if ($this->displayErrorDetails) {
            $contentType = $this->determineContentType($request);
            $this->pushHandlerByContentType($contentType);
            $output = $this->whoops->handleException($exception);
            $body = $response->getBody();
            $body->write($output);
            return $response
                ->withStatus(500)
                ->withHeader('Content-type', $contentType)
                ->withBody($body);
        }

        return parent::__invoke($request, $response, $exception);
    }
    /**
     * @param $contentType
     */
    protected function pushHandlerByContentType($contentType)
    {
        $contentTypeBasedHandler = null;
        switch ($contentType) {
            case 'application/json':
                $contentTypeBasedHandler = new JsonResponseHandler();
                break;
            case 'text/xml':
            case 'application/xml':
                $contentTypeBasedHandler = new XmlResponseHandler();
                break;
            case 'text/html':
                $contentTypeBasedHandler = new PrettyPageHandler();
                break;
            default:
                return;
        }
        $existingHandlers = array_merge([$contentTypeBasedHandler], $this->whoops->getHandlers());
        $this->whoops->clearHandlers();
        foreach ($existingHandlers as $existingHandler) {
            $this->whoops->pushHandler($existingHandler);
        }
    }

    /**
     * @param \Exception|\Throwable $throwable
     */
    protected function writeToErrorLog($throwable)
    {
        if (isset($this->logger)) {
            $message  = $throwable->getMessage();
            $message .= " ({$throwable->getFile()}:{$throwable->getLine()}:{$throwable->getCode()})";
            $this->logger->error(
                $message,
                [
                    'exceptions' => $throwable
                ]
            );
            return;
        }
        parent::writeToErrorLog($throwable);
    }
}
