<?php
$items = [
    ['/settings/users', 'Users'], ['/settings/teams', 'Teams'], ['/settings/pipelines', 'Pipelines'], ['/settings/fields', 'Custom fields'],
    ['/settings/import', 'Import'], ['/settings/organization', 'Organisation'], ['/settings/audit', 'Audit log'],
];
$path = '/' . ltrim(service('request')->getUri()->getPath(), '/');
?>
<div class="mb-5 flex flex-wrap gap-1 border-b border-line-100" data-testid="settings-nav">
  <?php foreach ($items as [$href, $label]): ?>
    <a href="<?= $href ?>" class="tab<?= $path === $href || ($href === '/settings/users' && $path === '/settings') ? ' active' : '' ?>"><?= esc($label) ?></a>
  <?php endforeach ?>
</div>
