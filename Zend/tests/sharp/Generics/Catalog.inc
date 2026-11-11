<?php declare(strict_types=1); namespace Paging;

class Catalog
{
    /** @return list<Order> */
    public static function orders(): array { return [new Order(1), new Order(2)]; }

    public static function paged(): int { return (new PaginatedList(Catalog::orders()))->first()->id; }

    public static function listed(): int { return [${'receiver#1'} = new Repository(), \Sharp\Internal\returned(${'receiver#1'}->list(new OrderQuery(Catalog::orders())), ${'receiver#1'}, 'list', 'Paging.PaginatedList<Paging.Order>', 'Paging.Repository.list()')][1]->first()->id; }

    public static function written(): int { return Store::pick(Catalog::orders(), 2)?->id ?? 0; }

    public static function inferred(): int { return Store::pick(Catalog::orders(), 2)?->id ?? 0; }

    public static function headed(): int { return (new OrderPage(Catalog::orders()))->first()->id; }
    /** @param Feed<Order> $feed */
    public static function newest(Feed $feed): int { return $feed->next()->id; }

    public static function fed(): int { return Catalog::newest(new Feed(Catalog::orders())); }
    /** @param Validator<DatabaseEntity> $validator */
    public static function check(Validator $validator, Order $order): bool { return \Sharp\Internal\returned($validator->validate($order), $validator, 'validate', 'bool', 'Paging.Validator.validate()'); }

    public static function checked(): bool { return Catalog::check(new EntityValidator(), new Order(3)); }

    public static function firstId(): int { return (new Store())->first(Catalog::orders())->id; }

    public static function shared(): string { return (new Store())->share(new Order(4)); }

    public static function loaded(): int
    {
        $store = new Store();
        $store->save(Order::class, new Order(5));
        return $store->load(\Sharp\Internal\returned($store->kind("order"), $store, 'kind', 'Class<Paging.DatabaseEntity>', 'Paging.Store.kind()'), 5)?->id ?? 0;
    }
}
