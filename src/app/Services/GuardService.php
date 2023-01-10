<?php

namespace App\Services;

use App\Models\Guard;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
class GuardService
{
    public function getGuards()
    {
        $guards = Guard::all();
        $config = [];
        foreach ($guards as $guard) {
            $name = Str::camel($guard->name);
            $config[$name] = [
                'driver' => $guard->driver,
                'provider' => $guard->provider,
            ];
        }

        return $config;
    }
}
