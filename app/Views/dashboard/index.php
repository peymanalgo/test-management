<?php $title = t('nav.dashboard'); $active = 'dashboard'; ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= t('nav.group.home') ?></div>
    <h1><?= t('dash.hello') ?>، <?= esc(session('display_name')) ?> 👋</h1>
    <p><?= t('dash.desc') ?></p>
  </div>
  <div class="btn-row">
    <a class="btn alt" href="/projects"><?= t('nav.projects') ?></a>
    <a class="btn" href="/projects"><?= t('dash.new_project') ?></a>
  </div>
</div>

<?php if (!$projects): ?>
  <div class="card"><div class="tbl-empty">
    <span class="big">📁</span>
    <h3><?= t('dash.no_project') ?></h3>
    <p><?= t('dash.no_project_sub') ?></p>
    <a class="btn" href="/projects"><?= t('dash.first_project') ?></a>
  </div></div>
<?php else: ?>
  <div class="grid-auto">
    <?php foreach ($projects as $p): $total = max(1, (int) $p['total_cases']); ?>
    <div class="proj-card">
      <div class="pc-top"><span class="code"><?= esc($p['code']) ?></span></div>
      <div class="pc-b" onclick="location.href='/projects/<?= $p['id'] ?>/tree'">
        <h3><?= esc($p['name']) ?></h3>
        <small class="text-muted"><?= t('dash.last_exec') ?>: <?= $p['last_execution'] ? fa_dt($p['last_execution']) : t('dash.never') ?></small>
        <div style="margin-top:6px">
          <?php
            $bars = [
              [t('dash.approved'), $p['approved'], 'var(--st-approved)'],
              [t('dash.in_fix'), $p['in_fix'], 'var(--st-infix)'],
              [t('status.PENDING_APPROVAL'), $p['pending_approval'], 'var(--st-approval)'],
              [t('status.READY'), $p['pending_test'], 'var(--st-ready)'],
            ];
            foreach ($bars as [$lbl, $cnt, $color]): ?>
            <div class="bar-row">
              <span class="lbl"><?= $lbl ?></span>
              <div class="track"><div class="fill" style="width:<?= min(100, round(100 * $cnt / $total)) ?>%;background:<?= $color ?>"></div></div>
              <span class="val"><?= (int) $cnt ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="proj-stats">
          <div><b><?= (int) $p['total_cases'] ?></b><small><?= t('dash.cases') ?></small></div>
          <div><b style="color:var(--danger)"><?= (int) $p['open_defects'] ?></b><small><?= t('dash.open_defects') ?></small></div>
          <div><b><?= (int) $p['in_fix'] ?></b><small><?= t('dash.in_fix') ?></small></div>
          <div><b style="color:var(--success)"><?= (int) $p['approved'] ?></b><small><?= t('dash.approved') ?></small></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card mt">
    <div class="card-h"><h2><?= t('dash.recent') ?></h2><span class="text-muted" style="font-size:11.5px"><?= t('dash.recent_auto') ?></span></div>
    <div class="tbl-wrap">
      <table class="tbl" id="recent-tbl">
        <thead><tr><th><?= t('common.status') ?></th><th><?= t('dash.col.scenario') ?></th><th><?= t('dash.col.project') ?></th><th><?= t('dash.col.tester') ?></th><th><?= t('dash.col.time') ?></th></tr></thead>
        <tbody><tr class="loading-row"><td><?= t('common.loading') ?></td></tr></tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<script>
window.I18N.dash = { no_exec: <?= json_encode(t('dash.no_exec')) ?> };
document.addEventListener('DOMContentLoaded', async () => {
  const tb = qs('#recent-tbl tbody');
  if (!tb) return;
  try {
    const d = await api('/dashboard/recent');
    tb.innerHTML = d.rows.length ? d.rows.map((r) => `
      <tr>
        <td data-label="${L('common.status')}"><span class="pill ${r.result_class}">${r.result_label}</span></td>
        <td data-label="${L('dash.col.scenario')}"><b>${esc(r.code)}</b> — ${esc(r.title)}</td>
        <td data-label="${L('dash.col.project')}">${esc(r.project)}</td>
        <td data-label="${L('dash.col.tester')}">${esc(r.tester)}</td>
        <td data-label="${L('dash.col.time')}" class="num">${faDateTime(r.at)}</td>
      </tr>`).join('')
      : `<tr><td colspan="5"><div class="tbl-empty"><span class="big">🕐</span>${L('dash.no_exec')}</div></td></tr>`;
  } catch (e) { tb.innerHTML = `<tr><td colspan="5">${esc(e.message)}</td></tr>`; }
});
</script>
<?= $this->endSection() ?>
