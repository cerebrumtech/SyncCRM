<?php /** $tags (suggestions), $value (array of tags), $id */ $id = $id ?? 'tags'; $old = old('tags'); ?>
<div class="field"><label class="label" for="<?= $id ?>">Tags</label>
  <input class="input" id="<?= $id ?>" name="tags" list="tag-suggestions" placeholder="comma separated, e.g. priority, patsanstha" value="<?= esc($old !== null ? $old : implode(', ', $value ?? []), 'attr') ?>">
  <datalist id="tag-suggestions"><?php foreach ($tags as $t): ?><option value="<?= esc($t, 'attr') ?>"><?php endforeach ?></datalist>
</div>
