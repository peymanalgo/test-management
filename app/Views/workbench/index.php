<?php $title = t('nav.workbench'); $active = 'workbench'; ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= esc($role_label) ?></div>
    <h1><?= t('wb.title', ['name' => session('display_name')]) ?></h1>
    <p>
      <?php if ($role === 'TESTER'): ?><?= t('wb.desc.tester') ?>
      <?php elseif ($role === 'DEVELOPER'): ?><?= t('wb.desc.developer') ?>
      <?php else: ?><?= t('wb.desc.manager') ?>
      <?php endif; ?>
    </p>
  </div>
</div>

<?php if (!$sections): ?>
  <div class="card"><div class="tbl-empty">
    <span class="big">📭</span>
    <h3><?= t('wb.no_sections') ?></h3>
    <p><?= t('wb.no_sections_sub') ?></p>
  </div></div>
<?php endif; ?>

<?php if ($sections): ?>
<div class="filterbar">
  <div class="frow">
    <div class="search" style="flex:1.2"><span class="sic">🔎</span>
      <input class="inp" data-f-q value="<?= esc($f['q']) ?>" placeholder="<?= t('common.search') ?>"></div>
    <div data-tsel style="min-width:250px"></div>
    <select class="inp" data-f-status>
      <option value=""><?= t('common.status') ?></option>
      <?php if ($role === 'DEVELOPER'): foreach (['OPEN' => 'dstatus.OPEN', 'REOPENED' => 'dstatus.REOPENED', 'IN_PROGRESS' => 'dstatus.IN_PROGRESS', 'FIXED_PENDING_RETEST' => 'dstatus.FIXED_PENDING_RETEST', 'VERIFIED' => 'dstatus.VERIFIED', 'CLOSED' => 'dstatus.CLOSED'] as $k => $lbl): ?>
        <option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= t($lbl) ?></option>
      <?php endforeach; else: foreach (['READY','IN_TEST','IN_FIX','READY_RETEST','BLOCKED','PENDING_APPROVAL','APPROVED','REJECTED','CANCELLED'] as $k): ?>
        <option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= t('status.' . $k) ?></option>
      <?php endforeach; endif; ?>
    </select>
    <select class="inp" data-f-prio>
      <option value=""><?= $role === 'DEVELOPER' ? t('wb.col.severity') : t('cases.all_priorities') ?></option>
      <option value="CRITICAL" <?= $f['priority'] === 'CRITICAL' ? 'selected' : '' ?>><?= t('pr.CRITICAL') ?></option>
      <option value="HIGH" <?= $f['priority'] === 'HIGH' ? 'selected' : '' ?>><?= t('pr.HIGH') ?></option>
      <option value="MEDIUM" <?= $f['priority'] === 'MEDIUM' ? 'selected' : '' ?>><?= t('pr.MEDIUM') ?></option>
      <option value="LOW" <?= $f['priority'] === 'LOW' ? 'selected' : '' ?>><?= t('pr.LOW') ?></option>
    </select>
  </div>
</div>
<?php endif; ?>

<?php if ($role === 'DEVELOPER' && isset($defects)): ?>
<div class="tabs">
  <button class="on" data-tab="todo"><?= t('wb.dev.tab.todo') ?> <span class="cnt"><?= (int) $counts['todo'] ?></span></button>
  <button data-tab="waiting"><?= t('wb.dev.tab.waiting') ?> <span class="cnt"><?= (int) $counts['waiting'] ?></span></button>
  <button data-tab="done"><?= t('wb.tab.done') ?> <span class="cnt"><?= (int) $counts['done'] ?></span></button>
