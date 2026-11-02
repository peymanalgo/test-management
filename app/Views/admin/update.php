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
    <div style="margin-bottom:20px;padding:16px;background:var(--bg);border-radius:13px;display:flex;gap:12px;align-items:center">
      <span style="font-size:32px" id="u-ic">⏳</span>
      <div style="flex:1">
        <b id="u-lbl">در حال بررسی…</b>
        <div style="font-size:12px;color:var(--muted);margin-top:4px" id="u-det">—</div>
      </div>
    </div>
    <div id="u-res" class="hide" style="margin-bottom:16px"></div>
    <div class="btn-row">
      <button class="btn" id="u-btn" onclick="applyUpd()" disabled style="opacity:.5">⬇ دریافت و اعمال آپدیت</button>
      <button class="btn alt" onclick="checkUpd()">🔄 بررسی مجدد</button>
    </div>
  </div>
</div>

<script>
async function checkUpd() {
  const ic = qs('#u-ic'), lbl = qs('#u-lbl'), det = qs('#u-det'), btn = qs('#u-btn');
  ic.textContent = '⏳'; lbl.textContent = 'در حال بررسی…'; btn.disabled = true; btn.style.opacity = .5;
  qs('#u-res').classList.add('hide');
  try {
    const d = await api('/admin/update/check');
    if (d.hasUpdate) {
      ic.textContent = '📦'; lbl.textContent = 'نسخهٔ جدیدی در دسترس است.';
      det.innerHTML = `فعلی: <code style="font-size:10px">${esc(String(d.local).slice(0,20))}</code> → جدید: <code style="font-size:10px">${esc(String(d.remote).slice(0,20))}</code>`;
      btn.disabled = false; btn.style.opacity = 1;
    } else {
      ic.textContent = '✅'; lbl.textContent = 'شما آخرین نسخه را دارید.';
      det.innerHTML = `نسخه: <code style="font-size:10px">${esc(String(d.local).slice(0,20))}</code>`;
      btn.disabled = true; btn.style.opacity = .5;
    }
  } catch (e) {
    ic.textContent = '📦'; lbl.textContent = 'بررسی ممکن نبود — می‌توانید مستقیم آپدیت کنید.';
    btn.disabled = false; btn.style.opacity = 1;
  }
}

async function applyUpd() {
  const ic = qs('#u-ic'), lbl = qs('#u-lbl'), btn = qs('#u-btn'), res = qs('#u-res');
  ic.textContent = '⏬'; lbl.textContent = 'در حال دریافت… (حدود ۳۰ ثانیه)'; btn.disabled = true; btn.style.opacity = .5;
  try {
    const d = await api('/admin/update/apply', { method: 'POST' });
    res.classList.remove('hide');
    res.innerHTML = d.ok
      ? `<div class="flash ok">✓ ${esc(d.message)} — صفحه در حال رفرش…</div>`
      : `<div class="flash err">⚠ ${esc(d.message)}</div>`;
    ic.textContent = d.ok ? '✅' : '⚠️';
    lbl.textContent = d.ok ? 'آپدیت با موفقیت اعمال شد.' : 'آپدیت ناموفق بود.';
    if (d.ok) setTimeout(() => location.reload(), 2000);
  } catch (e) {
    res.classList.remove('hide');
    res.innerHTML = `<div class="flash err">⚠ ${esc(e.message)}</div>`;
    ic.textContent = '⚠️'; lbl.textContent = 'آپدیت ناموفق بود.';
  }
  btn.disabled = false; btn.style.opacity = 1;
}

document.addEventListener('DOMContentLoaded', checkUpd);
</script>
<?= $this->endSection() ?>
