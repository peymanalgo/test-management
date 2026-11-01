<?php $title = t('tree.title'); $active = 'projects';
$renderNodes = function (array $nodes) use (&$renderNodes) {
    foreach ($nodes as $n): $hasKids = !empty($n['children']); ?>
    <li>
      <div class="tree-node" data-id="<?= $n['id'] ?>" data-name="<?= esc($n['name']) ?>" role="button" title="<?= t('tree.hint.click') ?>">
        <button class="tw <?= $hasKids ? '' : 'empty' ?>" data-tw aria-label="+">+</button>
        <span class="t-name" data-rename="<?= $n['id'] ?>"><?= esc($n['name']) ?></span>
        <span class="t-meta"><span class="chip" title="<?= t('common.title') ?>">📋 <?= (int) $n['case_count'] ?></span></span>
        <span class="t-people" title="<?= t('tree.people') ?>">
          <?php if ($n['tester_name']): ?>
            🧪 <?= esc($n['tester_name']) ?> · 💻 <?= esc($n['developer_name']) ?> · 🧭 <?= esc($n['manager_name']) ?>
          <?php else: ?><span style="color:var(--warn)"><?= t('tree.no_owner') ?></span><?php endif; ?>
        </span>
        <div class="t-actions">
          <button class="t-cases-btn" data-cases="<?= $n['id'] ?>" data-name="<?= esc($n['name']) ?>" title="<?= t('tree.hint.click') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><polyline points="9 14 11.5 16.5 15.5 11.5"/></svg>
            <?= t('tree.scenarios') ?>
          </button>
          <button data-add="<?= $n['id'] ?>" data-name="<?= esc($n['name']) ?>" title="<?= t('tree.add_child') ?>">+</button>
          <button data-rename-btn="<?= $n['id'] ?>" title="<?= t('tree.rename') ?>">✏️</button>
          <button data-assign="<?= $n['id'] ?>" data-name="<?= esc($n['name']) ?>" data-assign-values='<?= esc(json_encode(['t' => $n['tester_name'], 'd' => $n['developer_name'], 'm' => $n['manager_name']])) ?>' title="<?= t('tree.people') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </button>
          <button data-del="<?= $n['id'] ?>" data-name="<?= esc($n['name']) ?>" title="<?= t('tree.del_node') ?>">🗑</button>
        </div>
      </div>
      <?php if ($hasKids): ?><ul class="tree-branch"><?= $renderNodes($n['children']) ?></ul><?php endif; ?>
    </li>
  <?php endforeach;
};
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= esc($project['name']) ?></div>
    <h1><?= t('tree.title') ?></h1>
    <p><?= t('tree.desc') ?></p>
  </div>
  <div class="btn-row">
    <a class="btn alt" href="/projects"><?= t('tree.back') ?></a>
    <button class="btn" onclick="nodeModal(null)"><?= t('tree.new_root') ?></button>
  </div>
</div>

<div class="tree-hint">
  <span><b><?= t('tree.hint.click') ?></b></span>
  <span><b><?= t('tree.hint.dblclick') ?></b></span>
  <span><b><?= t('tree.hint.plus') ?></b></span>
  <span><b><?= t('tree.hint.people') ?></b></span>
</div>

<div class="card">
  <div class="card-b">
    <?php if (!$nodes): ?>
      <div class="tbl-empty"><span class="big">🌳</span><h3><?= t('tree.empty') ?></h3>
      <p><?= t('tree.empty_sub') ?></p>
      <button class="btn" onclick="nodeModal(null)"><?= t('tree.new_root') ?></button></div>
    <?php else: ?>
      <ul class="tree"><?= $renderNodes($nodes) ?></ul>
    <?php endif; ?>
  </div>
</div>

<script>
const PROJECT_ID = <?= (int) $project['id'] ?>;
const USERS = <?= json_encode(array_map(fn($u) => ['id' => (int) $u['id'], 'name' => $u['display_name'], 'roles' => $u['roles']], $users), JSON_UNESCAPED_UNICODE) ?>;
const nodeIndex = {};
<?php foreach ($flat as $f): ?>nodeIndex[<?= (int) $f['id'] ?>] = <?= json_encode(['id' => (int) $f['id'], 'name' => $f['name'], 'parent_id' => $f['parent_id'] ? (int) $f['parent_id'] : null], JSON_UNESCAPED_UNICODE) ?>;
<?php endforeach; ?>

