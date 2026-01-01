<?php
/** @var callable $t */
/** @var callable $csrf */
$error = $error ?? null;
$settings = $settings ?? null;
$defaults = $defaults ?? [];

$val = function (string $k, $fallback = '') use ($settings, $defaults) {
  if (is_array($settings) && isset($settings[$k]) && $settings[$k] !== null && $settings[$k] !== '') return (string)$settings[$k];
  if (is_array($defaults) && isset($defaults[$k])) return (string)$defaults[$k];
  return (string)$fallback;
};

ob_start();
?>
<h1><?= htmlspecialchars($t('settings.mail_title')) ?></h1>

<?php if ($error): ?>
  <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<div class="card" style="margin-bottom:12px">
  <p class="muted"><?= htmlspecialchars($t('settings.mail_help')) ?></p>
</div>

<form method="post" action="/settings/mail" class="card">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />

  <label>SMTP Host</label>
  <input name="host" required value="<?= htmlspecialchars($val('host', 'smtp.office365.com')) ?>" />

  <label>SMTP Port</label>
  <input name="port" type="number" required value="<?= htmlspecialchars($val('port', '587')) ?>" />

  <label>SMTP Username</label>
  <input name="username" required value="<?= htmlspecialchars($val('username', '')) ?>" />

  <label>SMTP Password</label>
  <input name="password" type="password" required value="" />
  <p class="muted"><?= htmlspecialchars($t('settings.mail_password_note')) ?></p>

  <label>Encryption</label>
  <select name="encryption">
    <?php $enc = $val('encryption', 'tls'); ?>
    <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>tls</option>
    <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>ssl</option>
    <option value="none" <?= $enc === 'none' ? 'selected' : '' ?>>none</option>
  </select>

  <label>From email</label>
  <input name="from_email" required value="<?= htmlspecialchars($val('from_email', $val('username', ''))) ?>" />

  <label>From name</label>
  <input name="from_name" value="<?= htmlspecialchars($val('from_name', 'Libro de Reclamaciones')) ?>" />

  <div style="margin-top:12px">
    <button class="btn primary" type="submit"><?= htmlspecialchars($t('settings.save')) ?></button>
  </div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
