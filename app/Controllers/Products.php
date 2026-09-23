<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Permissions;
use App\Models\DealLineItemModel;
use App\Models\ProductModel;

class Products extends BaseController
{
    public function index()
    {
        $p = $this->request->getGet();
        $m = model(ProductModel::class)->where('organization_id', $this->orgId());
        if (! empty($p['q'])) {
            $m->groupStart()->like('name', $p['q'])->orLike('sku', $p['q'])->groupEnd();
        }
        if (($p['status'] ?? 'active') === 'active') {
            $m->where('is_active', 1);
        } elseif ($p['status'] === 'inactive') {
            $m->where('is_active', 0);
        }
        $rows = $m->orderBy('name')->findAll();
        $usage = [];
        foreach (db_connect()->table('deal_line_items')->select('product_id, COUNT(*) AS n')->where('product_id IS NOT NULL')->groupBy('product_id')->get()->getResultArray() as $r) {
            $usage[(int) $r['product_id']] = (int) $r['n'];
        }
        return $this->render('products/index', ['title' => 'Products', 'rows' => $rows, 'p' => $p, 'usage' => $usage, 'isAdmin' => Permissions::isAdmin($this->me)]);
    }

    private function readForm(?int $excludeId = null): array
    {
        $name = $this->str('name', 160) ?? $this->fail('Product name is required');
        $sku = $this->str('sku', 60);
        $price = $this->str('price') ?? '0';
        $tax = $this->str('tax_rate') ?? '0';
        if (! is_numeric($price) || (float) $price < 0) {
            $this->fail("Price can't be negative");
        }
        if (! is_numeric($tax) || (float) $tax < 0 || (float) $tax > 100) {
            $this->fail('GST rate must be between 0 and 100');
        }
        if ($sku) {
            $dup = model(ProductModel::class)->where('organization_id', $this->orgId())->where('sku', $sku);
            if ($excludeId) {
                $dup->where('id !=', $excludeId);
            }
            if ($d = $dup->first()) {
                $this->fail("SKU {$sku} is already used by \"{$d['name']}\".");
            }
        }
        return ['name' => $name, 'sku' => $sku, 'description' => $this->str('description', 2000), 'price' => round((float) $price, 2), 'tax_rate' => round((float) $tax, 2), 'image_url' => $this->str('image_url', 500), 'is_active' => $this->on('is_active') || $this->request->getPost('is_active') === null];
    }

    public function create()
    {
        return $this->attempt(function () {
            Permissions::assert(Permissions::isAdmin($this->me), 'Only admins can manage the product catalogue.');
            $data = $this->readForm();
            $id = model(ProductModel::class)->insert($data + ['organization_id' => $this->orgId()]);
            Audit::log($this->me, 'create', 'Product', $id, $data['name'], null, $data);
            return $this->ok('Product added.', '/products');
        }, 'product-dialog');
    }

    public function update(int $id)
    {
        return $this->attempt(function () use ($id) {
            Permissions::assert(Permissions::isAdmin($this->me), 'Only admins can manage the product catalogue.');
            $existing = model(ProductModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Product not found.');
            if ($this->request->getPost('toggle_active') !== null) {
                model(ProductModel::class)->update($id, ['is_active' => ! $existing['is_active']]);
                Audit::log($this->me, $existing['is_active'] ? 'deactivate' : 'activate', 'Product', $id, $existing['name']);
                return $this->ok($existing['name'] . ($existing['is_active'] ? ' marked inactive.' : ' reactivated.'), '/products');
            }
            $data = $this->readForm($id);
            model(ProductModel::class)->update($id, $data);
            Audit::log($this->me, 'update', 'Product', $id, $data['name'], $existing, $data);
            return $this->ok('Product updated.', '/products');
        }, 'product-dialog');
    }

    public function delete(int $id)
    {
        return $this->attempt(function () use ($id) {
            Permissions::assert(Permissions::isAdmin($this->me), 'Only admins can manage the product catalogue.');
            $existing = model(ProductModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Product not found.');
            $used = model(DealLineItemModel::class)->where('product_id', $id)->countAllResults();
            if ($used > 0) {
                $this->fail("This product is on {$used} deal(s). Mark it inactive instead.");
            }
            model(ProductModel::class)->delete($id);
            Audit::log($this->me, 'delete', 'Product', $id, $existing['name'], $existing);
            return $this->ok('Product deleted.', '/products');
        });
    }
}
