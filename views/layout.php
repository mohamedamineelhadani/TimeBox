<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= $e($title ?? 'TimeBox') ?> — TimeBox</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;700&display=swap">
<link rel="stylesheet" href="<?= $e($asset('css/style.css')) ?>">
</head>
<body>
<div class="frame-tr"></div>
<div class="frame-br"></div>

<?= $content ?>

<?php foreach ($scripts ?? [] as $script): ?>
<script type="module" src="<?= $e($asset($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
