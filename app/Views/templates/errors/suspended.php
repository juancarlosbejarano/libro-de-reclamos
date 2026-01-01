<?php
/** @var callable $t */
ob_start();
?>
<h1><?= htmlspecialchars($t('errors.suspended_title')) ?></h1>
<p class="error"><?= htmlspecialchars($t('errors.suspended_message')) ?></p>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
