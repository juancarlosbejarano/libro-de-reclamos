<?php
/** @var callable $t */
$stats = $stats ?? [];
$cron = $cron ?? null;
ob_start();
?>

<h1><?= htmlspecialchars($t('platform.dashboard')) ?></h1>

<div class="card">
  <p><strong><?= htmlspecialchars($t('platform.stats')) ?></strong></p>
  <ul>
    <li><?= htmlspecialchars($t('platform.tenants')) ?>: <?= (int)($stats['tenants'] ?? 0) ?></li>
    <li><?= htmlspecialchars($t('platform.complaints')) ?>: <?= (int)($stats['complaints'] ?? 0) ?> (open <?= (int)($stats['open'] ?? 0) ?>, in_progress <?= (int)($stats['in_progress'] ?? 0) ?>, closed <?= (int)($stats['closed'] ?? 0) ?>)</li>
    <li><?= htmlspecialchars($t('platform.jobs')) ?>: pending <?= (int)($stats['jobs_pending'] ?? 0) ?>, failed <?= (int)($stats['jobs_failed'] ?? 0) ?></li>
  </ul>
</div>

<div class="card" style="margin-top:12px">
  <p><strong><?= htmlspecialchars($t('platform.system_status')) ?></strong></p>
  <p class="muted">
    <?= htmlspecialchars($t('platform.cron_last_run')) ?>:
    <?= $cron && is_array($cron) ? htmlspecialchars((string)($cron['updated_at'] ?? '')) : '—' ?>
  </p>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
