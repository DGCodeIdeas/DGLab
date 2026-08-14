<?php

namespace DGLab\Services\Superpowers\Runtime;

/**
 * Class Runtime
 *
 * Provides runtime helpers for SuperPHP expressions.
 */
class Runtime
{
    /**
     * Intelligently access an array key or object property.
     *
     * @param mixed $target
     * @param string $key
     * @param bool $nullSafe
     * @return mixed
     */
    public static function access(mixed $target, string $key, bool $nullSafe = false): mixed
    {
        if ($target === null) {
            return null;
        }

        if (is_array($target)) {
            if (array_key_exists($key, $target)) {
                return $target[$key];
            }
            return null;
        }

        if (is_object($target)) {
            if (isset($target->$key)) {
                return $target->$key;
            }
            if (property_exists($target, $key)) {
                return $target->$key;
            }
            if (method_exists($target, $key)) {
                return $target->$key();
            }
            if (method_exists($target, 'get' . ucfirst($key))) {
                return $target->{'get' . ucfirst($key)}();
            }
        }

        return null;
    }
}
