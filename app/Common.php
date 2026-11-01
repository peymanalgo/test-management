<?php

/**
 * The goal of this file is to allow developers
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (!function_exists('app_lang')) {
    /** زبان فعلی رابط (fa|en) — از سشن/کوکی */
    function app_lang(): string
    {
        $lang = session()->get('lang') ?? ($_COOKIE['lang'] ?? null);
        return in_array($lang, ['fa', 'en'], true) ? $lang : 'fa';
    }
}

if (!function_exists('t')) {
    /** ترجمهٔ رشتهٔ رابط از فایل‌های زبان (با placeholder های نامی مثل {name}) */
    function t(string $key, array $args = []): string
    {
        $line = lang('App.' . $key);
        foreach ($args as $k => $v) $line = str_replace('{' . $k . '}', (string) $v, $line);
        return $line;
    }
}

/*
 * موتور تاریخ دوزبانه:
 *  fa → تقویم جلالی/شمسی (fa_IR@calendar=persian)
 *  en → تقویم میلادی گریگوری (en_US)
 * هر دو روی همان لحظهٔ UTC ذخیره‌شده و با منطقهٔ زمانی تهران نمایش داده می‌شوند.
 */
if (!function_exists('fmt_dt')) {
    function fmt_dt(?string $utc, bool $withTime = true): string
    {
        if (!$utc) return '—';
        try {
            $locale = app_lang() === 'en' ? 'en_US@calendar=gregorian' : 'fa_IR@calendar=persian';
            $fmt = new IntlDateFormatter($locale, IntlDateFormatter::MEDIUM, $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE, 'Asia/Tehran');
            return $fmt->format(new DateTime($utc . ' UTC'));
        } catch (Throwable $e) {
            return $utc;
        }
    }
}

if (!function_exists('fa_dt')) {
    function fa_dt(?string $utc): string { return fmt_dt($utc, true); }
}

if (!function_exists('fa_d')) {
    function fa_d(?string $utc): string { return fmt_dt($utc, false); }
}

if (!function_exists('status_pill')) {
    /** پیل رنگی وضعیت گردش کار (برچسب بر اساس زبان) */
    function status_pill(?string $status): string
    {
        if (!$status) return '<span class="text-muted">—</span>';
        $meta = \App\Services\WorkflowService::statusClasses();
        [$label, $class] = [t('status.' . $status), $meta[$status] ?? 'st-blocked'];
        return '<span class="pill ' . $class . '">' . esc($label) . '</span>';
    }
}

if (!function_exists('prio_pill')) {
    function prio_pill(?string $p, string $kind = 'priority'): string
    {
        if (!$p) return '<span class="text-muted">—</span>';
        $class = ['LOW' => 'pr-low', 'MEDIUM' => 'pr-medium', 'HIGH' => 'pr-high', 'CRITICAL' => 'pr-critical'][$p] ?? 'pr-medium';
        return '<span class="pill ' . $class . '">' . esc(t('pr.' . $p)) . '</span>';
    }
}

if (!function_exists('defect_status_pill')) {
    function defect_status_pill(?string $s): string
    {
        if (!$s) return '<span class="text-muted">—</span>';
        $class = ['OPEN' => 'st-infix', 'IN_PROGRESS' => 'st-intest', 'FIXED_PENDING_RETEST' => 'st-retest', 'VERIFIED' => 'st-approved', 'REOPENED' => 'st-rejected', 'CLOSED' => 'st-cancelled'][$s] ?? 'st-blocked';
        return '<span class="pill ' . $class . '">' . esc(t('dstatus.' . $s)) . '</span>';
    }
}

if (!function_exists('result_pill')) {
    function result_pill(?string $r): string
    {
        if (!$r) return '<span class="text-muted">' . esc(t('result.none')) . '</span>';
        return '<span class="pill res-' . $r . '">' . esc(t('result.' . $r)) . '</span>';
    }
}
