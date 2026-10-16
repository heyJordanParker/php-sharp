<?php declare(strict_types=1); namespace Store;

use Lib\Model;

class Order extends Model
{
    #[\Override] protected $table = "orders";
    #[\Override] protected $fillable = ["number", "total"];
    #[\Override] protected $with = ["customer"];
    #[\Override] public $timestamps = false;
    #[\Override] protected int $perPage = 20;

    public function tableName(): string
    {
        return $this->table;
    }

    public function pageSize(): int
    {
        return $this->perPage;
    }
}
