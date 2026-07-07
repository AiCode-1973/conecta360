<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — Conecta360</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0073ea 0%, #0059b3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-card {
            background: #fff;
            border-radius: 12px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0073ea;
            letter-spacing: -0.5px;
        }
        .logo p { color: #6c757d; font-size: .9rem; margin-top: .25rem; }

        .alert {
            border-radius: 8px;
            padding: .75rem 1rem;
            margin-bottom: 1.25rem;
            font-size: .9rem;
            font-weight: 500;
        }
        .alert-error   { background: #fff0f0; color: #c0392b; border-left: 4px solid #c0392b; }
        .alert-success { background: #f0fff4; color: #1a7e3f; border-left: 4px solid #1a7e3f; }

        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: .85rem; font-weight: 600; color: #495057; margin-bottom: .4rem; }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: .7rem 1rem;
            border: 1.5px solid #dee2e6;
            border-radius: 8px;
            font-size: .95rem;
            color: #212529;
            transition: border-color .2s;
            outline: none;
        }
        input:focus { border-color: #0073ea; box-shadow: 0 0 0 3px rgba(0,115,234,.15); }

        .form-check {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 1.5rem;
        }
        .form-check input { width: auto; }
        .form-check label { margin: 0; font-weight: 400; cursor: pointer; }

        .btn-login {
            width: 100%;
            padding: .8rem;
            background: #0073ea;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }
        .btn-login:hover { background: #0059b3; }
        .btn-login:active { transform: scale(.99); }

        .links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .85rem;
            color: #6c757d;
        }
        .links a { color: #0073ea; text-decoration: none; font-weight: 500; }
        .links a:hover { text-decoration: underline; }

        .divider {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin: 1.5rem 0;
            color: #adb5bd;
            font-size: .8rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <h1>Conecta360</h1>
            <p>Gestão hospitalar inteligente</p>
        </div>

        <?php $flash = flash_get(); if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                <?= htmlspecialchars($flash['msg']) ?>
            </div>
        <?php endif ?>

        <form method="POST" action="/login" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    placeholder="seu@hospital.com"
                    autocomplete="email"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Senha</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="form-check">
                <input type="checkbox" id="remember_me" name="remember_me" value="1">
                <label for="remember_me">Lembrar-me por 30 dias</label>
            </div>

            <button type="submit" class="btn-login">Entrar</button>
        </form>

        <div class="links">
            <a href="<?= htmlspecialchars(env('APP_URL') . '/forgot-password') ?>">Esqueci minha senha</a>
        </div>
    </div>
</body>
</html>
