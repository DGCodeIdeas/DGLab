<?php

namespace DGLab\Core;

class Config
{
    /**
     * The loaded configuration items.
     *
     * @var array
     */
    protected array $items = [];

    /**
     * The path to the configuration directory.
     *
     * @var string
     */
    protected string $configPath;

    /**
     * Create a new configuration instance.
     *
     * @param string $configPath
     */
    public function __construct(string $configPath)
    {
        $this->configPath = rtrim($configPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Get the specified configuration value.
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = $parts[0];

        if (!isset($this->items[$file])) {
            $this->load($file);
        }

        $array = $this->items[$file];

        // Remove the file name from parts
        array_shift($parts);

        if (empty($parts)) {
            return $array;
        }

        foreach ($parts as $part) {
            if (is_array($array) && array_key_exists($part, $array)) {
                $array = $array[$part];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Load the configuration file.
     *
     * @param string $file
     * @return void
     */
    protected function load(string $file): void
    {
        $path = $this->configPath . $file . '.php';

        if (file_exists($path)) {
            $this->items[$file] = require $path;
        } else {
            $this->items[$file] = [];
        }
    }
}
