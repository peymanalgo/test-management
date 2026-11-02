/* آزمون‌یار — فریم‌ورک رابط کاربری (Vanilla JS، بدون وابستگی) */
'use strict';

const qs  = (s, r = document) => r.querySelector(s);
const qsa = (s, r = document) => Array.from(r.querySelectorAll(s));
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const CSRF = qs('meta[name="csrf"]')?.content || '';

/* ---------- تاریخ دوزبانه: fa = تقویم جلالی، en = میلادی ---------- */
const CUR_LANG = () => window.I18N?.lang || document.documentElement.lang || 'fa';
const faDateFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { dateStyle: 'medium' });
const faTimeFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { dateStyle: 'medium', timeStyle: 'short' });
const enDateFmt = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' });
const enTimeFmt = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
const toUtcDate = (v) => new Date(String(v).replace(' ', 'T') + 'Z');
function faDate(v) { if (!v) return '—'; try { return (CUR_LANG() === 'en' ? enDateFmt : faDateFmt).format(toUtcDate(v)); } catch { return v; } }
function faDateTime(v) { if (!v) return '—'; try { return (CUR_LANG() === 'en' ? enTimeFmt : faTimeFmt).format(toUtcDate(v)); } catch { return v; } }
/* برچسب وضعیت/اولویت/نتیجه از دیکشنری تزریق‌شدهٔ سرور */
const L = (path) => path.split('.').reduce((o, k) => (o || {})[k], window.I18N) ?? path;
const stLabel = (s) => s ? L('status.' + s) : '—';
const stClass = (s) => ({ READY: 'st-ready', IN_TEST: 'st-intest', IN_FIX: 'st-infix', READY_RETEST: 'st-retest', BLOCKED: 'st-blocked', PENDING_APPROVAL: 'st-approval', APPROVED: 'st-approved', REJECTED: 'st-rejected', CANCELLED: 'st-cancelled' }[s] || 'st-blocked');
const stPill = (s) => s ? `<span class="pill ${stClass(s)}">${stLabel(s)}</span>` : '<span class="text-muted">—</span>';
const prioLabel = (p) => L('pr.' + p);
const prioPill = (p) => p ? `<span class="pill pr-${p.toLowerCase()}">${prioLabel(p)}</span>` : '—';
const resPill = (r) => r ? `<span class="pill res-${r}">${L('result.' + r)}</span>` : `<span class="text-muted">${L('result.none')}</span>`;

/* ---------- API ---------- */
async function api(path, { method = 'GET', body = null } = {}) {
  const opt = { method, headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } };
  if (body instanceof FormData) opt.body = body;
  else if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
  let res;
  try { res = await fetch(path, opt); } catch { throw new Error(window.I18N?.common?.network_error || 'Network error.'); }
  let data = {};
  try { data = await res.json(); } catch { /* empty */ }
  if (res.status === 401) { location.href = '/login'; throw new Error(data.message || window.I18N?.common?.session_expired || 'Session expired.'); }
  if (!res.ok || data.ok === false) throw new Error(data.message || 'خطا در عملیات.');
  return data;
}

