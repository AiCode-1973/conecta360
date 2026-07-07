<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Conecta360</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #f6f7fb; display: flex; align-items: center; justify-content: center; min-height: 100vh; text-align: center; }
        .box { max-width: 400px; }
        h1 { font-size: 5rem; color: #0073ea; margin: 0; }
        p { color: #676879; margin: 1rem 0 2rem; }
        a { background: #0073ea; color: #fff; padding: .6rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="box">
        <h1>404</h1>
        <p>A página que você procura não existe.</p>
        <a href="<?= rtrim(env('APP_URL', ''), '/') ?>/dashboard">Voltar ao Dashboard</a>
    </div>
</body>
</html>
