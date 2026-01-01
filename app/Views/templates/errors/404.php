<?php
/** @var callable $t */
$path = $path ?? '';
ob_start();
?>
<h1><?= htmlspecialchars($t('errors.404')) ?></h1>
<p class="muted"><?= htmlspecialchars($t('errors.path', ['path' => (string)$path])) ?></p>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
