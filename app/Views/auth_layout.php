<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title><?= e($title ?? config('app.name')) ?> | <?= e(config('app.name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('/public/assets/img/favicon.svg')) ?>">
    <link rel="alternate icon" href="<?= e(asset_url('/public/favicon.svg')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset_url('/public/assets/img/favicon.svg')) ?>">
    <meta name="theme-color" content="#007a70">
    <meta name="app-base-path" content="<?= e(app_base_path()) ?>">
    <link rel="manifest" href="<?= e(asset_url('/public/manifest.json')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/public/assets/css/app.css')) ?>">
</head>
<body class="auth-page">
    <?= $content ?>
</body>
</html>