/* ---------- توست ---------- */
function toast(msg, type = 'ok', ms = 3600) {
  let host = qs('#toasts');
  if (!host) { host = document.createElement('div'); host.id = 'toasts'; document.body.appendChild(host); }
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<span>${type === 'ok' ? '✓' : type === '✕' ? '' : '!'}</span><div>${esc(msg)}</div>`;
  host.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .4s'; setTimeout(() => t.remove(), 420); }, ms);
}

/* ---------- مودال ---------- */
function openModal({ title = '', sub = '', body = '', footer = '', wide = false, narrow = false, onClose = null }) {
  const ov = document.createElement('div');
  ov.className = 'overlay';
  ov.innerHTML = `
    <div class="modal ${wide ? 'wide' : ''} ${narrow ? 'narrow' : ''}" role="dialog">
      <div class="modal-h"><div style="flex:1;min-width:0"><h3>${title}</h3>${sub ? `<p>${sub}</p>` : ''}</div>
        <button class="x" title="بستن">✕</button></div>
      <div class="modal-b">${body}</div>
      ${footer ? `<div class="modal-f">${footer}</div>` : ''}
    </div>`;
  const close = () => { ov.remove(); document.body.style.overflow = ''; onClose && onClose(); };
  ov.addEventListener('mousedown', (e) => { if (e.target === ov) close(); });
  qs('.x', ov).addEventListener('click', close);
  document.body.appendChild(ov);
  document.body.style.overflow = 'hidden';
  return { root: ov, close, body: qs('.modal-b', ov), footer: qs('.modal-f', ov) };
}

/* مودال تأیید با دلیل اختیاری/الزامی — Promise<string|null> */
function reasonModal({ title, label = 'دلیل', required = false, okText = 'تأیید', danger = false, note = '' }) {
  return new Promise((resolve) => {
    const m = openModal({
      title, narrow: true,
      body: `${note ? `<p>${note}</p>` : ''}
        <div class="field"><label>${label} ${required ? '<span class="req">*</span>' : '<small class="text-muted">(اختیاری)</small>'}</label>
        <textarea class="inp" data-reason rows="3" placeholder="توضیح کوتاه..."></textarea></div>`,
      footer: `<button class="btn ${danger ? 'danger' : ''}" data-ok>${okText}</button>
               <button class="btn alt" data-cancel>انصراف</button>`,
      onClose: () => resolve(null),
    });
    qs('[data-ok]', m.root).addEventListener('click', () => {
      const v = qs('[data-reason]', m.root).value.trim();
      if (required && v.length < 3) { toast(L('common.confirm_min'), 'warn'); return; }
      resolve(v);
      qs('.x', m.root).click();
    });
    qs('[data-cancel]', m.root).addEventListener('click', () => qs('.x', m.root).click());
  });
}

/* ---------- گرید داده (سرورساید: جست‌وجو/فیلتر/صفحه‌بندی) ---------- */
function createGrid({ container, endpoint, columns, filterState = () => ({}), onRowClick = null, emptyText = null }) {
  const state = { page: 1, total: 0, perPage: 20 };
  const render = (rows) => {
    const head = columns.map((c) => `<th>${c.label}</th>`).join('');
    const body = rows.length
      ? rows.map((r, idx) => `<tr class="${onRowClick ? 'clickable' : ''}" data-i="${idx}">${columns.map((c) => `<td data-label="${esc(c.label)}">${c.render ? c.render(r) : esc(r[c.key] ?? '—')}</td>`).join('')}</tr>`).join('')
      : `<tr><td colspan="${columns.length}"><div class="tbl-empty"><span class="big">🗂️</span>${emptyText || L('common.empty')}</div></td></tr>`;
        const pages = Math.max(1, Math.ceil(state.total / state.perPage));
        container.innerHTML = `
      <div class="card"><div class="tbl-wrap">
        <table class="tbl"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>
      </div>
      <div class="pager">
        <button data-pg="prev" ${state.page <= 1 ? 'disabled' : ''}>${L('common.prev')}</button>
        <span>${L('common.page')} ${state.page} ${L('common.of')} ${pages} — ${state.total} ${L('common.records')}</span>
        <button data-pg="next" ${state.page >= pages ? 'disabled' : ''}>${L('common.next')}</button>
      </div></div>`;
    if (onRowClick) qsa('tbody tr[data-i]', container).forEach((tr) =>
      tr.addEventListener('click', (e) => {
        if (e.target.closest('button, a, input, select')) return; // کلیک روی دکمه‌های داخل ردیف نباید ردیف را فعال کند
        onRowClick(rows[+tr.dataset.i]);
      }));
    qs('[data-pg="prev"]', container)?.addEventListener('click', () => { if (state.page > 1) { state.page--; load(); } });
    qs('[data-pg="next"]', container)?.addEventListener('click', () => {
      if (state.page < Math.ceil(state.total / state.perPage)) { state.page++; load(); }
    });
  };
  const load = async () => {
    container.innerHTML = `<div class="card"><div class="tbl-wrap"><table class="tbl"><tbody><tr class="loading-row"><td>${L('common.loading')}</td></tr></tbody></table></div></div>`;
    try {
      const params = new URLSearchParams({ ...filterState(), page: state.page });
      const data = await api(`${endpoint}?${params}`);
      state.total = data.total; state.perPage = data.perPage || 20;
      render(data.rows || []);
    } catch (e) {
      container.innerHTML = `<div class="flash err">⚠ ${esc(e.message)}</div>`;
    }
  };
  return { load, reload: () => { state.page = 1; load(); }, get page() { return state.page; } };
}

/* ---------- درخت تاریخچهٔ سناریو ---------- */
async function historyModal(caseId, codeHint) {
  let d;
  try { d = await api(`/history/${caseId}`); } catch (e) { toast(e.message, 'err'); return; }
  const node = (n, isRoot = false) => `
    <li><div class="hnode ${isRoot ? 'is-root' : ''}">
      <div class="h-ic">${n.icon}</div>
      <div class="h-body">
        <b>${esc(n.label)}</b>
        ${n.reason ? `<div class="h-reason">${esc(n.reason)}</div>` : ''}
        <div class="h-meta"><span>👤 ${esc(n.actor || '—')}</span><span>🕐 ${faDateTime(n.at)}</span></div>
      </div></div>
    ${(n.children && n.children.length) ? `<ul class="htree">${n.children.map((c) => node(c)).join('')}</ul>` : ''}</li>`;
  openModal({
    title: L('hist.title').replace('{code}', esc(d.code)),
    sub: L('hist.sub').replace('{title}', esc(d.title)),
    wide: true,
    body: `<ul class="htree">${node(d.tree, true)}</ul>`,
    footer: `<button class="btn alt" data-close>${L('common.close')}</button>`,
  }).root.querySelector('[data-close]').addEventListener('click', () => qs('.overlay .x').click());
}

/* ---------- دراپ‌داون انتخاب درختی (پروژه و بخش — انتخاب پروژه = همهٔ سناریوهای آن) ---------- */
function treeSelect(mount, { projects, placeholder = '...', onSelect, searchPlaceholder, allLabel }) {
  let selected = null; // {projectId, nodeId|null, projectName, nodeName|null}
  const SEARCH_PH = searchPlaceholder || placeholder;
  const ALL_LBL = allLabel || '';
  const box = document.createElement('div');
  box.className = 'tsel';
  box.innerHTML = `<div class="tsel-box" tabindex="0"><span class="ph">${placeholder}</span></div>
    <div class="tsel-pop"><input class="ts-search" placeholder="${SEARCH_PH}">
    <div class="ts-list"></div></div>`;
  mount.appendChild(box);
  const pop = qs('.tsel-pop', box), listBox = qs('.ts-list', box), boxBtn = qs('.tsel-box', box);
  const matches = (name, f) => !f || name.includes(f);
  const subtreeHas = (x, f) => matches(x.name, f) || (x.children || []).some((c) => subtreeHas(c, f));
  const renderLabel = () => {
    if (!selected) { boxBtn.innerHTML = `<span class="ph">${placeholder}</span>`; return; }
    const label = selected.nodeName
      ? `${selected.projectName} <i style="opacity:.55">/</i> ${selected.nodeName}`
      : `${selected.projectName} <small>(همهٔ بخش‌های من)</small>`;
    boxBtn.innerHTML = `<span class="tsel-tag">${label} <button data-clear>✕</button></span>`;
    qs('[data-clear]', box).addEventListener('click', (e) => {
      e.stopPropagation(); selected = null; renderLabel(); onSelect(null);
    });
  };
  const isSel = (pid, nid) => selected && selected.projectId === pid && (selected.nodeId ?? null) === nid;
  const renderTree = (filter = '') => {
    const f = filter.trim();
    const nodeHtml = (n, projectId, projectName, depth) => {
      const hasKids = n.children && n.children.length;
      const show = matches(n.name, f) || (hasKids && n.children.some((c) => subtreeHas(c, f)));
      if (!show) return '';
      return `<div class="ts-node ${isSel(projectId, n.id) ? 'sel' : ''}" data-pid="${projectId}" data-pname="${esc(projectName)}" data-nid="${n.id}" data-nname="${esc(n.name)}">
          <button class="ts-tw" data-tw style="${hasKids ? '' : 'visibility:hidden'}">▾</button>
          <span style="${depth ? 'opacity:.85' : 'font-weight:700'}">${esc(n.name)}</span>
        </div>
        ${hasKids ? `<div class="ts-kids">${n.children.map((c) => nodeHtml(c, projectId, projectName, depth + 1)).join('')}</div>` : ''}`;
    };
    listBox.innerHTML = projects.map((p) => {
      const kids = p.nodes.map((n) => nodeHtml(n, p.id, p.name, 0)).join('');
      if (!kids && f && !matches(p.name, f)) return '';
      return `<div class="ts-node ts-project ${isSel(p.id, null) ? 'sel' : ''}" data-pid="${p.id}" data-pname="${esc(p.name)}" data-nid="" data-nname="">
          <button class="ts-tw" style="visibility:hidden"></button>
          <b>▣ ${esc(p.name)}</b><small class="text-muted" style="margin-right:auto;font-size:10px">${ALL_LBL}</small>
        </div>${kids}`;
    }).join('') || `<div class="center text-muted" style="padding:14px">${window.I18N?.gc?.no_result || '—'}</div>`;
    qsa('.ts-tw', listBox).forEach((b) => b.addEventListener('click', (e) => {
      e.stopPropagation();
      const kids = b.parentElement.nextElementSibling;
      if (kids) { kids.classList.toggle('open'); b.textContent = kids.classList.contains('open') ? '▾' : '▸'; }
    }));
    qsa('.ts-node', listBox).forEach((el) => el.addEventListener('click', () => {
      selected = {
        projectId: +el.dataset.pid,
        nodeId: el.dataset.nid ? +el.dataset.nid : null,
        projectName: el.dataset.pname,
        nodeName: el.dataset.nid ? el.dataset.nname : null,
      };
      renderLabel();
      pop.classList.remove('open');
      onSelect(selected);
    }));
  };
  boxBtn.addEventListener('click', () => {
    renderTree(qs('.ts-search', box).value);
    pop.classList.toggle('open');
    setTimeout(() => qs('.ts-search', box).focus(), 40);
  });
  document.addEventListener('click', (e) => { if (!box.contains(e.target)) pop.classList.remove('open'); });
  qs('.ts-search', box).addEventListener('input', (e) => renderTree(e.target.value));
  return {
    get selected() { return selected; },
    set(v) { selected = v; renderLabel(); },
    clear() { selected = null; renderLabel(); },
  };
}

/* ---------- تب‌ها ---------- */
function wireTabs(scope = document) {
  qsa('.tabs button[data-tab]', scope).forEach((b) => b.addEventListener('click', () => {
    qsa('.tabs button', b.parentElement).forEach((x) => x.classList.remove('on'));
    b.classList.add('on');
    qsa('[data-pane]', scope).forEach((p) => p.classList.toggle('hide', p.dataset.pane !== b.dataset.tab));
  }));
}

/* ---------- اعلان‌ها (زنگ) — کامل و دقیق ---------- */
let bellOpen = false;

const NOTIF_ICONS = {
  APPROVAL: { icon: '⏳', bg: 'linear-gradient(135deg,#0891b2,#0e7490)' },
  DEFECT: { icon: '🐛', bg: 'linear-gradient(135deg,#dc2626,#991b1b)' },
  RETEST: { icon: '🔄', bg: 'linear-gradient(135deg,#7c3aed,#6d28d9)' },
  APPROVED: { icon: '✅', bg: 'linear-gradient(135deg,#16a34a,#15803d)' },
  REJECTED: { icon: '❌', bg: 'linear-gradient(135deg,#dc2626,#b91c1c)' },
  BLOCKED: { icon: '⛔', bg: 'linear-gradient(135deg,#64748b,#475569)' },
  CANCELLED: { icon: '🚫', bg: 'linear-gradient(135deg,#94a3b8,#64748b)' },
};

function timeAgo(isoStr) {
  const d = new Date(String(isoStr).replace(' ', 'T') + 'Z');
  const diff = Math.floor((Date.now() - d.getTime()) / 1000);
  if (diff < 10) return 'الان';
  if (diff < 60) return `${diff} ثانیه پیش`;
  if (diff < 3600) return `${Math.floor(diff / 60)} دقیقه پیش`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} ساعت پیش`;
  return faDate(isoStr);
}

