<!DOCTYPE html>
<html lang="pt-br" data-tenant="<?= htmlspecialchars($tenant->subdomain) ?>"
      data-user-id="<?= (int)$user['id'] ?>"
      data-sidebar="<?= $menuState['sidebar_collapsed'] ? 'collapsed' : 'expanded' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Conecta360') ?> — <?= htmlspecialchars($tenant->name) ?></title>

    {{-- Favicon e brand do tenant --}}
    <link rel="icon" href="<?= htmlspecialchars($tenant->logo_url ?? '/assets/img/favicon.ico') ?>">

    {{-- CSS principal (compilado/minificado) --}}
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= APP_VERSION ?>">

    {{-- Cor primária do tenant injetada como CSS var --}}
    <style>
        :root {
            --primary: <?= htmlspecialchars($tenant->primary_color ?? '#0073ea') ?>;
            --primary-dark: color-mix(in srgb, var(--primary) 80%, black);
            --primary-light: color-mix(in srgb, var(--primary) 20%, white);
        }
    </style>
</head>
<body class="layout-app <?= $menuState['sidebar_collapsed'] ? 'sidebar-collapsed' : '' ?>">

    {{-- ═══ SIDEBAR ═══════════════════════════════════════════════════════ --}}
    <aside id="sidebar" class="sidebar" role="navigation" aria-label="Menu principal">
        <?php require __DIR__ . '/../partials/sidebar_header.php' ?>
        <?php require __DIR__ . '/../partials/sidebar_nav.php' ?>
        <?php require __DIR__ . '/../partials/sidebar_footer.php' ?>
    </aside>

    {{-- ═══ MAIN WRAPPER ═══════════════════════════════════════════════════ --}}
    <div class="main-wrapper">

        {{-- Topbar --}}
        <?php require __DIR__ . '/../partials/topbar.php' ?>

        {{-- Flash Messages --}}
        <?php require __DIR__ . '/../partials/flash.php' ?>

        {{-- Conteúdo da página --}}
        <main id="main-content" class="main-content" role="main">
            <?= $content ?? '' ?>
        </main>

    </div>{{-- /.main-wrapper --}}

    {{-- ═══ OVERLAYS GLOBAIS ════════════════════════════════════════════════ --}}
    {{-- Painel de notificações (slide-in) --}}
    <?php require __DIR__ . '/../partials/notifications_panel.php' ?>

    {{-- Modal de busca global --}}
    <?php require __DIR__ . '/../partials/search_modal.php' ?>

    {{-- Toast de feedback --}}
    <div id="toast-container" class="toast-container" aria-live="polite"></div>

    {{-- JS principal --}}
    <script src="/assets/js/app.js?v=<?= APP_VERSION ?>"></script>

    {{-- Dados injetados para o JS (não usar para dados sensíveis) --}}
    <script>
        window.C360 = {
            csrfToken:       '<?= htmlspecialchars($csrfToken) ?>',
            userId:          <?= (int)$user['id'] ?>,
            tenantSubdomain: '<?= htmlspecialchars($tenant->subdomain) ?>',
            baseUrl:         '<?= htmlspecialchars($baseUrl) ?>',
            locale:          '<?= htmlspecialchars($user['locale'] ?? 'pt_BR') ?>',
            timezone:        '<?= htmlspecialchars($user['timezone'] ?? 'America/Sao_Paulo') ?>',
        };
    </script>

    <?php if (isset($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>?v=<?= APP_VERSION ?>"></script>
        <?php endforeach ?>
    <?php endif ?>
</body>
</html>
