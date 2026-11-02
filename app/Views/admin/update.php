<?php $title = t('update.title'); $active = 'update'; ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= t('nav.group.system') ?></div>
    <h1>🔄 <?= t('update.title') ?></h1>
    <p><?= t('update.desc') ?></p>
  </div>
</div>

<div class="card" style="max-width:640px">
  <div class="card-b">
    <div id="update-status" style="margin-bottom:20px">
      <div style="display:flex;align-items:center;gap:12px;padding:16px;background:var(--bg);border-radius:13px">
        <span style="font-size:32px" id="update-icon">⏳</span>
        <div style="flex:1">
          <b id="update-label"><?= t('update.checking') ?></b>
          <div style="font-size:12px;color:var(--muted);margin-top:4px" id="update-detail">
            <?= t('update.local_version') ?>: <code><?= esc($localVersion) ?></code>
          </div>
        </div>
      </div>
    </div>
    <div id="update-result" class="hide" style="margin-bottom:20px"></div>
    <div class="btn-row">
      <button class="btn" id="btn-apply" onclick="applyUpdate()" style="display:none">
        ⬇ <?= t('update.apply') ?>
      </button>
      <button class="btn alt" onclick="checkUpdate()">
        🔄 <?= t('update.recheck') ?>
      </button>
    </div>
  </div>
</div>

<script>
window.I18N.update = {
  checking: <?= json_encode(t('update.checking')) ?>,
  up_to_date: <?= json_encode(t('update.up_to_date')) ?>,
  new_version: <?= json_encode(t('update.new_version')) ?>,
  remote_version: <?= json_encode(t('update.remote_version')) ?>,
  apply: <?= json_encode(t('update.apply')) ?>,
  applying: <?= json_encode(t('update.applying')) ?>,
  connection_error: <?= json_encode(t('update.connection_error')) ?>,
};

async function checkUpdate() {
  const el = qs('#update-icon'), lbl = qs('#update-label'), det = qs('#update-detail');
  const btn = qs('#btn-apply'), res = qs('#update-result');
  el.textContent = '⏳'; lbl.textContent = I18N.update.checking; btn.style.display = 'none'; res.classList.add('hide');
  try {
    const d = await api('/admin/update/check');
    if (d.hasUpdate) {
      el.textContent = '📦';
      lbl.textContent = I18N.update.new_version;
      det.innerHTML = `<?= t('update.local_version') ?>: <code>${esc(d.local)}</code><br>${I18N.update.remote_version}: <code>${esc(d.remote)}</code>`;
      btn.style.display = 'inline-flex';
    } else {
      el.textContent = '✅';
      lbl.textContent = I18N.update.up_to_date;
      det.innerHTML = `<?= t('update.local_version') ?>: <code>${esc(d.local)}</code>`;
    }
  } catch (e) {
    el.textContent = '⚠️'; lbl.textContent = I18N.update.connection_error;
    det.textContent = e.message;
  }
}

async function applyUpdate() {
  const el = qs('#update-icon'), lbl = qs('#update-label'), btn = qs('#btn-apply'), res = qs('#update-result');
  el.textContent = '⏬'; lbl.textContent = I18N.update.applying; btn.disabled = true; btn.style.opacity = .5;
  try {
    const d = await api('/admin/update/apply');
    res.classList.remove('hide');
    res.innerHTML = d.ok
      ? `<div class="flash ok">✓ ${esc(d.message)}</div>`
      : `<div class="flash err">⚠ ${esc(d.message)}</div>`;
    el.textContent = d.ok ? '✅' : '⚠️';
    lbl.textContent = d.ok ? '<?= t("update.done") ?>' : '<?= t("update.failed") ?>';
    if (d.ok) setTimeout(() => location.reload(), 2000);
  } catch (e) {
    res.classList.remove('hide');
    res.innerHTML = `<div class="flash err">⚠ ${esc(e.message)}</div>`;
    el.textContent = '⚠️'; lbl.textContent = '<?= t("update.failed") ?>';
  }
  btn.disabled = false; btn.style.opacity = 1;
}

document.addEventListener('DOMContentLoaded', checkUpdate);
</script>
<?= $this->endSection() ?>
