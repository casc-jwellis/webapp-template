<?php /** Expects $stats (array: 'users' for administrators), $isAdmin (bool). */ ?>
<div class="page-head">
    <div>
        <h1>Welcome back, <?= e(current_user()['firstname'] ?: current_user()['username']) ?></h1>
        <p class="muted">This is the home page of <?= e(app_name()) ?>. Put the numbers and shortcuts people need first here.</p>
    </div>
</div>

<?php if ($stats !== []): ?>
    <div class="stat-grid">
        <?php if (isset($stats['users'])): ?>
            <a class="card stat stat-link" href="<?= e(url('/users')) ?>">
                <div class="stat-label">Active users</div>
                <div class="stat-value"><?= e(number_format($stats['users'])) ?></div>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-head"><h2>Getting started</h2></div>
    <p>This page, the <a href="<?= e(url('/items')) ?>">Items</a> example and the files in <code>modules/</code> are yours to change.
        See <code>README.md</code> for how a module is put together.</p>
    <?php if ($isAdmin): ?>
        <ul>
            <li><a href="<?= e(url('/users')) ?>">Users</a>: add the people who can sign in.</li>
            <li><a href="<?= e(url('/theme')) ?>">Theme</a>: the site name and colours.</li>
            <li><a href="<?= e(url('/email')) ?>">Email</a>: the mail server for temporary passwords and "Forgot password".</li>
            <li><a href="<?= e(url('/signin')) ?>">Sign-in</a>: let people sign in with Google.</li>
            <li><a href="<?= e(url('/migrate')) ?>">Migrations</a>: apply database updates after you update the code.</li>
        </ul>
    <?php endif; ?>
</div>
