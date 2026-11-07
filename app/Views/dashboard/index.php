<?php $title = t('nav.dashboard'); $active = 'dashboard'; ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= t('nav.group.home') ?></div>
    <h1><?= t('dash.hello') ?>، <?= esc(session('display_name')) ?> 👋</h1>
  </div>
  <div class="btn-row">
    <a class="btn alt" href="/reports">📊 <?= t('dash.view_reports') ?></a>
    <a class="btn" href="/projects"><?= t('dash.new_project') ?></a>
  </div>
</div>

<!-- KPI -->
<div class="kpis">
  <div class="kpi"><div class="k-ic" style="background:var(--primary-soft);font-size:20px">▣</div><div><b><?= (int) ($kpi['projects'] ?? 0) ?></b><small>پروژهٔ فعال</small></div></div>
  <div class="kpi"><div class="k-ic" style="background:#e0f5fa;font-size:20px">📋</div><div><b><?= (int) ($kpi['total_cases'] ?? 0) ?></b><small>کل سناریوها</small></div></div>
  <div class="kpi"><div class="k-ic" style="background:#fdf0dd;font-size:20px">🧪</div><div><b><?= (int) ($kpi['pending_test'] ?? 0) ?></b><small><?= t('dash.pending_test') ?></small></div></div>
  <div class="kpi"><div class="k-ic" style="background:#e0f5fa;font-size:20px">⏳</div><div><b><?= (int) ($kpi['pending_approval'] ?? 0) ?></b><small><?= t('dash.pending_approval') ?></small></div></div>
  <div class="kpi"><div class="k-ic" style="background:#e3f7ea;font-size:20px">✅</div><div><b><?= (int) ($kpi['approved'] ?? 0) ?></b><small><?= t('report.approved') ?></small></div></div>
  <div class="kpi"><div class="k-ic" style="background:#fde5e5;font-size:20px">🐛</div><div><b style="color:var(--danger)"><?= (int) ($kpi['open_defects'] ?? 0) ?></b><small><?= t('dash.open_defects') ?></small></div></div>
</div>

<div class="two-col">
  <!-- آخرین فعالیت‌ها -->
  <div class="card">
    <div class="card-h"><h2><?= t('dash.recent_activity') ?></h2></div>
    <div class="card-b" style="padding:0">
      <?php if (empty($activity)): ?>
        <div class="tbl-empty" style="padding:30px"><span class="big">🕐</span><?= t('dash.no_activity') ?></div>
      <?php else: ?>
        <?php $statusMeta = \App\Services\WorkflowService::statusMeta(); ?>
        <?php foreach ($activity as $a): ?>
          <div style="display:flex;gap:10px;padding:11px 18px;border-bottom:1px solid var(--line);align-items:flex-start">
            <span style="width:8px;height:8px;border-radius:50%;background:var(--primary);margin-top:8px;flex:none"></span>
            <div style="flex:1;min-width:0">
              <div style="font-size:12px"><b><?= esc($a['actor']) ?></b>
                <?php if ($a['to_status']): ?>
                  → <span class="pill <?= \App\Services\WorkflowService::statusClasses()[$a['to_status']] ?? '' ?>" style="font-size:10px"><?= $statusMeta[$a['to_status']][0] ?? $a['to_status'] ?></span>
                <?php endif; ?>
              </div>
              <div style="font-size:11px;color:var(--muted);margin-top:2px">
                <b style="direction:ltr;display:inline-block"><?= esc($a['code']) ?></b> — <?= esc(mb_substr($a['title'], 0, 40)) ?> • <?= esc($a['project_name']) ?>
              </div>
              <?php if ($a['reason']): ?><div style="font-size:10.5px;color:var(--muted-2);margin-top:1px"><?= esc(mb_substr($a['reason'], 0, 60)) ?></div><?php endif; ?>
            </div>
            <time style="font-size:10px;color:var(--muted-2);white-space:nowrap"><?= fa_d($a['created_at']) ?></time>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- خطاهای باز + میانبر -->
  <div>
    <?php if (!empty($recentDefects)): ?>
    <div class="card mb">
      <div class="card-h"><h2>🐛 <?= t('dash.open_defects') ?></h2><a href="/defects" style="font-size:11.5px;color:var(--accent)">همه ←</a></div>
      <div class="card-b" style="padding:0">
        <?php foreach ($recentDefects as $d): ?>
          <div style="display:flex;gap:10px;padding:10px 18px;border-bottom:1px solid var(--line);align-items:center">
            <span class="pill pr-<?= strtolower($d['severity']) ?>"><?= \App\Services\WorkflowService::severityMeta()[$d['severity']][0] ?? $d['severity'] ?></span>
            <div style="flex:1;min-width:0">
              <b style="font-size:12px;direction:ltr;display:inline-block"><?= esc($d['code']) ?></b>
              <span style="font-size:11.5px"><?= esc(mb_substr($d['title'], 0, 30)) ?></span>
              <div style="font-size:10px;color:var(--muted-2)"><?= esc($d['project_name']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-h"><h2><?= t('dash.quick_actions') ?></h2></div>
      <div class="card-b" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <a class="btn" href="/projects" style="justify-content:flex-start">▣ <?= t('dash.new_project') ?></a>
        <a class="btn alt" href="/test-cases" style="justify-content:flex-start">☑ <?= t('dash.new_case') ?></a>
        <a class="btn alt" href="/reports" style="justify-content:flex-start">📊 <?= t('report.title') ?></a>
        <a class="btn alt" href="/defects" style="justify-content:flex-start">🐛 <?= t('defects.title') ?></a>
        <a class="btn alt" href="/admin/users" style="justify-content:flex-start">♙ <?= t('nav.users') ?></a>
      </div>
    </div>

    <!-- کارت‌های پروژه -->
    <?php if (!empty($projects)): ?>
    <div class="card mt">
      <div class="card-h"><h2>▣ پروژه‌ها</h2></div>
      <div class="card-b" style="padding:0">
        <?php foreach ($projects as $p): $total = max(1, (int) $p['total_cases']); ?>
          <div style="display:flex;gap:12px;padding:12px 18px;border-bottom:1px solid var(--line);align-items:center;cursor:pointer" onclick="location.href='/projects/<?= $p['id'] ?>/tree'">
            <span class="pill" style="background:var(--primary-soft);color:var(--primary)"><?= esc($p['code']) ?></span>
            <div style="flex:1">
              <b style="font-size:13px"><?= esc($p['name']) ?></b>
              <div class="bar-row" style="margin-top:4px"><span class="lbl" style="width:70px">تأییدشده</span>
                <div class="track" style="height:6px"><div class="fill" style="width:<?= round((int) $p['approved'] / $total * 100) ?>%;background:var(--st-approved)"></div></div>
                <span class="val"><?= (int) $p['approved'] ?>/<?= (int) $p['total_cases'] ?></span></div>
            </div>
            <?php if ($p['open_defects'] > 0): ?><span class="pill st-infix"><?= (int) $p['open_defects'] ?> خطا</span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?= $this->endSection() ?>
