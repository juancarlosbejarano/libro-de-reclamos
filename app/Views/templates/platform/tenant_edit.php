<?php
/** @var callable $t */
/** @var callable $csrf */
$tenant = is_array($tenant ?? null) ? $tenant : null;
$error = $error ?? null;
$saved = $saved ?? null;
$domains = is_array($domains ?? null) ? $domains : [];
$editDomain = is_array($editDomain ?? null) ? $editDomain : null;

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
        <p class="muted" style="margin-top:6px">
          Para usar un dominio propio, crea un registro A a <strong>207.58.173.84</strong> o un CNAME a <strong><?= htmlspecialchars((string)($tenant['slug'] ?? '')) ?>.<?= htmlspecialchars((string)$_ENV['PLATFORM_BASE_DOMAIN']) ?></strong>.
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

    <h2 style="margin:0 0 8px"><?= htmlspecialchars($t('platform.domains')) ?></h2>
    <div class="card" style="margin-bottom:12px">
      <p class="muted"><?= htmlspecialchars($t('settings.domain_help')) ?></p>
    </div>

    <?php if ($editDomain): ?>
      <form method="post" action="/platform/tenants/<?= (int)$tenant['id'] ?>/domains/<?= (int)$editDomain['id'] ?>/update" class="card" style="margin-bottom:12px">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
        <label><?= htmlspecialchars($t('settings.domain_label')) ?> (editar)</label>
        <input name="domain" placeholder="tudominio.com" required value="<?= htmlspecialchars((string)($editDomain['domain'] ?? '')) ?>" />
        <label>
          <input type="checkbox" name="is_primary" value="1" <?= ((int)($editDomain['is_primary'] ?? 0) === 1) ? 'checked' : '' ?> />
          <?= htmlspecialchars($t('settings.domain_primary')) ?>
        </label>
        <div style="margin-top:12px">
          <button class="btn primary" type="submit">Actualizar dominio</button>
          <a class="btn" href="/platform/tenants/<?= (int)$tenant['id'] ?>/edit">Cancelar</a>
        </div>
      </form>
    <?php else: ?>
      <form method="post" action="/platform/tenants/<?= (int)$tenant['id'] ?>/domains/add" class="card" style="margin-bottom:12px">
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
    <?php endif; ?>

    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th><?= htmlspecialchars($t('settings.domain')) ?></th>
            <th><?= htmlspecialchars($t('settings.domain_kind')) ?></th>
            <th><?= htmlspecialchars($t('settings.domain_primary_col')) ?></th>
            <th><?= htmlspecialchars($t('settings.domain_verified')) ?></th>
            <th><?= htmlspecialchars($t('platform.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($domains as $d): ?>
            <tr>
              <td><?= htmlspecialchars((string)$d['domain']) ?></td>
              <td><?= htmlspecialchars((string)$d['kind']) ?></td>
              <td><?= ((int)$d['is_primary'] === 1) ? 'yes' : 'no' ?></td>
              <td><?= $d['verified_at'] ? htmlspecialchars((string)$d['verified_at']) : 'pending' ?></td>
              <td>
                <?php if ((int)($d['is_primary'] ?? 0) !== 1): ?>
                  <form method="post" action="/platform/tenants/<?= (int)$tenant['id'] ?>/domains/<?= (int)$d['id'] ?>/primary" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
                    <button class="btn" type="submit">Hacer principal</button>
                  </form>
                <?php endif; ?>

                <?php if (((string)($d['kind'] ?? '')) === 'custom' && empty($d['verified_at'])): ?>
                  <form method="post" action="/platform/tenants/<?= (int)$tenant['id'] ?>/domains/<?= (int)$d['id'] ?>/verify" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
                    <button class="btn" type="submit">Verificar</button>
                  </form>
                <?php endif; ?>

                <?php if (((string)($d['kind'] ?? '')) === 'custom'): ?>
                  <a class="btn" href="/platform/tenants/<?= (int)$tenant['id'] ?>/edit?edit_domain=<?= (int)$d['id'] ?>">Editar</a>
                  <form method="post" action="/platform/tenants/<?= (int)$tenant['id'] ?>/domains/<?= (int)$d['id'] ?>/delete" style="display:inline" onsubmit="return confirm('¿Eliminar dominio?');">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
                    <button class="btn danger" type="submit">Eliminar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$domains): ?>
            <tr><td colspan="5" class="muted"><?= htmlspecialchars($t('settings.domain_none')) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

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