window.I18N.tree = {
  popup_title: <?= json_encode(t('cases.popup_title')) ?>,
  popup_sub: <?= json_encode(t('cases.popup_sub')) ?>,
  all_statuses: <?= json_encode(t('cases.all_statuses')) ?>,
  all_priorities: <?= json_encode(t('cases.all_priorities')) ?>,
  new_case: <?= json_encode(t('cases.new')) ?>,
  import: <?= json_encode(t('cases.import')) ?>,
  col_workflow: <?= json_encode(t('cases.col.workflow')) ?>,
  col_last: <?= json_encode(t('cases.col.last_result')) ?>,
  locked: <?= json_encode(t('cases.locked_view')) ?>,
  locked_title: <?= json_encode(t('cases.locked_view_title')) ?>,
  search_ph: <?= json_encode(t('cases.search_ph')) ?>,
  form_new: <?= json_encode(t('cases.form.new')) ?>,
  form_view_note: <?= json_encode(t('cases.form.view_note')) ?>,
  f_code: <?= json_encode(t('cases.f.code')) ?>,
  f_title: <?= json_encode(t('cases.f.title')) ?>,
  f_obj: <?= json_encode(t('cases.f.objective')) ?>,
  f_pre: <?= json_encode(t('cases.f.preconditions')) ?>,
  f_data: <?= json_encode(t('cases.f.test_data')) ?>,
  f_steps: <?= json_encode(t('cases.f.steps')) ?>,
  f_steps_hint: <?= json_encode(t('cases.f.steps_hint')) ?>,
  f_expected: <?= json_encode(t('cases.f.expected')) ?>,
  f_priority: <?= json_encode(t('cases.f.priority')) ?>,
  f_type: <?= json_encode(t('cases.f.type')) ?>,
  save_new: <?= json_encode(t('cases.save_btn')) ?>,
  save_edit: <?= json_encode(t('cases.save_edit_btn')) ?>,
  form_sub: <?= json_encode(t('cases.form.sub')) ?>,
  form_view_sub: <?= json_encode(t('cases.form.view_sub')) ?>,
  history_btn: <?= json_encode(t('cases.history_btn')) ?>,
  archive_confirm: <?= json_encode(t('cases.archive_confirm')) ?>,
  archive_btn: <?= json_encode(t('cases.archive_btn')) ?>,
  node_new: <?= json_encode(t('tree.node_new')) ?>,
  node_name: <?= json_encode(t('tree.node_name')) ?>,
  node_parent: <?= json_encode(t('tree.node_parent')) ?>,
  no_parent: <?= json_encode(t('tree.no_parent')) ?>,
  node_sub_tpl: <?= json_encode(t('tree.node_sub')) ?>,
  node_edit_tpl: <?= json_encode(t('tree.node_edit')) ?>,
  assign_sub: <?= json_encode(t('tree.assign_sub')) ?>,
  assign_save: <?= json_encode(t('common.save')) ?>,
  del_confirm: <?= json_encode(t('tree.node_del_confirm')) ?>,
  del_btn: <?= json_encode(t('tree.del_btn')) ?>,
  rename_toast: <?= json_encode(t('tree.rename_toast')) ?>,
  imp_title: <?= json_encode(t('imp.title')) ?>,
  imp_sub: <?= json_encode(t('imp.sub')) ?>,
  imp_template: <?= json_encode(t('imp.template')) ?>,
  imp_file: <?= json_encode(t('imp.file')) ?>,
  imp_preview: <?= json_encode(t('imp.preview_btn')) ?>,
  imp_commit: <?= json_encode(t('imp.commit_btn')) ?>,
  type_labels: {
    FUNCTIONAL: <?= json_encode(t('cases.type.functional')) ?>,
    UI: <?= json_encode(t('cases.type.ui')) ?>,
    SECURITY: <?= json_encode(t('cases.type.security')) ?>,
    REGRESSION: <?= json_encode(t('cases.type.regression')) ?>,
    OTHER: <?= json_encode(t('cases.type.other')) ?>,
  },
};
const T = window.I18N.tree;

