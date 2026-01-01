<?php
/** @var callable $t */
/** @var callable $csrf */
$configured = (bool)($configured ?? false);
$saved = $saved ?? null;
$error = $error ?? null;

ob_start();
?>

<h1><?= htmlspecialchars($t('platform.arca_settings')) ?></h1>

<?php if ($saved): ?>
  <p class="muted"><?= htmlspecialchars($t('platform.saved')) ?></p>
<?php endif; ?>

<?php if ($error): ?>
  <p class="error"><?= htmlspecialchars($t('platform.error')) ?>: <?= htmlspecialchars((string)$error) ?></p>
<?php endif; ?>

<div class="card">
  <p class="muted"><?= htmlspecialchars($t('platform.arca_settings_help')) ?></p>
  <p>
    <strong><?= htmlspecialchars($t('platform.status')) ?>:</strong>
    <?= $configured ? htmlspecialchars($t('platform.configured')) : htmlspecialchars($t('platform.not_configured')) ?>
  </p>

  <form method="post" action="/platform/settings/arca">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />

    <label><?= htmlspecialchars($t('platform.api_token')) ?></label>
    <input type="password" name="api_token" required autocomplete="new-password" />

    <div style="margin-top:12px">
      <button class="btn primary" type="submit"><?= htmlspecialchars($t('settings.save')) ?></button>
    </div>
  </form>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
