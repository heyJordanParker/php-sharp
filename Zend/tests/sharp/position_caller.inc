<?php

// Prints each position the Callers fixture's calls give, as `file line:column function`, then the ones a plain PHP
// caller gets, which are the positions of the parameters' declarations.
function print_positions(iterable $positions): void
{
    foreach ($positions as $position) {
        echo basename($position->file), ' ', $position->line, ':', $position->column, ' ', $position->function, "\n";
    }
}

print_positions(Callers\Desk::calls());
echo "== a plain PHP caller\n";
print_positions([Callers\Reports::logSlow('plain PHP'), (new Callers\Reports())->seen(1), (new Callers\Note())->made]);
