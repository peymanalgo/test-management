<?php $title = t('notif.title'); $active = session('is_admin') ? '' : 'workbench'; ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= t('nav.group.user') ?></div>
    <h1>🔔 <?= t('notif.title') ?></h1>
    <p><?= t('notif.desc') ?></p>
  </div>
  <button class="btn alt" onclick="markAllRead()"><?= t('common.read_all') ?></button>
</div>

<?php if (!$rows): ?>
  <div class="card"><div class="notif-empty" style="padding:60px">
    <span style="font-size:48px;opacity:.3">🔕</span>
    <h3><?= t('notif.empty') ?></h3>
  </div></div>
<?php else: ?>
<div class="card">
  <?php foreach ($rows as $n): ?>
  <div class="notif-item <?= $n['read_at'] ? '' : 'unread' ?>" data-id="<?= $n['id'] ?>" style="cursor:pointer">
    <span class="notif-ic" style="background:<?= [
      'APPROVAL' => 'linear-gradient(135deg,#0891b2,#0e7490)',
      'DEFECT' => 'linear-gradient(135deg,#dc2626,#991b1b)',
      'RETEST' => 'linear-gradient(135deg,#7c3aed,#6d28d9)',
      'APPROVED' => 'linear-gradient(135deg,#16a34a,#15803d)',
      'REJECTED' => 'linear-gradient(135deg,#dc2626,#b91c1c)',
      'BLOCKED' => 'linear-gradient(135deg,#64748b,#475569)',
      'CANCELLED' => 'linear-gradient(135deg,#94a3b8,#64748b)',
    ][$n['type']] ?? 'linear-gradient(135deg,#6957e8,#5a49d5)' ?>"><?= [
      'APPROVAL' => '⏳', 'DEFECT' => '🐛', 'RETEST' => '🔄', 'APPROVED' => '✅',
      'REJECTED' => '❌', 'BLOCKED' => '⛔', 'CANCELLED' => '🚫',
    ][$n['type']] ?? '🔔' ?></span>
    <div class="notif-body">
      <b><?= esc($n['title']) ?></b>
      <p style="-webkit-line-clamp:unset"><?= esc($n['message']) ?></p>
      <time><?= fa_dt($n['created_at']) ?></time>
    </div>
    <?= $n['read_at'] ? '' : '<span class="notif-dot"></span>' ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
async function markAllRead() {
  try { await api('/notifications/read-all', { method: 'POST' }); location.reload(); }
  catch (e) { toast(e.message, 'err'); }
}
qsa('.notif-item').forEach((el) => el.addEventListener('click', async () => {
  await api(`/notifications/read/${el.dataset.id}`, { method: 'POST' });
  location.href = '/workbench';
}));
</script>
<?= $this->endSection() ?>
