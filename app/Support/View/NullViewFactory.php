<?php

declare(strict_types=1);

namespace Phlag\Support\View;

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

    public function file($path, $data = [], $mergeData = [])
    {
        throw new LogicException('View rendering is not supported in this application.');
    }

    public function make($view, $data = [], $mergeData = [])
    {
        throw new LogicException('View rendering is not supported in this application.');
    }

    public function share($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $nestedKey => $nestedValue) {
                $this->shared[$nestedKey] = $nestedValue;
            }

            return $this->shared;
        }

        return $this->shared[$key] = $value;
    }

    public function composer($views, $callback)
    {
        return [];
    }

    public function creator($views, $callback)
    {
        return [];
    }

    public function addNamespace($namespace, $hints)
    {
        return $this;
    }

    public function replaceNamespace($namespace, $hints)
    {
        return $this;
    }
}
