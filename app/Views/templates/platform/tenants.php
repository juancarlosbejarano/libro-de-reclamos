<?php
/** @var callable $t */
$tenants = $tenants ?? [];
ob_start();
?>

<h1><?= htmlspecialchars($t('platform.tenants')) ?></h1>

<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Slug</th>
        <th><?= htmlspecialchars($t('auth.company')) ?></th>
        <th><?= htmlspecialchars($t('platform.complaints')) ?></th>
        <th><?= htmlspecialchars($t('platform.domains')) ?></th>
        <th><?= htmlspecialchars($t('platform.admins')) ?></th>
        <th><?= htmlspecialchars($t('complaints.created')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tenants as $tn): ?>
        <tr>
          <td><?= (int)($tn['id'] ?? 0) ?></td>
          <td><?= htmlspecialchars((string)($tn['slug'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($tn['name'] ?? '')) ?></td>
          <td><?= (int)($tn['complaints_count'] ?? 0) ?></td>
          <td><?= (int)($tn['domains_count'] ?? 0) ?></td>
          <td><?= (int)($tn['admins_count'] ?? 0) ?></td>
          <td class="muted"><?= htmlspecialchars((string)($tn['created_at'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tenants): ?>
        <tr><td colspan="7" class="muted">—</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