</div>
<?php
$inTodo = fn($d) => in_array($d['status'], ['OPEN', 'REOPENED', 'IN_PROGRESS'], true);
$inWait = fn($d) => $d['status'] === 'FIXED_PENDING_RETEST';
$inDone = fn($d) => in_array($d['status'], ['VERIFIED', 'CLOSED'], true);
$renderDefects = function (array $list) {
    if (!$list) return '<div class="tbl-empty"><span class="big">☕</span>' . t('wb.empty_tab') . '</div>';
    $rows = '';
    foreach ($list as $d) {
        $act = '<button class="h-btn" data-history="' . $d['tc_id'] . '" title="🕘">🕘</button>';
        if (in_array($d['status'], ['OPEN', 'REOPENED'], true))
            $act .= '<button class="btn sm warn" data-startfix="' . $d['id'] . '">' . t('wb.start_fix') . '</button>';
        if (in_array($d['status'], ['OPEN', 'REOPENED', 'IN_PROGRESS'], true))
            $act .= '<button class="btn sm success" data-fixdone="' . $d['id'] . '">' . t('wb.fix_done') . '</button>';
        $rows .= '<tr>
          <td data-label="' . t('common.code') . '"><b style="direction:ltr;display:inline-block">' . esc($d['code']) . '</b></td>
          <td data-label="' . t('wb.col.defect_title') . '">' . esc($d['title']) . '<br><small class="text-muted">' . esc($d['tc_code']) . ' — ' . esc($d['tc_title']) . '</small>
              ' . ($d['fix_notes'] ? '<br><small style="color:var(--success)">' . t('wb.prev_fix') . ': ' . esc(mb_substr($d['fix_notes'], 0, 90)) . '</small>' : '') . '</td>
          <td data-label="' . t('wb.col.severity') . '">' . prio_pill($d['severity'], 'severity') . '</td>
          <td data-label="' . t('common.status') . '">' . defect_status_pill($d['status']) . '</td>
          <td data-label="' . t('wb.col.project_section') . '"><small>' . esc($d['project_name']) . '<br>' . esc($d['node_name']) . '</small></td>
          <td data-label="' . t('wb.col.report') . '"><small>' . t('wb.reported_by') . ': ' . esc($d['reporter_name']) . '<br>' . fa_dt($d['created_at']) . '</small></td>
          <td data-label="' . t('wb.col.action') . '"><div class="btn-row">' . $act . '</div></td></tr>';
    }
    return '<div class="tbl-wrap"><table class="tbl"><thead><tr><th>' . t('common.code') . '</th><th>' . t('wb.col.defect_title') . '</th><th>' . t('wb.col.severity') . '</th><th>' . t('common.status') . '</th><th>' . t('wb.col.project_section') . '</th><th>' . t('wb.col.report') . '</th><th>' . t('wb.col.action') . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
};
?>
<div data-pane="todo"><div class="card"><?= $renderDefects(array_filter($defects, $inTodo)) ?></div></div>
<div data-pane="waiting" class="hide"><div class="card"><?= $renderDefects(array_filter($defects, $inWait)) ?></div></div>
<div data-pane="done" class="hide"><div class="card"><?= $renderDefects(array_filter($defects, $inDone)) ?></div></div>

<?php elseif (isset($items)): ?>
<div class="tabs">
  <button class="on" data-tab="todo"><?= $role === 'TESTER' ? t('wb.tab.todo_tester') : t('wb.tab.todo_manager') ?> <span class="cnt"><?= (int) $counts['todo'] ?></span></button>
  <?php if ($role === 'TESTER'): ?>
  <button data-tab="blocked"><?= t('wb.tab.blocked') ?> <span class="cnt"><?= (int) $counts['blocked'] ?></span></button>
  <?php endif; ?>
  <button data-tab="waiting"><?= t('wb.tab.waiting') ?> <span class="cnt"><?= (int) $counts['waiting'] ?></span></button>
  <button data-tab="done"><?= t('wb.tab.done') ?> <span class="cnt"><?= (int) $counts['done'] ?></span></button>