/* ---------- باز/بسته شدن شاخه‌ها ---------- */
qsa('[data-tw]').forEach((b) => b.addEventListener('click', (e) => {
  e.stopPropagation();
  const br = b.closest('li').querySelector(':scope > .tree-branch');
  if (!br) return;
  br.classList.toggle('open');
  b.textContent = br.classList.contains('open') ? '−' : '+';
}));

/* ---------- ویرایش نام بخش ---------- */
function startRename(el) {
  if (el.querySelector('input')) return;
  const id = +el.dataset.rename;
  const old = nodeIndex[id].name;
  el.innerHTML = `<input value="${esc(old)}">`;
  const inp = el.querySelector('input');
  inp.focus(); inp.select();
  const save = async () => {
    const name = inp.value.trim();
    if (name.length < 2 || name === old) { el.textContent = old; return; }
    const fd = new FormData();
    fd.append('id', id); fd.append('name', name); fd.append('parent_id', nodeIndex[id].parent_id || 0);
    try {
      await api(`/projects/${PROJECT_ID}/nodes/save`, { method: 'POST', body: fd });
      nodeIndex[id].name = name;
      el.textContent = name;
      toast(T.rename_toast);
    } catch (err) { el.textContent = old; toast(err.message, 'err'); }
  };
  inp.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') { ev.preventDefault(); inp.blur(); }
    if (ev.key === 'Escape') { el.textContent = old; }
  });
  inp.addEventListener('blur', () => save());
}
qsa('[data-rename]').forEach((el) => {
  el.addEventListener('dblclick', (e) => { e.stopPropagation(); startRename(el); });
  el.addEventListener('click', (e) => { if (el.querySelector('input')) e.stopPropagation(); });
});
qsa('[data-rename-btn]').forEach((b) => b.addEventListener('click', (e) => {
  e.stopPropagation();
  const span = b.closest('.tree-node').querySelector('[data-rename]');
  if (span) startRename(span);
}));

/* ---------- کلیک روی ردیف = پنجرهٔ سناریوها ---------- */
qsa('.tree-node').forEach((row) => row.addEventListener('click', (e) => {
  if (e.target.closest('button') || e.target.closest('input')) return;
  scenariosModal(+row.dataset.id, row.dataset.name);
}));

/* ---------- مودال بخش ---------- */
function nodeModal(node, parentId, parentName) {
  const isEdit = !!node;
  const m = openModal({
    title: isEdit ? T.node_edit_tpl.replace('{name}', esc(node.name)) : (parentName ? T.node_sub_tpl.replace('{name}', esc(parentName)) : T.node_new),
    narrow: true,
    body: `<div class="field"><label>${T.node_name} <span class="req">*</span></label>
      <input class="inp" data-f-name value="${isEdit ? esc(node.name) : ''}"></div>
      <div class="field"><label>${T.node_parent}</label>
      <select class="inp" data-f-parent>
        <option value="0">${T.no_parent}</option>
        ${Object.values(nodeIndex).map((n) => isEdit && n.id === node.id ? '' : `<option value="${n.id}" ${parentId === n.id ? 'selected' : ''}>${esc(pathOf(n.id))}</option>`).join('')}
      </select></div>`,
    footer: `<button class="btn" data-save>${isEdit ? L('common.save') : L('common.add')}</button>
             <button class="btn alt" data-close>${L('common.cancel')}</button>`,
  });
  qs('[data-save]', m.root).addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('id', isEdit ? node.id : '');
    fd.append('name', qs('[data-f-name]', m.root).value);
    fd.append('parent_id', qs('[data-f-parent]', m.root).value);
    try {
      await api(`/projects/${PROJECT_ID}/nodes/save`, { method: 'POST', body: fd });
      toast(isEdit ? window.I18N.tree.node_saved : window.I18N.tree.node_added);
      setTimeout(() => location.reload(), 450);
    } catch (err) { toast(err.message, 'err'); }
  });
  qs('[data-close]', m.root).addEventListener('click', m.close);
}
window.I18N.tree.node_saved = <?= json_encode(t('tree.node_saved')) ?>;
window.I18N.tree.node_added = <?= json_encode(t('tree.node_added')) ?>;
function pathOf(id) {
  const parts = [];
  let cur = nodeIndex[id];
  let guard = 0;
  while (cur && guard++ < 20) { parts.unshift(cur.name); cur = cur.parent_id ? nodeIndex[cur.parent_id] : null; }
  return parts.join(' ← ');
}

