<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? config('app.name')) ?> | <?= e(config('app.name')) ?></title>
    <meta name="app-base-path" content="<?= e(app_base_path()) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/public/assets/css/app.css')) ?>">
</head>
<body class="auth-page">
    <?= $content ?>
</body>
</html>
