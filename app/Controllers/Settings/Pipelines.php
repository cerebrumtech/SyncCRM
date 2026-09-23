<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Libraries\Audit;
use App\Libraries\CustomFields;
use App\Libraries\Deals as DealLib;
use App\Models\DealModel;
use App\Models\PipelineModel;
use App\Models\StageModel;

class Pipelines extends BaseController
{
    public function index()
    {
        $pipelines = DealLib::pipelines($this->orgId());
        $counts = [];
        foreach (db_connect()->table('deals')->select('stage_id, COUNT(*) AS n')->where('organization_id', $this->orgId())->groupBy('stage_id')->get()->getResultArray() as $r) {
            $counts[(int) $r['stage_id']] = (int) $r['n'];
        }
        $required = DealLib::REQUIRED_OPTIONS;
        foreach (CustomFields::defs($this->orgId(), 'DEAL') as $d) {
            $required['cf_' . $d['field_key']] = $d['label'] . ' (custom)';
        }
        return $this->render('settings/pipelines', ['title' => 'Pipelines', 'pipelines' => $pipelines, 'counts' => $counts, 'required' => $required]);
    }

    private function pipeline(int $id): array
    {
        return model(PipelineModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Pipeline not found.');
    }

    private function stage(int $id): array
    {
        $s = db_connect()->table('stages s')->select('s.*, p.name AS pipeline_name')->join('pipelines p', 'p.id = s.pipeline_id')->where('s.id', $id)->where('p.organization_id', $this->orgId())->get()->getRowArray();
        if (! $s) {
            $this->fail('Stage not found.');
        }
        $s['required_fields'] = $s['required_fields'] ? json_decode($s['required_fields'], true) : [];
        return $s;
    }

    public function create()
    {
        return $this->attempt(function () {
            $name = $this->str('name', 60);
            if (! $name || mb_strlen($name) < 2) {
                $this->fail('Pipeline name is too short');
            }
            $m = model(PipelineModel::class);
            if ($m->where('organization_id', $this->orgId())->where('name', $name)->first()) {
                $this->fail('A pipeline with that name already exists.');
            }
            $count = $m->where('organization_id', $this->orgId())->countAllResults();
            $id = $m->insert(['organization_id' => $this->orgId(), 'name' => $name, 'position' => $count, 'is_default' => $count === 0]);
            $stages = model(StageModel::class);
            foreach ([['New', 0, 10, '#04A2FB', false, false], ['In Progress', 1, 50, '#0068FF', false, false], ['Won', 2, 100, '#10B981', true, false], ['Lost', 3, 0, '#EF4444', false, true]] as [$n, $pos, $prob, $color, $won, $lost]) {
                $stages->insert(['pipeline_id' => $id, 'name' => $n, 'position' => $pos, 'probability' => $prob, 'color' => $color, 'is_won' => $won, 'is_lost' => $lost, 'required_fields' => []]);
            }
            Audit::log($this->me, 'create', 'Pipeline', $id, $name);
            return $this->ok('Pipeline created.', '/settings/pipelines');
        }, 'pipeline-dialog');
    }

    public function update(int $id)
    {
        return $this->attempt(function () use ($id) {
            $p = $this->pipeline($id);
            if ($this->on('make_default')) {
                model(PipelineModel::class)->where('organization_id', $this->orgId())->set(['is_default' => 0])->update();
                model(PipelineModel::class)->update($id, ['is_default' => true]);
                return $this->ok($p['name'] . ' is now the default pipeline.', '/settings/pipelines');
            }
            $name = $this->str('name', 60);
            if (! $name || mb_strlen($name) < 2) {
                $this->fail('Pipeline name is too short');
            }
            model(PipelineModel::class)->update($id, ['name' => $name]);
            Audit::log($this->me, 'update', 'Pipeline', $id, $name, ['name' => $p['name']], ['name' => $name]);
            return $this->ok('Pipeline renamed.', '/settings/pipelines');
        });
    }

    public function delete(int $id)
    {
        return $this->attempt(function () use ($id) {
            $p = $this->pipeline($id);
            $deals = model(DealModel::class)->where('pipeline_id', $id)->countAllResults();
            if ($deals > 0) {
                $this->fail("This pipeline still has {$deals} deal(s). Move or delete them first.");
            }
            if (model(PipelineModel::class)->where('organization_id', $this->orgId())->countAllResults() <= 1) {
                $this->fail('You need at least one pipeline.');
            }
            model(PipelineModel::class)->delete($id);
            if ($p['is_default']) {
                $first = model(PipelineModel::class)->where('organization_id', $this->orgId())->orderBy('position')->first();
                if ($first) {
                    model(PipelineModel::class)->update($first['id'], ['is_default' => true]);
                }
            }
            Audit::log($this->me, 'delete', 'Pipeline', $id, $p['name']);
            return $this->ok('Pipeline deleted.', '/settings/pipelines');
        });
    }

    private function stageInput(): array
    {
        $name = $this->str('name', 40) ?? $this->fail('Stage name is required');
        $kind = $this->str('kind') ?? 'OPEN';
        if (! in_array($kind, ['OPEN', 'WON', 'LOST'], true)) {
            $this->fail('Invalid stage type.');
        }
        $prob = (int) ($this->str('probability') ?? 0);
        $color = $this->str('color') ?? '#0068FF';
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $this->fail('Invalid colour');
        }
        $required = array_values(array_filter((array) $this->request->getPost('required_fields'), 'is_string'));
        return ['name' => $name, 'probability' => $kind === 'WON' ? 100 : ($kind === 'LOST' ? 0 : max(0, min(100, $prob))), 'color' => $color, 'is_won' => $kind === 'WON', 'is_lost' => $kind === 'LOST', 'required_fields' => $required];
    }

