<section class="login-card">
    <div class="login-copy">
        <div class="login-logo-wrap">
            <img src="<?= e(asset_url('/public/assets/img/eduriser-logo.svg')) ?>" alt="EduRiser" class="login-brand-logo">
        </div>
        <span class="eyebrow">Enterprise Project Management</span>
        <h1>Choose a new password</h1>
        <p>Your password must be at least 6 characters long. For security, choose a strong password you don't use elsewhere.</p>
        
        <div class="login-features">
            <span class="login-feature-tag">🛡️ High-Grade Encryption</span>
            <span class="login-feature-tag">🔐 Instant Account Update</span>
        </div>
    </div>

    <form method="post" action="<?= url('/reset-password?token=' . urlencode($token)) ?>" class="panel login-form">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="login-form-header">
            <div class="mobile-login-logo">
                <img src="<?= e(asset_url('/public/assets/img/eduriser-logo.svg')) ?>" alt="EduRiser">
            </div>
            <h2>Set New Password</h2>
            <p class="login-subtext">Resetting password for <strong><?= e($email) ?></strong></p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <label>
            <span>New Password</span>
            <input type="password" name="password" placeholder="At least 6 characters" minlength="6" autocomplete="new-password" required autofocus>
        </label>

        <label>
            <span>Confirm New Password</span>
            <input type="password" name="password_confirm" placeholder="Re-enter your new password" minlength="6" autocomplete="new-password" required>
        </label>

        <button type="submit" class="btn login-submit-btn">Update Password & Sign In</button>

        <div class="login-alt-action">
            <a href="<?= url('/login') ?>" class="back-to-login-link">&larr; Back to Sign In</a>
        </div>

        <div class="login-footer-copy">
            <small>© <?= date('Y') ?> EduRiser Learning Solutions. All rights reserved.</small>
        </div>
    </form>
</section>
