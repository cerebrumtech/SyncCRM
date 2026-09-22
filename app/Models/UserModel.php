<?php

namespace App\Models;

class UserModel extends AppModel
{
    protected $table         = 'users';
    protected $allowedFields = ['organization_id', 'email', 'name', 'password_hash', 'role', 'is_active', 'color', 'last_login_at', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected array $casts = [
        'id' => 'int',
        'is_active' => 'int-bool',
        'organization_id' => 'int',
    ];
}
