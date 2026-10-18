<?php

use LoopNames\Walks;

echo 'from for-of: ', json_encode(Walks::orders([[1, 2], [3, 0, 4], [5, -1, 6], [7, 99, 8], [9]], 50)), "\n";
echo 'from for: ', json_encode(Walks::counters(5)), "\n";
echo 'from while: ', json_encode(Walks::whiles(5)), "\n";
echo 'from do: ', json_encode(Walks::dos(5)), "\n";
echo 'to a for whose counter each pass keeps: ', json_encode(Walks::fresh(5)), "\n";
