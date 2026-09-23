<?php

namespace App\Models;

class TeamMemberModel extends AppModel
{
    protected $table         = 'team_members';
    protected $allowedFields = ['team_id', 'user_id'];
    protected $useTimestamps = false;
    protected array $casts = [
        'id' => 'int',
    ];
}