</div>
<?php
$activeSet = ['READY', 'IN_TEST', 'READY_RETEST'];
$pick = function (string $pane) use ($items, $role, $activeSet) {
    return array_values(array_filter($items, function ($i) use ($pane, $role, $activeSet) {
        $st = $i['workflow_status'];
        if ($pane === 'todo') return in_array($st, $activeSet, true);
        if ($pane === 'blocked') return $st === 'BLOCKED';
        if ($pane === 'waiting') return in_array($st, ['IN_FIX', 'PENDING_APPROVAL'], true);
        return in_array($st, ['APPROVED', 'REJECTED', 'CANCELLED'], true);
    }));
};
$renderItems = function (array $list) use ($role) {
    if (!$list) return '<div class="tbl-empty"><span class="big">☕</span>' . t('wb.empty_tab') . '</div>';
    $rows = '';
    foreach ($list as $i) {
        $st = $i['workflow_status'];
        $act = '<button class="h-btn" data-history="' . $i['tc_id'] . '" title="🕘">🕘</button><button class="btn sm alt" data-detail="' . $i['id'] . '">' . t('common.details') . '</button>';
        if ($role === 'TESTER') {
            if ($st === 'READY') $act .= '<button class="btn sm" data-start="' . $i['id'] . '">' . t('wb.start_test') . '</button>';
            if (in_array($st, ['READY', 'IN_TEST', 'READY_RETEST'], true))
                $act .= '<button class="btn sm success" data-execute="' . $i['id'] . '">' . t('wb.submit_result') . '</button>';
            if ($st === 'BLOCKED') $act .= '<button class="btn sm warn" data-unblock="' . $i['id'] . '">' . t('wb.unblock') . '</button>';
        }
        if ($role === 'MANAGER') {
            if ($st === 'PENDING_APPROVAL') {
                $act .= '<button class="btn sm success" data-approve="' . $i['id'] . '">' . t('wb.approve') . '</button>';
                $act .= '<button class="btn sm danger" data-reject="' . $i['id'] . '">' . t('wb.reject') . '</button>';
            }
            if (in_array($st, ['APPROVED', 'REJECTED', 'CANCELLED', 'PENDING_APPROVAL'], true) && $i['run_status'] === 'OPEN')
                $act .= '<button class="btn sm alt" data-reopen="' . $i['id'] . '">' . t('wb.reopen') . '</button>';
            if (in_array($st, ['READY', 'IN_TEST', 'IN_FIX', 'READY_RETEST', 'BLOCKED', 'PENDING_APPROVAL'], true) && $i['run_status'] === 'OPEN')
                $act .= '<button class="btn sm warn" data-cancel="' . $i['id'] . '">' . t('wb.cancel') . '</button>';
        }
        $stNote = '';
        if ($st === 'IN_FIX' && $i['open_defects']) $stNote = '<span class="pill st-infix">' . (int) $i['open_defects'] . '</span>';
        $rows .= '<tr>
          <td data-label="' . t('common.code') . '"><b style="direction:ltr;display:inline-block">' . esc($i['code']) . '</b></td>
          <td data-label="' . t('wb.col.scenario') . '"><b>' . esc($i['title']) . '</b><br><small class="text-muted">' . esc($i['project_name']) . ' / ' . esc($i['node_name']) . '</small></td>
          <td data-label="' . t('common.priority') . '">' . prio_pill($i['priority']) . '</td>
          <td data-label="' . t('common.status') . '">' . status_pill($st) . ($stNote ? '<br>' . $stNote : '') . '</td>
          <td data-label="' . t('cases.col.last_result') . '">' . result_pill($i['last_result']) . ($i['exec_count'] ? '<br><small class="text-muted">' . (int) $i['exec_count'] . ' ' . t('wb.execs') . '</small>' : '') . '</td>
          <td data-label="' . t('wb.col.people') . '"><small>🧪 ' . esc($i['tester_name'] ?? '—') . '<br>💻 ' . esc($i['developer_name'] ?? '—') . '<br>🧭 ' . esc($i['manager_name'] ?? '—') . '</small></td>
          <td data-label="' . t('wb.col.action') . '"><div class="btn-row">' . $act . '</div></td></tr>';
    }
    return '<div class="tbl-wrap"><table class="tbl"><thead><tr><th>' . t('common.code') . '</th><th>' . t('wb.col.scenario') . '</th><th>' . t('common.priority') . '</th><th>' . t('common.status') . '</th><th>' . t('cases.col.last_result') . '</th><th>' . t('wb.col.people') . '</th><th>' . t('wb.col.action') . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
};
?>
<div data-pane="todo"><div class="card"><?= $renderItems($pick('todo')) ?></div></div>
<?php if ($role === 'TESTER'): ?>
<div data-pane="blocked" class="hide"><div class="card"><?= $renderItems($pick('blocked')) ?></div></div>
<?php endif; ?>
<div data-pane="waiting" class="hide"><div class="card"><?= $renderItems($pick('waiting')) ?></div></div>
<div data-pane="done" class="hide"><div class="card"><?= $renderItems($pick('done')) ?></div></div>
<?php endif; ?>

