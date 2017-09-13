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
use Slim\App;
use Slim\Container;

/**
 * Class ServiceLocator
 * @package PentagonalProject\SlimService
 */
class ServiceLocator implements \ArrayAccess
{
    /**
     * @var \ArrayObject|ServiceLocator[]
     */
    private static $serviceCollections;

    /**
     * @var string
     */
    private static $default;

    /**
     * @var App
     */
    protected $app;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|bool
     */
    private static $defaultAppChange = false;

    /**
     * ServiceLocator constructor.
     *
     * @param App $container
     * @param string $appName
     */
    private function __construct(App $container, string $appName = 'default')
    {
        if (!isset(self::$serviceCollections)) {
            self::$serviceCollections = new \ArrayObject();
            $new = true;
            self::$default = $appName;
        }

        if (!isset($new) && self::exist($appName)) {
            throw new \RuntimeException(
                sprintf(
                    'Duplicate service for %s',
                    $appName
                ),
                E_WARNING
            );
        }
        $this->name                         = $appName;
        $this->app                          = $container;
        self::$serviceCollections[$appName] = $this;
    }

    /**
     * @return ServiceLocator[]
     */
    public static function getServices()
    {
        return self::$serviceCollections;
    }

    /**
     * @return App
     */
    public function &getApp() : App
    {
        return $this->app;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer() : ContainerInterface
    {
        return $this->getApp()->getContainer();
    }

    /**
     * @return ServiceLocator
     */
    public static function getDefault() : ServiceLocator
    {
        if (!isset(self::$serviceCollections)) {
            $service = self::create(new App());
            return $service->getDefault();
        }

        return self::$serviceCollections[self::$default];
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        if ($this->name === $name) {
            return;
        }

        if (self::exist($name)) {
            throw new \RuntimeException(
                sprintf('Service %s is exist!', $name),
                E_WARNING
            );
        }

        self::$default = $name;
        self::remove($this->name);
        $this->name = $name;
        self::$serviceCollections[$this->name] = $this;
    }

    /**
     * @param ServiceLocator $locator
     */
    public static function setDefault(ServiceLocator $locator)
    {
        if (self::$default && self::$default != $locator->getName()) {
            self::$defaultAppChange = self::$default;
        }

        self::$default = $locator->getName();
    }

    /**
     * @param App $app
     * @param string|null $appName
     *
     * @return ServiceLocator
     */
    public static function create(App $app, string $appName = null) : ServiceLocator
    {
        if ($appName === null) {
            $default = 'default';
            $appName = $default;
            if (self::$serviceCollections && isset(self::$serviceCollections[$default])) {
                $c = 0;
                while (true) {
                    if (!isset(self::$serviceCollections["{$default}_{$c}"])) {
                        $appName = "{$default}_{$c}";
                        break;
                    }
                }
            }
        }

        return new ServiceLocator($app, $appName);
    }

    /**
     * @param string $appName
     *
     * @return bool
     */
    public static function exist(string $appName) : bool
    {
        return isset(self::$serviceCollections[$appName]);
    }

    /**
     * @param string $appName
     *
     * @return bool
     */
    public static function remove(string $appName) : bool
    {
        if (self::exist($appName)) {
            if ($appName === self::$default) {
                throw new \RuntimeException(
                    sprintf('Can not remove default service : %s!', $appName),
                    E_WARNING
                );
            }

            unset(self::$serviceCollections[$appName]);
            $service = reset(self::$serviceCollections);
            if (!$service) {
                self::$serviceCollections = new \ArrayObject();
                return true;
            }

            self::setDefault($service);
            return true;
        }

        return false;
    }

    /**
     * @param string $appName
     *
     * @return ServiceLocator
     */
    public static function getService(string $appName = null) : ServiceLocator
    {
        if ($appName == null) {
            return self::getDefault();
        }

        if (! self::exist($appName)) {
            throw new \RuntimeException(
                sprintf(
                    'Service %s has not found.',
                    $appName
                ),
                E_WARNING
            );
        }

        return self::$serviceCollections[$appName];
    }

    /**
     * @param null $appName
     *
     * @return ServiceLocator
     */
    private static function determineService($appName = null) : ServiceLocator
    {
        if ($appName === null) {
            $service = !isset(self::$serviceCollections)
                ? new ServiceLocator(new App())
                : self::getDefault();
            return $service;
        }

        return self::getService($appName);
    }

    /**
     * @param string $name
     * @param \Closure $closure
     * @param string|null $appName
     *
     * @return ServiceLocator
     */
    public static function inject(string $name, \Closure $closure, string $appName = null) : ServiceLocator
    {
        $service = self::determineService($appName);
        $service[$name] = $closure;
        return $service;
    }

    /**
     * @param string $name
     * @param \Closure $closure
     * @param string|null $appName
     *
     * @return ServiceLocator
     */
    public static function override(string $name, \Closure $closure, string $appName = null) : ServiceLocator
    {
        $service = self::determineService($appName);
        if (isset($service[$name])) {
            unset($service[$name]);
        }
        $service[$name] = $closure;
        return $service;
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset) : bool
    {
        return isset($this->getContainer()[$offset]);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->getContainer()[$offset]);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->getContainer()->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->getContainer()[$offset] = $value;
    }

    /**
     * @param mixed $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this[$name];
    }

    /**
     * @param string $name
     * @param \Closure $value
     */
    public function __set($name, $value)
    {
        $this[$name] = $value;
    }

    /**
     * @param mixed $name
     *
     * @return bool
     */
    public function __isset($name) : bool
    {
        return isset($this[$name]);
    }

    /**
     * @param mixed $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call($name, array $arguments)
    {
        return call_user_func_array([$this->getApp(), $name], $arguments);
    }
}
