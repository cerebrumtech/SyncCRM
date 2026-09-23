<?php /** $deals rows from Lists::deals */ ?>
<?php if (! $deals): ?><p class="muted text-[13px]">No deals yet. <a class="link" href="/deals?new=1<?= $newParams ?? '' ?>">Create one</a>.</p><?php else: ?>
<table class="table"><thead><tr><th>Deal</th><th>Pipeline / stage</th><th class="text-right">Amount</th><th>Status</th><th>Close</th><th>Owner</th></tr></thead><tbody>
<?php foreach ($deals as $d): ?>
  <tr><td><a class="font-medium text-primary hover:underline" href="/deals/<?= $d['id'] ?>"><?= esc($d['title']) ?></a></td>
    <td><span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background:<?= esc($d['stage_color'], 'attr') ?>"></span><?= esc($d['pipeline_name']) ?> · <?= esc($d['stage_name']) ?></span></td>
    <td class="text-right tabular"><?= format_inr($d['amount']) ?></td><td><?= deal_status_badge($d['status']) ?></td><td class="muted"><?= format_date($d['expected_close_date']) ?></td>
    <td><?= $d['owner_name'] ? esc($d['owner_name']) : '<span class="muted">—</span>' ?></td></tr>
<?php endforeach ?>
</tbody></table>
<?php endif ?>
