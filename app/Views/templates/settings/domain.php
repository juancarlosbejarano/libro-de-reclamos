<?php
/** @var callable $t */
/** @var callable $csrf */
$error = $error ?? null;
$domains = $domains ?? [];
ob_start();
?>
<h1><?= htmlspecialchars($t('settings.domain_title')) ?></h1>

<?php if ($error): ?>
  <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<div class="card" style="margin-bottom:12px">
  <p class="muted"><?= htmlspecialchars($t('settings.domain_help')) ?></p>
</div>

<form method="post" action="/settings/domain" class="card">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
  <label><?= htmlspecialchars($t('settings.domain_label')) ?></label>
  <input name="domain" placeholder="tudominio.com" required />
  <label>
    <input type="checkbox" name="is_primary" value="1" />
    <?= htmlspecialchars($t('settings.domain_primary')) ?>
  </label>
  <div style="margin-top:12px">
    <button class="btn primary" type="submit"><?= htmlspecialchars($t('settings.domain_add')) ?></button>
  </div>
</form>

<h2 style="margin-top:16px"><?= htmlspecialchars($t('settings.domain_list')) ?></h2>
<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th><?= htmlspecialchars($t('settings.domain')) ?></th>
        <th><?= htmlspecialchars($t('settings.domain_kind')) ?></th>
        <th><?= htmlspecialchars($t('settings.domain_primary_col')) ?></th>
        <th><?= htmlspecialchars($t('settings.domain_verified')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($domains as $d): ?>
        <tr>
          <td><?= htmlspecialchars((string)$d['domain']) ?></td>
          <td><?= htmlspecialchars((string)$d['kind']) ?></td>
          <td><?= ((int)$d['is_primary'] === 1) ? 'yes' : 'no' ?></td>
          <td><?= $d['verified_at'] ? htmlspecialchars((string)$d['verified_at']) : 'pending' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$domains): ?>
        <tr><td colspan="4" class="muted"><?= htmlspecialchars($t('settings.domain_none')) ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