function renderBellDropdown(d) {
  const host = qs('[data-bell]');
  if (!host) return;
  const wrap = host.parentElement;
  const pop = qs('.bell-pop', wrap);

  // badge
  const dot = qs('.dot', host);
  if (d.unread > 0) {
    if (!dot) { const s = document.createElement('span'); s.className = 'dot'; host.appendChild(s); }
    qs('.dot', host).textContent = d.unread > 99 ? '99+' : d.unread;
  } else qs('.dot', host)?.remove();

  // dropdown content
  const items = d.rows.length ? d.rows.map((n) => {
    const meta = NOTIF_ICONS[n.type] || { icon: '🔔', bg: 'linear-gradient(135deg,#6957e8,#5a49d5)' };
    return `
      <div class="notif-item ${n.read_at ? '' : 'unread'}" data-id="${n.id}">
        <span class="notif-ic" style="background:${meta.bg}">${meta.icon}</span>
        <div class="notif-body">
          <b>${esc(n.title)}</b>
          <p>${esc(n.message)}</p>
          <time>${timeAgo(n.created_at)}</time>
        </div>
        ${n.read_at ? '' : '<span class="notif-dot"></span>'}
      </div>`;
  }).join('') : `<div class="notif-empty"><span style="font-size:36px;opacity:.4">🔕</span><p>${L('common.no_notifications')}</p></div>`;

  pop.innerHTML = `
    <div class="notif-head">
      <b>${L('common.notifications')}</b>
      ${d.unread > 0 ? `<span class="notif-badge">${d.unread}</span>` : ''}
      <button data-readall title="${L('common.read_all')}">${L('common.read_all')}</button>
    </div>
    <div class="notif-list">${items}</div>
    <a class="notif-foot" href="/notifications">${L('notif.view_all')} →</a>`;

  // رویدادها
  qs('[data-readall]', pop)?.addEventListener('click', async (e) => {
    e.stopPropagation();
    await api('/notifications/read-all', { method: 'POST' });
    pollBell();
  });
  qsa('.notif-item', pop).forEach((el) => el.addEventListener('click', async (e) => {
    e.stopPropagation();
    await api(`/notifications/read/${el.dataset.id}`, { method: 'POST' });
    pop.classList.remove('open'); bellOpen = false;
    location.href = '/workbench';
  }));
}

