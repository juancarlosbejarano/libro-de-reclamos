<?php
/** @var callable $t */
$complaint = $complaint ?? null;
$responses = $responses ?? [];
$currentUser = $_SESSION['user'] ?? null;
ob_start();
?>
<?php if (!$complaint): ?>
  <h1><?= htmlspecialchars($t('complaints.not_found')) ?></h1>
<?php else: ?>
  <h1><?= htmlspecialchars($t('complaints.detail')) ?> #<?= (int)$complaint['id'] ?></h1>
  <div class="card">
    <p><strong><?= htmlspecialchars($t('complaints.subject')) ?>:</strong> <?= htmlspecialchars((string)$complaint['subject']) ?></p>
    <p><strong><?= htmlspecialchars($t('complaints.status')) ?>:</strong> <?= htmlspecialchars((string)$complaint['status']) ?></p>
    <?php if (!empty($complaint['customer_name']) || !empty($complaint['customer_email']) || !empty($complaint['customer_phone'])): ?>
      <hr />
      <p><strong><?= htmlspecialchars($t('complaints.customer')) ?>:</strong></p>
      <?php if (!empty($complaint['customer_name'])): ?><p class="muted"><?= htmlspecialchars($t('complaints.customer_name')) ?>: <?= htmlspecialchars((string)$complaint['customer_name']) ?></p><?php endif; ?>
      <?php if (!empty($complaint['customer_email'])): ?><p class="muted"><?= htmlspecialchars($t('complaints.customer_email')) ?>: <?= htmlspecialchars((string)$complaint['customer_email']) ?></p><?php endif; ?>
      <?php if (!empty($complaint['customer_phone'])): ?><p class="muted"><?= htmlspecialchars($t('complaints.customer_phone')) ?>: <?= htmlspecialchars((string)$complaint['customer_phone']) ?></p><?php endif; ?>
    <?php endif; ?>
    <p><strong><?= htmlspecialchars($t('complaints.message')) ?>:</strong><br /><?= nl2br(htmlspecialchars((string)$complaint['message'])) ?></p>
    <p class="muted"><?= htmlspecialchars((string)$complaint['created_at']) ?></p>
  </div>

  <h2 style="margin-top:18px"><?= htmlspecialchars($t('complaints.responses')) ?></h2>
  <?php if (is_array($responses) && count($responses) > 0): ?>
    <?php foreach ($responses as $r): ?>
      <div class="card">
        <p class="muted">
          <?= htmlspecialchars((string)($r['created_at'] ?? '')) ?> · <?= htmlspecialchars((string)($r['user_email'] ?? '')) ?>
          <span style="margin-left:10px">
            <?= htmlspecialchars($t('complaints.email')) ?>: <?= !empty($r['email_sent_at']) ? '✓' : (!empty($r['email_error']) ? '✗' : '—') ?>
            · <?= htmlspecialchars($t('complaints.whatsapp')) ?>: <?= !empty($r['whatsapp_sent_at']) ? '✓' : (!empty($r['whatsapp_error']) ? '✗' : '—') ?>
          </span>
        </p>
        <p><?= nl2br(htmlspecialchars((string)($r['message'] ?? ''))) ?></p>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="muted"><?= htmlspecialchars($t('complaints.no_responses')) ?></p>
  <?php endif; ?>

  <?php if ($currentUser && in_array((string)($currentUser['role'] ?? ''), ['admin','staff'], true)): ?>
    <h3 style="margin-top:18px"><?= htmlspecialchars($t('complaints.add_response')) ?></h3>
    <form method="post" action="/complaints/<?= (int)$complaint['id'] ?>/responses" class="card">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
      <label><?= htmlspecialchars($t('complaints.response_message')) ?></label>
      <textarea name="message" rows="5" required></textarea>
      <div style="margin-top:12px">
        <button class="btn primary" type="submit"><?= htmlspecialchars($t('complaints.send')) ?></button>
      </div>
      <p class="muted" style="margin-top:10px"><?= htmlspecialchars($t('complaints.response_notify_note')) ?></p>
    </form>
  <?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
