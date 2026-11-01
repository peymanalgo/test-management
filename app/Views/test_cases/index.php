<?php $title = t('gc.title'); $active = 'cases';
$treeData = [];
foreach ($projects as $p) $treeData[] = ['id' => (int) $p['id'], 'name' => $p['name'], 'nodes' => $tree[$p['id']] ?? []];
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= t('nav.group.test') ?></div>
    <h1><?= t('gc.title') ?></h1>
    <p><?= t('gc.desc') ?></p>
  </div>
</div>

<div class="filterbar">
  <div class="frow">
    <div class="search" style="flex:1.4"><span class="sic">🔎</span>
      <input class="inp" data-q placeholder="<?= t('gc.search_ph') ?>"></div>
    <div data-tsel style="min-width:260px"></div>
    <select class="inp" data-wstatus><option value=""><?= t('cases.all_statuses') ?></option>
      <?php foreach (['READY','IN_TEST','IN_FIX','READY_RETEST','BLOCKED','PENDING_APPROVAL','APPROVED','REJECTED','CANCELLED'] as $st): ?>
        <option value="<?= $st ?>"><?= t('status.' . $st) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="inp" data-priority><option value=""><?= t('cases.all_priorities') ?></option>
      <option value="CRITICAL"><?= t('pr.CRITICAL') ?></option><option value="HIGH"><?= t('pr.HIGH') ?></option>
      <option value="MEDIUM"><?= t('pr.MEDIUM') ?></option><option value="LOW"><?= t('pr.LOW') ?></option></select>
    <select class="inp" data-type><option value=""><?= t('gc.all_types') ?></option>
      <option value="FUNCTIONAL"><?= t('cases.type.functional') ?></option><option value="UI"><?= t('cases.type.ui') ?></option>
      <option value="SECURITY"><?= t('cases.type.security') ?></option><option value="REGRESSION"><?= t('cases.type.regression') ?></option>
      <option value="OTHER"><?= t('cases.type.other') ?></option></select>
  </div>
  <div class="chips" data-chips></div>
</div>

<div data-grid></div>

<script>
window.I18N.gc = {
  tsel_ph: <?= json_encode(t('gc.tsel_ph')) ?>,
  tsel_search: <?= json_encode(t('gc.tsel_search')) ?>,
  tsel_all: <?= json_encode(t('gc.tsel_all')) ?>,
  no_result: <?= json_encode(t('gc.no_result') ) ?>,
  filter_search: <?= json_encode(t('gc.filter_search')) ?>,
  filter_section: <?= json_encode(t('gc.filter_section')) ?>,
  filter_project: <?= json_encode(t('gc.filter_project')) ?>,
  filter_status: <?= json_encode(t('gc.filter_status')) ?>,
  filter_prio: <?= json_encode(t('gc.filter_prio')) ?>,
  filter_type: <?= json_encode(t('gc.filter_type')) ?>,
  specs: <?= json_encode(t('gc.specs')) ?>,
  goal_pre: <?= json_encode(t('gc.goal_pre')) ?>,
  pre: <?= json_encode(t('gc.pre')) ?>,
  test_data: <?= json_encode(t('gc.test_data')) ?>,
  expected: <?= json_encode(t('gc.expected')) ?>,
  steps: <?= json_encode(t('gc.steps')) ?>,
  no_steps: <?= json_encode(t('gc.no_steps')) ?>,
  cycle: <?= json_encode(t('gc.cycle')) ?>,
  execs: <?= json_encode(t('gc.execs')) ?>,
  no_event: <?= json_encode(t('gc.no_event')) ?>,
  no_exec: <?= json_encode(t('gc.no_exec')) ?>,
  current_status: <?= json_encode(t('gc.current_status')) ?>,
  revision: <?= json_encode(t('gc.revision')) ?>,
  extra: <?= json_encode(t('gc.extra')) ?>,
  goal: <?= json_encode(t('gc.goal')) ?>,
  defects: <?= json_encode(t('gc.defects')) ?>,
  severity: <?= json_encode(t('gc.severity')) ?>,
  run_closed: <?= json_encode(t('gc.run_closed')) ?>,
  run: <?= json_encode(t('wb.run_name')) ?>,
  expect_lbl: <?= json_encode(t('cases.f.expected')) ?>,
};
const G = window.I18N.gc;
const PROJECTS = <?= json_encode($treeData, JSON_UNESCAPED_UNICODE) ?>;
let sel = null;

