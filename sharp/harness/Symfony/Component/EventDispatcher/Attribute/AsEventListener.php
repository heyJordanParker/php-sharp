<?php

namespace Symfony\Component\EventDispatcher\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AsEventListener
{
}
