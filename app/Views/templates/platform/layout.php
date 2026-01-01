<?php
/** @var callable $t */
/** @var callable $csrf */
$puser = $_SESSION['platform_user'] ?? null;
?>
<!doctype html>
<html lang="<?= htmlspecialchars(App\Services\I18n::locale()) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="/assets/app.css" />
  <title><?= htmlspecialchars($t('platform.title')) ?></title>
</head>
<body>
  <div class="container">
    <div class="nav">
      <a href="/platform" class="btn"><?= htmlspecialchars($t('platform.dashboard')) ?></a>
      <a href="/platform/tenants" class="btn"><?= htmlspecialchars($t('platform.tenants')) ?></a>
      <a href="/platform/jobs" class="btn"><?= htmlspecialchars($t('platform.jobs')) ?></a>
      <a href="/platform/reports" class="btn"><?= htmlspecialchars($t('platform.reports')) ?></a>
      <span class="muted" style="margin-left:auto">
        <?= $puser ? htmlspecialchars((string)$puser['email']) : htmlspecialchars($t('nav.guest')) ?>
      </span>
      <?php if ($puser): ?>
        <form method="post" action="/platform/logout" style="margin:0">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
          <button class="btn danger" type="submit"><?= htmlspecialchars($t('platform.logout')) ?></button>
        </form>
      <?php else: ?>
        <a href="/platform/login" class="btn primary"><?= htmlspecialchars($t('platform.login')) ?></a>
      <?php endif; ?>
    </div>

    <?= $content ?? '' ?>
  </div>
</body>
</html>
