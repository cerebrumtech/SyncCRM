<?php

namespace App\Libraries;

use App\Models\ActivityModel;

class Activities
{
    public const OUTCOMES = ['Interested', 'Not interested', 'No answer', 'Call back later', 'Left voicemail', 'Wrong number', 'Other'];

    /** Rows with assignee and linked-record names. */
    public static function query(int $orgId)
    {
        $b = db_connect()->table('activities a')
            ->select('a.*, u.name AS assignee_name, u.color AS assignee_color, CONCAT_WS(" ", c.first_name, c.last_name) AS contact_name, co.name AS company_name, d.title AS deal_title')
            ->join('users u', 'u.id = a.assignee_id', 'left')
            ->join('contacts c', 'c.id = a.contact_id', 'left')
            ->join('companies co', 'co.id = a.company_id', 'left')
            ->join('deals d', 'd.id = a.deal_id', 'left')
            ->where('a.organization_id', $orgId);
        // An activity belongs to whoever it is assigned to. Activities are not shareable,
        // so there is no record_shares entity to consult.
        Visibility::apply($b, Auth::user() ?? ['id' => 0, 'organization_id' => 0, 'role' => 'OWNER'], 'a', null, 'assignee_id');
        return $b;
    }

    public static function canTouch(array $user, array $a): bool
    {
        return (int) $a['organization_id'] === (int) $user['organization_id']
            && (Permissions::isAdmin($user) || (int) $a['assignee_id'] === (int) $user['id'] || (int) $a['created_by_id'] === (int) $user['id'] || $a['assignee_id'] === null);
    }

    /** Next due date for a recurring activity, or null when it has ended. */
    public static function nextOccurrence(array $a): ?string
    {
        if (($a['recurrence'] ?? 'NONE') === 'NONE' || empty($a['due_at'])) {
            return null;
        }
        $d = new \DateTime($a['due_at']);
        $d->modify(pick($a['recurrence'], ['DAILY' => '+1 day', 'WEEKLY' => '+1 week'], '+1 month'));
        if (! empty($a['recurrence_until']) && $d > new \DateTime($a['recurrence_until'] . ' 23:59:59')) {
            return null;
        }
        return $d->format('Y-m-d H:i:s');
    }

    /** Creates the follow-on occurrence when a recurring activity is completed. */
    public static function spawnNext(array $a): void
    {
        $next = self::nextOccurrence($a);
        if (! $next) {
            return;
        }
        $duration = ($a['end_at'] && $a['due_at']) ? strtotime($a['end_at']) - strtotime($a['due_at']) : null;
        model(ActivityModel::class)->insert([
            'organization_id' => $a['organization_id'], 'type' => $a['type'], 'title' => $a['title'], 'description' => $a['description'], 'status' => 'OPEN',
            'due_at' => $next, 'end_at' => $duration ? date('Y-m-d H:i:s', strtotime($next) + $duration) : null, 'all_day' => $a['all_day'], 'location' => $a['location'],
            'recurrence' => $a['recurrence'], 'recurrence_until' => $a['recurrence_until'], 'reminder_minutes' => $a['reminder_minutes'], 'attendees' => $a['attendees'] ?? [],
            'assignee_id' => $a['assignee_id'], 'contact_id' => $a['contact_id'], 'company_id' => $a['company_id'], 'deal_id' => $a['deal_id'], 'created_by_id' => $a['created_by_id'],
        ]);
    }
}
