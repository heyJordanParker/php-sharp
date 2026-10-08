<?php declare(strict_types=1); namespace Paging;

abstract class DatabaseEntity
{
    public function __construct(public readonly int $id)
    {
    }
}

interface Shareable
{
    public function link(): string;
}

class Order extends DatabaseEntity implements Shareable
{
    public function link(): string { return \sprintf("/orders/%d", $this->id); }
}
/** @template TItem */
interface Query
{
    /** @return list<TItem> */
    public function rows(): array;
}
/** @implements Query<Order> */
class OrderQuery implements Query
{
    /** @param list<Order> $orders */
    public function __construct(private array $orders)
    {
    }

    public function rows(): array { return $this->orders; }
}
/** @template TItem of DatabaseEntity */
class PaginatedList
{
    /** @param list<TItem> $rows */
    public function __construct(private array $rows)
    {
    }
    /** @return TItem */
    public function first(): DatabaseEntity { return $this->rows[0]; }
}
/** @extends PaginatedList<Order> */
class OrderPage extends PaginatedList
{
}

class Repository
{
    /** @template TItem of DatabaseEntity
     *  @param Query<TItem> $query
     *  @return PaginatedList<TItem> */
    public function list(Query $query): PaginatedList { return new PaginatedList(((object) $query)->rows()); }
}
/** @template-covariant TItem of DatabaseEntity */
class Feed
{
    /** @param list<TItem> $items */
    public function __construct(private array $items)
    {
    }
    /** @return TItem */
    public function next(): DatabaseEntity { return $this->items[0]; }
}
/** @template-contravariant TItem of DatabaseEntity */
interface Validator
{
    /** @param TItem $item */
    public function validate(DatabaseEntity $item): bool;
}
/** @implements Validator<DatabaseEntity> */
class EntityValidator implements Validator
{
    public function validate(DatabaseEntity $item): bool { return $item->id > 0; }
}

class Store
{
    /** @var array<string, DatabaseEntity> */
    private array $saved = [];
    /** @var array<string, class-string<DatabaseEntity>> */
    private array $kinds = ["order" => Order::class];

    /** @template TItem of DatabaseEntity
     *  @param list<TItem> $items
     *  @return TItem|null */
    public static function pick(array $items, int $id): ?DatabaseEntity
    {
        foreach ($items as $item) {
            if ($item->id == $id) {
                return $item;
            }
        }
        return null;
    }

    /** @template T
     *  @param list<T> $items
     *  @return T */
    public function first(array $items): mixed { return $items[0]; }

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

class Catalog
{
    /** @return list<Order> */
    public static function orders(): array { return [new Order(1), new Order(2)]; }

    public static function paged(): int { return ((object) new PaginatedList(Catalog::orders()))->first()->id; }

    public static function listed(): int { return ((object) ((object) new Repository())->list(new OrderQuery(Catalog::orders())))->first()->id; }

    public static function written(): int { return Store::pick(Catalog::orders(), 2)?->id ?? 0; }

    public static function inferred(): int { return Store::pick(Catalog::orders(), 2)?->id ?? 0; }

    public static function headed(): int { return ((object) new OrderPage(Catalog::orders()))->first()->id; }
    /** @param Feed<DatabaseEntity> $feed */
    public static function newest(Feed $feed): int { return ((object) $feed)->next()->id; }

    public static function fed(): int { return Catalog::newest(new Feed(Catalog::orders())); }
    /** @param Validator<Order> $validator */
    public static function check(Validator $validator, Order $order): bool { return ((object) $validator)->validate($order); }

    public static function checked(): bool { return Catalog::check(new EntityValidator(), new Order(3)); }

    public static function firstId(): int { return ((object) new Store())->first(Catalog::orders())->id; }

    public static function shared(): string { return ((object) new Store())->share(new Order(4)); }

    public static function loaded(): int
    {
        $store = new Store();
        ((object) $store)->save(Order::class, new Order(5));
        return ((object) $store)->load(((object) $store)->kind("order"), 5)?->id ?? 0;
    }
}
