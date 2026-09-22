<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Settings', 'subtitle' => 'Organisation profile and data rules']) ?>
<?= view('settings/_nav') ?>
<div class="grid gap-4 lg:grid-cols-2">
  <div class="card card-pad">
    <h2 class="card-title mb-3">Organisation</h2>
    <form method="post" action="/settings/organization"><?= csrf_field() ?>
      <div class="field"><label class="label" for="org-name">Name</label><input class="input" id="org-name" name="name" value="<?= old_or('name', $org['name']) ?>" required></div>
      <div class="grid grid-cols-2 gap-3">
        <div class="field"><label class="label">Currency</label><input class="input" value="INR (₹)" disabled></div>
        <div class="field"><label class="label">Timezone</label><input class="input" value="Asia/Kolkata (IST)" disabled></div>
      </div>
      <button class="btn btn-primary" type="submit">Save</button>
    </form>
  </div>
  <div class="card card-pad">
    <h2 class="card-title mb-1">Duplicate detection</h2>
    <p class="mb-3 text-[13px] muted"><b>Warn</b> shows possible duplicates and lets the user decide; <b>Block</b> refuses to create the record; <b>Off</b> skips the check.</p>
    <form method="post" action="/settings/organization/dedupe"><?= csrf_field() ?>
      <?php foreach (['contactEmail' => 'Contacts with the same email', 'contactPhone' => 'Contacts with the same phone / WhatsApp number', 'companyName' => 'Companies with the same name'] as $k => $label): ?>
        <div class="field flex items-center justify-between gap-3"><label class="label mb-0" for="dd-<?= $k ?>"><?= $label ?></label>
          <select class="select w-28" id="dd-<?= $k ?>" name="<?= $k ?>"><?php foreach (['warn' => 'Warn', 'block' => 'Block', 'off' => 'Off'] as $v => $l): ?><option value="<?= $v ?>"<?= selected_if($dedupe[$k] === $v) ?>><?= $l ?></option><?php endforeach ?></select></div>
      <?php endforeach ?>
      <button class="btn btn-primary" type="submit">Save rules</button>
    </form>
  </div>
</div>
<?= $this->endSection() ?>
