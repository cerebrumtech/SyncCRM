<?php

namespace App\Controllers;

use App\Libraries\Visibility;

/** JSON search endpoints for record pickers. */
class Api extends BaseController
{
    public function search(string $kind)
    {
        $q = trim((string) $this->request->getGet('q'));
        $db = db_connect();
        $orgId = $this->orgId();
        $rows = [];
        if ($q !== '') {
            switch ($kind) {
                case 'contacts':
                    $b = $db->table('contacts c')->select('c.id, c.first_name, c.last_name, c.email, co.name AS company_name')->join('companies co', 'co.id = c.company_id', 'left')
                        ->where('c.organization_id', $orgId)->groupStart()->like('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.email', $q)->orLike('c.phone', $q)->groupEnd();
                    Visibility::apply($b, $this->me, 'c', 'CONTACT');
                    $r = $b->orderBy('c.updated_at', 'DESC')->limit(8)->get()->getResultArray();
                    $rows = array_map(fn ($x) => ['id' => $x['id'], 'label' => full_name($x), 'sub' => $x['email'] ?? $x['company_name'] ?? '', 'company_id' => null], $r);
                    break;
                case 'companies':
                    $b = $db->table('companies')->select('id, name, city')->where('organization_id', $orgId)->like('name', $q);
                    Visibility::apply($b, $this->me, 'companies', 'COMPANY');
                    $r = $b->orderBy('updated_at', 'DESC')->limit(8)->get()->getResultArray();
                    $rows = array_map(fn ($x) => ['id' => $x['id'], 'label' => $x['name'], 'sub' => $x['city'] ?? ''], $r);
                    break;
                case 'deals':
                    $b = $db->table('deals d')->select('d.id, d.title, p.name AS pipeline_name')->join('pipelines p', 'p.id = d.pipeline_id')->where('d.organization_id', $orgId)->like('d.title', $q);
                    Visibility::apply($b, $this->me, 'd', 'DEAL');
                    $r = $b->orderBy('d.updated_at', 'DESC')->limit(8)->get()->getResultArray();
                    $rows = array_map(fn ($x) => ['id' => $x['id'], 'label' => $x['title'], 'sub' => $x['pipeline_name']], $r);
                    break;
                case 'products':
                    $r = $db->table('products')->select('id, name, sku, price, tax_rate')->where('organization_id', $orgId)->where('is_active', 1)->groupStart()->like('name', $q)->orLike('sku', $q)->groupEnd()->limit(8)->get()->getResultArray();
                    $rows = array_map(fn ($x) => ['id' => $x['id'], 'label' => $x['name'], 'sub' => $x['sku'] ?? '', 'price' => $x['price'], 'tax_rate' => $x['tax_rate']], $r);
                    break;
                default:
                    return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Unknown search']);
            }
        }
        return $this->response->setJSON(['ok' => true, 'data' => $rows]);
    }
}
