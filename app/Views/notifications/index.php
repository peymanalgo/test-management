<?php $title = t('notif.title'); $active = session('is_admin') ? '' : 'workbench'; ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= t('nav.group.user') ?></div>
    <h1><?= t('notif.title') ?></h1>
    <p><?= t('notif.desc') ?></p>
  </div>
</div>
<div class="card">
  <?php if (!$rows): ?>
    <div class="tbl-empty"><span class="big">🔔</span><?= t('notif.empty') ?></div>
  <?php else: foreach ($rows as $n): ?>
    <div class="notif-item <?= $n['read_at'] ? '' : 'unread' ?>" style="cursor:default">
      <b><?= esc($n['title']) ?></b>
      <p><?= esc($n['message']) ?></p>
      <time><?= fa_dt($n['created_at']) ?></time>
    </div>
  <?php endforeach; endif; ?>
</div>
<?= $this->endSection() ?>
