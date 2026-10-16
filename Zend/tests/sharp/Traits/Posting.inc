<?php declare(strict_types=1); namespace Demo;

use Lib\HasUniqueStringIds;
use Lib\Persisted;
use Lib\SoftDeletes;

class Posting extends Persisted
{   use SoftDeletes, HasUniqueStringIds;
}
