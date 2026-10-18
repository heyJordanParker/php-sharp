<?php declare(strict_types=1); namespace Paging;

class Store
{
    /** @var \Sharp\Map<string, DatabaseEntity> */
    #[\Sharp\Crossing(['map', 'string', null])] private \Sharp\Map $saved;
    /** @var \Sharp\Map<string, class-string<DatabaseEntity>> */
    #[\Sharp\Crossing(['map', 'string', null])] private \Sharp\Map $kinds;

    /** @template TItem of DatabaseEntity
     *  @param list<TItem> $items
     *  @return TItem|null */
    public static function pick(array $items, int $id): ?DatabaseEntity
    {
        foreach ($items as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }
        return null;
    }

    /** @template T
     *  @param list<T> $items
     *  @return T */
    public function first(array $items) { return $items[0]; }

    /** @template TItem of DatabaseEntity&Shareable
     *  @param TItem $item */
    public function share(DatabaseEntity&Shareable $item): string { return ((object) $item)->link(); }
    /** @param class-string<DatabaseEntity> $type */
    public function save(string $type, DatabaseEntity $entity): void { $this->saved[\sprintf("%s#%d", $type, $entity->id)] = $entity; }
    /** @param class-string<DatabaseEntity> $type */
    public function load(string $type, int $id): ?DatabaseEntity { return $this->saved[\sprintf("%s#%d", $type, $id)] ?? null; }
    /** @return class-string<DatabaseEntity> */
    public function kind(string $name): string { return $this->kinds[$name] ?? Order::class; }
}
