<?php
/**
 * DashboardController — Endpoint do Dashboard Principal
 *
 * Responsabilidades:
 *   - Receber GET /dashboard (protegido por AuthMiddleware)
 *   - Montar menu, widgets e dados de contexto
 *   - Passar tudo para a view/layout principal
 *   - Endpoints AJAX para atualizar layout e recarregar widgets individuais
 *
 * ROTAS:
 *   GET  /dashboard                  → index()         [AuthMiddleware]
 *   POST /dashboard/layout           → saveLayout()    [AuthMiddleware, CsrfMiddleware]
 *   POST /dashboard/layout/reset     → resetLayout()   [AuthMiddleware, CsrfMiddleware]
 *   GET  /dashboard/widget/{key}     → refreshWidget() [AuthMiddleware] — AJAX
 *
 * MONTAGEM DA VIEW:
 *   1. $user    = SessionService::getUser()
 *   2. $menu    = MenuService::buildForUser($user)
 *   3. $widgets = DashboardService::getWidgetsForUser($user)
 *   4. $tenant  = Application::getTenant()
 *   5. $context = ['page_title'=>'Início', 'active_menu'=>'home', 'breadcrumb'=>[...]]
 *   6. require views/layouts/main.php com as variáveis acima
 *
 * @package Conecta360\Modules\Dashboard
 */

declare(strict_types=1);

namespace Conecta360\Modules\Dashboard;

use Conecta360\Core\Application;
use Conecta360\Services\MenuService;
use Conecta360\Services\DashboardService;
use Conecta360\Services\SessionService;

final class DashboardController
{
    public function __construct(
        private readonly MenuService      $menuService,
        private readonly DashboardService $dashboardService
    ) {}

    /** GET /dashboard — renderiza o dashboard completo */
    public function index(): void { /* ... */ }

    /** POST /dashboard/layout — salva layout personalizado (AJAX) */
    public function saveLayout(): void { /* ... */ }

    /** POST /dashboard/layout/reset — restaura padrão do papel */
    public function resetLayout(): void { /* ... */ }

    /** GET /dashboard/widget/{key} — recarrega um widget individual (AJAX polling) */
    public function refreshWidget(array $params): void { /* ... */ }
}
