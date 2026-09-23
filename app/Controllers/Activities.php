<?php

namespace App\Controllers;

use App\Libraries\Activities as Act;
use App\Libraries\Audit;
use App\Libraries\Lists;
use App\Libraries\Permissions;
use App\Models\ActivityModel;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;
use App\Models\UserModel;

class Activities extends BaseController
{
    public function index()
    {
        $p = $this->request->getGet();
        $view = ($p['view'] ?? 'list') === 'calendar' ? 'calendar' : 'list';
        $range = in_array($p['range'] ?? '', ['today', 'upcoming', 'overdue', 'completed', 'all'], true) ? $p['range'] : 'upcoming';
        $type = in_array($p['type'] ?? '', ['TASK', 'CALL', 'EVENT'], true) ? $p['type'] : null;
        $assignee = $p['assignee'] ?? 'me';
        $focus = isset($p['focus']) ? (int) $p['focus'] : null;
        $month = preg_match('/^\d{4}-\d{2}$/', $p['month'] ?? '') ? $p['month'] : date('Y-m');
        $today = date('Y-m-d 00:00:00');
        $tomorrow = date('Y-m-d 00:00:00', strtotime('+1 day'));
        $now = now_sql();

        $base = function () use ($type, $assignee) {
            $b = Act::query($this->orgId());
            if ($type) {
                $b->where('a.type', $type);
            }
            if ($assignee === 'me') {
                $b->where('a.assignee_id', $this->me['id']);
            } elseif ($assignee !== 'all' && ctype_digit((string) $assignee)) {
                $b->where('a.assignee_id', (int) $assignee);
            }
            return $b;
        };
        $users = Lists::activeUsers($this->orgId());
        $common = ['title' => 'Activities', 'p' => $p, 'view' => $view, 'range' => $range, 'type' => $type, 'assignee' => $assignee, 'users' => $users, 'focus' => $focus];

        if ($view === 'calendar') {
            [$y, $m] = array_map('intval', explode('-', $month));
            $start = sprintf('%04d-%02d-01 00:00:00', $y, $m);
            $end = date('Y-m-01 00:00:00', strtotime($start . ' +1 month'));
            $rows = $base()->where('a.due_at >=', $start)->where('a.due_at <', $end)->orderBy('a.due_at')->get()->getResultArray();
            $byDay = [];
            foreach ($rows as $r) {
                $byDay[substr($r['due_at'], 0, 10)][] = $r;
            }
            return $this->render('activities/index', $common + ['month' => $month, 'byDay' => $byDay, 'monthStart' => $start, 'today' => date('Y-m-d')]);
        }

        $b = $base();
        switch ($range) {
            case 'today':
                $b->where('a.status', 'OPEN')->where('a.due_at >=', $today)->where('a.due_at <', $tomorrow);
                break;
            case 'overdue':
                $b->where('a.status', 'OPEN')->where('a.due_at <', $now);
                break;
            case 'completed':
                $b->where('a.status', 'COMPLETED');
                break;
            case 'all':
                break;
            default:
                $b->where('a.status', 'OPEN')->groupStart()->where('a.due_at >=', $today)->orWhere('a.due_at IS NULL')->groupEnd();
        }
        if ($range === 'completed') {
            $b->orderBy('a.completed_at', 'DESC');
        } else {
            $b->orderBy('a.due_at IS NULL', '', false)->orderBy('a.due_at', 'ASC')->orderBy('a.created_at', 'DESC');
        }
        $rows = $b->limit(300)->get()->getResultArray();
        if ($focus && ! array_filter($rows, fn ($r) => (int) $r['id'] === $focus)) {
            $f = Act::query($this->orgId())->where('a.id', $focus)->get()->getRowArray();
            if ($f) {
                array_unshift($rows, $f);
            }
        }
        $counts = [
            'today' => (clone $base())->where('a.status', 'OPEN')->where('a.due_at >=', $today)->where('a.due_at <', $tomorrow)->countAllResults(),
            'overdue' => (clone $base())->where('a.status', 'OPEN')->where('a.due_at <', $now)->countAllResults(),
        ];
        return $this->render('activities/index', $common + ['rows' => $rows, 'counts' => $counts]);
    }

