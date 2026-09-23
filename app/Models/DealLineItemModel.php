<?php

namespace App\Models;

class DealLineItemModel extends AppModel
{
    protected $table         = 'deal_line_items';
    protected $allowedFields = ['deal_id', 'product_id', 'name', 'quantity', 'unit_price', 'discount_percent', 'tax_rate', 'total', 'position'];
    protected $useTimestamps = false;
    protected array $casts = [
        'id' => 'int',
        'quantity' => 'float',
        'unit_price' => 'float',
        'discount_percent' => 'float',
        'tax_rate' => 'float',
        'total' => 'float',
        'position' => 'int',
        'product_id' => '?int',
        'deal_id' => 'int',
    ];
}
