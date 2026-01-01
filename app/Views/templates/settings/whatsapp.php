<?php
/** @var callable $t */
/** @var callable $csrf */
/** @var array|null $settings */
/** @var string|null $error */

$settings = $settings ?? null;
$error = $error ?? null;

$enabled = $settings ? ((int)($settings['enabled'] ?? 0) === 1) : false;
$baseUrl = $settings ? (string)($settings['chatwoot_base_url'] ?? 'https://portalchat.arca.digital') : 'https://portalchat.arca.digital';
$accountId = $settings ? (int)($settings['account_id'] ?? 0) : 0;
$inboxId = $settings ? (int)($settings['inbox_id'] ?? 0) : 0;

ob_start();
?>
<h1><?= htmlspecialchars($t('settings.whatsapp_title')) ?></h1>

<?php if ($error): ?>
  <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<p class="muted"><?= htmlspecialchars($t('settings.whatsapp_help')) ?></p>

<form method="post" action="/settings/whatsapp" class="card">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />

  <label style="display:flex;gap:8px;align-items:center">
    <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?> />
    <span><?= htmlspecialchars($t('settings.whatsapp_enabled')) ?></span>
  </label>

  <label><?= htmlspecialchars($t('settings.whatsapp_base_url')) ?></label>
  <input type="text" name="chatwoot_base_url" value="<?= htmlspecialchars($baseUrl) ?>" placeholder="https://portalchat.arca.digital" />

  <label><?= htmlspecialchars($t('settings.whatsapp_account_id')) ?></label>
  <input type="number" name="account_id" min="1" value="<?= htmlspecialchars((string)$accountId) ?>" />

  <label><?= htmlspecialchars($t('settings.whatsapp_inbox_id')) ?></label>
  <input type="number" name="inbox_id" min="1" value="<?= htmlspecialchars((string)$inboxId) ?>" />

  <label><?= htmlspecialchars($t('settings.whatsapp_api_token')) ?></label>
  <input type="password" name="api_token" value="" placeholder="(no se muestra)" />

  <div style="margin-top:12px">
    <button class="btn primary" type="submit"><?= htmlspecialchars($t('settings.save')) ?></button>
  </div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
