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
use ArrayAccess;
use InvalidArgumentException;

/**
 * Class Configurator
 * @package PentagonalProject\SlimService
 */
class Configurator implements ArrayAccess
{
    /**
     * @var Collection
     */
    protected $originalCollection;

    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @var Collection
     */
    protected $lastCollection;

    /**
     * Config constructor.
     * @param array $setting
     */
    public function __construct(array $setting)
    {
        $this->collection = new Collection($setting);
        $this->originalCollection = clone $this->collection;
    }

    /**
     * Fetch from array
     *
     * Internal method used to retrieve values from global arrays.
     *
     * @param   mixed   $index   Index for item to be fetched from $array
     * @param   mixed   $default Default return if not exist
     * @return  mixed
     * @throws \ErrorException
     */
    protected function fetchFromArray($index = null, $default = null)
    {
        $array = $this->collection->all();
        if (empty($array)) {
            return $default;
        }
        if (!is_array($array)) {
            throw new \ErrorException(
                'Invalid records for array collections',
                E_ERROR
            );
        }

        // If $index is NULL, it means that the whole $array is requested
        isset($index) || $index = array_keys($array);
        // allow fetching multiple keys at once
        if (is_array($index)) {
            $output = [];
            foreach ($index as $key) {
                $output[$key] = $this->fetchFromArray($key);
            }
            return $output;
        }
        if (isset($array[$index])) {
            $value = $array[$index];
        } elseif (($count = preg_match_all('/(?:^[^\[]+)|\[[^]]*\]/', $index, $matches)) > 1) {
            // Does the index contain array notation
            $value = $array;
            for ($i = 0; $i < $count; $i++) {
                $key = trim($matches[0][$i], '[]');
                // Empty notation will return the value as array
                if ($key === '') {
                    break;
                }
                if (isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return $default;
                }
            }
        } else {
            return $default;
        }

        return $value;
    }

    /**
     * Get Config
     *
     * @param string|null $key
     * @param mixed  $default
     * @return mixed
     */
    public function get($key = null, $default = null)
    {
        if (!func_num_args()) {
            return $this->collection->all();
        }

        return $this->fetchFromArray($key, $default);
    }

    /**
     * Offset Exists
     *
     * @param string $key
     * @return bool
     */
    public function exist($key) : bool
    {
        return $this->get($key, false) !== false
               && $this->get($key, true) !== true;
    }

    /**
     * Reset Collection to default
     */
    public function restoreToDefault()
    {
        $this->lastCollection = $this->collection;
        $this->collection = clone $this->originalCollection;
    }

    /**
     * Restore to Last Configurations
     */
    public function restoreToLast()
    {
        $this->lastCollection = $this->collection;
        $this->collection = clone $this->getLastCollection();
    }

    /**
     * @return Collection
     */
    public function getCurrentCollection() : Collection
    {
        return $this->collection;
    }

    /**
     * @return Collection
     */
    public function getDefaultCollection() : Collection
    {
        return $this->originalCollection;
    }

    /**
     * @return Collection
     */
    public function getLastCollection() : Collection
    {
        return $this->lastCollection ?: $this->collection;
    }

    /**
     * @param array|string $key     string
     * @param mixed  $values  the value key name
     */
    public function set($key, $values = null)
    {
        if (is_array($key)) {
            $this->collection->replace($key);
            return;
        }

        if (!is_string($key) && !is_numeric($key)) {
            throw new InvalidArgumentException(
                'Invalid key name given! Key config must be as a string!'
            );
        }

        if (($count = preg_match_all('/(?:^[^\[]+)|\[[^]]*\]/', $key, $matches)) > 1) {
            // Does the index contain array notation
            $the_key = null;
            $matches[0] = array_reverse($matches[0]);
            $old_key = null;
            for ($i = 0; $i < $count; $i++) {
                $key = trim($matches[0][$i], '[]');
                // Empty notation will return the value as array
                if ($key === '') {
                    $the_key[] = $the_key?: $values;
                    if (count($the_key) > 1) {
                        unset($the_key[key($the_key)]);
                    }
                    continue;
                }
                if (!isset($the_key)) {
                    $the_key[$key] = $values;
                    continue;
                }

                $the_key[$key] = $the_key;
                if (count($the_key) > 1) {
                    unset($the_key[key($the_key)]);
                }
            }

            $key = key($the_key);
            $values = is_array($this->collection[$key])
                ? array_merge($this->collection[$key], $the_key[$key])
                : $the_key;
            unset($the_key);
        }

        $this->collection->set($key, $values);
    }

    /**
     * Remove Key from nested selector
     *
     * @param string $key
     */
    public function remove($key)
    {
        if (!is_string($key) && !is_numeric($key)) {
            throw new InvalidArgumentException(
                'Invalid key name given! Key config to remove must be as a string!'
            );
        }

        if (!$this->exist($key)) {
            return;
        }

        if (($count = preg_match_all('/(?:^[^\[]+)|\[[^]]*\]/', $key, $matches)) > 1) {
            $firstKey = reset($matches[0]);
            $keyName  = $firstKey;
            $tmp      = $this->collection[$keyName];
            if (!is_array($tmp)) {
                return;
            }
            array_shift($matches[0]);
            $unsetPosition = 0;
            foreach ($matches[0] as $keyNum => $keyName) {
                $keyName = trim($keyName, '[]');
                if ($unsetPosition <> $keyNum &&
                    (!is_array($tmp) || ! array_key_exists($keyName, $tmp))
                ) {
                    return;
                }

                $unsetPosition++;
                $tmp = $tmp[$keyName];
            }

            $tmp = $this->collection[$firstKey];
            $currentUnsetPosition = 0;
            // binding anonymous function to handle array reference
            $recursiveUnset = function (
                &$array,
                $unwanted_key
            ) use (
                $unsetPosition,
                &$currentUnsetPosition,
                &$recursiveUnset
            ) {
                $currentUnsetPosition++;
                if ($unsetPosition !== $currentUnsetPosition) {
                    if (array_key_exists($unwanted_key, $array)) {
                        unset($array[$unwanted_key]);
                    }
                    // stop
                    return;
                }
                foreach ($array as &$value) {
                    if (is_array($value)) {
                        $recursiveUnset($value, $unwanted_key);
                    }
                }
            };

            // call closure to binding reference
            $recursiveUnset($tmp, $keyName);
            $this->collection[$firstKey] = $tmp;
            unset($tmp);
            return; // stop
        }

        unset($this->collection[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset) : bool
    {
        return $this->exist($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        return $this->set($offset, $value);
    }

    /**
     * @param int|string $offset
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
}
