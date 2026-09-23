<dialog id="lost-reason-dialog" class="modal"><form method="dialog"><input type="hidden" name="deal_id"><input type="hidden" name="stage_id"><input type="hidden" name="position">
  <h2 class="card-title mb-1">Why was this deal lost?</h2>
  <p class="mb-3 text-[13px] muted">A reason is required when a deal moves to a lost stage.</p>
  <div class="field"><label class="label" for="lost-reason">Reason</label><select class="select" id="lost-reason" name="lost_reason" required><?php foreach (\App\Libraries\Defaults::LOST_REASONS as $r): ?><option value="<?= esc($r, 'attr') ?>"><?= esc($r) ?></option><?php endforeach ?></select></div>
  <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-danger" type="submit" value="ok">Mark as lost</button></div>
</form></dialog>
