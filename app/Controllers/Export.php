<?php

namespace App\Controllers;

use App\Libraries\CustomFields;
use App\Libraries\Lists;

/** CSV export of the current list (same filters as the page). */
class Export extends BaseController
{
    public function run(string $entity)
    {
        $p = $this->request->getGet();
        $orgId = $this->orgId();
        switch ($entity) {
            case 'contacts':
                $rows = Lists::contacts($orgId, $p)->get()->getResultArray();
                $defs = CustomFields::defs($orgId, 'CONTACT');
                $head = ['First name', 'Last name', 'Email', 'Phone', 'WhatsApp', 'Job title', 'Company', 'Owner', 'Tags', 'Created'];
                $map = fn ($r) => [$r['first_name'], $r['last_name'], $r['email'], $r['phone'], $r['whatsapp_number'], $r['job_title'], $r['company_name'], $r['owner_name'], implode('; ', json_decode($r['tags'] ?: '[]', true)), $r['created_at']];
                break;
            case 'companies':
                $rows = Lists::companies($orgId, $p)->get()->getResultArray();
                $defs = CustomFields::defs($orgId, 'COMPANY');
                $head = ['Name', 'Industry', 'Website', 'Phone', 'Email', 'Address', 'City', 'State', 'PIN', 'Country', 'Owner', 'Tags', 'Created'];
                $map = fn ($r) => [$r['name'], $r['industry'], $r['website'], $r['phone'], $r['email'], $r['address_line'], $r['city'], $r['state'], $r['postal_code'], $r['country'], $r['owner_name'], implode('; ', json_decode($r['tags'] ?: '[]', true)), $r['created_at']];
                break;
            case 'deals':
                $rows = Lists::deals($orgId, $p)->get()->getResultArray();
                $defs = CustomFields::defs($orgId, 'DEAL');
                $head = ['Title', 'Pipeline', 'Stage', 'Status', 'Amount', 'Expected close', 'Closed at', 'Lost reason', 'Contact', 'Company', 'Owner', 'Tags', 'Created'];
                $map = fn ($r) => [$r['title'], $r['pipeline_name'], $r['stage_name'], $r['status'], $r['amount'], $r['expected_close_date'], $r['closed_at'], $r['lost_reason'], trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')), $r['company_name'], $r['owner_name'], implode('; ', json_decode($r['tags'] ?: '[]', true)), $r['created_at']];
                break;
            case 'products':
                $rows = db_connect()->table('products')->where('organization_id', $orgId)->orderBy('name')->get()->getResultArray();
                $defs = [];
                $head = ['Name', 'SKU', 'Price', 'GST %', 'Active', 'Description'];
                $map = fn ($r) => [$r['name'], $r['sku'], $r['price'], $r['tax_rate'], $r['is_active'] ? 'yes' : 'no', $r['description']];
                break;
            case 'activities':
                $rows = db_connect()->table('activities a')->select('a.*, u.name AS assignee_name')->join('users u', 'u.id = a.assignee_id', 'left')->where('a.organization_id', $orgId)->orderBy('a.due_at', 'DESC')->get()->getResultArray();
                $defs = [];
                $head = ['Type', 'Title', 'Status', 'Due', 'Assignee', 'Call direction', 'Call minutes', 'Outcome', 'Created'];
                $map = fn ($r) => [$r['type'], $r['title'], $r['status'], $r['due_at'], $r['assignee_name'], $r['call_direction'], $r['call_duration_sec'] ? round($r['call_duration_sec'] / 60) : '', $r['call_outcome'], $r['created_at']];
                break;
            default:
                return $this->response->setStatusCode(404)->setBody('Unknown export');
        }
        foreach ($defs as $d) {
            $head[] = $d['label'];
        }
        $out = fopen('php://temp', 'w+');
        fputcsv($out, $head);
        foreach ($rows as $r) {
            $line = $map($r);
            $cf = isset($r['custom_fields']) ? json_decode($r['custom_fields'] ?: '{}', true) : [];
            foreach ($defs as $d) {
                $v = $cf[$d['field_key']] ?? '';
                $line[] = is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v;
            }
            fputcsv($out, array_map(fn ($v) => $v === null ? '' : (is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v), $line));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="synccrm-' . $entity . '-' . date('Ymd') . '.csv"')
            ->setBody("\xEF\xBB\xBF" . $csv);
    }
}
