<?php

namespace App\Models;

class ActivityModel extends AppModel
{
    protected $table         = 'activities';
    protected $allowedFields = ['organization_id', 'type', 'title', 'description', 'status', 'due_at', 'end_at', 'all_day', 'location', 'recurrence', 'recurrence_until', 'reminder_minutes', 'attendees', 'call_direction', 'call_duration_sec', 'call_outcome', 'assignee_id', 'contact_id', 'company_id', 'deal_id', 'created_by_id', 'completed_at', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected array $casts = [
        'id' => 'int',
        'attendees' => '?json-array',
        'all_day' => 'int-bool',
        'organization_id' => 'int',
        'assignee_id' => '?int',
        'contact_id' => '?int',
        'company_id' => '?int',
        'deal_id' => '?int',
        'created_by_id' => 'int',
        'reminder_minutes' => '?int',
        'call_duration_sec' => '?int',
    ];
}
