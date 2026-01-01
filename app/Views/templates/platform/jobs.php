<?php
/** @var callable $t */
$jobs = $jobs ?? [];
ob_start();
?>

<h1><?= htmlspecialchars($t('platform.jobs')) ?></h1>

<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Tenant</th>
        <th>Domain</th>
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
          <td><?= (int)($j['tenant_id'] ?? 0) ?></td>
          <td><?= htmlspecialchars((string)($j['domain'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($j['status'] ?? '')) ?></td>
          <td><?= (int)($j['attempts'] ?? 0) ?></td>
          <td class="muted"><?= htmlspecialchars((string)($j['last_error'] ?? '')) ?></td>
          <td class="muted"><?= htmlspecialchars((string)($j['created_at'] ?? '')) ?></td>
          <td class="muted"><?= htmlspecialchars((string)($j['processed_at'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$jobs): ?>
        <tr><td colspan="8" class="muted">—</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
