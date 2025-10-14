<?php

declare(strict_types=1);

namespace Phlag\Support\View;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\View\Factory as FactoryContract;
use LogicException;

final class NullViewFactory implements FactoryContract
{
    /**
     * Stored shared data for compatibility; not used.
     *
     * @var array<string, mixed>
     */
    private array $shared = [];

    public function exists($view)
    {
        return false;
    }

    /**
     * @param  Arrayable<string, mixed>|array<string, mixed>  $data
     * @param  array<string, mixed>  $mergeData
     */
    public function file($path, $data = [], $mergeData = [])
    {
        throw new LogicException('View rendering is not supported in this application.');
    }

    /**
     * @param  Arrayable<string, mixed>|array<string, mixed>  $data
     * @param  array<string, mixed>  $mergeData
     */
    public function make($view, $data = [], $mergeData = [])
    {
        throw new LogicException('View rendering is not supported in this application.');
    }

    /**
     * @param  array<string, mixed>|string  $key
     * @param  mixed  $value
     * @return array<string, mixed>|mixed
     */
    public function share($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $nestedKey => $nestedValue) {
                $this->shared[(string) $nestedKey] = $nestedValue;
            }

            return $this->shared;
        }

        return $this->shared[(string) $key] = $value;
    }

    /**
     * @param  array<int, string>|string  $views
     * @param  callable|string  $callback
     * @return array<int, mixed>
     */
    public function composer($views, $callback)
    {
        return [];
    }

    /**
     * @param  array<int, string>|string  $views
     * @param  callable|string  $callback
     * @return array<int, mixed>
     */
    public function creator($views, $callback)
    {
        return [];
    }

    /**
     * @param  array<int, string>|string  $hints
     * @return $this
     */
    public function addNamespace($namespace, $hints)
    {
        return $this;
    }

    /**
     * @param  array<int, string>|string  $hints
     * @return $this
     */
    public function replaceNamespace($namespace, $hints)
    {
        return $this;
    }
}
