<?php $title = t('report.title'); $active = 'reports'; ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= t('nav.group.test') ?></div>
    <h1>📊 <?= t('report.title') ?></h1>
    <p><?= t('report.desc') ?></p>
  </div>
  <div class="btn-row">
    <a class="btn alt" id="btn-excel" href="#" style="display:none"><?= t('report.export_excel') ?></a>
    <a class="btn alt" id="btn-print" href="#" target="_blank" style="display:none"><?= t('report.print') ?></a>
  </div>
</div>

<div class="filterbar">
  <div class="frow">
    <select class="inp" id="sel-project" style="min-width:280px">
      <option value=""><?= t('report.select_project') ?>…</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= $p['id'] ?>"><?= esc($p['name']) ?> (<?= esc($p['code']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div id="report-body">
  <div class="card"><div class="tbl-empty">
    <span class="big">📊</span>
    <p><?= t('report.select_project') ?> تا گزارش نمایش داده شود</p>
  </div></div>
</div>

<script>
const STM = { READY:['در صف تست','st-ready'], IN_TEST:['در حال تست','st-intest'], IN_FIX:['در انتظار اصلاح','st-infix'], READY_RETEST:['آماده تست مجدد','st-retest'], BLOCKED:['مسدود','st-blocked'], PENDING_APPROVAL:['در انتظار تأیید','st-approval'], APPROVED:['تأیید شده','st-approved'], REJECTED:['رد شده','st-rejected'], CANCELLED:['لغو شده','st-cancelled'] };
const DSTM = { OPEN:['باز','st-infix'], IN_PROGRESS:['در حال اصلاح','st-intest'], FIXED_PENDING_RETEST:['نیازمند تست','st-retest'], VERIFIED:['تأیید','st-approved'], REOPENED:['بازگشایی','st-rejected'], CLOSED:['بسته','st-cancelled'] };
const SEV = { CRITICAL:'بحرانی', HIGH:'زیاد', MEDIUM:'متوسط', LOW:'کم' };

qs('#sel-project').addEventListener('change', () => loadReport());