const grid = createGrid({
  container: qs('[data-grid]'),
  endpoint: '/test-cases/data',
  filterState: () => ({
    q: qs('[data-q]').value,
    project_id: sel ? sel.projectId : '',
    node_id: sel && sel.nodeId ? sel.nodeId : '',
    workflow: qs('[data-wstatus]').value,
    priority: qs('[data-priority]').value,
    test_type: qs('[data-type]').value,
  }),
  onRowClick: (r) => detailModal(r.id),
  columns: [
    { key: 'code', label: L('common.code'), render: (r) => `<b style="direction:ltr;display:inline-block">${esc(r.code)}</b>` },
    { key: 'title', label: L('common.title'), render: (r) => `${esc(r.title)}<br><small class="text-muted">${esc(r.project_name)} / ${esc(r.node_name)}</small>` },
    { key: 'priority', label: L('common.priority'), render: (r) => prioPill(r.priority) },
    { key: 'test_type', label: L('common.type'), render: (r) => ({ FUNCTIONAL: 'func', UI: 'UI', SECURITY: 'sec', REGRESSION: 'reg', OTHER: 'other' }[r.test_type] ? ({ FUNCTIONAL: T_type('FUNCTIONAL'), UI: T_type('UI'), SECURITY: T_type('SECURITY'), REGRESSION: T_type('REGRESSION'), OTHER: T_type('OTHER') }[r.test_type]) : r.test_type) },
    { key: 'tester_name', label: L('common.tester'), render: (r) => esc(r.tester_name || '—') },
    { key: 'workflow_status', label: T_col_wf(), render: (r) => stPill(r.workflow_status) },
    { key: 'last_result', label: T_col_last(), render: (r) => resPill(r.last_result) },
    { key: 'created_at', label: L('common.created'), render: (r) => `<span class="num">${faDateTime(r.created_at)}</span>` },
    { label: '', render: (r) => `<button class="h-btn" data-history="${r.id}" title="🕘">🕘</button>` },
  ],
});
function T_type(k) { return window.I18N.tree?.type_labels?.[k] || k; }
function T_col_wf() { return window.I18N.tree?.col_workflow || 'Workflow'; }
function T_col_last() { return window.I18N.tree?.col_last || 'Last result'; }
grid.load();
qs('[data-grid]').addEventListener('click', (e) => {
  const hs = e.target.closest('[data-history]');
  if (hs) { e.stopPropagation(); historyModal(+hs.dataset.history); }
}, true);

treeSelect(qs('[data-tsel]'), {
  projects: PROJECTS,
  placeholder: G.tsel_ph,
  searchPlaceholder: G.tsel_search,
  allLabel: G.tsel_all,
  onSelect: (s2) => { sel = s2; grid.reload(); renderChips(); },
});

function renderChips() {
  const chips = [];
  const q = qs('[data-q]').value.trim(); if (q) chips.push(G.filter_search + q);
  if (sel) chips.push(sel.nodeName ? `${G.filter_section}${sel.projectName} / ${sel.nodeName}` : `${G.filter_project}${sel.projectName}`);
  const st = qs('[data-wstatus]'); if (st.value) chips.push(G.filter_status + st.options[st.selectedIndex].text);
  const pr = qs('[data-priority]'); if (pr.value) chips.push(G.filter_prio + pr.options[pr.selectedIndex].text);
  const tp = qs('[data-type]'); if (tp.value) chips.push(G.filter_type + tp.options[tp.selectedIndex].text);
  qs('[data-chips]').innerHTML = chips.map((c) => `<span class="chip">${esc(c)}</span>`).join('');
}
let deb; qs('[data-q]').addEventListener('input', () => { clearTimeout(deb); deb = setTimeout(() => { grid.reload(); renderChips(); }, 350); });
['[data-wstatus]', '[data-priority]', '[data-type]'].forEach((s) => qs(s).addEventListener('change', () => { grid.reload(); renderChips(); }));

