<?php $lang = app_lang(); ?>
<!doctype html>
<html lang="<?= $lang === 'fa' ? 'fa' : 'en' ?>" dir="<?= $lang === 'fa' ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= csrf_hash() ?>">
<title><?= t('login.heading') ?> | <?= t('app_name') ?></title>
<link rel="stylesheet" href="/assets/css/theme.css?v=7">
</head>
<body class="auth-body">

<span class="orb o1"></span><span class="orb o2"></span><span class="orb o3"></span>

<div class="lang-float" dir="ltr">
  <a href="/lang/fa" class="<?= $lang === 'fa' ? 'on' : '' ?>">فارسی</a>
  <a href="/lang/en" class="<?= $lang === 'en' ? 'on' : '' ?>">English</a>
</div>

<div class="auth-wrap">
  <div class="auth-emblem" aria-hidden="true">
    <svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><polyline points="9 14 11.5 16.5 15.5 11.5"/></svg>
  </div>

  <h1 class="auth-title"><b><?= t('login.title') ?></b></h1>
  <p class="auth-sub"><?= t('login.subtitle') ?></p>

  <div class="auth-card">
    <h2><?= t('login.heading') ?></h2>
    <p class="sub"><?= t('login.sub') ?></p>

    <?php if (session('error')): ?><div class="flash">⚠ <?= esc(session('error')) ?></div><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?= t('login.username') ?></label>
        <div class="auth-inp">
          <input class="inp" name="username" autocomplete="username" required autofocus value="<?= esc(old('username')) ?>" placeholder="<?= t('login.example') ?>" dir="ltr">
          <span class="iic"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
        </div>
      </div>
      <div class="field">
        <label><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> <?= t('login.password') ?></label>
        <div class="auth-inp">
          <input class="inp" type="password" name="password" autocomplete="current-password" required placeholder="••••••••">
          <span class="iic"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
        </div>
      </div>
      <button class="auth-btn" type="submit"><?= t('login.button') ?></button>
    </form>
  </div>

  <div class="auth-feats">
    <div class="auth-feat" title="<?= t('login.f1.title') ?>">
      <div class="fi"><svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
      <b><?= t('login.f1.title') ?></b><small><?= t('login.f1.sub') ?></small>
    </div>
    <div class="auth-feat" title="<?= t('login.f2.title') ?>">
      <div class="fi"><svg viewBox="0 0 24 24"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg></div>
      <b><?= t('login.f2.title') ?></b><small><?= t('login.f2.sub') ?></small>
    </div>
    <div class="auth-feat" title="<?= t('login.f3.title') ?>">
      <div class="fi"><svg viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/><circle cx="12" cy="12" r="10"/></svg></div>
      <b><?= t('login.f3.title') ?></b><small><?= t('login.f3.sub') ?></small>
    </div>
  </div>

  <p class="auth-foot"><b><?= t('app_name') ?></b> — <?= t('login.footer') ?></p>
</div>

</body>
</html>
