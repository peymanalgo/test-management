<?php $statusMeta = \App\Services\WorkflowService::statusMeta(); ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>گزارش تست: <?= esc($project['name']) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font:13px/1.9 Tahoma,Arial,sans-serif;color:#222;padding:30px 40px}
h1{font-size:20px;color:#2a3f7e;border-bottom:3px solid #2a3f7e;padding-bottom:8px;margin-bottom:6px}
.meta{font-size:11px;color:#666;margin-bottom:24px}
h2{font-size:14px;margin:20px 0 8px;color:#2a3f7e}
table{width:100%;border-collapse:collapse;margin-bottom:16px}
th,td{border:1px solid #ddd;padding:6px 10px;text-align:right;font-size:11.5px}
th{background:#f0f2f8;font-weight:bold}
.summary{display:flex;gap:14px;margin-bottom:20px}
.stat{flex:1;border:1.5px solid #ddd;border-radius:10px;padding:12px;text-align:center}
.stat b{font-size:22px;display:block}
.stat small{font-size:10.5px;color:#666}
.badge{display:inline-block;border-radius:99px;padding:2px 10px;font-size:10px;font-weight:bold}
.st-approved{background:#e3f7ea;color:#16a34a}.st-infix{background:#fde5e5;color:#dc2626}
.st-ready{background:#e7eeff;color:#2563eb}.st-approval{background:#e0f5fa;color:#0891b2}
.st-cancelled{background:#f1f4f8;color:#94a3b8}.st-retest{background:#f1e8ff;color:#7c3aed}
.st-blocked{background:#edf1f6;color:#64748b}.st-rejected{background:#f9e6e6;color:#991b1b}
.st-intest{background:#fdf0dd;color:#d97706}
.pr-critical{background:#fde5e5;color:#dc2626}.pr-high{background:#fdf0dd;color:#d97706}
.pr-medium{background:#e0f5fa;color:#0891b2}.pr-low{background:#f1f4f8;color:#94a3b8}
.sign{margin-top:50px;display:flex;justify-content:space-around}
.sign div{text-align:center;border-top:1.5px dotted #999;padding-top:8px;width:180px;font-size:11px}
@media print{body{padding:10px 15px}.no-print{display:none}}
</style>
</head>
<body>

<h1>✓ گزارش تست: <?= esc($project['name']) ?></h1>
<p class="meta">کد: <?= esc($project['code']) ?> | تاریخ: <?= fa_d(gmdate('Y-m-d H:i:s')) ?> | ساعت: <?= gmdate('H:i') ?> UTC</p>

<div class="summary">
  <div class="stat"><b><?= (int) ($summary['total_cases'] ?? 0) ?></b><small>کل سناریوها</small></div>
  <div class="stat"><b style="color:#16a34a"><?= (int) ($summary['approved'] ?? 0) ?></b><small>تأیید شده</small></div>
  <div class="stat"><b style="color:#d97706"><?= (int) ($summary['active'] ?? 0) ?></b><small>در جریان</small></div>
  <div class="stat"><b style="color:#dc2626"><?= (int) ($summary['in_fix'] ?? 0) ?></b><small>در اصلاح</small></div>
  <div class="stat"><b style="color:#dc2626"><?= (int) ($summary['open_defects'] ?? 0) ?></b><small>خطاهای باز</small></div>
</div>

<?php if (!empty($cases)): ?>
<h2>فهرست سناریوها</h2>
<table>
  <thead><tr><th>#</th><th>کد</th><th>عنوان</th><th>اولویت</th><th>وضعیت</th><th>نتیجه</th></tr></thead>
  <tbody>
  <?php $i = 1; foreach ($cases as $c): ?>
    <tr>
      <td><?= $i++ ?></td>
      <td style="direction:ltr"><?= esc($c['code']) ?></td>
      <td><?= esc($c['title']) ?></td>
      <td><?= \App\Services\WorkflowService::priorityMeta()[$c['priority']][0] ?? $c['priority'] ?></td>
      <td><?= isset($statusMeta[$c['workflow_status']]) ? '<span class="badge ' . \App\Services\WorkflowService::statusClasses()[$c['workflow_status']] . '">' . $statusMeta[$c['workflow_status']][0] . '</span>' : '—' ?></td>
      <td><?= $c['last_result'] === 'PASS' ? '✅ موفق' : ($c['last_result'] === 'FAIL' ? '❌ ناموفق' : ($c['last_result'] === 'BLOCKED' ? '⛔ مسدود' : '—')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if (!empty($defects)): ?>
<h2>خطاهای باز</h2>
<table>
  <thead><tr><th>کد</th><th>عنوان</th><th>شدت</th><th>وضعیت</th></tr></thead>
  <tbody>
  <?php foreach ($defects as $d): ?>
    <tr>
      <td style="direction:ltr"><?= esc($d['code']) ?></td>
      <td><?= esc($d['title']) ?></td>
      <td><?= \App\Services\WorkflowService::severityMeta()[$d['severity']][0] ?? $d['severity'] ?></td>
      <td><?= \App\Services\WorkflowService::defectStatusMeta()[$d['status']][0] ?? $d['status'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="sign">
  <div>مدیر پروژه</div>
  <div>تأییدکننده</div>
  <div>تاریخ</div>
</div>

<div class="no-print" style="margin-top:20px;text-align:center">
  <button onclick="window.print()" style="padding:10px 30px;border:0;border-radius:8px;background:#2a3f7e;color:#fff;font:700 14px Tahoma;cursor:pointer">🖨 چاپ</button>
</div>

</body>
</html>
