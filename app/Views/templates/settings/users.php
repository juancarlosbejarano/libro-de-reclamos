<?php
/** @var callable $t */
/** @var callable $csrf */
/** @var array<int,array<string,mixed>> $users */
/** @var string|null $error */
/** @var string|null $token_plain */

$users = $users ?? [];
$error = $error ?? null;
$tokenPlain = $token_plain ?? null;

ob_start();
?>

<h1><?= htmlspecialchars($t('settings.users_title')) ?></h1>

<?php if ($error): ?>
  <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<?php if ($tokenPlain): ?>
  <div class="card">
    <p><strong><?= htmlspecialchars($t('settings.users_token_generated')) ?></strong></p>
    <p class="muted"><?= htmlspecialchars($t('settings.users_token_once')) ?></p>
    <pre class="pre"><?= htmlspecialchars((string)$tokenPlain) ?></pre>
  </div>
<?php endif; ?>

<div class="card">
  <h3 style="margin-top:0"><?= htmlspecialchars($t('settings.users_create')) ?></h3>
  <form method="post" action="/settings/users">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
    <input type="hidden" name="action" value="create" />

    <label><?= htmlspecialchars($t('settings.users_email')) ?></label>
    <input type="email" name="email" required />

    <label><?= htmlspecialchars($t('settings.users_password')) ?></label>
    <input type="password" name="password" minlength="8" required />

    <label><?= htmlspecialchars($t('settings.users_role')) ?></label>
    <select name="role">
      <option value="staff">staff</option>
      <option value="user">user</option>
      <option value="bot">bot</option>
    </select>

    <div style="margin-top:12px">
      <button class="btn primary" type="submit"><?= htmlspecialchars($t('settings.save')) ?></button>
    </div>
    <p class="muted" style="margin-top:10px"><?= htmlspecialchars($t('settings.users_note')) ?></p>
  </form>
</div>

<div class="card" style="margin-top:14px">
  <h3 style="margin-top:0"><?= htmlspecialchars($t('settings.users_list')) ?></h3>
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th><?= htmlspecialchars($t('settings.users_email')) ?></th>
        <th><?= htmlspecialchars($t('settings.users_role')) ?></th>
        <th><?= htmlspecialchars($t('complaints.created')) ?></th>
        <th><?= htmlspecialchars($t('settings.users_actions')) ?></th>
        <th><?= htmlspecialchars($t('settings.users_api')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= (int)($u['id'] ?? 0) ?></td>
          <td><?= htmlspecialchars((string)($u['email'] ?? '')) ?></td>
          <td>
            <form method="post" action="/settings/users" style="display:flex;gap:8px;align-items:center">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
              <input type="hidden" name="action" value="role" />
              <input type="hidden" name="user_id" value="<?= (int)($u['id'] ?? 0) ?>" />
              <select name="role">
                <option value="admin" <?= ((string)($u['role'] ?? '') === 'admin') ? 'selected' : '' ?>>admin</option>
                <option value="staff" <?= ((string)($u['role'] ?? '') === 'staff') ? 'selected' : '' ?>>staff</option>
                <option value="user" <?= ((string)($u['role'] ?? '') === 'user') ? 'selected' : '' ?>>user</option>
                <option value="bot" <?= ((string)($u['role'] ?? '') === 'bot') ? 'selected' : '' ?>>bot</option>
              </select>
              <button class="btn" type="submit"><?= htmlspecialchars($t('settings.users_update_role')) ?></button>
            </form>
          </td>
          <td class="muted"><?= htmlspecialchars((string)($u['created_at'] ?? '')) ?></td>
          <td>
            <form method="post" action="/settings/users" style="display:flex;gap:8px;align-items:center">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
              <input type="hidden" name="action" value="password" />
              <input type="hidden" name="user_id" value="<?= (int)($u['id'] ?? 0) ?>" />
              <input type="password" name="password" minlength="8" placeholder="<?= htmlspecialchars($t('settings.users_new_password')) ?>" required />
              <button class="btn" type="submit"><?= htmlspecialchars($t('settings.users_reset_password')) ?></button>
            </form>
          </td>
          <td>
            <?php if (((string)($u['role'] ?? '')) === 'bot'): ?>
              <div style="display:flex;flex-direction:column;gap:8px">
                <form method="post" action="/settings/users" style="display:flex;gap:8px;align-items:center">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
                  <input type="hidden" name="action" value="token_create" />
                  <input type="hidden" name="user_id" value="<?= (int)($u['id'] ?? 0) ?>" />
                  <button class="btn" type="submit"><?= htmlspecialchars($t('settings.users_generate_token')) ?></button>
                </form>
                <form method="post" action="/settings/users" style="display:flex;gap:8px;align-items:center" onsubmit="return confirm('Revocar todos los tokens?');">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
                  <input type="hidden" name="action" value="token_revoke" />
                  <input type="hidden" name="user_id" value="<?= (int)($u['id'] ?? 0) ?>" />
                  <button class="btn danger" type="submit"><?= htmlspecialchars($t('settings.users_revoke_tokens')) ?></button>
                </form>
                <?php $tokens = $u['tokens'] ?? []; ?>
                <?php if (is_array($tokens) && count($tokens) > 0): ?>
                  <div class="muted">
                    <?= htmlspecialchars($t('settings.users_tokens_count')) ?>: <?= (int)count($tokens) ?>
                  </div>
                  <div class="card" style="padding:10px">
                    <table class="table">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th><?= htmlspecialchars($t('complaints.created')) ?></th>
                          <th><?= htmlspecialchars($t('settings.users_actions')) ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($tokens as $tok): ?>
                          <tr>
                            <td><?= (int)($tok['id'] ?? 0) ?></td>
                            <td class="muted"><?= htmlspecialchars((string)($tok['created_at'] ?? '')) ?></td>
                            <td>
                              <form method="post" action="/settings/users" style="margin:0" onsubmit="return confirm('Revocar este token?');">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf()) ?>" />
                                <input type="hidden" name="action" value="token_revoke_one" />
                                <input type="hidden" name="user_id" value="<?= (int)($u['id'] ?? 0) ?>" />
                                <input type="hidden" name="token_id" value="<?= (int)($tok['id'] ?? 0) ?>" />
                                <button class="btn danger" type="submit"><?= htmlspecialchars($t('settings.users_revoke_one')) ?></button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="muted"><?= htmlspecialchars($t('settings.users_no_tokens')) ?></div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?>
        <tr><td colspan="6" class="muted"><?= htmlspecialchars($t('settings.users_none')) ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
