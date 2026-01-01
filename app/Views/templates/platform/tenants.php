<?php
/** @var callable $t */
$tenants = $tenants ?? [];
ob_start();
?>

<h1><?= htmlspecialchars($t('platform.tenants')) ?></h1>

<div style="margin:12px 0">
  <a class="btn primary" href="/platform/tenants/create"><?= htmlspecialchars($t('platform.tenant_create')) ?></a>
</div>

<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Slug</th>
        <th><?= htmlspecialchars($t('auth.company')) ?></th>
        <th><?= htmlspecialchars($t('platform.status')) ?></th>
        <th><?= htmlspecialchars($t('platform.complaints')) ?></th>
        <th><?= htmlspecialchars($t('platform.domains')) ?></th>
        <th><?= htmlspecialchars($t('platform.admins')) ?></th>
        <th><?= htmlspecialchars($t('complaints.created')) ?></th>
        <th><?= htmlspecialchars($t('platform.actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tenants as $tn): ?>
        <tr>
          <td><?= (int)($tn['id'] ?? 0) ?></td>
          <td><?= htmlspecialchars((string)($tn['slug'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($tn['name'] ?? '')) ?></td>
          <td>
            <?php if (((string)($tn['status'] ?? 'active')) === 'suspended'): ?>
              <span class="error"><?= htmlspecialchars($t('platform.status_suspended')) ?></span>
            <?php else: ?>
              <span class="muted"><?= htmlspecialchars($t('platform.status_active')) ?></span>
            <?php endif; ?>
          </td>
          <td><?= (int)($tn['complaints_count'] ?? 0) ?></td>
          <td><?= (int)($tn['domains_count'] ?? 0) ?></td>
          <td><?= (int)($tn['admins_count'] ?? 0) ?></td>
          <td class="muted"><?= htmlspecialchars((string)($tn['created_at'] ?? '')) ?></td>
          <td>
            <a class="btn" href="/platform/tenants/<?= (int)($tn['id'] ?? 0) ?>/edit"><?= htmlspecialchars($t('platform.edit')) ?></a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tenants): ?>
        <tr><td colspan="9" class="muted">—</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
