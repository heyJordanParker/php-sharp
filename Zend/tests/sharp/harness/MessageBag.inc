<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP class whose array<V> holds a list, as a validator's message bag gives each field's messages. */
final class MessageBag
{
    /** @return array<string, array<string>> */
    public function messages(): array
    {
        return ['price' => ['The price must be at least 0.']];
    }
}
