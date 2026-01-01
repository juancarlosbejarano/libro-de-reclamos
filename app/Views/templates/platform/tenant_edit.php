<?php
/** @var callable $t */
/** @var callable $csrf */
$tenant = is_array($tenant ?? null) ? $tenant : null;
$error = $error ?? null;
$saved = $saved ?? null;

ob_start();
?>

<h1><?= htmlspecialchars($t('platform.edit')) ?></h1>

<?php if (!$tenant): ?>
  <p class="error">Tenant no encontrado</p>
<?php else: ?>
  <?php if ($saved): ?>
    <p class="muted"><?= htmlspecialchars($t('platform.saved')) ?></p>
  <?php endif; ?>

  <?php if ($error): ?>
    <p class="error"><?= htmlspecialchars((string)$error) ?></p>
  <?php endif; ?>

  <div class="card">
    <p class="muted"><strong>ID:</strong> <?= (int)$tenant['id'] ?> · <strong>Slug:</strong> <?= htmlspecialchars((string)$tenant['slug']) ?></p>
    <p>
      <strong><?= htmlspecialchars($t('platform.status')) ?>:</strong>
      <?= ((string)($tenant['status'] ?? 'active') === 'suspended') ? htmlspecialchars($t('platform.status_suspended')) : htmlspecialchars($t('platform.status_active')) ?>
    </p>

    <form method="post" action="/platform/tenants/<?= (int)$tenant['id'] ?>/edit" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />

      <label><?= htmlspecialchars($t('auth.subdomain')) ?></label>
      <input name="slug" maxlength="32" value="<?= htmlspecialchars((string)($tenant['slug'] ?? '')) ?>" placeholder="miempresa" />
      <?php if (!empty($_ENV['PLATFORM_BASE_DOMAIN'])): ?>
        <p class="muted" style="margin-top:6px">
          Dominio: <span style="font-family: monospace"><?= htmlspecialchars((string)($tenant['slug'] ?? '')) ?>.<?= htmlspecialchars((string)$_ENV['PLATFORM_BASE_DOMAIN']) ?></span>
        </p>
      <?php endif; ?>

      <label><?= htmlspecialchars($t('auth.company')) ?></label>
      <input name="name" maxlength="180" required value="<?= htmlspecialchars((string)($tenant['name'] ?? '')) ?>" />

      <label><?= htmlspecialchars($t('platform.address_full')) ?></label>
      <input name="address_full" maxlength="255" value="<?= htmlspecialchars((string)($tenant['address_full'] ?? '')) ?>" />

      <label><?= htmlspecialchars($t('platform.logo')) ?> (PNG/JPG/WebP)</label>
      <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" />
      <?php if (!empty($tenant['logo_path'])): ?>
        <p class="muted" style="margin-top:8px">
          <img src="<?= htmlspecialchars((string)$tenant['logo_path']) ?>" alt="logo" style="max-height:64px" />
        </p>
      <?php endif; ?>

      <div style="margin-top:12px">
        <button class="btn primary" type="submit"><?= htmlspecialchars($t('platform.save')) ?></button>
        <a class="btn" href="/platform/tenants"><?= htmlspecialchars($t('platform.tenants')) ?></a>
      </div>
    </form>

    <hr style="margin:16px 0" />

    <?php if (((string)($tenant['status'] ?? 'active')) === 'suspended'): ?>
      <form method="post" action="/platform/tenants/<?= (int)$tenant['id'] ?>/reactivate" style="margin:0">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
        <button class="btn" type="submit"><?= htmlspecialchars($t('platform.reactivate')) ?></button>
      </form>
    <?php else: ?>
      <form method="post" action="/platform/tenants/<?= (int)$tenant['id'] ?>/suspend" style="margin:0">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
        <button class="btn danger" type="submit"><?= htmlspecialchars($t('platform.suspend')) ?></button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
