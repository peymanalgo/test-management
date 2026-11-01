<?php $title = t('users.title'); $active = 'users';
$roles = db_connect()->table('roles')->orderBy('id')->get()->getResultArray();
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= t('nav.group.system') ?></div>
    <h1><?= t('users.title') ?></h1>
    <p><?= t('users.desc') ?></p>
  </div>
  <button class="btn" onclick='userModal()'><?= t('users.new') ?></button>
</div>

<div class="filterbar">
  <div class="frow">
    <div class="search"><span class="sic">🔎</span><input class="inp" data-q placeholder="<?= t('users.search_ph') ?>"></div>
    <select class="inp" data-role>
      <option value=""><?= t('users.all_roles') ?></option>
      <?php foreach ($roles as $r): ?><option value="<?= esc($r['name']) ?>"><?= esc($r['name']) ?></option><?php endforeach; ?>
      <option value="<?= t('role.admin') ?>"><?= t('role.admin') ?></option>
    </select>
    <select class="inp" data-active>
      <option value=""><?= t('users.all') ?></option><option value="1"><?= t('users.active') ?></option><option value="0"><?= t('users.inactive') ?></option>
    </select>
  </div>
</div>
<div data-grid></div>

<script>
window.I18N.users = {
  new: <?= json_encode(t('users.create_title')) ?>,
  edit_tpl: <?= json_encode(t('users.edit_title')) ?>,
  sub: <?= json_encode(t('users.sub')) ?>,
  username: <?= json_encode(t('users.f.username')) ?>,
  display: <?= json_encode(t('users.f.display')) ?>,
  email: <?= json_encode(t('users.f.email')) ?>,
  role: <?= json_encode(t('users.f.role')) ?>,
  no_role: <?= json_encode(t('users.f.no_role')) ?>,
  password: <?= json_encode(t('users.f.password')) ?>,
  password_edit: <?= json_encode(t('users.f.password_edit')) ?>,
  admin: <?= json_encode(t('users.f.admin')) ?>,
  create_btn: <?= json_encode(t('users.create_btn')) ?>,
  save_btn: <?= json_encode(t('proj.edit_btn')) ?>,
  active: <?= json_encode(t('users.active')) ?>,
  inactive: <?= json_encode(t('users.inactive')) ?>,
  deactivate: <?= json_encode(t('users.deactivate') ) ?>,
  activate: <?= json_encode(t('users.activate')) ?>,
  admin_lbl: <?= json_encode(t('role.admin')) ?>,
  never: <?= json_encode(t('users.never')) ?>,
};
const U = window.I18N.users;
const ROLES = <?= json_encode(array_column($roles, 'name', 'id'), JSON_UNESCAPED_UNICODE) ?>;

const grid = createGrid({
  container: qs('[data-grid]'),
  endpoint: '/admin/users/data',
  filterState: () => ({ q: qs('[data-q]').value, role: qs('[data-role]').value, active: qs('[data-active]').value }),
  columns: [
    { key: 'username', label: L('common.code'), render: (r) => `<b style="direction:ltr;display:inline-block">${esc(r.username)}</b>` },
    { key: 'display_name', label: U.display, render: (r) => `${esc(r.display_name)} ${r.is_admin == 1 ? `<span class="pill st-approval">${U.admin_lbl}</span>` : ''}` },
    { key: 'roles', label: U.role, render: (r) => r.roles ? `<span class="pill st-ready">${esc(r.roles)}</span>` : '—' },
    { key: 'last_login_at', label: <?= json_encode(t('users.col.last_login')) ?>, render: (r) => `<span class="num">${r.last_login_at ? faDateTime(r.last_login_at) : U.never}</span>` },
    { key: 'is_active', label: L('common.status'), render: (r) => r.is_active == 1 ? `<span class="pill st-approved">${U.active}</span>` : `<span class="pill st-cancelled">${U.inactive}</span>` },
    { label: L('common.actions'), render: (r) => `<span style="white-space:nowrap">
      <button class="btn xs alt" data-edit='${esc(json_encode(r, JSON_UNESCAPED_UNICODE))}'>${L('common.edit')}</button>
      <button class="btn xs ${r.is_active == 1 ? 'warn' : 'success'}" data-toggle="${r.id}">${r.is_active == 1 ? U.deactivate : U.activate}</button>
    </span>` },
  ],
});
grid.load();
let deb; qs('[data-q]').addEventListener('input', () => { clearTimeout(deb); deb = setTimeout(() => grid.reload(), 350); });
qs('[data-role]').addEventListener('change', () => grid.reload());
qs('[data-active]').addEventListener('change', () => grid.reload());
qs('[data-grid]').addEventListener('click', async (e) => {
  const ed = e.target.closest('[data-edit]');
  if (ed) { let row = {}; try { row = JSON.parse(ed.dataset.edit); } catch {} return userModal(row); }
  const tg = e.target.closest('[data-toggle]');
  if (tg) {
    try { const r = await api(`/admin/users/${tg.dataset.toggle}/toggle`, { method: 'POST' }); toast(r.message); grid.load(); }
    catch (err) { toast(err.message, 'err'); }
  }
}, true);

