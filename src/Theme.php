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
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Uri;

/**
 * Class Theme
 * @package PentagonalProject\SlimService
 */
class Theme
{
    /**
     * @var Config
     */
    protected $themeDetails;

    /**
     * @var string
     */
    protected $extension = '.phtml';

    /**
     * @var int[]
     */
    protected $loaded = [];

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var bool
     */
    public $isSearch = false;

    /**
     * @var bool
     */
    public $isAdminArea = false;

    /**
     * @var callable
     */
    protected $beforeFirstTimeLoadCallBack;

    /**
     * @var string
     */
    private $cachedPath;

    /**
     * Theme constructor.
     *
     * @param array $list
     */
    public function __construct(array $list)
    {
        $this->themeDetails = new Config($list);
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer() : ContainerInterface
    {
        return $this->container;
    }

    /**
     * @param callable $callable
     */
    public function setBeforeLoadCallBack(callable $callable)
    {
        $this->beforeFirstTimeLoadCallBack = $callable;
    }

    /**
     * @return bool
     */
    public function is404() : bool
    {
        /**
         * @var Response $response
         */
        $response = $this->container['response'];
        return $response->isNotFound();
    }

    /**
     * @return bool
     */
    public function isAdminArea()
    {
        return isset($this->isAdminArea) && $this->isAdminArea === true;
    }

    /**
     * @return bool
     */
    public function isSearch()
    {
        return isset($this->isSearch) && $this->isSearch=== true;
    }

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $path
     * @return string
     */
    public function getBaseUrl($path = '')
    {
        /**
         * @var Request $request
         * @var Uri $uri
         */
        $request =  $this->getContainer()['request'];
        $uri = $request->getUri();
        $uri = rtrim($uri->getBaseUrl(), '/'). '/';
        if (!is_null($path) && ! is_bool($path) && ! is_string($path) && !is_numeric($path)) {
            $path = is_resource($path) ? '' : gettype($path);
        }

        $path = (string) $path;
        return $uri . ltrim($path, '/');
    }

    /**
     * @param string $path
     *
     * @return string
     */
    public function getThemeUrl($path = '')
    {
        if (!is_null($path) && ! is_bool($path) && ! is_string($path) && !is_numeric($path)) {
            $path = is_resource($path) ? '' : gettype($path);
        }

        $path = (string) $path;
        if (!isset($this->cachedPath)) {
            $this->cachedPath = substr($this->getThemePath(), strlen(dirname($_SERVER['SCRIPT_FILENAME'])));
        }

        $path = $this->cachedPath . ltrim($path, '/');
        return $this->getBaseUrl($path);
    }

    /**
     * @param string $file
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function loadResponse(string $file, ResponseInterface $response) : ResponseInterface
    {
        return $this->includeResponse('load', $file, $response);
    }

    /**
     * @param string $method
     * @param string $file
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    private function includeResponse(string $method, string $file, ResponseInterface $response) : ResponseInterface
    {
        ob_start();
        $content = ob_get_clean();
        $this->$method($file);
        $body = $response->getBody();
        $body->write($content);
        return $response->withBody($body);
    }

    /**
     * @param string $file
     *
     * @return mixed
     */
    public function load(string $file)
    {
        $file = $this->sanitizeFileName($file);
        if (empty($this->loaded)
            && !empty($this->beforeFirstTimeLoadCallBack)
            && $this->isValid()
        ) {
            $callback = $this->beforeFirstTimeLoadCallBack;
            $callback($this);
            $this->beforeFirstTimeLoadCallBack = false;
        }

        $this->loaded[$file] = isset($this->loaded[$file])
            ? $this->loaded[$file]+1
            : 1;
        return RequireBinding::with(($this->getThemePath() . $file), $this);
    }

    /**
     * @param string $file
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function loadIgnoreResponse(string $file, ResponseInterface $response) : ResponseInterface
    {
        return $this->includeResponse('loadIgnore', $file, $response);
    }

    /**
     * @param string $file
     *
     * @return bool|mixed
     */
    public function loadIgnore(string $file)
    {
        if (file_exists($this->getThemePath() . $this->sanitizeFileName($file))) {
            return $this->load($file);
        }

        return false;
    }

    /**
     * @param string $file
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function onceResponse(string $file, ResponseInterface $response) : ResponseInterface
    {
        return $this->includeResponse('once', $file, $response);
    }

    /**
     * @param string $file
     *
     * @return bool|mixed
     */
    public function once(string $file)
    {
        if (!$this->hasLoaded($file)) {
            return $this->load($file);
        }

        return false;
    }

    /**
     * @param string $file
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function onceIgnoreResponse(string $file, ResponseInterface $response) : ResponseInterface
    {
        return $this->includeResponse('onceIgnore', $file, $response);
    }

    /**
     * @param string $file
     *
     * @return bool|mixed
     */
    public function onceIgnore(string $file)
    {
        if (!$this->hasLoaded($file)) {
            return $this->loadIgnore($file);
        }

        return false;
    }

    /**
     * @param string $file
     *
     * @return bool
     */
    public function hasLoaded(string $file) : bool
    {
        $file = $this->sanitizeFileName($file);
        return isset($this->loaded[$file]);
    }

    /**
     * @param string $file
     *
     * @return mixed|string
     */
    public function sanitizeFileName(string $file)
    {
        $file = preg_replace('/(\\\|\/)+/', DIRECTORY_SEPARATOR, $file);
        $file = ltrim($file, DIRECTORY_SEPARATOR);
        if (substr($file, -6) !== $this->extension) {
            $file .= $this->extension;
        }
        return $file;
    }

    /**
     * @return bool
     */
    public function isValid() : bool
    {
        return $this->themeDetails->get(ThemeCollection::THEME_VALIDATION_NAME) === ThemeCollection::THEME_VALID;
    }

    /**
     * @return string
     */
    public function getBaseName()
    {
        return $this->themeDetails->get(ThemeCollection::THEME_BASE_NAME);
    }

    /**
     * Alias Theme Path
     * @return string
     */
    public function getThemeDir()
    {
        return $this->getThemePath();
    }

    /**
     * @return string
     */
    public function getThemePath()
    {
        return $this->themeDetails->get(ThemeCollection::THEME_PATH_NAME);
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        return $this->themeDetails->get(ThemeCollection::THEME_INFO_NAME);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name) : bool
    {
        return $this->themeDetails->offsetExists($name);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->themeDetails->get($name);
    }

    /**
     * @return mixed
     */
    public function toArray()
    {
        return $this->themeDetails->toArray();
    }
}
