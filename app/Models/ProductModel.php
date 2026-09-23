<?php

namespace App\Models;

class ProductModel extends AppModel
{
    protected $table         = 'products';
    protected $allowedFields = ['organization_id', 'name', 'sku', 'description', 'price', 'tax_rate', 'is_active', 'image_url', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected array $casts = [
        'id' => 'int',
        'is_active' => 'int-bool',
        'price' => 'float',
        'tax_rate' => 'float',
        'organization_id' => 'int',
    ];
}
