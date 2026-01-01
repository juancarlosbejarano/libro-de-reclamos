<?php
/** @var callable $t */
/** @var callable $csrf */
$error = $error ?? null;
ob_start();
?>
<h1><?= htmlspecialchars($t('auth.login')) ?></h1>

<?php if ($error): ?>
  <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post" action="/login" class="card">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
  <label><?= htmlspecialchars($t('auth.email')) ?></label>
  <input name="email" type="email" required />
  <label><?= htmlspecialchars($t('auth.password')) ?></label>
  <input name="password" type="password" required />
  <div style="margin-top:12px">
    <button class="btn primary" type="submit"><?= htmlspecialchars($t('auth.login')) ?></button>
  </div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
