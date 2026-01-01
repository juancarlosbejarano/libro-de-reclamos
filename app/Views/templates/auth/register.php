<?php
/** @var callable $t */
/** @var callable $csrf */
$error = $error ?? null;
$base = App\Support\Env::get('PLATFORM_BASE_DOMAIN', 'ldr.arca.digital');
ob_start();
?>
<h1><?= htmlspecialchars($t('auth.register')) ?></h1>

<?php if ($error): ?>
  <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post" action="/register" class="card">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />

  <label><?= htmlspecialchars($t('auth.company')) ?></label>
  <input name="company" maxlength="180" required />

  <label><?= htmlspecialchars($t('auth.subdomain')) ?> (<?= htmlspecialchars((string)$base) ?>)</label>
  <input name="slug" maxlength="64" required placeholder="miempresa" />
  <p class="muted">Se creará: <strong>miempresa.<?= htmlspecialchars((string)$base) ?></strong></p>

  <label><?= htmlspecialchars($t('auth.email')) ?></label>
  <input name="email" type="email" required />

  <label><?= htmlspecialchars($t('auth.password')) ?></label>
  <input name="password" type="password" required minlength="8" />

  <label><?= htmlspecialchars($t('auth.custom_domain')) ?> (opcional)</label>
  <input name="custom_domain" placeholder="tudominio.com" />
  <p class="muted"><?= htmlspecialchars($t('auth.custom_domain_help')) ?></p>

  <div style="margin-top:12px">
    <button class="btn primary" type="submit"><?= htmlspecialchars($t('auth.register')) ?></button>
  </div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';