/* ---------- حذف بخش ---------- */
function delNode(id, name) {
  const m = openModal({
    title: `${window.I18N.tree.del}: «${esc(name)}»`.replace('undefined: ', ''),
    narrow: true,
    body: `<p>${T.del_confirm}</p>`,
    footer: `<button class="btn danger" data-ok>${T.del_btn}</button><button class="btn alt" data-close>${L('common.cancel')}</button>`,
  });
  qs('[data-ok]', m.root).addEventListener('click', async () => {
    try { await api(`/nodes/${id}/delete`, { method: 'POST' }); toast(window.I18N.tree.node_deleted); setTimeout(() => location.reload(), 400); }
    catch (err) { toast(err.message, 'err'); }
  });
  qs('[data-close]', m.root).addEventListener('click', m.close);
}
window.I18N.tree.del = <?= json_encode(t('tree.del_node')) ?>;
window.I18N.tree.node_deleted = <?= json_encode(t('tree.node_deleted')) ?>;

/* ---------- مسئولان بخش ---------- */
function assignModal(id, name, cur) {
  const opts = (role) => `<option value="">— ${L('common.confirm')} —</option>` + USERS
    .filter((u) => (u.roles || '').split(',').includes(role))
    .map((u) => `<option value="${u.id}" ${cur && cur[role[0]] === u.name ? 'selected' : ''}>${esc(u.name)}</option>`).join('');
  const m = openModal({
    title: window.I18N.tree.assign_tpl.replace('{name}', esc(name)),
    sub: T.assign_sub,
    body: `<div class="field"><label>🧪 ${L('common.tester')}</label><select class="inp" data-t>${opts('TESTER')}</select></div>
      <div class="field"><label>💻 ${L('common.developer')}</label><select class="inp" data-d>${opts('DEVELOPER')}</select></div>
      <div class="field"><label>🧭 ${L('common.manager')}</label><select class="inp" data-m>${opts('MANAGER')}</select></div>`,
    footer: `<button class="btn" data-save>${T.assign_save}</button><button class="btn alt" data-close>${L('common.cancel')}</button>`,
  });
  qs('[data-save]', m.root).addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('tester_id', qs('[data-t]', m.root).value);
    fd.append('developer_id', qs('[data-d]', m.root).value);
    fd.append('manager_id', qs('[data-m]', m.root).value);
    try {
      const r = await api(`/nodes/${id}/assign`, { method: 'POST', body: fd });
      toast(r.message);
      setTimeout(() => location.reload(), 600);
    } catch (err) { toast(err.message, 'err'); }
  });
  qs('[data-close]', m.root).addEventListener('click', m.close);
}
window.I18N.tree.assign_tpl = <?= json_encode(t('tree.assign_title')) ?>;

qsa('[data-add]').forEach((b) => b.addEventListener('click', (e) => { e.stopPropagation(); nodeModal(null, +b.dataset.add, b.dataset.name); }));
qsa('[data-del]').forEach((b) => b.addEventListener('click', (e) => { e.stopPropagation(); delNode(+b.dataset.del, b.dataset.name); }));
qsa('[data-assign]').forEach((b) => b.addEventListener('click', (e) => {
  e.stopPropagation();
  let cur = null;
  try { cur = JSON.parse(b.dataset.assignValues); } catch {}
  assignModal(+b.dataset.assign, b.dataset.name, cur);
}));
qsa('[data-cases]').forEach((b) => b.addEventListener('click', (e) => { e.stopPropagation(); scenariosModal(+b.dataset.cases, b.dataset.name); }));

