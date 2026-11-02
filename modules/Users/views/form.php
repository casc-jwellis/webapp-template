<?php
/**
 * Create/edit form. Expects $isNew, $isSelf, $user (row|null), $f (field values), $errors, $timezones,
 * $mailReady (new user and email can be sent), $mustChange (edit: still on a temporary password).
 */
$action = ($isNew ? url('/users/new') : url('/users/' . rawurlencode((string) $f['user_id']))) . $listQs;
$back = url('/users') . $listQs;
$never = $user === null || $user['lastlogin'] === null || str_starts_with((string) $user['lastlogin'], '0000') || str_starts_with((string) $user['lastlogin'], '1970-01-01');
?>
<div class="narrow-col">
<div class="page-head">
    <div>
        <a class="back-link" href="<?= e($back) ?>">&larr; <?= $listQs !== '' ? 'Back to results' : 'All users' ?></a>
        <h1><?= $isNew ? 'New user' : e($f['username']) ?></h1>
        <?php if (!$isNew): ?>
            <p class="muted">User ID <?= e($f['user_id']) ?> · Last sign-in <?= $never ? 'never' : e(fmt_date($user['lastlogin'])) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!$isNew): ?>
        <?php /* Separate forms (they cannot nest inside the edit form below). Both are reversible, so no confirmation page. */ ?>
        <div class="page-actions">
            <?php if ($f['disabled']): ?>
                <form method="post" action="<?= e(url('/users/' . rawurlencode((string) $f['user_id']) . '/enable') . $listQs) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary">Re-enable user</button>
                </form>
            <?php elseif ($isSelf): ?>
                <span class="muted small">You cannot disable your own account.</span>
            <?php else: ?>
                <form method="post" action="<?= e(url('/users/' . rawurlencode((string) $f['user_id']) . '/disable') . $listQs) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn" title="Stop this person signing in. Their account and history are kept.">Disable user</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!$isNew && $f['disabled']): ?>
    <div class="alert alert-warning" role="status">
        <strong>This user is disabled</strong> and cannot sign in. Their account and history are kept; re-enable them to restore access.
    </div>
<?php endif; ?>

<?php if ($mustChange): ?>
    <div class="alert alert-info" role="status">
        <strong>Still on a temporary password.</strong> This person has not chosen their own yet, and will be asked to when they sign in.
    </div>
<?php endif; ?>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger" role="alert"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" action="<?= e($action) ?>" autocomplete="off">
    <?= csrf_field() ?>

    <div class="card form-card wide">
        <h2>Account</h2>
        <div class="field">
            <label for="username">Username (used to sign in)</label>
            <input type="text" id="username" name="username" value="<?= e($f['username']) ?>" maxlength="64" required>
        </div>
        <div class="field-row even">
            <div class="field">
                <label for="firstname">First name</label>
                <input type="text" id="firstname" name="firstname" value="<?= e($f['firstname']) ?>" maxlength="32">
            </div>
            <div class="field">
                <label for="lastname">Last name</label>
                <input type="text" id="lastname" name="lastname" value="<?= e($f['lastname']) ?>" maxlength="32">
            </div>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($f['email']) ?>" maxlength="128"<?= $mailReady ? ' required' : '' ?>>
            <?php if ($mailReady): ?>
                <small class="muted">The temporary password is emailed here.</small>
            <?php else: ?>
                <small class="muted">Used for "Forgot password" and, if turned on, Sign in with Google.</small>
            <?php endif; ?>
        </div>
        <div class="field-row even">
            <div class="field">
                <label for="role">Role</label>
                <select id="role" name="role"<?= $isSelf ? ' disabled' : '' ?>>
                    <option value=""<?= !$f['admin'] ? ' selected' : '' ?>>User</option>
                    <option value="admin"<?= $f['admin'] ? ' selected' : '' ?>>Administrator: everything, including users, theme, email and migrations</option>
                </select>
                <?php if ($isSelf): ?><small class="muted">You cannot change your own role.</small><?php endif; ?>
            </div>
            <div class="field">
                <label for="timezone">Time zone</label>
                <select id="timezone" name="timezone">
                    <?php foreach ($timezones as $tz): ?>
                        <option value="<?= e($tz) ?>"<?= $f['timezone'] === $tz ? ' selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <?php if ($isNew): ?>
        <?php /* No password card: a temporary one is generated when the user is created. */ ?>
        <div class="card form-card wide section">
            <h2>Password</h2>
            <p class="muted small">A temporary password is generated when you create the user and <?= $mailReady ? 'emailed to them' : 'shown to you once (email is not set up, so it cannot be sent; an administrator can set it up on the Email page)' ?>.
                The first time they sign in they must choose their own.</p>
        </div>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create user' : 'Save changes' ?></button>
        <a class="btn" href="<?= e($back) ?>">Cancel</a>
    </div>
</form>
</div>
