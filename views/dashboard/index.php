<?php
/**
 * View: Dashboard Principal
 *
 * VARIÁVEIS ESPERADAS:
 *   $widgets     → array de widgets renderizáveis (DashboardService::getWidgetsForUser)
 *   $user        → array do usuário logado (SessionService::getUser)
 *   $tenant      → objeto Tenant
 *
 * CADA ITEM EM $widgets TEM:
 *   [key]         → 'my_tasks', 'recent_activity', etc.
 *   [title]       → título display
 *   [template]    → caminho do partial: views/widgets/my_tasks.php
 *   [data]        → dados pré-carregados pelo componente
 *   [position_x]  → coluna na grade (0-11)
 *   [position_y]  → linha na grade
 *   [width]       → colunas de largura (1-12)
 *   [height]      → linhas de altura
 *   [config]      → configurações do widget
 *
 * LAYOUT:
 *   Grade 12 colunas via CSS Grid ou classes Bootstrap (col-*)
 *   Widgets arrastáveis via JS (Sortable.js ou similar)
 *   Estado salvo via POST /dashboard/layout (AJAX)
 */
?>
<div class="dashboard-page">

    {{-- Cabeçalho da página --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                Olá, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?> 👋
            </h1>
            <p class="page-subtitle">
                <?= date('l, d \d\e F \d\e Y') ?>
            </p>
        </div>
        <div class="page-header-actions">
            {{-- Botão de personalizar dashboard (abre seletor de widgets) --}}
            <button class="btn btn-secondary btn-sm"
                    id="btn-customize-dashboard"
                    data-action="open-widget-catalog"
                    aria-label="Personalizar dashboard">
                <i class="fas fa-th" aria-hidden="true"></i>
                <span>Personalizar</span>
            </button>
            <?php if (can('boards.create')): ?>
                <a href="/boards/new" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Novo Board
                </a>
            <?php endif ?>
        </div>
    </div>

    {{-- Grid de Widgets --}}
    <div class="dashboard-grid"
         id="dashboard-grid"
         data-cols="12"
         data-save-url="/dashboard/layout"
         data-csrf="<?= htmlspecialchars($csrfToken) ?>">

        <?php foreach ($widgets as $widget): ?>
            <div class="dashboard-widget"
                 id="widget-<?= htmlspecialchars($widget['key']) ?>"
                 data-key="<?= htmlspecialchars($widget['key']) ?>"
                 data-col-start="<?= (int)$widget['position_x'] + 1 ?>"
                 data-col-span="<?= (int)$widget['width'] ?>"
                 data-row="<?= (int)$widget['position_y'] + 1 ?>"
                 style="grid-column: <?= (int)$widget['position_x'] + 1 ?> / span <?= (int)$widget['width'] ?>;
                         grid-row:   <?= (int)$widget['position_y'] + 1 ?> / span <?= (int)$widget['height'] ?>">

                <div class="widget-inner">
                    <div class="widget-header">
                        <h3 class="widget-title">
                            <?= htmlspecialchars($widget['title']) ?>
                        </h3>
                        <div class="widget-actions">
                            {{-- Botão recarregar widget --}}
                            <button class="widget-action-btn"
                                    data-action="refresh-widget"
                                    data-url="/dashboard/widget/<?= htmlspecialchars($widget['key']) ?>"
                                    aria-label="Atualizar widget">
                                <i class="fas fa-sync-alt" aria-hidden="true"></i>
                            </button>
                            {{-- Botão ocultar widget --}}
                            <button class="widget-action-btn"
                                    data-action="hide-widget"
                                    data-key="<?= htmlspecialchars($widget['key']) ?>"
                                    aria-label="Ocultar widget">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="widget-body" data-loaded="1">
                        <?php
                            // Injeta os dados do widget no escopo do partial
                            $widgetData   = $widget['data'];
                            $widgetConfig = $widget['config'] ?? [];
                            require $widget['template'];
                        ?>
                    </div>
                </div>

            </div>
        <?php endforeach ?>

        <?php if (empty($widgets)): ?>
            <div class="dashboard-empty">
                <i class="fas fa-th-large" aria-hidden="true"></i>
                <p>Nenhum widget no dashboard.</p>
                <button class="btn btn-primary" data-action="open-widget-catalog">
                    Adicionar Widgets
                </button>
            </div>
        <?php endif ?>

    </div>{{-- /.dashboard-grid --}}

</div>{{-- /.dashboard-page --}}
