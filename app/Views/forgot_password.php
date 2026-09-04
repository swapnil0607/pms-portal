<section class="login-card">
    <div class="login-copy">
        <div class="login-logo-wrap">
            <img src="<?= e(asset_url('/public/assets/img/eduriser-logo.svg')) ?>" alt="EduRiser" class="login-brand-logo">
        </div>
        <span class="eyebrow">Enterprise Project Management</span>
        <h1>Reset your password</h1>
        <p>Enter your registered EduRiser email address, and we'll send you a secure password reset link powered by MSG91.</p>
        
        <div class="login-features">
            <span class="login-feature-tag">🔒 Secure One-Time Link</span>
            <span class="login-feature-tag">⏱️ 60-Minute Expiry</span>
            <span class="login-feature-tag">⚡ Instant Delivery</span>
        </div>
    </div>

    <form method="post" action="<?= url('/forgot-password') ?>" class="panel login-form">
        <?= csrf_field() ?>
        <div class="login-form-header">
            <div class="mobile-login-logo">
                <img src="<?= e(asset_url('/public/assets/img/eduriser-logo.svg')) ?>" alt="EduRiser">
            </div>
            <h2>Forgot password?</h2>
            <p class="login-subtext">We'll send a password recovery link to your email</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if (empty($success)): ?>
            <label>
                <span>Email Address</span>
                <input type="email" name="email" value="<?= e($prefillEmail ?? '') ?>" placeholder="name@eduriser.in" inputmode="email" autocomplete="username" required autofocus>
            </label>

            <button type="submit" class="btn login-submit-btn">Send Reset Link</button>
        <?php endif; ?>

        <div class="login-alt-action">
            <a href="<?= url('/login') ?>" class="back-to-login-link">&larr; Back to Sign In</a>
        </div>

        <div class="login-footer-copy">
            <small>© <?= date('Y') ?> EduRiser Learning Solutions. All rights reserved.</small>
        </div>
    </form>
</section>