/* ============================================================
   پنجرهٔ سناریوهای بخش
   ============================================================ */
function scenariosModal(nodeId, nodeName) {
  const statusOptions = Object.keys(window.I18N.status).map((k) => `<option value="${k}">${window.I18N.status[k]}</option>`).join('');
  const prioOptions = Object.keys(window.I18N.pr).map((k) => `<option value="${k}">${window.I18N.pr[k]}</option>`).join('');
  const m = openModal({
    title: T.popup_title.replace('{name}', esc(nodeName)),
    sub: `${esc(pathOf(nodeId))} — ${T.popup_sub}`,
    wide: true,
    body: `
      <div class="filterbar">
        <div class="frow">
          <div class="search"><span class="sic">🔎</span><input class="inp" data-q placeholder="${T.search_ph}"></div>
          <select class="inp" data-status><option value="">${T.all_statuses}</option>${statusOptions}</select>
          <select class="inp" data-priority><option value="">${T.all_priorities}</option>${prioOptions}</select>
          <button class="btn" data-new>${T.new_case}</button>
          <button class="btn alt" data-import>${T.import}</button>
        </div>
      </div>
      <div data-grid></div>`,
  });
  const grid = createGrid({
    container: qs('[data-grid]', m.root),
    endpoint: `/nodes/${nodeId}/cases`,
    filterState: () => ({ q: qs('[data-q]', m.root).value, status: qs('[data-status]', m.root).value, priority: qs('[data-priority]', m.root).value }),
    onRowClick: (r) => caseModal(nodeId, r.id, grid, !r.editable || r.editable === '0'),
    columns: [
      { key: 'code', label: L('common.code'), render: (r) => `<b style="direction:ltr;display:inline-block">${esc(r.code)}</b>` },
      { key: 'title', label: L('common.title') },
      { key: 'priority', label: L('common.priority'), render: (r) => prioPill(r.priority) },
      { key: 'workflow_status', label: T.col_workflow, render: (r) => stPill(r.workflow_status) },
      { key: 'last_result', label: T.col_last, render: (r) => resPill(r.last_result) },
      { key: 'created_at', label: L('common.created'), render: (r) => `<span class="num">${faDateTime(r.created_at)}</span>` },
      { label: L('common.actions'), render: (r) => {
        const locked = !r.editable || r.editable === '0';
        return `<span style="white-space:nowrap">
          <button class="h-btn" data-history="${r.id}" title="🕘">🕘</button>
          ${locked
            ? `<button class="btn xs alt" data-view="${r.id}" title="${T.locked_title}">🔒 ${T.locked}</button>`
            : `<button class="btn xs alt" data-edit="${r.id}">${L('common.edit')}</button>
               <button class="btn xs alt" data-archive="${r.id}" title="${T.archive_btn}">🗄</button>`}
        </span>`; } },
    ],
  });
  grid.load();
  qs('[data-q]', m.root).addEventListener('input', debounce(() => grid.reload(), 350));
  qs('[data-status]', m.root).addEventListener('change', () => grid.reload());
  qs('[data-priority]', m.root).addEventListener('change', () => grid.reload());
  qs('[data-new]', m.root).addEventListener('click', () => caseModal(nodeId, null, grid));
  qs('[data-import]', m.root).addEventListener('click', () => importModal(nodeId, grid));
  m.body.addEventListener('click', (e) => {
    const hs = e.target.closest('[data-history]'); if (hs) return historyModal(+hs.dataset.history);
    const vw = e.target.closest('[data-view]'); if (vw) return caseModal(nodeId, +vw.dataset.view, grid, true);
    const ed = e.target.closest('[data-edit]'); if (ed) caseModal(nodeId, +ed.dataset.edit, grid, false);
    const ar = e.target.closest('[data-archive]');
    if (ar) {
      const am = openModal({ title: T.archive_btn, narrow: true, body: `<p>${T.archive_confirm}</p>`,
        footer: `<button class="btn warn" data-ok>${T.archive_btn}</button><button class="btn alt" data-close>${L('common.cancel')}</button>` });
      qs('[data-ok]', am.root).addEventListener('click', async () => {
        try { await api(`/cases/${ar.dataset.archive}/archive`, { method: 'POST' }); toast(window.I18N.tree.archived); am.close(); grid.load(); }
        catch (err) { toast(err.message, 'err'); }
      });
      qs('[data-close]', am.root).addEventListener('click', am.close);
    }
  });
}
window.I18N.tree.archived = <?= json_encode(t('cases.archived_msg')) ?>;

