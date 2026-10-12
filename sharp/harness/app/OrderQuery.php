<?php

namespace App;

// What Order.query() gives, as Eloquent's builder does: cursor() reads rows one at a time.
final class OrderQuery
{
    /** @return iterable<Order> */
    public function cursor(): iterable
    {
        yield new Order();
    }
}
