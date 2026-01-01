<?php
/** @var callable $t */
$complaints = $complaints ?? [];
$titleKey = $title ?? 'complaints.title';
ob_start();
?>
<h1><?= htmlspecialchars($t((string)$titleKey)) ?></h1>
<div class="row" style="margin-bottom:12px">
  <a class="btn primary" href="/complaints/new"><?= htmlspecialchars($t('complaints.new')) ?></a>
</div>

<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th><?= htmlspecialchars($t('complaints.subject')) ?></th>
        <th><?= htmlspecialchars($t('complaints.status')) ?></th>
        <th><?= htmlspecialchars($t('complaints.created')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($complaints as $c): ?>
        <tr>
          <td><a href="/complaints/<?= (int)$c['id'] ?>"><?= (int)$c['id'] ?></a></td>
          <td><?= htmlspecialchars((string)$c['subject']) ?></td>
          <td><?= htmlspecialchars((string)$c['status']) ?></td>
          <td><?= htmlspecialchars((string)$c['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$complaints): ?>
        <tr><td colspan="4" class="muted"><?= htmlspecialchars($t('complaints.none')) ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
