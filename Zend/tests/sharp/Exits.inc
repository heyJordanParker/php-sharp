<?php declare(strict_types=1); namespace Demo;

class Exits
{
    public function __construct(private \Sharp\Process $process, private \Sharp\Console $console) { }

    public function stop(int $code): void
    {
        try {
            $this->process->exit($code);
        } finally {
            $this->console->write("finally\n");
        }
    }
}