<script>
window.I18N.wb = {
  exec_title: <?= json_encode(t('wb.exec_title')) ?>,
  exec_steps: <?= json_encode(t('wb.exec_steps')) ?>,
  exec_pre: <?= json_encode(t('wb.exec_pre_data')) ?>,
  exec_result: <?= json_encode(t('wb.exec_result')) ?>,
  res_pass: <?= json_encode(t('wb.exec_res_pass')) ?>,
  res_fail: <?= json_encode(t('wb.exec_res_fail')) ?>,
  res_blocked: <?= json_encode(t('wb.exec_res_blocked')) ?>,
  exec_actual: <?= json_encode(t('wb.exec_actual')) ?>,
  defect_box: <?= json_encode(t('wb.exec_defect_box')) ?>,
  defect_title: <?= json_encode(t('wb.exec_defect_title')) ?>,
  defect_sev: <?= json_encode(t('wb.exec_defect_sev')) ?>,
  defect_desc: <?= json_encode(t('wb.exec_defect_desc')) ?>,
  defect_ph: <?= json_encode(t('wb.exec_defect_ph')) ?>,
  exec_submit: <?= json_encode(t('wb.exec_submit')) ?>,
  detail_status: <?= json_encode(t('wb.detail_status')) ?>,
  detail_steps: <?= json_encode(t('wb.detail_steps')) ?>,
  fix_title: <?= json_encode(t('wb.fix_done_title')) ?>,
  fix_label: <?= json_encode(t('wb.fix_done_label')) ?>,
  fix_btn: <?= json_encode(t('wb.fix_done_btn')) ?>,
  fix_note: <?= json_encode(t('wb.fix_done_note')) ?>,
  unblock_title: <?= json_encode(t('wb.unblock_title')) ?>,
  unblock_label: <?= json_encode(t('wb.unblock_label')) ?>,
  reject_title: <?= json_encode(t('wb.reject_title')) ?>,
  reject_label: <?= json_encode(t('wb.reject_label')) ?>,
  reject_note: <?= json_encode(t('wb.reject_note')) ?>,
  reject_btn: <?= json_encode(t('wb.reject_btn')) ?>,
  reopen_title: <?= json_encode(t('wb.reopen_title')) ?>,
  reopen_label: <?= json_encode(t('wb.reopen_label')) ?>,
  reopen_note: <?= json_encode(t('wb.reopen_note')) ?>,
  cancel_title: <?= json_encode(t('wb.cancel_title')) ?>,
  cancel_label: <?= json_encode(t('wb.cancel_label')) ?>,
  cancel_btn: <?= json_encode(t('wb.cancel_btn')) ?>,
  goal: <?= json_encode(t('gc.goal')) ?>,
  pre: <?= json_encode(t('gc.pre')) ?>,
  data: <?= json_encode(t('gc.test_data')) ?>,
  expected: <?= json_encode(t('gc.expected')) ?>,
  cycle: <?= json_encode(t('gc.cycle')) ?>,
  execs: <?= json_encode(t('gc.execs')) ?>,
  no_steps: <?= json_encode(t('gc.no_steps')) ?>,
  no_exec: <?= json_encode(t('gc.no_exec')) ?>,
  no_event: <?= json_encode(t('gc.no_event')) ?>,
  run_closed: <?= json_encode(t('gc.run_closed')) ?>,
  run: <?= json_encode(t('wb.run_name')) ?>,
  tsel_ph: <?= json_encode(t('wb.tsel_ph')) ?>,
  tsel_search: <?= json_encode(t('gc.tsel_search')) ?>,
  tsel_all: <?= json_encode(t('gc.tsel_all_mine')) ?>,
  extra: <?= json_encode(t('gc.extra')) ?>,
};
const W = window.I18N.wb;

