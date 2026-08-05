<section class="login-card">
    <div class="login-copy">
        <span class="eyebrow">EduRiser PMS</span>
        <h1>Project work, deadlines, and team visibility in one place.</h1>
        <p>Sign in to manage projects, tasks, ownership, and progress tracking.</p>
    </div>

    <form method="post" class="panel login-form">
        <?= csrf_field() ?>
        <h2>Sign in</h2>
        <?php if ($error): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>
        <label>
            Email
            <input type="text" name="email" value="<?= e($prefillEmail !== '' ? $prefillEmail : 'admin@eduriser.in') ?>" inputmode="email" required>
        </label>
        <label>
            Password
            <input type="password" name="password" value="Admin@123" required>
        </label>
        <button type="submit">Login</button>
        <p class="hint">Default admin: admin@eduriser.in / Admin@123</p>
    </form>
</section>
