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

use Slim\Collection;

/**
 * Class ThemeCollection
 * @package PentagonalProject\SlimService
 */
class ThemeCollection
{
    const THEME_VALID = true;
    const THEME_INVALID = false;

    const INVALID_REASON_INFO_INVALID     = -2;
    const INVALID_REASON_INFO_NOT_EXISTS  = -3;
    const INVALID_REASON_INCOMPLETE       = -4;
    const FILE_INFO = 'theme.ini';

    /**
     * @var string
     */
    protected $themeDir;

    /**
     * @var string
     */
    protected $activeTheme;

    /**
     * @var array
     */
    protected $listFiles = [];

    /**
     * @var Collection|Theme[]
     */
    protected $themeLists;

    /**
     * @var string[]
     */
    protected $validThemes = [];

    /**
     * @var array
     */
    protected $mustBeExists = [
        self::FILE_INFO => self::INVALID_REASON_INFO_NOT_EXISTS,
        '401.phtml' => self::INVALID_REASON_INCOMPLETE,
        '404.phtml' => self::INVALID_REASON_INCOMPLETE,
        '405.phtml' => self::INVALID_REASON_INCOMPLETE,
        '500.phtml' => self::INVALID_REASON_INCOMPLETE,
        'index.phtml' => self::INVALID_REASON_INCOMPLETE,
        'post.phtml' => self::INVALID_REASON_INCOMPLETE,
        'page.phtml' => self::INVALID_REASON_INCOMPLETE,
        'header.phtml' => self::INVALID_REASON_INCOMPLETE,
        'footer.phtml' => self::INVALID_REASON_INCOMPLETE,
    ];

    const THEME_BASE_NAME  = 'theme_base';
    const THEME_PATH_NAME  = 'theme_path';
    const THEME_VALIDATION_NAME = 'theme_validation';
    const THEME_INFO_NAME  = 'theme_info';
    const THEME_CORRUPT_NAME  = 'theme_corrupt';
    const WARNING_INFO      = 'theme_warning';

    /**
     * @var string
     */
    protected $themeClass = Theme::class;

    /**
     * ThemeCollection constructor.
     *
     * @param string $themeDir
     * @param string $themeClass
     */
    public function __construct(string $themeDir, string $themeClass = Theme::class)
    {
        $this->themeDir = realpath($themeDir) ?: $themeDir;
        $this->themeDir = preg_replace('/(\\\|\/)+/', DIRECTORY_SEPARATOR, $this->themeDir);
        $this->themeDir = rtrim($this->themeDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->themeClass = $themeClass;
        if (!is_dir($themeDir)) {
            throw new \RuntimeException(
                sprintf(
                    'Invalid theme directory, directory %s does not exists.'
                ),
                E_WARNING
            );
        }

        $this->init();
    }

    /**
     * @return string
     */
    public function getThemesDir(): string
    {
        return $this->themeDir;
    }

    /**
     * @return null|string
     */
    public function getActiveThemeDir()
    {
        return rtrim(
            $this->getThemesDir() . $this->getActiveThemeBasePath(),
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR;
    }

    /**
     * @return null|string
     */
    public function getActiveThemeBasePath()
    {
        return $this->activeTheme;
    }

    /**
     * @return null|Theme
     */
    public function getActiveTheme()
    {
        return $this->activeTheme
            ? $this->themeLists[$this->activeTheme]
            : null;
    }

    /**
     * @param string $name
     *
     * @return ThemeCollection
     */
    public function setActiveTheme(string $name) : ThemeCollection
    {
        if (!$this->isThemeIsValid($name)) {
            $exist = $this->isThemeExists($name);
            throw new \RuntimeException(
                sprintf(
                    $exist ? 'ThemeCollection %s is not valid' : 'ThemeCollection %s is not exists.',
                    $name
                )
            );
        }

        $this->activeTheme = $name;
        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function isThemeExists(string $name) : bool
    {
        return isset($this->themeLists[$name]);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function isThemeIsValid(string $name) : bool
    {
        return isset($this->validThemes[$name]);
    }

    /**
     * @return Collection|Theme[]
     */
    public function getThemeList() : Collection
    {
        $theme = clone $this->themeLists;
        return $theme;
    }

    /**
     * @return Collection
     */
    public function getValidThemeList(): Collection
    {
        $collections = new Collection();
        foreach ($this->validThemes as $key => $status) {
            $collections[$key] = $this->themeLists[$key];
        }

        return $collections;
    }

    /**
     * Init
     */
    protected function init()
    {
        $themeClass = $this->themeClass;
        $list = [];
        foreach (new \DirectoryIterator($this->getThemesDir()) as $iterator) {
            $name = $iterator->getFilename();
            if (in_array($name, ['.', '..'])) {
                continue;
            }
            if (! $iterator->isDir()) {
                if ($iterator->isFile()) {
                    $this->listFiles[] = $name;
                }
                continue;
            }

            $path = rtrim($iterator->getRealPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $list[$name] = [
                self::THEME_VALIDATION_NAME => self::THEME_VALID,
                self::THEME_BASE_NAME => $name,
                self::THEME_PATH_NAME => $path,
                self::THEME_INFO_NAME => [],
                self::THEME_CORRUPT_NAME => [],
                self::WARNING_INFO => null
            ];

            foreach ($this->mustBeExists as $key => $reason) {
                if (!file_exists($path .  DIRECTORY_SEPARATOR . $key)) {
                    $list[$name][self::THEME_VALIDATION_NAME] = $reason;
                    $list[$name][self::THEME_CORRUPT_NAME][] = $key;
                    continue;
                }

                if ($key == self::FILE_INFO) {
                    error_clear_last();
                    $info = @parse_ini_file($path . $key);
                    $last = error_get_last();
                    if (!is_array($info) || $last) {
                        $list[$name][self::THEME_VALIDATION_NAME] = self::THEME_VALID;
                        $list[$name][self::THEME_CORRUPT_NAME][] = $key;
                        $list[$name][self::WARNING_INFO] = $last;
                        continue;
                    }
                    $list[$name][self::THEME_INFO_NAME] = $info;
                }
            }

            if ($list[$name][self::THEME_VALIDATION_NAME] === self::THEME_VALID) {
                $this->validThemes[$name] = true;
                if (!isset($this->activeTheme)) {
                    $this->activeTheme = $name;
                }
            }

            $list[$name] = new $themeClass($list[$name]);
        }

        $this->themeLists = new Collection($list);
    }
}