async function act(url, body = {}) {
  const fd = new FormData();
  Object.entries(body).forEach(([k, v]) => fd.append(k, v));
  try { const r = await api(url, { method: 'POST', body: fd }); toast(r.message); setTimeout(() => location.reload(), 550); }
  catch (e) { toast(e.message, 'err'); }
}

document.addEventListener('click', async (e) => {
  const t = e.target.closest('button'); if (!t) return;
  const start = t.dataset.start, detail = t.dataset.detail, execute = t.dataset.execute;
  const unblock = t.dataset.unblock, approve = t.dataset.approve, reject = t.dataset.reject;
  const reopen = t.dataset.reopen, cancel = t.dataset.cancel;
  const startfix = t.dataset.startfix, fixdone = t.dataset.fixdone;
  const history = t.dataset.history;

  if (history) return historyModal(+history);
  if (start) return act(`/workbench/${start}/start`);
  if (approve) return act(`/workbench/${approve}/approve`);
  if (startfix) return act(`/defects/${startfix}/fix-start`);
  if (detail) return showDetail(+detail);

  if (execute) return executeModal(+execute);
  if (fixdone) {
    const reason = await reasonModal({ title: W.fix_title, label: W.fix_label, required: true, okText: W.fix_btn, note: W.fix_note });
    if (reason !== null) return act(`/defects/${fixdone}/fix-done`, { fix_notes: reason });
    return;
  }
  if (unblock) {
    const reason = await reasonModal({ title: W.unblock_title, label: W.unblock_label, required: true, okText: W.unblock_title });
    if (reason !== null) return act(`/workbench/${unblock}/unblock`, { reason });
    return;
  }
  if (reject) {
    const reason = await reasonModal({ title: W.reject_title, label: W.reject_label, required: true, okText: W.reject_btn, danger: true, note: W.reject_note });
    if (reason !== null) return act(`/workbench/${reject}/reject`, { reason });
    return;
  }
  if (reopen) {
    const reason = await reasonModal({ title: W.reopen_title, label: W.reopen_label, required: true, okText: W.reopen_title, note: W.reopen_note });
    if (reason !== null) return act(`/workbench/${reopen}/reopen`, { reason });
    return;
  }
  if (cancel) {
    const reason = await reasonModal({ title: W.cancel_title, label: W.cancel_label, required: true, okText: W.cancel_btn, danger: true });
    if (reason !== null) return act(`/workbench/${cancel}/cancel`, { reason });
    return;
  }
});

/* ---------- فیلترهای کارتابل ---------- */
const MY_SECTIONS = <?= json_encode($sections ?? [], JSON_UNESCAPED_UNICODE) ?>;
const F_INIT = <?= json_encode($f_init ?? null, JSON_UNESCAPED_UNICODE) ?>;
let wbSel = F_INIT;
if (qs('[data-tsel]')) {
  const tsel = treeSelect(qs('[data-tsel]'), {
    projects: MY_SECTIONS,
    placeholder: W.tsel_ph,
    searchPlaceholder: W.tsel_search,
    allLabel: W.tsel_all,
    onSelect: (s) => { wbSel = s; applyFilters(s); },
  });
  if (F_INIT) tsel.set(F_INIT);
}
function applyFilters(s = wbSel) {
  const p = new URLSearchParams();
  const q = qs('[data-f-q]')?.value.trim();
  if (q) p.set('q', q);
  if (s && s.projectId) p.set('project_id', s.projectId);
  if (s && s.nodeId) p.set('node_id', s.nodeId);
  const st = qs('[data-f-status]')?.value; if (st) p.set('status', st);
  const pr = qs('[data-f-prio]')?.value; if (pr) p.set('priority', pr);
  location.href = '/workbench' + (p.toString() ? '?' + p.toString() : '');
}
let deb; qs('[data-f-q]')?.addEventListener('input', () => { clearTimeout(deb); deb = setTimeout(() => applyFilters(), 450); });
qs('[data-f-status]')?.addEventListener('change', () => applyFilters());
qs('[data-f-prio]')?.addEventListener('change', () => applyFilters());