function userModal(row) {
  const isEdit = !!row && !!row.id;
  const roleId = isEdit ? (Object.keys(ROLES).find((k) => ROLES[k] === row.roles) || '') : '';
  const m = openModal({
    title: isEdit ? U.edit_tpl.replace('{name}', esc(row.display_name)) : U.new,
    sub: U.sub,
    body: `<div class="form-grid">
      <div class="field"><label>${U.username} <span class="req">*</span></label>
        <input class="inp" data-u-username style="direction:ltr;text-align:left" value="${isEdit ? esc(row.username) : ''}"></div>
      <div class="field"><label>${U.display} <span class="req">*</span></label>
        <input class="inp" data-u-name value="${isEdit ? esc(row.display_name) : ''}"></div>
      <div class="field"><label>${U.email}</label>
        <input class="inp" data-u-email style="direction:ltr;text-align:left" value="${isEdit ? esc(row.email || '') : ''}"></div>
      <div class="field"><label>${U.role}</label><select class="inp" data-u-role>
        <option value="">${U.no_role}</option>
        ${Object.entries(ROLES).map(([rid, name]) => `<option value="${rid}" ${roleId == rid ? 'selected' : ''}>${esc(name)}</option>`).join('')}
      </select></div>
      <div class="field full"><label>${U.password} ${isEdit ? `<small class="text-muted">${U.password_edit}</small>` : '<span class="req">*</span>'}</label>
        <input class="inp" type="password" data-u-pass style="direction:ltr;text-align:left"></div>
      <div class="field full"><label class="check"><input type="checkbox" data-u-admin ${isEdit && row.is_admin == 1 ? 'checked' : ''}> ${U.admin}</label></div>
    </div>`,
    footer: `<button class="btn" data-save>${isEdit ? U.save_btn : U.create_btn}</button><button class="btn alt" data-close>${L('common.cancel')}</button>`,
  });
  qs('[data-close]', m.root).addEventListener('click', m.close);
  qs('[data-save]', m.root).addEventListener('click', async () => {
    const fd = new FormData();
    if (isEdit) fd.append('id', row.id);
    fd.append('username', qs('[data-u-username]', m.root).value);
    fd.append('display_name', qs('[data-u-name]', m.root).value);
    fd.append('email', qs('[data-u-email]', m.root).value);
    fd.append('role_id', qs('[data-u-role]', m.root).value);
    fd.append('password', qs('[data-u-pass]', m.root).value);
    fd.append('is_admin', qs('[data-u-admin]', m.root).checked ? '1' : '');
    try { const r = await api('/admin/users/save', { method: 'POST', body: fd }); toast(r.message); m.close(); grid.load(); }
    catch (err) { toast(err.message, 'err'); }
  });
}
</script>
<?= $this->endSection() ?>
