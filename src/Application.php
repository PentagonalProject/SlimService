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

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Interfaces\RouteGroupInterface;
use Slim\Interfaces\RouteInterface;

/**
 * Class Application
 * @package PentagonalProject\SlimService
 * @method ContainerInterface getContainer() : ContainerInterface
 * @method RouteInterface get(string $pattern, callable $callable)
 * @method RouteInterface post(string $pattern, callable $callable)
 * @method RouteInterface put(string $pattern, callable $callable)
 * @method RouteInterface patch(string $pattern, callable $callable)
 * @method RouteInterface delete(string $pattern, callable $callable)
 * @method RouteInterface options(string $pattern, callable $callable)
 * @method RouteInterface any(string $pattern, callable $callable)
 * @method RouteInterface map(array $methods, string $pattern, callable $callable)
 * @method RouteGroupInterface group(string $pattern, callable $callable)
 * @method ResponseInterface process(ServerRequestInterface $request, ResponseInterface $response)
 * @method ResponseInterface respond(ResponseInterface $response)
 * @method ResponseInterface subRequest(...$params)
 */
class Application implements \ArrayAccess
{
    /**
     * @var string
     */
    private $name = 'default';

    /**
     * Application constructor.
     *
     * @param array $config
     * @param string $appName
     */
    public function __construct(array $config, string $appName = 'default')
    {
        $c =& $this;
        $this->name = $appName;
        $config = new Config($config);
        $service = ServiceLocator::create(new App(), $this->getName());
        if ($config['mode'] == 'development') {
            $service['settings']['displayErrorDetails'] = true;
        }
        $service['config'] = function () use (&$config) : Config {
            return $config;
        };
        $service['app'] = function () use (&$c) {
            return $c;
        };

        $service['slim'] = function () use (&$service) {
            return $service->getApp();
        };
    }

    /**
     * @param string $name
     *
     * @return Application
     */
    public function setName(string $name) : Application
    {
        $service = $this->getService();
        $service->setName($name);
        $this->name = $service->getName();
        return $this;
    }

    /**
     * @return ServiceLocator
     */
    public function &getService() : ServiceLocator
    {
        $service = ServiceLocator::getService($this->getName());
        return $service;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @param string $file
     *
     * @return mixed
     */
    public function required(string $file)
    {
        /** @noinspection PhpIncludeInspection */
        return require $file;
    }

    /**
     * @param array $files
     *
     * @return array
     */
    public function requires(array $files) : array
    {
        return array_map([$this, 'required'], $files);
    }

    /**
     * @param callable $callable
     *
     * @return Application
     */
    public function add(callable $callable) : Application
    {
        call_user_func_array([$this->getService()->getApp(), __FUNCTION__], func_get_args());
        return $this;
    }

    /**
     * @return ResponseInterface
     */
    public function run() : ResponseInterface
    {
        return call_user_func_array([$this->getService()->getApp(), __FUNCTION__], func_get_args());
    }

    /**
     * @param mixed $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call($name, array $arguments)
    {
        return call_user_func_array([$this->getService()->getApp(), $name], $arguments);
    }

    /**
     * @param mixed $name
     *
     * @return bool
     */
    public function __isset($name) : bool
    {
        return isset($this->getContainer()[$name]);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getContainer()[$name];
    }

    /**
     * @param mixed $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $container = $this->getContainer();
        $container[$name] = $value;
    }

    /**
     * @param mixed $name
     */
    public function __unset($name)
    {
        $container = $this->getContainer();
        unset($container[$name]);
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     *
     * @return mixed
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        return call_user_func_array([$this->getService()->getApp(), '__invoke'], func_get_args());
    }
}