async function pollBell() {
  try {
    const d = await api('/notifications/latest');
    renderBellDropdown(d);
  } catch { /* بی‌صدا */ }
}

function initBell() {
  const host = qs('[data-bell]');
  if (!host) return;
  const wrap = host.parentElement;
  const pop = qs('.bell-pop', wrap);

  // toggle
  host.addEventListener('click', (e) => {
    e.stopPropagation();
    bellOpen = !bellOpen;
    pop.classList.toggle('open', bellOpen);
    if (bellOpen) pollBell();
  });

  // بستن با کلیک بیرون
  document.addEventListener('click', (e) => {
    if (bellOpen && !wrap.contains(e.target)) {
      pop.classList.remove('open'); bellOpen = false;
    }
  });

  // بستن با Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && bellOpen) {
      pop.classList.remove('open'); bellOpen = false;
    }
  });
}

/* ---------- سایدبار موبایل ---------- */
document.addEventListener('click', (e) => {
  if (e.target.closest('[data-menu-btn]')) qs('.side')?.classList.toggle('open');
  else if (!e.target.closest('.side')) qs('.side.open')?.classList.remove('open');
});

/* ---------- بوت ---------- */
document.addEventListener('DOMContentLoaded', () => {
  wireTabs();
  initBell();
  pollBell();
  setInterval(pollBell, 30000);
});
