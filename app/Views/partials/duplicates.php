<?php $dups = session()->getFlashdata('duplicates'); if ($dups): ?>
  <div class="alert alert-error mb-3" data-testid="duplicates">
    <p class="font-medium">Possible duplicate<?= count($dups) === 1 ? '' : 's' ?> found</p>
    <ul class="mt-1 list-disc pl-5">
      <?php foreach ($dups as $d): ?><li><a class="link" href="<?= $href ?>/<?= $d['id'] ?>"><?= esc($d['name']) ?></a> — <?= esc($d['reason']) ?><?= ! empty($d['email']) ? ' (' . esc($d['email']) . ')' : '' ?><?= ! empty($d['phone']) ? ' ' . esc($d['phone']) : '' ?></li><?php endforeach ?>
    </ul>
    <p class="mt-2">Open the existing record, or <button type="submit" name="force" value="1" class="link font-medium" data-no-disable>create anyway</button>.</p>
  </div>
<?php endif ?>
