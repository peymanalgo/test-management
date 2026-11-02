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
    <div style="margin-bottom:20px">
      <div style="display:flex;align-items:center;gap:12px;padding:16px;background:var(--bg);border-radius:13px">
        <span style="font-size:32px" id="update-icon">⏳</span>
        <div style="flex:1">
          <b id="update-label"><?= t('update.checking') ?></b>
          <div style="font-size:12px;color:var(--muted);margin-top:4px" id="update-detail">
            <?= t('update.local_version') ?>: <code style="direction:ltr;display:inline-block;font-size:10px"><?= esc(substr($localVersion, 0, 12)) ?></code>
          </div>
        </div>
      </div>
    </div>
    <div id="update-result" class="hide" style="margin-bottom:20px"></div>
    <div class="btn-row">
      <button class="btn" id="btn-apply" onclick="applyUpdate()">
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
  apply: <?= json_encode(t('update.apply')) ?>,
  applying: <?= json_encode(t('update.applying')) ?>,
};

async function checkUpdate() {
  const el = qs('#update-icon'), lbl = qs('#update-label'), det = qs('#update-detail');
  el.textContent = '⏳'; lbl.textContent = I18N.update.checking;
  try {
    const d = await api('/admin/update/check');
    el.textContent = '📦';
    lbl.textContent = d.message || 'برای دریافت آخرین تغییرات، آپدیت را بزنید.';
    if (d.remote && d.remote !== 'در دسترس') {
      det.innerHTML = `نسخهٔ جدید: <code style="font-size:10px">${esc(String(d.remote).slice(0,30))}</code>`;
    }
  } catch (e) {
    el.textContent = '📦';
    lbl.textContent = 'برای دریافت آخرین تغییرات، دکمهٔ آپدیت را بزنید.';
  }
}

async function applyUpdate() {
  const el = qs('#update-icon'), lbl = qs('#update-label'), btn = qs('#btn-apply'), res = qs('#update-result');
  el.textContent = '⏬'; lbl.textContent = I18N.update.applying; btn.disabled = true; btn.style.opacity = .5;
  res.classList.add('hide');
  try {
    const d = await api('/admin/update/apply', { method: 'POST' });
    res.classList.remove('hide');
    res.innerHTML = d.ok
      ? `<div class="flash ok">✓ ${esc(d.message)}<br><small>Commit: <code>${esc((d.commit||'').slice(0,8))}</code> — صفحه در حال رفرش…</small></div>`
      : `<div class="flash err">⚠ ${esc(d.message)}</div>`;
    el.textContent = d.ok ? '✅' : '⚠️';
    lbl.textContent = d.ok ? '<?= t("update.done") ?>' : '<?= t("update.failed") ?>';
    if (d.ok) setTimeout(() => location.reload(), 3000);
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
