<?php /** $rows: label, value, display, sub?, href?, color? ; $color default; $empty */ $max = max(array_map(fn ($r) => (float) $r['value'], $rows ?: [['value' => 0]])); ?>
<?php if (! $rows || $max <= 0 && ! array_filter($rows, fn ($r) => $r['value'] > 0)): ?><p class="muted text-[13px]"><?= esc($empty ?? 'Nothing to show yet.') ?></p><?php endif ?>
<?php if ($rows): ?><ul class="space-y-2.5">
<?php foreach ($rows as $r): $pct = $max > 0 ? max(2, (int) round($r['value'] / $max * 100)) : 2; $inner = '<div class="flex items-baseline justify-between text-[13px]"><span class="font-medium text-ink">' . esc($r['label']) . '</span><span class="tabular">' . esc($r['display']) . (! empty($r['sub']) ? ' <span class="text-xs muted">· ' . esc($r['sub']) . '</span>' : '') . '</span></div><div class="mt-1 h-2 w-full rounded-full bg-surface"><div class="h-2 rounded-full" style="width:' . $pct . '%;background:' . esc($r['color'] ?? $color ?? '#0068FF', 'attr') . '"></div></div>'; ?>
  <li><?= ! empty($r['href']) ? '<a href="' . esc($r['href'], 'attr') . '" class="block rounded hover:bg-surface/60">' . $inner . '</a>' : $inner ?></li>
<?php endforeach ?>
</ul><?php endif ?>