    private function readForm(): array
    {
        $type = $this->str('type') ?? 'TASK';
        if (! in_array($type, ['TASK', 'CALL', 'EVENT'], true)) {
            $this->fail('Invalid activity type.');
        }
        $title = $this->str('title', 160) ?? $this->fail('Title is required');
        $allDay = $this->on('all_day');
        $dueRaw = $this->str('due_at');
        $dueAt = $allDay ? parse_date($dueRaw) : parse_datetime($dueRaw);
        if ($allDay && $dueAt) {
            $dueAt .= ' 00:00:00';
        }
        $endAt = $allDay ? null : parse_datetime($this->str('end_at'));
        if ($type === 'EVENT' && ! $dueAt) {
            $this->fail('Events need a start date and time.');
        }
        if ($endAt && $dueAt && $endAt < $dueAt) {
            $this->fail('End time must be after the start time.');
        }
        $recurrence = $this->str('recurrence') ?? 'NONE';
        if (! in_array($recurrence, ['NONE', 'DAILY', 'WEEKLY', 'MONTHLY'], true)) {
            $this->fail('Invalid recurrence.');
        }
        $reminder = $this->str('reminder_minutes');
        $reminder = $reminder !== null && is_numeric($reminder) ? max(0, min(43200, (int) $reminder)) : null;
        $assigneeId = $this->intOrNull('assignee_id');
        if ($assigneeId && ! model(UserModel::class)->where('organization_id', $this->orgId())->where('is_active', 1)->find($assigneeId)) {
            $this->fail('Assignee must be an active user.');
        }
        $contactId = $this->intOrNull('contact_id');
        $companyId = $this->intOrNull('company_id');
        $dealId = $this->intOrNull('deal_id');
        $contact = $contactId ? (model(ContactModel::class)->findInOrg($this->orgId(), $contactId) ?? $this->fail('Contact not found.')) : null;
        if ($companyId && ! model(CompanyModel::class)->findInOrg($this->orgId(), $companyId)) {
            $this->fail('Company not found.');
        }
        $deal = $dealId ? (model(DealModel::class)->findInOrg($this->orgId(), $dealId) ?? $this->fail('Deal not found.')) : null;
        $attendees = [];
        foreach (preg_split('/[,\s]+/', (string) $this->str('attendees')) as $e) {
            if (filter_var($e, FILTER_VALIDATE_EMAIL) && count($attendees) < 50) {
                $attendees[] = strtolower($e);
            }
        }
        $dir = $this->str('call_direction');
        $durMin = $this->str('call_duration_min');
        return [
            'type' => $type, 'title' => $title, 'description' => $this->str('description', 4000), 'due_at' => $dueAt, 'end_at' => $endAt, 'all_day' => $allDay,
            'location' => $this->str('location', 200), 'recurrence' => $recurrence, 'recurrence_until' => parse_date($this->str('recurrence_until')), 'reminder_minutes' => $reminder,
            'attendees' => $attendees, 'assignee_id' => $assigneeId, 'contact_id' => $contactId, 'company_id' => $companyId ?? ($deal['company_id'] ?? null) ?? ($contact['company_id'] ?? null), 'deal_id' => $dealId,
            'call_direction' => $type === 'CALL' ? (in_array($dir, ['INBOUND', 'OUTBOUND'], true) ? $dir : 'OUTBOUND') : null,
            'call_duration_sec' => $type === 'CALL' && is_numeric($durMin) ? (int) round((float) $durMin * 60) : null,
            'call_outcome' => $type === 'CALL' ? $this->str('call_outcome', 200) : null,
        ];
    }

    private function backTo(array $a): string
    {
        $ref = (string) $this->request->getServer('HTTP_REFERER');
        $path = parse_url($ref, PHP_URL_PATH) ?: '';
        if (preg_match('#^/(contacts|companies|deals)/\d+#', $path) || str_starts_with($path, '/activities') || $path === '/dashboard') {
            return $path . (parse_url($ref, PHP_URL_QUERY) ? '?' . parse_url($ref, PHP_URL_QUERY) : '');
        }
        return '/activities';
    }

