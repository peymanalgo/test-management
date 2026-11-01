<?php $current = $current ?? ''; ?>
<aside class="side"><div class="brand"><i>✓</i> آزمون‌یار</div><nav class="nav">
<a class="<?= $current === 'dashboard' ? 'active' : '' ?>" href="/dashboard">▦ داشبورد</a>
<a class="<?= $current === 'tasks' ? 'active' : '' ?>" href="/my-tasks">◷ کارتابل من</a>
<a class="<?= $current === 'projects' ? 'active' : '' ?>" href="/projects">▣ پروژه‌ها</a>
<a class="<?= $current === 'cases' ? 'active' : '' ?>" href="/test-cases">☑ سناریوهای تست</a>
<a class="<?= $current === 'runs' ? 'active' : '' ?>" href="/test-runs">◉ دوره‌های تست</a>
<a class="<?= $current === 'defects' ? 'active' : '' ?>" href="/defects">⚑ خطاها</a>
<a href="#">▥ گزارش‌ها</a><a href="#">♢ اعلان‌ها</a>
<?php if (session('is_admin')): ?><a class="<?= $current === 'users' ? 'active' : '' ?>" href="/admin/users">♙ کاربران و نقش‌ها</a><a href="#">⚙ تنظیمات</a><?php endif ?>
</nav><div class="profile"><b><?= esc(session('display_name')) ?></b><small><?= session('is_admin') ? 'مدیر سیستم' : 'کاربر پروژه' ?> · آنلاین</small><form method="post" action="/logout"><?= csrf_field() ?><button>خروج</button></form></div></aside>