/* ---------- مودال اجرای تست ---------- */
async function executeModal(id) {
  let d; try { d = await api(`/workbench/${id}/detail`); } catch (e) { toast(e.message, 'err'); return; }
  const c = d.case;
  const steps = (d.steps || []).map((s) => `<li><span class="n">${s.step_order}</span><div>${esc(s.instruction)}${s.expected_result ? `<small>${W.expected}: ${esc(s.expected_result)}</small>` : ''}</div></li>`).join('') || `<li class="text-muted">${W.no_steps}</li>`;
  const m = openModal({
    title: W.exec_title.replace('{code}', esc(c.code)),
    sub: `${esc(c.title)} • ${esc(c.project)} / ${esc(c.node)}`,
    wide: true,
    body: `<div class="two-col">
      <div>
        <div class="section-title">${W.exec_steps}</div>
        <ol class="steps-list">${steps}</ol>
        <div class="section-title mt">${W.exec_pre}</div>
        <p style="font-size:12.5px"><b>${W.pre}:</b> ${esc(c.preconditions || '—')}</p>
        <p style="font-size:12.5px"><b>${W.data}:</b> ${esc(c.test_data || '—')}</p>
        <p style="font-size:12.5px"><b>${W.expected}:</b> ${esc(c.expected_result)}</p>
      </div>
      <div>
        <div class="section-title">${W.exec_result}</div>
        <div class="field"><label>${L('common.status')} <span class="req">*</span></label>
          <div class="btn-row" role="radiogroup">
            <label class="check"><input type="radio" name="res" value="PASS" checked> ${W.res_pass}</label>
            <label class="check"><input type="radio" name="res" value="FAIL"> ${W.res_fail}</label>
            <label class="check"><input type="radio" name="res" value="BLOCKED"> ${W.res_blocked}</label>
          </div></div>
        <div class="field"><label>${W.exec_actual} <span class="req">*</span></label>
          <textarea class="inp" data-actual rows="3"></textarea></div>
        <div data-defect-box class="hide">
          <div class="section-title">${W.defect_box}</div>
          <div class="field"><label>${W.defect_title} <span class="req">*</span></label>
            <input class="inp" data-d-title placeholder="${W.defect_ph}"></div>
          <div class="field"><label>${W.defect_sev}</label><select class="inp" data-d-sev>
            ${Object.keys(window.I18N.pr).map((k) => `<option value="${k}">${window.I18N.pr[k]}</option>`).join('')}</select></div>
          <div class="field"><label>${W.defect_desc}</label><textarea class="inp" data-d-desc rows="2"></textarea></div>
        </div>
      </div>
    </div>`,
    footer: `<button class="btn" data-submit>${W.exec_submit}</button>
             <button class="btn alt" data-close>${L('common.cancel')}</button>`,
  });
  const syncDefect = () => qs('[data-defect-box]', m.root).classList.toggle('hide', qs('input[name="res"]:checked', m.root).value !== 'FAIL');
  qsa('input[name="res"]', m.root).forEach((r) => r.addEventListener('change', syncDefect));
  qs('[data-close]', m.root).addEventListener('click', m.close);
  qs('[data-submit]', m.root).addEventListener('click', async () => {
    const res = qs('input[name="res"]:checked', m.root).value;
    const body = { result: res, actual_result: qs('[data-actual]', m.root).value, notes: '' };
    if (res === 'FAIL') {
      body.defect_title = qs('[data-d-title]', m.root).value;
      body.defect_severity = qs('[data-d-sev]', m.root).value;
      body.defect_description = qs('[data-d-desc]', m.root).value;
    }
    await act(`/workbench/${id}/execute`, body);
  });
}

