<?php
/** Tabs for a record page: $entity, $record, $timeline, $activities, $notes, $files, $me, $users, $canEdit, optional $extraTabs (array key => [label, count, html]) */
$col = \App\Libraries\Records::parentColumn($entity);
$openCount = count(array_filter($activities, fn ($a) => $a['status'] === 'OPEN'));
$tabs = ['timeline' => ['Timeline', null], 'activities' => ['Activities', $openCount]];
foreach ($extraTabs ?? [] as $k => $t) { $tabs[$k] = [$t[0], $t[1]]; }
$tabs['notes'] = ['Notes', count($notes)];
$tabs['files'] = ['Files', count($files)];
?>
<div class="card card-pad">
  <div class="mb-4 flex flex-wrap gap-1 border-b border-line-100" data-tabs>
    <?php $first = true; foreach ($tabs as $k => [$label, $count]): ?><button type="button" class="tab<?= $first ? ' active' : '' ?>" data-tab="<?= $k ?>"><?= $label ?><?= $count !== null ? ' <span class="badge badge-neutral ml-1">' . $count . '</span>' : '' ?></button><?php $first = false; endforeach ?>
  </div>
  <div data-panel="timeline">
    <?php if (! $timeline): ?><p class="muted text-[13px]">Nothing here yet. Notes, activities, files and changes will appear in order.</p><?php endif ?>
    <ol class="relative ml-2 space-y-4 border-l border-line-100 pl-5">
      <?php foreach ($timeline as $t): $iconName = match ($t['kind']) { 'note' => 'note', 'file' => 'file', 'activity' => 'activities', default => 'clock' }; ?>
        <li class="relative"><span class="absolute -left-[29px] top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary-50 text-primary"><?= icon($iconName, 'h-2.5 w-2.5') ?></span>
          <div class="flex flex-wrap items-baseline justify-between gap-2"><p class="text-[13px] font-medium"><?= ! empty($t['href']) ? '<a class="hover:text-primary" href="' . esc($t['href'], 'attr') . '">' . esc($t['title']) . '</a>' : esc($t['title']) ?></p><span class="text-xs muted" title="<?= esc(format_datetime($t['at']), 'attr') ?>"><?= relative_time($t['at']) ?></span></div>
          <?php if (! empty($t['detail'])): ?><p class="mt-0.5 whitespace-pre-line text-[13px] text-ink-700"><?= esc($t['detail']) ?></p><?php endif ?>
          <?php if (! empty($t['meta'])): ?><p class="mt-0.5 text-xs muted"><?= esc($t['meta']) ?></p><?php endif ?>
        </li>
      <?php endforeach ?>
    </ol>
  </div>
  <div data-panel="activities" hidden><?= view('partials/activity_list', ['items' => $activities, 'users' => $users, 'showLinks' => false, 'emptyText' => 'No tasks, calls or meetings yet. Use the buttons above to add one.']) ?></div>
  <?php foreach ($extraTabs ?? [] as $k => $t): ?><div data-panel="<?= $k ?>" hidden><?= $t[2] ?></div><?php endforeach ?>
  <div data-panel="notes" hidden id="notes">
    <form method="post" action="/notes" class="mb-4"><?= csrf_field() ?><input type="hidden" name="entity" value="<?= $entity ?>"><input type="hidden" name="entity_id" value="<?= $record['id'] ?>">
      <textarea class="textarea" name="body" placeholder="Write a note…" required></textarea>
      <div class="mt-2 flex justify-end"><button class="btn btn-primary btn-sm" type="submit">Add note</button></div></form>
    <?php if (! $notes): ?><p class="muted text-[13px]">No notes yet.</p><?php endif ?>
    <?php foreach ($notes as $n): ?>
      <div class="mb-3 rounded-md border border-line-100 p-3" data-testid="note">
        <div class="mb-1 flex items-center justify-between text-xs muted"><span class="inline-flex items-center gap-1.5"><?= avatar($n['author_name'], $n['author_color'], 18) ?><?= esc($n['author_name']) ?> · <?= relative_time($n['created_at']) ?></span>
          <?php if ($n['author_id'] === $me['id'] || $me['role'] !== 'MEMBER'): ?><form method="post" action="/notes/<?= $n['id'] ?>/delete" data-confirm="Delete this note?"><?= csrf_field() ?><button class="hover:text-danger" title="Delete"><?= icon('trash', 'h-3.5 w-3.5') ?></button></form><?php endif ?></div>
        <p class="whitespace-pre-line text-[13px]"><?= esc($n['body']) ?></p>
      </div>
    <?php endforeach ?>
  </div>
  <div data-panel="files" hidden id="files">
    <?php if ($canEdit): ?>
    <form method="post" action="/attachments" enctype="multipart/form-data" class="mb-4 flex flex-wrap items-center gap-2"><?= csrf_field() ?><input type="hidden" name="entity" value="<?= $entity ?>"><input type="hidden" name="entity_id" value="<?= $record['id'] ?>">
      <input type="file" name="file" class="text-[13px]" required><button class="btn btn-primary btn-sm" type="submit"><?= icon('upload') ?> Upload</button><span class="hint">Up to 15 MB</span></form>
    <?php endif ?>
    <?php if (! $files): ?><p class="muted text-[13px]">No files yet.</p><?php endif ?>
    <ul class="divide-y divide-line-100">
      <?php foreach ($files as $f): ?>
        <li class="flex items-center justify-between gap-3 py-2 text-[13px]" data-testid="file">
          <a href="/files/<?= $f['id'] ?>" class="inline-flex items-center gap-2 hover:text-primary" target="_blank"><?= icon('file') ?><?= esc($f['filename']) ?></a>
          <span class="flex items-center gap-3 text-xs muted"><?= file_size_label((int) $f['size']) ?> · <?= esc($f['uploader_name']) ?> · <?= relative_time($f['created_at']) ?>
            <?php if ($f['uploaded_by_id'] === $me['id'] || $me['role'] !== 'MEMBER'): ?><form method="post" action="/attachments/<?= $f['id'] ?>/delete" data-confirm="Remove this file?"><?= csrf_field() ?><button class="hover:text-danger" title="Remove"><?= icon('trash', 'h-3.5 w-3.5') ?></button></form><?php endif ?></span>
        </li>
      <?php endforeach ?>
    </ul>
  </div>
</div>
