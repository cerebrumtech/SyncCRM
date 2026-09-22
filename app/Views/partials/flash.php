<?php $s = session(); ?>
<?php if ($s->getFlashdata('error')): ?>
  <div class="alert alert-error" data-testid="form-error" role="alert"><?= esc($s->getFlashdata('error')) ?></div>
<?php endif ?>
<?php if ($s->getFlashdata('success')): ?>
  <div class="alert alert-success" data-testid="form-success"><?= esc($s->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if ($s->getFlashdata('info')): ?>
  <div class="alert alert-info"><?= $s->getFlashdata('info') ?></div>
<?php endif ?>
<?php if ($s->getFlashdata('open')): ?>
  <div hidden data-auto-open="<?= esc($s->getFlashdata('open'), 'attr') ?>"></div>
<?php endif ?>
