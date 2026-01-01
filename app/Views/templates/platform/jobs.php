<?php
/** @var callable $t */
$puser = $_SESSION['platform_user'] ?? null;
$jobs = $jobs ?? [];
$stats = $stats ?? ['pending' => 0, 'failed' => 0, 'success' => 0];
$cron = $cron ?? null;
$plesk = $plesk ?? ['auto' => false, 'site' => '', 'url' => '', 'tls' => true, 'has_key' => false];
ob_start();
?>

<h1><?= htmlspecialchars($t('platform.jobs')) ?></h1>

<div class="row" style="margin-bottom:12px">
  <div class="card" style="flex:1; min-width:280px">
    <p><strong>Resumen</strong></p>
    <ul>
      <li>pending: <?= (int)($stats['pending'] ?? 0) ?></li>
      <li>failed: <?= (int)($stats['failed'] ?? 0) ?></li>
      <li>success: <?= (int)($stats['success'] ?? 0) ?></li>
    </ul>
  </div>
  <div class="card" style="flex:2; min-width:360px">
    <p><strong>Plesk</strong></p>
    <p class="muted" style="margin:0">
      Auto provision: <strong><?= !empty($plesk['auto']) ? 'ON' : 'OFF' ?></strong>
      &nbsp;|&nbsp; TLS verify: <strong><?= !empty($plesk['tls']) ? 'ON' : 'OFF' ?></strong>
      &nbsp;|&nbsp; API key: <strong><?= !empty($plesk['has_key']) ? 'OK' : 'MISSING' ?></strong>
    </p>
    <p class="muted" style="margin:6px 0 0 0">
      Site: <strong><?= htmlspecialchars((string)($plesk['site'] ?? '')) ?></strong>
      &nbsp;|&nbsp; URL: <strong><?= htmlspecialchars((string)($plesk['url'] ?? '')) ?></strong>
    </p>
    <p class="muted" style="margin:6px 0 0 0">
      <?= htmlspecialchars($t('platform.cron_last_run')) ?>:
      <strong><?= $cron && is_array($cron) ? htmlspecialchars((string)($cron['updated_at'] ?? '')) : '—' ?></strong>
      <?= $cron && is_array($cron) ? '<span class="muted">(' . htmlspecialchars((string)($cron['v'] ?? ''), ENT_QUOTES, 'UTF-8') . ')</span>' : '' ?>
    </p>

    <?php if ($puser && (($puser['role'] ?? '') === 'owner')): ?>
      <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap">
        <a class="btn" href="/scripts/plesk_ping.php" target="_blank" rel="noopener">Ping</a>
        <?php $site = (string)($plesk['site'] ?? ''); ?>
        <a class="btn" href="/scripts/plesk_provision.php<?= $site !== '' ? ('?site=' . urlencode($site)) : '' ?>" target="_blank" rel="noopener">Procesar ahora</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Tenant</th>
        <th>Domain</th>
        <th>Action</th>
        <th>Status</th>
        <th>Attempts</th>
        <th>Error</th>
        <th><?= htmlspecialchars($t('complaints.created')) ?></th>
        <th>Processed</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($jobs as $j): ?>
        <tr>
          <td><?= (int)($j['id'] ?? 0) ?></td>
          <td>
            <?php $tid = (int)($j['tenant_id'] ?? 0); ?>
            <?php if ($tid > 0): ?>
              <a href="/platform/tenants/<?= $tid ?>/edit"><?= $tid ?></a>
            <?php else: ?>
              0
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars((string)($j['domain'] ?? '')) ?></td>
          <td class="muted"><?= htmlspecialchars((string)($j['action'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($j['status'] ?? '')) ?></td>
          <td><?= (int)($j['attempts'] ?? 0) ?></td>
          <td class="muted"><?= htmlspecialchars((string)($j['last_error'] ?? '')) ?></td>
          <td class="muted"><?= htmlspecialchars((string)($j['created_at'] ?? '')) ?></td>
          <td class="muted"><?= htmlspecialchars((string)($j['processed_at'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$jobs): ?>
        <tr><td colspan="9" class="muted">—</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