/* ---------- مودال سناریو ---------- */
async function caseModal(nodeId, caseId, grid, readonly = false) {
  let d = { code: '', title: '', objective: '', preconditions: '', steps: '', test_data: '', expected_result: '', priority: 'MEDIUM', test_type: 'FUNCTIONAL' };
  if (caseId) {
    try {
      const r = await api(`/test-cases/${caseId}/detail`);
      const c = r.case;
      const stepText = (r.steps || []).map((s) => s.expected_result ? `${s.instruction}|${s.expected_result}` : s.instruction).join('\n');
      d = { code: c.code, title: c.title, objective: c.objective || '', preconditions: c.preconditions || '', steps: stepText, test_data: c.test_data || '', expected_result: c.expected_result, priority: c.priority, test_type: c.test_type };
    } catch (e) { toast(e.message, 'err'); return; }
  }
  window.I18N.tree.form_edit_tpl = <?= json_encode(t('cases.form.edit')) ?>;
  window.I18N.tree.form_view_tpl = <?= json_encode(t('cases.form.view')) ?>;
  const m = openModal({
    title: caseId ? (readonly ? T.form_view_tpl.replace('{code}', esc(d.code)) : T.form_edit_tpl.replace('{code}', esc(d.code))) : T.form_new,
    sub: readonly ? T.form_view_sub : T.form_sub,
    wide: true,
    body: `${readonly ? `<div class="flash info">${T.form_view_note}</div>` : ''}
      <fieldset ${readonly ? 'disabled' : ''} style="border:0;padding:0;margin:0">
      <div class="form-grid">
      <div class="field"><label>${caseId ? L('common.code') : T.f_code}</label>
        <input class="inp input-ltr" data-f-code value="${esc(d.code)}" style="direction:ltr;text-align:left" placeholder="AUTO" ${readonly ? 'readonly' : ''}></div>
      <div class="field"><label>${T.f_title} <span class="req">*</span></label>
        <input class="inp" data-f-title value="${esc(d.title)}"></div>
      <div class="field full"><label>${T.f_obj}</label><input class="inp" data-f-obj value="${esc(d.objective)}"></div>
      <div class="field"><label>${T.f_priority}</label><select class="inp" data-f-prio>
        ${Object.keys(window.I18N.pr).map((k) => `<option value="${k}" ${d.priority === k ? 'selected' : ''}>${window.I18N.pr[k]}</option>`).join('')}</select></div>
      <div class="field"><label>${T.f_type}</label><select class="inp" data-f-type>
        ${Object.entries(T.type_labels).map(([k, v]) => `<option value="${k}" ${d.test_type === k ? 'selected' : ''}>${v}</option>`).join('')}</select></div>
      <div class="field"><label>${T.f_pre}</label><textarea class="inp" data-f-pre rows="3">${esc(d.preconditions)}</textarea></div>
      <div class="field"><label>${T.f_data}</label><textarea class="inp" data-f-data rows="3">${esc(d.test_data)}</textarea></div>
      <div class="field full"><label>${T.f_steps} <small class="text-muted">${T.f_steps_hint}</small></label>
        <textarea class="inp" data-f-steps rows="5" style="line-height:2.1">${esc(d.steps)}</textarea></div>
      <div class="field full"><label>${T.f_expected} <span class="req">*</span></label>
        <textarea class="inp" data-f-exp rows="3">${esc(d.expected_result)}</textarea></div>
    </div></fieldset>`,
    footer: readonly
      ? `<button class="btn" data-hist>${T.history_btn}</button><button class="btn alt" data-close>${L('common.close')}</button>`
      : `<button class="btn" data-save>${caseId ? T.save_edit : T.save_new}</button>
         <button class="btn alt" data-close>${L('common.cancel')}</button>`,
  });
  qs('[data-close]', m.root).addEventListener('click', m.close);
  if (readonly) {
    qs('[data-hist]', m.root)?.addEventListener('click', () => { m.close(); historyModal(caseId); });
    return;
  }
  qs('[data-save]', m.root).addEventListener('click', async () => {
    const fd = new FormData();
    if (caseId) fd.append('id', caseId);
    fd.append('code', qs('[data-f-code]', m.root).value);
    fd.append('title', qs('[data-f-title]', m.root).value);
    fd.append('objective', qs('[data-f-obj]', m.root).value);
    fd.append('preconditions', qs('[data-f-pre]', m.root).value);
    fd.append('steps', qs('[data-f-steps]', m.root).value);
    fd.append('test_data', qs('[data-f-data]', m.root).value);
    fd.append('expected_result', qs('[data-f-exp]', m.root).value);
    fd.append('priority', qs('[data-f-prio]', m.root).value);
    fd.append('test_type', qs('[data-f-type]', m.root).value);
    try {
      const r = await api(`/nodes/${nodeId}/cases/save`, { method: 'POST', body: fd });
      toast(r.message);
      m.close();
      if (grid) grid.load(); else location.reload();
    } catch (err) { toast(err.message, 'err'); }
  });
}