/* ---------- پاپ‌آپ جزئیات فقط‌خواندنی ---------- */
async function detailModal(id) {
  let d;
  try { d = await api(`/test-cases/${id}/detail`); } catch (e) { toast(e.message, 'err'); return; }
  const c = d.case;
  const steps = (d.steps || []).map((s) => `<li><span class="n">${s.step_order}</span><div>${esc(s.instruction)}${s.expected_result ? `<small>${G.expected}: ${esc(s.expected_result)}</small>` : ''}</div></li>`).join('') || `<li class="text-muted">${G.no_steps}</li>`;
  const execs = (d.executions || []).map((e) => `
    <li class="${e.result === 'PASS' ? 't-pass' : e.result === 'FAIL' ? 't-fail' : 't-block'}">
      <span class="dot2"></span>
      <b>${esc(e.execution_no)} — ${resPill(e.result)} <small>${esc(e.tester_name)}</small></b>
      <div class="tl-reason">${esc(e.actual_result || '')}</div>
      <time>${faDateTime(e.executed_at)}</time>
    </li>`).join('') || `<li><span class="dot2"></span><span class="text-muted">${G.no_exec}</span></li>`;
  const hist = (d.history || []).map((h) => `
    <li><span class="dot2"></span>
      <b>${h.from_status ? stPill(h.from_status) + ' → ' : ''}${stPill(h.to_status)}</b>
      <div class="tl-reason">${esc(h.reason || '')} — <span class="text-muted">${esc(h.actor_name)}</span></div>
      <time>${faDateTime(h.created_at)}</time>
    </li>`).join('') || `<li><span class="dot2"></span><span class="text-muted">${G.no_event}</span></li>`;
  const defs = (d.defects || []).map((x) => `
    <tr><td><b>${esc(x.code)}</b></td><td>${esc(x.title)}</td>
    <td><span class="pill pr-${x.severity.toLowerCase()}">${L('pr.' + x.severity)}</span></td>
    <td>${x.status ? `<span class="pill">${L('dstatus.' + x.status)}</span>` : '—'}</td></tr>`).join('');
  const m = openModal({
    title: `${esc(c.code)} — ${esc(c.title)}`,
    sub: `${esc(c.project)} › ${esc(c.node)}`,
    wide: true,
    body: `
      <div class="two-col">
        <div>
          <div class="section-title">${G.specs}</div>
          <dl class="kv">
            <dt>${G.current_status}</dt><dd>${stPill(c.workflow_status)}</dd>
            <dt>${L('common.priority')}</dt><dd>${prioPill(c.priority)}</dd>
            <dt>${L('common.type')}</dt><dd>${esc(c.test_type_label)}</dd>
            <dt>${G.revision}</dt><dd>${c.current_revision}</dd>
            <dt>${L('common.tester')}</dt><dd>${esc(c.tester || '—')}</dd>
            <dt>${L('common.developer')}</dt><dd>${esc(c.developer || '—')}</dd>
            <dt>${L('common.manager')}</dt><dd>${esc(c.manager || '—')}</dd>
          </dl>
          <div class="section-title mt">${G.goal_pre}</div>
          <p style="font-size:12.5px">${esc(c.objective || '—')}</p>
          <p style="font-size:12.5px"><b>${G.pre}:</b> ${esc(c.preconditions || '—')}</p>
          <p style="font-size:12.5px"><b>${G.test_data}:</b> ${esc(c.test_data || '—')}</p>
          <p style="font-size:12.5px"><b>${G.expected}:</b> ${esc(c.expected_result)}</p>
          <div class="section-title mt">${G.steps}</div>
          <ol class="steps-list">${steps}</ol>
        </div>
        <div>
          <div class="section-title">${G.cycle}</div>
          <ul class="timeline">${hist}</ul>
          <div class="section-title mt">${G.execs}</div>
          <ul class="timeline">${execs}</ul>
          ${defs ? `<div class="section-title mt">${G.defects}</div><table class="tbl"><thead><tr><th>${L('common.code')}</th><th>${L('common.title')}</th><th>${G.severity}</th><th>${L('common.status')}</th></tr></thead><tbody>${defs}</tbody></table>` : ''}
        </div>
      </div>`,
    footer: `<button class="btn alt" data-close>${L('common.close')}</button>`,
  });
  qs('[data-close]', m.root).addEventListener('click', m.close);
}
</script>
<?= $this->endSection() ?>
