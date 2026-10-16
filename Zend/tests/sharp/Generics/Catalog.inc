<?php declare(strict_types=1); namespace Paging;

class Catalog
{
    /** @return list<Order> */
    public static function orders(): array { return [new Order(1), new Order(2)]; }

    public static function paged(): int { return ((object) new PaginatedList(Catalog::orders()))->first()->id; }

    public static function listed(): int { return ((object) ((object) new Repository())->list(new OrderQuery(Catalog::orders())))->first()->id; }

    public static function written(): int { return Store::pick(Catalog::orders(), 2)?->id ?? 0; }

    public static function inferred(): int { return Store::pick(Catalog::orders(), 2)?->id ?? 0; }

    public static function headed(): int { return ((object) new OrderPage(Catalog::orders()))->first()->id; }
    /** @param Feed<Order> $feed */
    public static function newest(Feed $feed): int { return ((object) $feed)->next()->id; }

    public static function fed(): int { return Catalog::newest(new Feed(Catalog::orders())); }
    /** @param Validator<DatabaseEntity> $validator */
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
