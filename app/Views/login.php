<section class="login-card">
    <div class="login-copy">
        <div class="login-logo-wrap">
            <img src="<?= e(asset_url('/public/assets/img/eduriser-logo.svg')) ?>" alt="EduRiser" class="login-brand-logo">
        </div>
        <span class="eyebrow">Enterprise Project Management</span>
        <h1>Project work, deadlines, and team visibility in one place.</h1>
        <p>Streamlined project tracking, task management, and automated work logging for modern teams.</p>
        
        <div class="login-features">
            <span class="login-feature-tag">⚡ Real-Time Kanban</span>
            <span class="login-feature-tag">📊 Time & Work Logs</span>
            <span class="login-feature-tag">📁 Project Renewals</span>
        </div>
    </div>

    <form method="post" class="panel login-form">
        <?= csrf_field() ?>
        <div class="login-form-header">
            <div class="mobile-login-logo">
                <img src="<?= e(asset_url('/public/assets/img/eduriser-logo.svg')) ?>" alt="EduRiser">
            </div>
            <h2>Sign in</h2>
            <p class="login-subtext">Enter your credentials to access your PMS workspace</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert success"><?= e($success) ?></div>
        <?php endif; ?>

        <label>
            <span>Email Address</span>
            <input type="email" name="email" value="<?= e($prefillEmail ?? '') ?>" placeholder="name@eduriser.in" inputmode="email" autocomplete="username" required autofocus>
        </label>

        <label>
            <div class="label-row-split">
                <span>Password</span>
                <a href="<?= url('/forgot-password') ?>" class="forgot-password-link">Forgot password?</a>
            </div>
            <input type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
        </label>

        <button type="submit" class="btn login-submit-btn">Sign In</button>

        <div class="login-footer-copy">
            <small>© <?= date('Y') ?> EduRiser Learning Solutions. All rights reserved.</small>
        </div>
    </form>
</section>