/* ---------- مودال جزئیات ---------- */
async function showDetail(id) {
  let d; try { d = await api(`/workbench/${id}/detail`); } catch (e) { toast(e.message, 'err'); return; }
  const c = d.case;
  const steps = (d.steps || []).map((s) => `<li><span class="n">${s.step_order}</span><div>${esc(s.instruction)}${s.expected_result ? `<small>${esc(s.expected_result)}</small>` : ''}</div></li>`).join('');
  const execs = (d.executions || []).map((e) => `<li class="${e.result === 'PASS' ? 't-pass' : e.result === 'FAIL' ? 't-fail' : 't-block'}"><span class="dot2"></span>
    <b>${e.execution_no} — ${resPill(e.result)}</b>
    <div class="tl-reason">${esc(e.actual_result || '')} — ${esc(e.tester_name)}</div><time>${faDateTime(e.executed_at)}</time></li>`).join('') || `<li><span class="dot2"></span><span class="text-muted">${W.no_exec}</span></li>`;
  const hist = (d.history || []).map((h) => `<li><span class="dot2"></span><b>${h.from_status ? stPill(h.from_status) + ' → ' : ''}${stPill(h.to_status)}</b>
    <div class="tl-reason">${esc(h.reason || '')} — ${esc(h.actor_name)}</div><time>${faDateTime(h.created_at)}</time></li>`).join('') || `<li><span class="dot2"></span><span class="text-muted">${W.no_event}</span></li>`;
  const defs = (d.defects || []).map((x) => `<tr><td><b>${esc(x.code)}</b></td><td>${esc(x.title)}</td><td>${L('dstatus.' + x.status)}</td></tr>`).join('');
  const m = openModal({
    title: `${esc(c.code)} — ${esc(c.title)}`,
    sub: `${esc(c.project)} / ${esc(c.node)} • ${W.run}: ${esc(c.run)} ${c.run_status !== 'OPEN' ? `(${W.run_closed})` : ''}`,
    wide: true,
    body: `<div class="two-col">
      <div>
        <div class="section-title">${W.detail_status}</div>
        <p>${stPill(c.workflow_status)} ${c.run_status !== 'OPEN' ? `<span class="pill st-cancelled">${W.run_closed}</span>` : ''}</p>
        <div class="section-title">${W.detail_steps}</div>
        <ol class="steps-list">${steps || `<li class="text-muted">${W.no_steps}</li>`}</ol>
        <div class="section-title mt">${W.extra}</div>
        <p style="font-size:12.5px"><b>${W.goal}:</b> ${esc(c.objective || '—')}</p>
        <p style="font-size:12.5px"><b>${W.pre}:</b> ${esc(c.preconditions || '—')}</p>
        <p style="font-size:12.5px"><b>${W.data}:</b> ${esc(c.test_data || '—')}</p>
        <p style="font-size:12.5px"><b>${W.expected}:</b> ${esc(c.expected_result)}</p>
      </div>
      <div>
        <div class="section-title">${W.cycle}</div>
        <ul class="timeline">${hist}</ul>
        <div class="section-title mt">${W.execs}</div>
        <ul class="timeline">${execs}</ul>
        ${defs ? `<div class="section-title mt">${L('gc.defects') || 'Defects'}</div><table class="tbl"><thead><tr><th>${L('common.code')}</th><th>${L('common.title')}</th><th>${L('common.status')}</th></tr></thead><tbody>${defs}</tbody></table>` : ''}
      </div>
    </div>`,
    footer: `<button class="btn alt" data-close>${L('common.close')}</button>`,
  });
  qs('[data-close]', m.root).addEventListener('click', m.close);
}
</script>
<?= $this->endSection() ?>
