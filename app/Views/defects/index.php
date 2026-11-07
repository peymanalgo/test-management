<?php $title = t('defects.title'); $active = 'defects'; ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= t('nav.group.test') ?></div>
    <h1>🐛 <?= t('defects.title') ?></h1>
    <p><?= t('defects.desc') ?></p>
  </div>
  <a class="btn alt" href="/export/defects"><?= t('defects.export') ?></a>
</div>

<div class="filterbar">
  <div class="frow">
    <div class="search" style="flex:1.4"><span class="sic">🔎</span>
      <input class="inp" data-q placeholder="جست‌وجو در کد، عنوان، توضیح…"></div>
    <select class="inp" data-status><option value="">همهٔ وضعیت‌ها</option>
      <option value="OPEN">باز</option><option value="IN_PROGRESS">در حال اصلاح</option>
      <option value="FIXED_PENDING_RETEST">نیازمند تست</option><option value="VERIFIED">تأیید شده</option>
      <option value="REOPENED">بازگشایی</option><option value="CLOSED">بسته</option></select>
    <select class="inp" data-severity><option value="">همهٔ شدت‌ها</option>
      <option value="CRITICAL">بحرانی</option><option value="HIGH">زیاد</option>
      <option value="MEDIUM">متوسط</option><option value="LOW">کم</option></select>
  </div>
</div>
<div data-grid></div>

<script>
const SEV = { CRITICAL:'بحرانی', HIGH:'زیاد', MEDIUM:'متوسط', LOW:'کم' };
const DSTM = { OPEN:['باز','st-infix'], IN_PROGRESS:['در حال اصلاح','st-intest'], FIXED_PENDING_RETEST:['نیازمند تست','st-retest'], VERIFIED:['تأیید','st-approved'], REOPENED:['بازگشایی','st-rejected'], CLOSED:['بسته','st-cancelled'] };

const grid = createGrid({
  container: qs('[data-grid]'),
  endpoint: '/defects/data',
  filterState: () => ({ q: qs('[data-q]').value, status: qs('[data-status]').value, severity: qs('[data-severity]').value }),
  onRowClick: (r) => detail(r.id),
  columns: [
    { key: 'code', label: 'کد', render: (r) => `<b style="direction:ltr;display:inline-block">${esc(r.code)}</b>` },
    { key: 'title', label: 'عنوان', render: (r) => `${esc(r.title)}<br><small class="text-muted">${esc(r.tc_code)} — ${esc(r.node_name || '')}</small>` },
    { key: 'project_name', label: 'پروژه' },
    { key: 'severity', label: 'شدت', render: (r) => `<span class="pill pr-${r.severity.toLowerCase()}">${SEV[r.severity] || r.severity}</span>` },
    { key: 'status', label: 'وضعیت', render: (r) => `<span class="pill ${DSTM[r.status]?.[1] || ''}">${DSTM[r.status]?.[0] || r.status}</span>` },
    { key: 'developer_name', label: 'برنامه‌نویس', render: (r) => esc(r.developer_name || '—') },
    { key: 'created_at', label: 'ثبت', render: (r) => `<span class="num">${faDate(r.created_at)}</span>` },
  ],
});
grid.load();
let deb; qs('[data-q]').addEventListener('input', () => { clearTimeout(deb); deb = setTimeout(() => grid.reload(), 350); });
qs('[data-status]').addEventListener('change', () => grid.reload());
qs('[data-severity]').addEventListener('change', () => grid.reload());

async function detail(id) {
  let d; try { d = await api(`/defects/${id}/detail`); } catch (e) { toast(e.message, 'err'); return; }
  const x = d.defect;
  const comments = (d.comments || []).map((c) => `
    <div style="background:var(--bg);border-radius:12px;padding:10px 14px;margin-bottom:8px">
      <b style="font-size:12px">${esc(c.author)}</b> <time style="font-size:10.5px;color:var(--muted-2)">${faDateTime(c.created_at)}</time>
      <div style="font-size:12.5px;margin-top:3px">${esc(c.body)}</div>
    </div>`).join('') || '<p class="text-muted">کامنتی ثبت نشده</p>';
  const m = openModal({
    title: `${esc(x.code)} — ${esc(x.title)}`,
    sub: `پروژه ${esc(x.project_name)} • سناریو ${esc(x.tc_code)}`,
    wide: true,
    body: `<div class="two-col">
      <div>
        <dl class="kv">
          <dt>شدت</dt><dd><span class="pill pr-${x.severity.toLowerCase()}">${SEV[x.severity] || x.severity}</span></dd>
          <dt>وضعیت</dt><dd><span class="pill ${DSTM[x.status]?.[1] || ''}">${DSTM[x.status]?.[0] || x.status}</span></dd>
          <dt>گزارش‌دهنده</dt><dd>${esc(x.reporter_name || '—')}</dd>
          <dt>برنامه‌نویس</dt><dd>${esc(x.developer_name || '—')}</dd>
          <dt>زمان ثبت</dt><dd>${faDateTime(x.created_at)}</dd>
        </dl>
        <div class="section-title mt">توضیح</div>
        <p style="font-size:12.5px;white-space:pre-wrap">${esc(x.description || '—')}</p>
        ${x.fix_notes ? `<div class="section-title mt">یادداشت رفع</div><div class="flash ok" style="white-space:pre-wrap">${esc(x.fix_notes)}</div>` : ''}
      </div>
      <div>
        <div class="section-title">گفت‌وگو</div>
        ${comments}
        <div class="field mt"><textarea class="inp" data-comment rows="2" placeholder="کامنت جدید…"></textarea></div>
        <button class="btn sm" data-send>ثبت کامنت</button>
      </div>
    </div>`,
    footer: `<button class="btn alt" data-close>بستن</button>`,
  });
  qs('[data-close]', m.root).addEventListener('click', m.close);
  qs('[data-send]', m.root).addEventListener('click', async () => {
    const body = qs('[data-comment]', m.root).value.trim();
    if (body.length < 2) { toast('متن کوتاه است.', 'warn'); return; }
    try { await api(`/defects/${id}/comment`, { method: 'POST', body: { body } }); toast('کامنت ثبت شد.'); m.close(); detail(id); }
    catch (e) { toast(e.message, 'err'); }
  });
}
</script>
<?= $this->endSection() ?>
