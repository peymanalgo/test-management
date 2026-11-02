<?php
/**
 * لایهٔ مشترک صفحات — $title، $active (کلید منو) و $crumb را از ویو می‌گیرد.
 * زبان از سشن (fa|en) خوانده می‌شود؛ جهت صفحه و تمام برچسب‌ها بر اساس آن است.
 */
$lang = app_lang();
$isAdmin = session('is_admin');
$active = $active ?? '';
$crumb = $crumb ?? ($title ?? '');
$ic = fn(string $paths) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
$icons = [
    'dashboard' => $ic('<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>'),
    'projects'  => $ic('<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>'),
    'cases'     => $ic('<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><polyline points="9 14 11.5 16.5 15.5 11.5"/>'),
    'users'     => $ic('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'),
    'workbench' => $ic('<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>'),
    'globe'     => $ic('<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>'),
];
$adminMenu = [
    ['dashboard', 'nav.group.home', 'nav.dashboard', $icons['dashboard'], '/dashboard'],
    ['projects', 'nav.group.test', 'nav.projects', $icons['projects'], '/projects'],
    ['cases', 'nav.group.test', 'nav.cases', $icons['cases'], '/test-cases'],
    ['users', 'nav.group.system', 'nav.users', $icons['users'], '/admin/users'],
];
$initials = mb_substr(session('display_name') ?? '؟', 0, 1);

