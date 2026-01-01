<?php
/** @var callable $t */
/** @var callable $csrf */
$error = $error ?? null;
ob_start();
?>
<h1><?= htmlspecialchars($t('complaints.new')) ?></h1>

<?php if ($error): ?>
  <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post" action="/complaints" class="card" enctype="multipart/form-data">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
  <label><?= htmlspecialchars($t('complaints.customer_name')) ?></label>
  <input name="customer_name" maxlength="180" value="<?= htmlspecialchars((string)($_POST['customer_name'] ?? '')) ?>" />
  <label><?= htmlspecialchars($t('complaints.customer_email')) ?></label>
  <input name="customer_email" type="email" maxlength="255" value="<?= htmlspecialchars((string)($_POST['customer_email'] ?? '')) ?>" />
  <label><?= htmlspecialchars($t('complaints.customer_phone')) ?></label>
  <input name="customer_phone" maxlength="32" value="<?= htmlspecialchars((string)($_POST['customer_phone'] ?? '')) ?>" placeholder="+51999999999" />
  <label><?= htmlspecialchars($t('complaints.subject')) ?></label>
  <input name="subject" maxlength="180" value="<?= htmlspecialchars((string)($_POST['subject'] ?? '')) ?>" required />
  <label><?= htmlspecialchars($t('complaints.message')) ?></label>
  <textarea name="message" rows="6" required><?= htmlspecialchars((string)($_POST['message'] ?? '')) ?></textarea>
  <label><?= htmlspecialchars($t('complaints.attachment')) ?> (opcional)</label>
  <input name="attachment" type="file" />
  <div style="margin-top:12px">
    <button class="btn primary" type="submit"><?= htmlspecialchars($t('complaints.submit')) ?></button>
  </div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
