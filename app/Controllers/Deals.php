<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\CustomFields;
use App\Libraries\Deals as DealLib;
use App\Libraries\Defaults;
use App\Libraries\Lists;
use App\Libraries\Permissions;
use App\Libraries\Records;
use App\Libraries\Tags;
use App\Libraries\Visibility;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealLineItemModel;
use App\Models\DealModel;
use App\Models\ProductModel;
use App\Models\StageModel;
use App\Models\UserModel;

class Deals extends BaseController
{
    private const PER_PAGE = 25;

    private function pipelineFrom(array $pipelines, $id): ?array
    {
        foreach ($pipelines as $p) {
            if ((string) $p['id'] === (string) $id) {
                return $p;
            }
        }
        foreach ($pipelines as $p) {
            if ($p['is_default']) {
                return $p;
            }
        }
        return $pipelines[0] ?? null;
    }

    public function index()
    {
        $p = $this->request->getGet();
        $pipelines = DealLib::pipelines($this->orgId());
        $pipeline = $this->pipelineFrom($pipelines, $p['pipeline'] ?? null);
        $view = ($p['view'] ?? 'board') === 'list' ? 'list' : 'board';
        $common = [
            'title' => 'Deals', 'p' => $p, 'pipelines' => $pipelines, 'pipeline' => $pipeline, 'view' => $view,
            'users' => Lists::activeUsers($this->orgId()), 'tags' => Tags::names($this->orgId()), 'defs' => CustomFields::defs($this->orgId(), 'DEAL'),
            'views' => Lists::savedViews($this->orgId(), 'DEAL', $this->me['id']), 'lostReasons' => Defaults::LOST_REASONS, 'prefill' => $this->prefill($p),
        ];
        if (! $pipeline) {
            return $this->render('deals/index', $common + ['rows' => [], 'total' => 0, 'page' => 1, 'perPage' => self::PER_PAGE, 'columns' => []]);
        }
        if ($view === 'list') {
            $page = max(1, (int) ($p['page'] ?? 1));
            $q = $p + ['pipeline' => $pipeline['id']];
            if (! isset($p['pipeline']) || $p['pipeline'] === '') {
                $q['pipeline'] = $pipeline['id'];
            }
            $b = Lists::deals($this->orgId(), $q);
            $total = (clone $b)->countAllResults(false);
            $rows = $b->limit(self::PER_PAGE, ($page - 1) * self::PER_PAGE)->get()->getResultArray();
            return $this->render('deals/index', $common + ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => self::PER_PAGE, 'columns' => []]);
        }
        $rows = Lists::deals($this->orgId(), ['pipeline' => $pipeline['id'], 'owner' => $p['owner'] ?? null, 'q' => $p['q'] ?? null, 'tag' => $p['tag'] ?? null, 'sort' => 'updated'])
            ->orderBy('d.position', 'ASC')->get()->getResultArray();
        usort($rows, fn ($a, $b) => $a['position'] <=> $b['position'] ?: $a['id'] <=> $b['id']);
        $columns = [];
        foreach ($pipeline['stages'] as $s) {
            $cards = array_values(array_filter($rows, fn ($r) => (int) $r['stage_id'] === (int) $s['id']));
            $columns[] = ['stage' => $s, 'deals' => $cards, 'total' => array_sum(array_map(fn ($r) => (float) $r['amount'], $cards))];
        }
        return $this->render('deals/index', $common + ['rows' => [], 'total' => count($rows), 'page' => 1, 'perPage' => self::PER_PAGE, 'columns' => $columns]);
    }

    private function prefill(array $p): array
    {
        $out = [];
        if (! empty($p['contact_id'])) {
            $c = model(ContactModel::class)->findInOrg($this->orgId(), (int) $p['contact_id']);
            if ($c) {
                $out['contact_id'] = $c['id'];
                $out['contact_label'] = full_name($c);
                if ($c['company_id']) {
                    $co = model(CompanyModel::class)->find($c['company_id']);
                    $out['company_id'] = $co['id'] ?? null;
                    $out['company_label'] = $co['name'] ?? '';
                }
            }
        }
        if (! empty($p['company_id'])) {
            $co = model(CompanyModel::class)->findInOrg($this->orgId(), (int) $p['company_id']);
            if ($co) {
                $out['company_id'] = $co['id'];
                $out['company_label'] = $co['name'];
            }
        }
        return $out;
    }

    public function show(int $id)
    {
        $deal = model(DealModel::class)->findInOrg($this->orgId(), $id);
        if (! $deal) {
            throw \Sync\Exceptions\PageNotFound::forPageNotFound();
        }
        // Filtering the list is not enough on its own: without this the record is still
        // reachable by typing its URL. 404 rather than 403, so the reply does not confirm
        // that a record this user may not read exists.
        if (! Visibility::canView($this->me, $deal, 'DEAL')) {
            throw \Sync\Exceptions\PageNotFound::forPageNotFound();
        }
        $pipelines = DealLib::pipelines($this->orgId());
        $pipeline = $this->pipelineFrom($pipelines, $deal['pipeline_id']);
        $stage = model(StageModel::class)->find($deal['stage_id']);
        $contact = $deal['contact_id'] ? model(ContactModel::class)->find($deal['contact_id']) : null;
        $company = $deal['company_id'] ? model(CompanyModel::class)->find($deal['company_id']) : null;
        $owner = $deal['owner_id'] ? model(UserModel::class)->find($deal['owner_id']) : null;
        $source = $deal['source_deal_id'] ? model(DealModel::class)->find($deal['source_deal_id']) : null;
        $handoffs = Lists::deals($this->orgId(), [])->where('d.source_deal_id', $id)->get()->getResultArray();
        $products = model(ProductModel::class)->where('organization_id', $this->orgId())->where('is_active', 1)->orderBy('name')->findAll();
        return $this->render('deals/show', [
            'title' => $deal['title'], 'deal' => $deal, 'pipeline' => $pipeline, 'pipelines' => $pipelines, 'stage' => $stage, 'contact' => $contact, 'company' => $company, 'owner' => $owner,
            'source' => $source, 'handoffs' => $handoffs, 'items' => DealLib::lineItems($id), 'products' => $products, 'lostReasons' => Defaults::LOST_REASONS,
            'users' => Lists::activeUsers($this->orgId()), 'tags' => Tags::names($this->orgId()), 'defs' => CustomFields::defs($this->orgId(), 'DEAL'),
            'notes' => Records::notes($this->orgId(), 'deal_id', $id), 'files' => Records::attachments($this->orgId(), 'deal_id', $id),
            'activities' => Records::activities($this->orgId(), 'deal_id', $id), 'timeline' => Records::timeline($this->orgId(), 'DEAL', $id),
            'shares' => Records::shares($this->orgId(), 'DEAL', $id), 'teams' => db_connect()->table('teams')->where('organization_id', $this->orgId())->orderBy('name')->get()->getResultArray(),
            'canEdit' => Permissions::canEditRecord($this->me, 'DEAL', $deal), 'canDelete' => Permissions::canDeleteRecord($this->me, $deal),
        ]);
    }

    private function readForm(): array
    {
        $title = $this->str('title', 160) ?? $this->fail('Deal title is required');
        $pipelineId = $this->intOrNull('pipeline_id') ?? $this->fail('Choose a pipeline');
        $stageId = $this->intOrNull('stage_id') ?? $this->fail('Choose a stage');
        $stage = db_connect()->table('stages s')->select('s.*')->join('pipelines p', 'p.id = s.pipeline_id')->where('s.id', $stageId)->where('s.pipeline_id', $pipelineId)->where('p.organization_id', $this->orgId())->get()->getRowArray();
        if (! $stage) {
            $this->fail("Stage doesn't belong to the chosen pipeline.");
        }
        $stage['required_fields'] = $stage['required_fields'] ? json_decode($stage['required_fields'], true) : [];
        $stage['is_won'] = (bool) $stage['is_won'];
        $stage['is_lost'] = (bool) $stage['is_lost'];
        $amountRaw = $this->str('amount') ?? '0';
        if (! is_numeric($amountRaw) || (float) $amountRaw < 0) {
            $this->fail("Amount can't be negative");
        }
        $contactId = $this->intOrNull('contact_id');
        $companyId = $this->intOrNull('company_id');
        if ($contactId) {
            $c = model(ContactModel::class)->findInOrg($this->orgId(), $contactId) ?? $this->fail('Contact not found.');
            if (! $companyId && $c['company_id']) {
                $companyId = (int) $c['company_id'];
            }
        }
        if ($companyId && ! model(CompanyModel::class)->findInOrg($this->orgId(), $companyId)) {
            $this->fail('Company not found.');
        }
        $ownerId = $this->intOrNull('owner_id');
        if ($ownerId && ! model(UserModel::class)->where('organization_id', $this->orgId())->where('is_active', 1)->find($ownerId)) {
            $this->fail('Owner must be an active user.');
        }
        $data = [
            'title' => $title, 'pipeline_id' => $pipelineId, 'stage_id' => $stageId, 'amount' => round((float) $amountRaw, 2),
            'expected_close_date' => parse_date($this->str('expected_close_date')), 'contact_id' => $contactId, 'company_id' => $companyId, 'owner_id' => $ownerId,
            'tags' => Tags::parse($this->str('tags')), 'custom_fields' => CustomFields::parse(CustomFields::defs($this->orgId(), 'DEAL'), $this->request->getPost()),
        ];
        return [$data, $stage];
    }

    public function create()
    {
        return $this->attempt(function () {
            [$data, $stage] = $this->readForm();
            $missing = DealLib::missingFields($stage, $data, CustomFields::defs($this->orgId(), 'DEAL'));
            if ($missing) {
                $this->fail('To be in "' . $stage['name'] . '" a deal needs: ' . implode(', ', $missing) . '.');
            }
            $status = DealLib::statusFor($stage);
            $data += ['organization_id' => $this->orgId(), 'status' => $status, 'closed_at' => $status === 'OPEN' ? null : now_sql(), 'position' => DealLib::nextPosition($stage['id']), 'amount_is_manual' => true];
            $data['owner_id'] ??= $this->me['id'];
            $id = model(DealModel::class)->insert($data);
            Tags::ensure($this->orgId(), $data['tags']);
            Audit::log($this->me, 'create', 'DEAL', $id, $data['title'], null, $data + ['stage' => $stage['name']]);
            return $this->ok('Deal created.', '/deals/' . $id);
        }, 'deal-dialog');
    }

    public function update(int $id)
    {
        return $this->attempt(function () use ($id) {
            $existing = model(DealModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Deal not found.');
            Permissions::assert(Permissions::canEditRecord($this->me, 'DEAL', $existing));
            [$data, $stage] = $this->readForm();
            $oldStage = model(StageModel::class)->find($existing['stage_id']);
            if ($stage['id'] !== $existing['stage_id']) {
                $missing = DealLib::missingFields($stage, $data, CustomFields::defs($this->orgId(), 'DEAL'));
                if ($missing) {
                    $this->fail('To move to "' . $stage['name'] . '" this deal needs: ' . implode(', ', $missing) . '.');
                }
            }
            $status = DealLib::statusFor($stage);
            if (! $existing['amount_is_manual']) {
                $data['amount'] = $existing['amount'];
            }
            $data['status'] = $status;
            $data['closed_at'] = $status === 'OPEN' ? null : ($existing['closed_at'] ?? now_sql());
            $data['lost_reason'] = $status === 'LOST' ? $existing['lost_reason'] : null;
            model(DealModel::class)->update($id, $data);
            Tags::ensure($this->orgId(), $data['tags']);
            Audit::log($this->me, 'update', 'DEAL', $id, $data['title'], $existing + ['stage' => $oldStage['name'] ?? ''], $data + ['stage' => $stage['name']]);
            return $this->ok('Deal updated.', '/deals/' . $id);
        }, 'deal-dialog');
    }

    public function delete(int $id)
    {
        return $this->attempt(function () use ($id) {
            $existing = model(DealModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Deal not found.');
            Permissions::assert(Permissions::canDeleteRecord($this->me, $existing), 'Only the owner or an admin can delete this deal.');
            model(DealModel::class)->delete($id);
            Audit::log($this->me, 'delete', 'DEAL', $id, $existing['title'], $existing);
            return $this->ok('Deal deleted.', '/deals?pipeline=' . $existing['pipeline_id']);
        });
    }

    /** Kanban drop / stage stepper (JSON). Returns {missing:[]} or {needsLostReason:true} when the move is not yet allowed. */
    public function move(int $id)
    {
        return $this->attemptJson(function () use ($id) {
            $body = $this->jsonBody();
            $deal = model(DealModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Deal not found.');
            Permissions::assert(Permissions::canEditRecord($this->me, 'DEAL', $deal), "You can't move a deal you don't own.");
            $stage = model(StageModel::class)->where('pipeline_id', $deal['pipeline_id'])->find((int) ($body['stage_id'] ?? 0)) ?? $this->fail('Stage not found in this pipeline.');
            $position = isset($body['position']) && $body['position'] !== null && $body['position'] !== '' ? (int) $body['position'] : null;
            $lostReason = isset($body['lost_reason']) ? mb_substr(trim((string) $body['lost_reason']), 0, 200) : null;
            if ($stage['id'] !== $deal['stage_id']) {
                $missing = DealLib::missingFields($stage, $deal, CustomFields::defs($this->orgId(), 'DEAL'));
                if ($missing) {
                    return ['missing' => $missing];
                }
                if ($stage['is_lost'] && ! $lostReason && ! $deal['lost_reason']) {
                    return ['needsLostReason' => true];
                }
            }
            $oldStage = model(StageModel::class)->find($deal['stage_id']);
            DealLib::place($deal, $stage, $position, $lostReason ?: null);
            if ($stage['id'] !== $deal['stage_id']) {
                $status = DealLib::statusFor($stage);
                $action = $status === 'WON' ? 'won' : ($status === 'LOST' ? 'lost' : ($deal['status'] !== 'OPEN' ? 'reopen' : 'stage_change'));
                Audit::log($this->me, $action, 'DEAL', $id, $deal['title'], ['stage' => $oldStage['name'] ?? ''], ['stage' => $stage['name']] + ($lostReason ? ['lostReason' => $lostReason] : []));
            }
            return [];
        });
    }

    public function lostReason(int $id)
    {
        return $this->attempt(function () use ($id) {
            $deal = model(DealModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Deal not found.');
            Permissions::assert(Permissions::canEditRecord($this->me, 'DEAL', $deal));
            model(DealModel::class)->update($id, ['lost_reason' => $this->str('lost_reason', 200)]);
            return $this->ok('Lost reason saved.', '/deals/' . $id);
        });
    }

    /** Creates a linked copy of the deal in another pipeline (e.g. Sales -> Onboarding). */
    public function handoff(int $id)
    {
        return $this->attempt(function () use ($id) {
            $deal = model(DealModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Deal not found.');
            Permissions::assert(Permissions::canEditRecord($this->me, 'DEAL', $deal));
            $targetId = $this->intOrNull('pipeline_id') ?? $this->fail('Choose a pipeline.');
            if ($targetId === (int) $deal['pipeline_id']) {
                $this->fail('Choose a different pipeline.');
            }
            $target = $this->pipelineFrom(array_filter(DealLib::pipelines($this->orgId()), fn ($p) => $p['id'] === $targetId), $targetId);
            if (! $target || $target['id'] !== $targetId) {
                $this->fail('Pipeline not found.');
            }
            $first = null;
            foreach ($target['stages'] as $s) {
                if (! $s['is_won'] && ! $s['is_lost']) {
                    $first = $s;
                    break;
                }
            }
            $first ??= $target['stages'][0] ?? $this->fail('Target pipeline has no stages.');
            $already = model(DealModel::class)->where('source_deal_id', $id)->where('pipeline_id', $targetId)->first();
            if ($already) {
                return redirect()->to('/deals/' . $already['id'])->with('info', 'This deal was already handed off to ' . esc($target['name']) . '.');
            }
            $copyId = model(DealModel::class)->insert([
                'organization_id' => $this->orgId(), 'title' => $deal['title'], 'pipeline_id' => $targetId, 'stage_id' => $first['id'], 'status' => 'OPEN', 'amount' => $deal['amount'], 'amount_is_manual' => $deal['amount_is_manual'],
                'contact_id' => $deal['contact_id'], 'company_id' => $deal['company_id'], 'owner_id' => $deal['owner_id'], 'tags' => $deal['tags'] ?? [], 'custom_fields' => $deal['custom_fields'] ?? [],
                'source_deal_id' => $id, 'position' => DealLib::nextPosition($first['id']),
            ]);
            foreach (DealLib::lineItems($id) as $li) {
                unset($li['id']);
                $li['deal_id'] = $copyId;
                model(DealLineItemModel::class)->insert($li);
            }
            Audit::log($this->me, 'handoff', 'DEAL', $id, $deal['title'], null, ['toPipeline' => $target['name'], 'newDealId' => $copyId]);
            return redirect()->to('/deals/' . $copyId)->with('success', 'Deal handed off to ' . $target['name'] . '.');
        }, 'handoff-dialog');
    }

    public function lineItems(int $id)
    {
        return $this->attempt(function () use ($id) {
            $deal = model(DealModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Deal not found.');
            Permissions::assert(Permissions::canEditRecord($this->me, 'DEAL', $deal));
            $items = (array) $this->request->getPost('items');
            if (count($items) > 100) {
                $this->fail('At most 100 line items.');
            }
            $rows = [];
            $productIds = [];
            foreach (array_values($items) as $i => $it) {
                $name = trim((string) ($it['name'] ?? ''));
                if ($name === '') {
                    $this->fail('Line item ' . ($i + 1) . ' needs a name');
                }
                $qty = (float) ($it['quantity'] ?? 1);
                $price = (float) ($it['unit_price'] ?? 0);
                $disc = (float) ($it['discount_percent'] ?? 0);
                $tax = (float) ($it['tax_rate'] ?? 0);
                if ($qty < 0 || $price < 0 || $disc < 0 || $disc > 100 || $tax < 0 || $tax > 100) {
                    $this->fail('Line item ' . ($i + 1) . ' has an invalid number.');
                }
                $pid = ! empty($it['product_id']) ? (int) $it['product_id'] : null;
                if ($pid) {
                    $productIds[] = $pid;
                }
                $rows[] = ['deal_id' => $id, 'product_id' => $pid, 'name' => mb_substr($name, 0, 160), 'quantity' => $qty, 'unit_price' => $price, 'discount_percent' => $disc, 'tax_rate' => $tax, 'total' => DealLib::lineTotal($qty, $price, $disc, $tax), 'position' => $i];
            }
            if ($productIds) {
                $known = model(ProductModel::class)->where('organization_id', $this->orgId())->whereIn('id', array_unique($productIds))->countAllResults();
                if ($known !== count(array_unique($productIds))) {
                    $this->fail('One of the products no longer exists.');
                }
            }
            $fromItems = $this->on('amount_from_items');
            $sum = round(array_sum(array_column($rows, 'total')), 2);
            $db = db_connect();
            $db->transStart();
            model(DealLineItemModel::class)->where('deal_id', $id)->delete();
            foreach ($rows as $r) {
                model(DealLineItemModel::class)->insert($r);
            }
            model(DealModel::class)->update($id, ['amount_is_manual' => ! $fromItems] + ($fromItems ? ['amount' => $sum] : []));
            $db->transComplete();
            Audit::log($this->me, 'update_line_items', 'DEAL', $id, $deal['title'], ['amount' => $deal['amount']], ['items' => count($rows), 'amount' => $fromItems ? $sum : $deal['amount']]);
            return redirect()->to('/deals/' . $id . '#items')->with('success', 'Line items saved.');
        });
    }
}
