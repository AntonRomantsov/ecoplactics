<?php
$updatedFile = __DIR__ . '/Import_updated.xlsx';
$xmlFile     = __DIR__ . '/marketplace.xml';

function fileStatus($path) {
	if (!is_file($path)) {
		return 'ще не створено';
	}
	return 'оновлено ' . date('d.m.Y H:i', filemtime($path));
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Імпорт даних</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #f4f6f9;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }
    .card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
      padding: 48px 56px;
      text-align: center;
      min-width: 360px;
    }
    h1 {
      font-size: 22px;
      font-weight: 600;
      color: #1a1a2e;
      margin-bottom: 32px;
    }
    .btn-group {
      display: flex;
      gap: 12px;
      justify-content: center;
    }
    .btn {
      padding: 14px 28px;
      font-size: 15px;
      font-weight: 500;
      color: #fff;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      transition: background .2s;
    }
    .btn-import { background: #3b5bdb; }
    .btn-import:hover { background: #2f4ac0; }
    .btn-xml { background: #2f9e44; }
    .btn-xml:hover { background: #276e30; }
    .status {
      margin-top: 28px;
      font-size: 13px;
      color: #555;
      text-align: left;
      line-height: 1.8;
    }
    .status b { color: #1a1a2e; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Імпорт даних</h1>
    <div class="btn-group">
      <a class="btn btn-import" href="process_prices.php" target="_blank">1. Оновити ціни</a>
      <a class="btn btn-xml" href="generate_xml.php" target="_blank">2. Отримати XML</a>
    </div>
    <div class="status">
      <div><b>Import_updated.xlsx:</b> <?= htmlspecialchars(fileStatus($updatedFile)) ?></div>
      <div><b>marketplace.xml:</b> <?= htmlspecialchars(fileStatus($xmlFile)) ?></div>
    </div>
  </div>
</body>
</html>
