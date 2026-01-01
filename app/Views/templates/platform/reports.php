<?php
/** @var callable $t */
$byDay = $byDay ?? [];
ob_start();
?>

<h1><?= htmlspecialchars($t('platform.reports')) ?></h1>

<div class="card">
  <p class="muted"><?= htmlspecialchars($t('platform.report_last_14_days')) ?></p>
  <table class="table">
    <thead>
      <tr>
        <th>Date</th>
        <th><?= htmlspecialchars($t('platform.complaints')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($byDay as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string)($r['d'] ?? '')) ?></td>
          <td><?= (int)($r['c'] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$byDay): ?>
        <tr><td colspan="2" class="muted">—</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