async function loadReport() {
  const pid = qs('#sel-project').value;
  if (!pid) return;
  qs('#report-body').innerHTML = '<div class="card" style="padding:40px;text-align:center;color:var(--muted)">⏳ در حال بارگذاری…</div>';
  qs('#btn-excel').style.display = 'none'; qs('#btn-print').style.display = 'none';
  try {
    const d = await api(`/reports/data?project_id=${pid}`);
    qs('#btn-excel').href = `/reports/excel?project_id=${pid}`;
    qs('#btn-excel').style.display = '';
    qs('#btn-print').href = `/reports/print?project_id=${pid}`;
    qs('#btn-print').style.display = '';
    const s = d.summary;
    const max = Math.max(s.approved || 0, s.in_progress || 0, s.in_fix || 0, s.pending_approval || 0, s.closed || 0, 1);

    // خلاصهٔ KPI
    let html = `
    <div class="kpis" style="margin-bottom:18px">
      <div class="kpi"><div class="k-ic" style="background:var(--primary-soft)">📋</div><div><b>${s.total_cases || 0}</b><small><?= t('report.total_cases') ?></small></div></div>
      <div class="kpi"><div class="k-ic" style="background:#e0f5fa">▶️</div><div><b>${s.executed || 0}</b><small><?= t('report.executed') ?></small></div></div>
      <div class="kpi"><div class="k-ic" style="background:#e3f7ea">✅</div><div><b>${s.approved || 0}</b><small><?= t('report.approved') ?></small></div></div>
      <div class="kpi"><div class="k-ic" style="background:#fdf0dd">🎯</div><div><b>${s.pass_rate || 0}%</b><small><?= t('report.pass_rate') ?></small></div></div>
      <div class="kpi"><div class="k-ic" style="background:#fde5e5">🐛</div><div><b>${s.open_defects || 0}</b><small><?= t('report.open_defects') ?></small></div></div>
    </div>`;

    // نمودار وضعیت
    html += `<div class="two-col" style="margin-bottom:18px">
      <div class="card"><div class="card-h"><h2><?= t('report.summary') ?></h2></div><div class="card-b">`;
    const bars = [
      ['تأیید شده', s.approved, 'var(--st-approved)'],
      ['در حال اجرا', s.in_progress, 'var(--st-ready)'],
      ['در اصلاح', s.in_fix, 'var(--st-infix)'],
      ['در انتظار تأیید', s.pending_approval, 'var(--st-approval)'],
      ['خاتمه‌یافته', s.closed, 'var(--st-cancelled)'],
    ];
    for (const [lbl, cnt, color] of bars) {
      const pct = Math.round((cnt || 0) / (s.total_cases || 1) * 100);
      html += `<div class="bar-row"><span class="lbl">${lbl}</span><div class="track"><div class="fill" style="width:${pct}%;background:${color}"></div></div><span class="val">${cnt || 0}</span></div>`;
    }
    html += `</div></div>`;

    // پوشش
    html += `<div class="card"><div class="card-h"><h2><?= t('report.coverage') ?></h2></div><div class="card-b">
      <div style="text-align:center;padding:20px">
        <div class="donut" style="background:conic-gradient(var(--st-approved) 0% ${s.coverage || 0}%, var(--bg) ${s.coverage || 0}% 100%)" data-center="${s.coverage || 0}%"></div>
        <p style="margin-top:14px">${s.executed || 0} از ${s.total_cases || 0} سناریو اجرا شده</p>
      </div>
    </div></div></div>`;

    // به تفکیک بخش
    if (d.byNode && d.byNode.length) {
      html += `<div class="card" style="margin-bottom:18px"><div class="card-h"><h2><?= t('report.by_section') ?></h2></div>
      <div class="tbl-wrap"><table class="tbl"><thead><tr>
        <th><?= t('common.title') ?></th><th>کل</th><th>تأییدشده</th><th>در اصلاح</th><th>در جریان</th>
      </tr></thead><tbody>`;
      for (const n of d.byNode) {
        html += `<tr><td>${esc(n.node_name)}</td><td class="num"><b>${n.total}</b></td>
          <td><span class="pill st-approved">${n.approved || 0}</span></td>
          <td><span class="pill st-infix">${n.in_fix || 0}</span></td>
          <td><span class="pill st-ready">${n.active || 0}</span></td></tr>`;
      }
      html += `</tbody></table></div></div>`;
    }

    // خطاهای باز
    if (d.defects && d.defects.length) {
      html += `<div class="card" style="margin-bottom:18px"><div class="card-h"><h2>🐛 <?= t('report.open_defects') ?></h2></div>
      <div class="tbl-wrap"><table class="tbl"><thead><tr>
        <th>کد</th><th>عنوان</th><th>شدت</th><th>وضعیت</th><th>برنامه‌نویس</th>
      </tr></thead><tbody>`;
      for (const d2 of d.defects) {
        html += `<tr><td><b>${esc(d2.code)}</b></td><td>${esc(d2.title)}</td>
          <td><span class="pill pr-${d2.severity.toLowerCase()}">${SEV[d2.severity] || d2.severity}</span></td>
          <td><span class="pill ${DSTM[d2.status]?.[1] || ''}">${DSTM[d2.status]?.[0] || d2.status}</span></td>
          <td>${esc(d2.developer_name || '—')}</td></tr>`;
      }
      html += `</tbody></table></div></div>`;
    }

    // آخرین اجراها
    if (d.recent && d.recent.length) {
      html += `<div class="card"><div class="card-h"><h2>🕘 <?= t('report.recent_exec') ?></h2></div>
      <div class="tbl-wrap"><table class="tbl"><thead><tr>
        <th>کد</th><th>سناریو</th><th>نتیجه</th><th>آزمونگر</th><th>زمان</th>
      </tr></thead><tbody>`;
      for (const r of d.recent) {
        const rm = { PASS: ['موفق', 'st-approved'], FAIL: ['ناموفق', 'st-infix'], BLOCKED: ['مسدود', 'st-blocked'] };
        html += `<tr><td><b>${esc(r.code)}</b></td><td>${esc(r.title)}</td>
          <td><span class="pill ${rm[r.result]?.[1] || ''}">${rm[r.result]?.[0] || r.result}</span></td>
          <td>${esc(r.tester)}</td><td class="num">${faDateTime(r.executed_at)}</td></tr>`;
      }
      html += `</tbody></table></div></div>`;
    }

    qs('#report-body').innerHTML = html;
  } catch (e) {
    qs('#report-body').innerHTML = `<div class="flash err">⚠ ${esc(e.message)}</div>`;
  }
}
</script>
<?= $this->endSection() ?>
