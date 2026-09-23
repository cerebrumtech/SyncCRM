<?php /** $required (key=>label), $idp */ ?>
<div class="grid gap-3 sm:grid-cols-2">
  <div class="field sm:col-span-2"><label class="label" for="<?= $idp ?>-name">Stage name</label><input class="input" id="<?= $idp ?>-name" name="name" required></div>
  <div class="field"><label class="label" for="<?= $idp ?>-kind">Type</label><select class="select" id="<?= $idp ?>-kind" name="kind"><option value="OPEN">Open (in progress)</option><option value="WON">Won (closes the deal)</option><option value="LOST">Lost (closes the deal)</option></select></div>
  <div class="field"><label class="label" for="<?= $idp ?>-prob">Probability %</label><input class="input" id="<?= $idp ?>-prob" name="probability" type="number" min="0" max="100" value="50"></div>
  <div class="field"><label class="label" for="<?= $idp ?>-color">Colour</label><input class="input h-9" id="<?= $idp ?>-color" name="color" type="color" value="#0068FF"></div>
</div>
<fieldset class="field"><legend class="label">Required before a deal can enter this stage</legend>
  <div class="grid gap-1 sm:grid-cols-2"><?php foreach ($required as $k => $l): ?><label class="flex items-center gap-2 text-[13px]"><input type="checkbox" name="required_fields[]" value="<?= esc($k, 'attr') ?>"> <?= esc($l) ?></label><?php endforeach ?></div>
</fieldset>
