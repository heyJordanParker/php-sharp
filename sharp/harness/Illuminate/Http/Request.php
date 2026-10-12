<?php

namespace Illuminate\Http;

final class Request
{
    public function input(string $key): mixed
    {
        return ['qty' => '12', 'flag' => '1'][$key] ?? null;
    }

    public function getHost(): string
    {
        return 'example.com';
    }
}
