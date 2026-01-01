<?php
/** @var callable $t */
/** @var callable $csrf */
$currentUser = $_SESSION['user'] ?? null;
?>
<!doctype html>
<html lang="<?= htmlspecialchars(App\Services\I18n::locale()) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="manifest" href="/manifest.webmanifest" />
  <link rel="stylesheet" href="/assets/app.css" />
  <title><?= htmlspecialchars($t('app.title')) ?></title>
</head>
<body>
  <div class="container">
    <div class="nav">
      <a href="/" class="btn"><?= htmlspecialchars($t('nav.home')) ?></a>
      <a href="/complaints" class="btn"><?= htmlspecialchars($t('nav.complaints')) ?></a>
      <?php if ($currentUser && (($currentUser['role'] ?? '') === 'user')): ?>
        <a href="/my/complaints" class="btn"><?= htmlspecialchars($t('nav.my_complaints')) ?></a>
      <?php endif; ?>
      <?php if ($currentUser && (($currentUser['role'] ?? '') === 'admin')): ?>
        <a href="/settings/domain" class="btn"><?= htmlspecialchars($t('nav.settings')) ?></a>
        <a href="/settings/mail" class="btn"><?= htmlspecialchars($t('nav.mail')) ?></a>
        <a href="/settings/whatsapp" class="btn"><?= htmlspecialchars($t('nav.whatsapp')) ?></a>
        <a href="/settings/users" class="btn"><?= htmlspecialchars($t('nav.users')) ?></a>
      <?php endif; ?>
      <a href="?lang=es" class="btn">ES</a>
      <a href="?lang=en" class="btn">EN</a>
      <span class="muted" style="margin-left:auto">
        <?= $currentUser ? htmlspecialchars($currentUser['email']) : htmlspecialchars($t('nav.guest')) ?>
      </span>
      <?php if ($currentUser): ?>
        <form method="post" action="/logout" style="margin:0">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
          <button class="btn danger" type="submit"><?= htmlspecialchars($t('nav.logout')) ?></button>
        </form>
      <?php else: ?>
        <a href="/login" class="btn primary"><?= htmlspecialchars($t('nav.login')) ?></a>
        <a href="/register" class="btn"><?= htmlspecialchars($t('nav.register')) ?></a>
      <?php endif; ?>
    </div>

    <?= $content ?? '' ?>
  </div>

  <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').catch(() => undefined);
    }
  </script>
</body>
</html>

