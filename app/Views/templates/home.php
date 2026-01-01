<?php
/** @var callable $t */
ob_start();
?>
<h1><?= htmlspecialchars($t('home.title')) ?></h1>
<div class="card">
  <p class="muted"><?= htmlspecialchars($t('home.subtitle')) ?></p>
  <div class="row">
    <a class="btn primary" href="/complaints/new"><?= htmlspecialchars($t('home.new_complaint')) ?></a>
    <a class="btn" href="/complaints"><?= htmlspecialchars($t('home.view_complaints')) ?></a>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