    public function addStage(int $pipelineId)
    {
        return $this->attempt(function () use ($pipelineId) {
            $p = $this->pipeline($pipelineId);
            $data = $this->stageInput();
            $stages = model(StageModel::class)->where('pipeline_id', $pipelineId)->orderBy('position')->findAll();
            $open = array_filter($stages, fn ($s) => ! $s['is_won'] && ! $s['is_lost']);
            $position = (! $data['is_won'] && ! $data['is_lost']) ? count($open) : count($stages);
            $db = db_connect();
            $db->transStart();
            if ($position < count($stages)) {
                $db->table('stages')->where('pipeline_id', $pipelineId)->where('position >=', $position)->set('position', 'position + 1', false)->update();
            }
            $sid = model(StageModel::class)->insert($data + ['pipeline_id' => $pipelineId, 'position' => $position]);
            $db->transComplete();
            Audit::log($this->me, 'add_stage', 'Pipeline', $pipelineId, $p['name'], null, $data);
            return $this->ok('Stage added.', '/settings/pipelines');
        }, 'stage-dialog-' . $pipelineId);
    }

    public function updateStage(int $id)
    {
        return $this->attempt(function () use ($id) {
            $s = $this->stage($id);
            $data = $this->stageInput();
            model(StageModel::class)->update($id, $data);
            Audit::log($this->me, 'update_stage', 'Pipeline', $s['pipeline_id'], $s['pipeline_name'], ['name' => $s['name'], 'probability' => $s['probability']], $data);
            return $this->ok('Stage updated.', '/settings/pipelines');
        }, 'stage-edit-dialog');
    }

    public function deleteStage(int $id)
    {
        return $this->attempt(function () use ($id) {
            $s = $this->stage($id);
            $deals = model(DealModel::class)->where('stage_id', $id)->countAllResults();
            if ($deals > 0) {
                $this->fail("This stage has {$deals} deal(s). Move them first.");
            }
            if (model(StageModel::class)->where('pipeline_id', $s['pipeline_id'])->countAllResults() <= 2) {
                $this->fail('A pipeline needs at least two stages.');
            }
            model(StageModel::class)->delete($id);
            $this->renumber((int) $s['pipeline_id']);
            Audit::log($this->me, 'delete_stage', 'Pipeline', $s['pipeline_id'], $s['pipeline_name'], ['name' => $s['name']]);
            return $this->ok('Stage deleted.', '/settings/pipelines');
        });
    }

    public function moveStage(int $id)
    {
        return $this->attempt(function () use ($id) {
            $s = $this->stage($id);
            $dir = $this->str('dir') === 'up' ? -1 : 1;
            $stages = model(StageModel::class)->where('pipeline_id', $s['pipeline_id'])->orderBy('position')->orderBy('id')->findAll();
            $ids = array_column($stages, 'id');
            $i = array_search($id, $ids, true);
            $j = $i + $dir;
            if ($j < 0 || $j >= count($ids)) {
                return redirect()->to('/settings/pipelines');
            }
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            foreach ($ids as $pos => $sid) {
                model(StageModel::class)->update($sid, ['position' => $pos]);
            }
            return redirect()->to('/settings/pipelines');
        });
    }

    private function renumber(int $pipelineId): void
    {
        foreach (model(StageModel::class)->where('pipeline_id', $pipelineId)->orderBy('position')->orderBy('id')->findAll() as $i => $s) {
            model(StageModel::class)->update($s['id'], ['position' => $i]);
        }
    }
}
