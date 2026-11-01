<?php $title = t('proj.title'); $active = 'projects'; ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= t('nav.group.test') ?></div>
    <h1><?= t('proj.title') ?></h1>
    <p><?= t('proj.desc') ?></p>
  </div>
  <button class="btn" onclick="newProject()"><?= t('proj.create') ?></button>
</div>

<?php if (!$projects): ?>
  <div class="card"><div class="tbl-empty">
    <span class="big">📁</span>
    <h3><?= t('dash.no_project') ?></h3>
    <p><?= t('dash.no_project_sub') ?></p>
    <button class="btn" onclick="newProject()"><?= t('proj.create') ?></button>
  </div></div>
<?php else: ?>
  <div class="grid-auto">
    <?php foreach ($projects as $p): ?>
    <div class="proj-card">
      <div class="pc-top">
        <span class="code"><?= esc($p['code']) ?></span>
        <span style="color:#ffffffa8;font-size:11px;margin-inline-start:auto"><?= t('proj.built') ?>: <?= fa_d($p['created_at']) ?></span>
        <button class="pc-edit" data-edit-project='<?= esc(json_encode(['id' => (int) $p['id'], 'code' => $p['code'], 'name' => $p['name'], 'description' => $p['description'], 'target_url' => $p['target_url']], JSON_UNESCAPED_UNICODE)) ?>' title="<?= t('proj.edit') ?>">✏️</button>
      </div>
      <div class="pc-b" onclick="location.href='/projects/<?= $p['id'] ?>/tree'">
        <h3><?= esc($p['name']) ?></h3>
        <?php if ($p['description']): ?><p style="font-size:12px;line-height:1.9"><?= esc(mb_substr($p['description'], 0, 120)) ?><?= mb_strlen($p['description']) > 120 ? '…' : '' ?></p><?php endif; ?>
        <div class="proj-stats">
          <div><b><?= (int) $p['node_count'] ?></b><small><?= t('dash.sections') ?></small></div>
          <div><b><?= (int) $p['case_count'] ?></b><small><?= t('dash.cases') ?></small></div>
          <div><b style="color:var(--primary)">↗</b><small><?= t('dash.enter_tree') ?></small></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
window.I18N.proj = {
  create: <?= json_encode(t('proj.create')) ?>,
  create_sub: <?= json_encode(t('proj.create_sub')) ?>,
  code: <?= json_encode(t('proj.code')) ?>,
  code_hint: <?= json_encode(t('proj.code_hint')) ?>,
  name: <?= json_encode(t('proj.name')) ?>,
  url: <?= json_encode(t('proj.target_url')) ?>,
  desc: <?= json_encode(t('proj.description')) ?>,
  desc_ph: <?= json_encode(t('proj.description_ph')) ?>,
  create_btn: <?= json_encode(t('proj.create_btn')) ?>,
  edit: <?= json_encode(t('proj.edit')) ?>,
  name_lbl: <?= json_encode(t('proj.name')) ?>,
  save_btn: <?= json_encode(t('proj.edit_btn')) ?>,
  required: ' *',
};

qsa('[data-edit-project]').forEach((b) => b.addEventListener('click', (e) => {
  e.stopPropagation();
  const p = JSON.parse(b.dataset.editProject);
  const m = openModal({
    title: `${L('proj.edit')} «${esc(p.name)}»`,
    narrow: true,
    body: `<div class="field"><label>${L('proj.name_lbl')} <span class="req">*</span></label>
        <input class="inp" data-p-name value="${esc(p.name)}"></div>
      <div class="field"><label>${L('proj.code')} <span class="req">*</span></label>
        <input class="inp input-ltr" data-p-code value="${esc(p.code)}" style="direction:ltr;text-align:left">
        <div class="hint">${L('proj.code_hint')}</div></div>
      <div class="field"><label>${L('proj.url')}</label>
        <input class="inp" data-p-url value="${esc(p.target_url || '')}" placeholder="https://..." style="direction:ltr;text-align:left"></div>
      <div class="field"><label>${L('proj.desc')}</label>
        <textarea class="inp" data-p-desc rows="3">${esc(p.description || '')}</textarea></div>`,
    footer: `<button class="btn" data-save>${L('proj.save_btn')}</button>
             <button class="btn alt" data-close>${L('common.cancel')}</button>`,
  });
  qs('[data-close]', m.root).addEventListener('click', m.close);
  qs('[data-save]', m.root).addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('name', qs('[data-p-name]', m.root).value);
    fd.append('code', qs('[data-p-code]', m.root).value);
    fd.append('target_url', qs('[data-p-url]', m.root).value);
    fd.append('description', qs('[data-p-desc]', m.root).value);
    try {
      const r = await api(`/projects/${p.id}/update`, { method: 'POST', body: fd });
      toast(r.message);
      setTimeout(() => location.reload(), 450);
    } catch (err) { toast(err.message, 'err'); }
  });
}));

function newProject() {
  const m = openModal({
    title: L('proj.create'), sub: L('proj.create_sub'),
    body: `<form method="post" action="/projects" id="proj-form">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field"><label>${L('proj.code')} <span class="req">*</span></label>
          <input class="inp" name="code" required maxlength="50" placeholder="SHOP" style="direction:ltr;text-align:left">
          <div class="hint">${L('proj.code_hint')}</div></div>
        <div class="field"><label>${L('proj.name')} <span class="req">*</span></label>
          <input class="inp" name="name" required maxlength="180"></div>
        <div class="field full"><label>${L('proj.url')}</label>
          <input class="inp" name="target_url" placeholder="https://..." style="direction:ltr;text-align:left"></div>
        <div class="field full"><label>${L('proj.desc')}</label>
          <textarea class="inp" name="description" rows="3" placeholder="${L('proj.desc_ph')}"></textarea></div>
      </div></form>`,
    footer: `<button class="btn" type="submit" form="proj-form">${L('proj.create_btn')}</button>
             <button class="btn alt" data-close>${L('common.cancel')}</button>`,
  });
  qs('[data-close]', m.root).addEventListener('click', m.close);
}
</script>
<?= $this->endSection() ?>