    public function create()
    {
        return $this->attempt(function () {
            $data = $this->readForm();
            $completed = $this->on('mark_completed') || ($data['type'] === 'CALL' && $this->on('logged'));
            $data += ['organization_id' => $this->orgId(), 'created_by_id' => $this->me['id'], 'status' => $completed ? 'COMPLETED' : 'OPEN', 'completed_at' => $completed ? now_sql() : null];
            $data['assignee_id'] ??= $this->me['id'];
            $id = model(ActivityModel::class)->insert($data);
            Audit::log($this->me, $data['type'] === 'CALL' ? 'log_call' : 'create', 'Activity', $id, $data['title'], null, $data);
            $followUp = $this->str('follow_up_title', 160);
            if ($data['type'] === 'CALL' && $followUp) {
                model(ActivityModel::class)->insert([
                    'organization_id' => $this->orgId(), 'type' => 'TASK', 'title' => $followUp, 'due_at' => parse_datetime($this->str('follow_up_due_at')) ?? date('Y-m-d H:i:s', time() + 86400),
                    'assignee_id' => $data['assignee_id'], 'created_by_id' => $this->me['id'], 'contact_id' => $data['contact_id'], 'company_id' => $data['company_id'], 'deal_id' => $data['deal_id'], 'reminder_minutes' => 30, 'attendees' => [],
                ]);
            }
            return redirect()->to($this->backTo($data))->with('success', ($data['type'] === 'CALL' && $completed ? 'Call logged.' : activity_type_label($data['type']) . ' created.'));
        }, 'activity-dialog');
    }

    public function update(int $id)
    {
        return $this->attempt(function () use ($id) {
            $existing = model(ActivityModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Activity not found.');
            Permissions::assert(Act::canTouch($this->me, $existing), 'You can only edit activities assigned to you.');
            $data = $this->readForm();
            $mark = $this->on('mark_completed');
            if ($mark && $existing['status'] !== 'COMPLETED') {
                $data['status'] = 'COMPLETED';
                $data['completed_at'] = now_sql();
            } elseif (! $mark && $existing['status'] === 'COMPLETED') {
                $data['status'] = 'OPEN';
                $data['completed_at'] = null;
            }
            model(ActivityModel::class)->update($id, $data);
            Audit::log($this->me, 'update', 'Activity', $id, $data['title'], $existing, $data);
            return redirect()->to($this->backTo($data))->with('success', 'Activity updated.');
        }, 'activity-dialog');
    }

    public function status(int $id)
    {
        return $this->attempt(function () use ($id) {
            $a = model(ActivityModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Activity not found.');
            Permissions::assert(Act::canTouch($this->me, $a), 'You can only update activities assigned to you.');
            $status = $this->str('status');
            if (! in_array($status, ['OPEN', 'COMPLETED', 'CANCELLED'], true)) {
                $this->fail('Invalid status.');
            }
            model(ActivityModel::class)->update($id, ['status' => $status, 'completed_at' => $status === 'COMPLETED' ? now_sql() : null]);
            if ($status === 'COMPLETED' && $a['status'] !== 'COMPLETED') {
                Act::spawnNext($a);
            }
            Audit::log($this->me, strtolower($status), 'Activity', $id, $a['title']);
            return redirect()->to($this->backTo($a))->with('success', $status === 'COMPLETED' ? 'Marked complete.' . (Act::nextOccurrence($a) ? ' Next occurrence scheduled.' : '') : 'Status updated.');
        });
    }

    public function delete(int $id)
    {
        return $this->attempt(function () use ($id) {
            $a = model(ActivityModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Activity not found.');
            Permissions::assert(Permissions::isAdmin($this->me) || (int) $a['created_by_id'] === (int) $this->me['id'] || (int) $a['assignee_id'] === (int) $this->me['id'], 'You can only delete your own activities.');
            model(ActivityModel::class)->delete($id);
            Audit::log($this->me, 'delete', 'Activity', $id, $a['title'], $a);
            return redirect()->to($this->backTo($a))->with('success', 'Activity deleted.');
        });
    }
}
