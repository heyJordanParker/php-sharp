--TEST--
PHP# SortedMap and SortedSet keep their keys in order, as Rust's BTreeMap and BTreeSet, with every member a Map and a Set have and first, last, floor, ceiling and range, by the key's operator <=> or a comparison, and PriorityQueue gives the lowest priority first, as .NET's
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Int.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Math.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/ListMembers.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/MapMembers.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Text/StringMembers.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Collections/SortedMap.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Collections/SortedSet.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Collections/PriorityQueue.sharp';
require __DIR__ . '/SortedKeys/Version.sharp';
require __DIR__ . '/SortedKeys/Crate.sharp';
require __DIR__ . '/SortedQueries.sharp';

use Demo\SortedQueries;

function refused(Closure $call): void
{
    try {
        $call();
    } catch (ValueError|OutOfRangeException|UnderflowException $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

echo implode("\n", SortedQueries::reads()), "\n";
echo implode("\n", SortedQueries::writes()), "\n";
echo implode("\n", SortedQueries::versions()), "\n";
echo implode(',', SortedQueries::descending()), "\n";
echo implode("\n", SortedQueries::sets()), "\n";
echo implode("\n", SortedQueries::cleared()), "\n";
echo implode("\n", SortedQueries::queue()), "\n";
echo implode(',', SortedQueries::latestFirst()), "\n";
refused(fn () => SortedQueries::emptyQueue());
refused(fn () => SortedQueries::emptyPeek());
refused(fn () => SortedQueries::emptyMap());
refused(fn () => SortedQueries::emptySet());
refused(fn () => SortedQueries::unordered());
?>
--EXPECT--
4 false true 0 -1 5
true false apple,banana,fig,pear 5,2,0,4
5 5 -1 4 true
banana fig apple none none
banana=2,fig=0 apple=5,pear=4
11 50,20,0,40 5,4,2,0
apple:5,banana:2,fig:0,pear:4 2 4
true false 7 5
apple=5,banana=2,cherry=7,pear=4
0.9,1.2,1.10
beta,two again,ten
1.2
cy,bo,ann
added,added,added,held,added 1,3,5,9 4 true false
1 5 9 3 5 -1 -1
3,5 0 3,5,9
1,3,4,5,9 3,5 1,9
true false true false 9 true
-1 2,6,10 5,3,1 true
true 0
5 plan 0 true
plan,write,review,test,ship
new,mid,old
UnderflowException: PriorityQueue.dequeue: the queue is empty
UnderflowException: PriorityQueue.peek: the queue is empty
OutOfRangeException: SortedMap.first: the map is empty
OutOfRangeException: SortedSet.last: the set is empty
ValueError: Demo\Crate declares no operator <=>, so it has no order: give the collection a comparison
