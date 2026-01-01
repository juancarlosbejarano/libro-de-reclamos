<?php
/** @var callable $t */
/** @var callable $csrf */

$error = $error ?? null;
$form = is_array($form ?? null) ? $form : [];

ob_start();
?>

<h1><?= htmlspecialchars($t('platform.tenant_create')) ?></h1>

<?php if ($error): ?>
  <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post" action="/platform/tenants/create" class="card">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />

  <label><?= htmlspecialchars($t('platform.id_type')) ?></label>
  <select name="id_type">
    <option value="ruc" <?= (($form['id_type'] ?? '') === 'ruc') ? 'selected' : '' ?>>RUC</option>
    <option value="dni" <?= (($form['id_type'] ?? '') === 'dni') ? 'selected' : '' ?>>DNI</option>
  </select>

  <label><?= htmlspecialchars($t('platform.id_number')) ?></label>
  <input name="id_number" value="<?= htmlspecialchars((string)($form['id_number'] ?? '')) ?>" placeholder="20558318318" />

  <div style="margin-top:12px">
    <button class="btn" type="submit" name="mode" value="lookup"><?= htmlspecialchars($t('platform.lookup')) ?></button>
  </div>

  <hr style="margin:16px 0" />

  <label><?= htmlspecialchars($t('auth.company')) ?></label>
  <input name="name" maxlength="180" required value="<?= htmlspecialchars((string)($form['name'] ?? '')) ?>" />

  <label><?= htmlspecialchars($t('auth.subdomain')) ?></label>
  <input name="slug" maxlength="64" required value="<?= htmlspecialchars((string)($form['slug'] ?? '')) ?>" placeholder="miempresa" />

  <div style="margin-top:12px">
    <button class="btn primary" type="submit" name="mode" value="create"><?= htmlspecialchars($t('platform.create')) ?></button>
  </div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
