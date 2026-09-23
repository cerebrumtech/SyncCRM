<?php

namespace App\Models;

class InviteModel extends AppModel
{
    protected $table         = 'invites';
    protected $allowedFields = ['organization_id', 'email', 'role', 'token', 'status', 'invited_by_id', 'expires_at', 'created_at'];
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected array $casts = [
        'id' => 'int',
        'organization_id' => 'int',
    ];
}
