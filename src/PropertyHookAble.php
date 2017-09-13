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

/**
 * Class PropertyHookAble
 * @package PentagonalProject\SlimService
 */
class PropertyHookAble
{
    const PREFIX = __CLASS__;

    /**
     * @var Hook
     */
    protected $hook;

    /**
     * @var array
     */
    protected $called = [];

    /**
     * PropertyHookAble constructor.
     *
     * @param Hook $hook
     */
    public function __construct(Hook $hook)
    {
        $this->hook = $hook;
    }

    /**
     * @param string $name
     * @param $value
     * @param array ...$param
     */
    public function set(string $name, $value, ...$param)
    {
        $args = func_get_args();
        $args[0] = self::PREFIX . $name;
        $this->called[$name] = [
            'called'   => false,
            'value' => $args
        ];
    }

    /**
     * @param string $name
     */
    public function remove(string $name)
    {
        unset($this->called[$name]);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name) : bool
    {
        return array_key_exists($name, $this->called);
    }

    /**
     * @param string $name
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        if (! $this->has($name)) {
            return $default;
        }

        if (isset($this->called[$name]['called'])) {
            $this->called[$name] = [
                'value' => call_user_func_array([$this->hook, 'apply'], $this->called[$name]['value'])
            ];
        }

        return $this->called[$name]['value'];
    }

    /**
     * @param string $name
     * @param null $default
     * @param array ...$param
     *
     * @return mixed
     */
    public function getOrApply(string $name, $default = null, ...$param)
    {
        if (! $this->has($name)) {
            call_user_func_array([$this, 'set'], func_get_args());
        }

        if (isset($this->called[$name]['called'])) {
            $this->called[$name] = [
                'value' => call_user_func_array([$this->hook, 'apply'], $this->called[$name]['value'])
            ];
        }

        return $this->called[$name]['value'];
    }
}