/* ---------- ورود گروهی اکسل ---------- */
function importModal(nodeId, grid) {
  const m = openModal({
    title: T.imp_title,
    sub: T.imp_sub,
    body: `
      <a class="btn alt sm" href="/import/template" style="margin-bottom:14px">${T.imp_template}</a>
      <div class="field"><label>${T.imp_file} <span class="req">*</span></label>
        <input type="file" class="inp" data-file accept=".xlsx,.csv"></div>
      <div data-result></div>`,
    footer: `<button class="btn" data-upload>${T.imp_preview}</button>
             <button class="btn success hide" data-commit>${T.imp_commit}</button>
             <button class="btn alt" data-close>${L('common.close')}</button>`,
  });
  qs('[data-upload]', m.root).addEventListener('click', async () => {
    const f = qs('[data-file]', m.root).files[0];
    if (!f) { toast(window.I18N.tree.imp_no_file, 'warn'); return; }
    const fd = new FormData(); fd.append('file', f);
    qs('[data-upload]', m.root).disabled = true;
    try {
      const r = await api(`/nodes/${nodeId}/import`, { method: 'POST', body: fd });
      const errHtml = (r.errors || []).length ? `<div class="flash err" style="margin-top:10px"><div>${r.errors.map(esc).join('<br>')}</div></div>` : '';
      const prev = (r.preview || []).map((row) => `<tr><td>${esc(row.title)}</td><td>${prioPill(row.priority)}</td><td>${esc(row.expected_result)}</td></tr>`).join('');
      qs('[data-result]', m.root).innerHTML = `
        <div class="flash ok">✓ ${esc(r.message)}</div>
        <table class="tbl"><thead><tr><th>${T.f_title}</th><th>${T.f_priority}</th><th>${T.f_expected}</th></tr></thead><tbody>${prev}</tbody></table>
        ${errHtml}`;
      qs('[data-commit]', m.root).classList.remove('hide');
    } catch (err) {
      qs('[data-result]', m.root).innerHTML = `<div class="flash err">✕ ${esc(err.message)}</div>`;
    }
    qs('[data-upload]', m.root).disabled = false;
  });
  qs('[data-commit]', m.root).addEventListener('click', async () => {
    try {
      const r = await api(`/nodes/${nodeId}/import/commit`, { method: 'POST' });
      toast(r.message);
      m.close();
      if (grid) grid.load();
    } catch (err) { toast(err.message, 'err'); }
  });
  qs('[data-close]', m.root).addEventListener('click', m.close);
}
window.I18N.tree.imp_no_file = <?= json_encode(t('imp.no_file')) ?>;

function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }
</script>
<?= $this->endSection() ?>
