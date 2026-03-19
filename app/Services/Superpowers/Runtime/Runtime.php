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
     * @return mixed
     */
    public static function access(mixed $target, string $key): mixed
    {
        if ($target === null) {
            return null;
        }

        if (is_array($target)) {
            return $target[$key] ?? null;
        }

        if (is_object($target)) {
            if (isset($target->$key)) {
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