/** دیکشنری مشترک برای اسکریپت‌های سمت کلاینت */
$i18nJs = [
    'lang' => $lang,
    'common' => ['close' => t('common.close'), 'cancel' => t('common.cancel'), 'save' => t('common.save'),
        'confirm' => t('common.confirm'), 'done' => t('common.done'), 'loading' => t('common.loading'),
        'empty' => t('common.empty'), 'created' => t('common.created'), 'actions' => t('common.actions'),
        'code' => t('common.code'), 'title' => t('common.title'), 'priority' => t('common.priority'),
        'status' => t('common.status'), 'type' => t('common.type'), 'tester' => t('common.tester'),
        'view' => t('common.view'), 'edit' => t('common.edit'), 'details' => t('common.details'),
        'page' => t('common.page'), 'of' => t('common.of'), 'records' => t('common.records'),
        'prev' => t('common.prev'), 'next' => t('common.next'), 'notifications' => t('common.notifications'),
        'read_all' => t('common.read_all'), 'no_notifications' => t('common.no_notifications'),
        'network_error' => t('common.network_error'), 'session_expired' => t('common.session_expired'),
        'confirm_min' => t('common.confirm_min'), 'updated' => t('common.updated')],
    'status' => ['READY' => t('status.READY'), 'IN_TEST' => t('status.IN_TEST'), 'IN_FIX' => t('status.IN_FIX'),
        'READY_RETEST' => t('status.READY_RETEST'), 'BLOCKED' => t('status.BLOCKED'), 'PENDING_APPROVAL' => t('status.PENDING_APPROVAL'),
        'APPROVED' => t('status.APPROVED'), 'REJECTED' => t('status.REJECTED'), 'CANCELLED' => t('status.CANCELLED')],
    'pr' => ['LOW' => t('pr.LOW'), 'MEDIUM' => t('pr.MEDIUM'), 'HIGH' => t('pr.HIGH'), 'CRITICAL' => t('pr.CRITICAL')],
    'result' => ['PASS' => t('result.PASS'), 'FAIL' => t('result.FAIL'), 'BLOCKED' => t('result.BLOCKED'), 'none' => t('result.none')],
    'dstatus' => ['OPEN' => t('dstatus.OPEN'), 'IN_PROGRESS' => t('dstatus.IN_PROGRESS'), 'FIXED_PENDING_RETEST' => t('dstatus.FIXED_PENDING_RETEST'),
        'VERIFIED' => t('dstatus.VERIFIED'), 'REOPENED' => t('dstatus.REOPENED'), 'CLOSED' => t('dstatus.CLOSED')],
    'hist' => ['title' => t('hist.title'), 'sub' => t('hist.sub')],
];
?>
<!doctype html>
<html lang="<?= $lang === 'fa' ? 'fa' : 'en' ?>" dir="<?= $lang === 'fa' ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= csrf_hash() ?>">
<title><?= esc($title ?? t('app_name')) ?> | <?= t('app_name') ?></title>
<?php
// CSS و JS به‌صورت inline — سازگار با هاست‌هایی که css/js/font را 403 می‌کنند
$_css = @file_get_contents(FCPATH . 'assets/css/theme.css') ?: '';
$_js  = @file_get_contents(FCPATH . 'assets/js/app.js') ?: '';
// فونت وزیرمتن base64
$_fontR = @file_get_contents(FCPATH . 'assets/fonts/Vazirmatn-Regular.woff2');
$_fontB = @file_get_contents(FCPATH . 'assets/fonts/Vazirmatn-Bold.woff2');
if ($_fontR !== false) {
    $_fontCSS = "@font-face{font-family:'Vazirmatn';src:url(data:font/woff2;base64," . base64_encode($_fontR) . ") format('woff2');font-weight:400;font-display:swap}";
    if ($_fontB !== false) $_fontCSS .= "@font-face{font-family:'Vazirmatn';src:url(data:font/woff2;base64," . base64_encode($_fontB) . ") format('woff2');font-weight:700;font-display:swap}";
    $_css = $_fontCSS . "\n" . $_css;
}
?>
<?php if ($_css !== ''): ?><style><?= $_css ?></style><?php else: ?><link rel="stylesheet" href="/assets/css/theme.css?v=8"><?php endif; ?>
<?php if ($_js !== ''): ?><script><?= $_js ?></script><?php else: ?><script src="/assets/js/app.js?v=8" defer></script><?php endif; ?>
<script>window.I18N = <?= json_encode($i18nJs, JSON_UNESCAPED_UNICODE) ?>;</script>
</head>
<body>
<div class="shell">
  <aside class="side">
    <div class="brand">
      <div class="logo">✓</div>
      <div><b><?= t('app_name') ?></b><small>TEST MANAGEMENT</small></div>
    </div>
    <?php if ($isAdmin): $group = ''; foreach ($adminMenu as [$key, $grp, $labelKey, $icon, $url]): if ($grp !== $group): $group = $grp; ?>
      <div class="nav-group"><span><?= t($grp) ?></span>
    <?php endif; ?>
      <a class="nav-item <?= $active === $key ? 'active' : '' ?>" href="<?= $url ?>">
        <span class="ic"><?= $icon ?></span><?= t($labelKey) ?>
      </a>
    <?php endforeach; ?></div>
    <?php else: ?>
    <div class="nav-group">
      <span><?= t('nav.group.user') ?></span>
      <a class="nav-item <?= $active === 'workbench' ? 'active' : '' ?>" href="/workbench">
        <span class="ic"><?= $icons['workbench'] ?></span><?= t('nav.workbench') ?>
      </a>
    </div>
    <?php endif; ?>
    <div class="profile">
      <div class="avatar"><?= esc($initials) ?></div>
      <div style="min-width:0">
        <b><?= esc(session('display_name')) ?></b>
        <small><?= $isAdmin ? t('role.admin') : (session('role_code') ? t('role.' . strtolower(session('role_code'))) : t('role.user')) ?></small>
      </div>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="menu-btn" data-menu-btn title="☰">☰</button>
      <div class="crumb"><?= t('common.crumb_home') ?> ‹ <b><?= esc($crumb) ?></b></div>
      <div class="spacer"></div>
      <a class="lang-toggle" href="/lang/<?= $lang === 'fa' ? 'en' : 'fa' ?>" title="<?= t('common.language') ?>">
        <?= $icons['globe'] ?><b><?= $lang === 'fa' ? 'EN' : 'فا' ?></b>
      </a>
      <div style="position:relative">
        <button class="bell" data-bell title="<?= t('common.notifications') ?>">🔔</button>
        <div class="bell-pop"></div>
      </div>
      <form method="post" action="/logout" class="topbar-exit">
        <?= csrf_field() ?>
        <button type="submit" title="<?= t('common.logout') ?>" aria-label="<?= t('common.logout') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
      </form>
    </header>
    <main class="content">
      <?php if (session('message')): ?><div class="flash ok" data-flash>✓ <?= esc(session('message')) ?></div><?php endif; ?>
      <?php if (session('error')): ?><div class="flash err" data-flash>⚠ <?= esc(session('error')) ?></div><?php endif; ?>
      <?= $this->renderSection('content') ?>
    </main>
  </div>
</div>
</body>
</html>
