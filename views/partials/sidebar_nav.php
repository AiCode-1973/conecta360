<?php
/**
 * Partial: sidebar_nav.php
 *
 * Renderiza a árvore de menu lateral.
 *
 * VARIÁVEIS ESPERADAS (passadas pelo layout):
 *   $menuTree    → array hierárquico gerado pelo MenuService::buildForUser()
 *   $activeKey   → string — key do item ativo atual (ex: 'home', 'boards_my')
 *   $expandedGroups → array de keys de grupos expandidos (do user_menu_state)
 *
 * ESTRUTURA HTML:
 *   <nav>
 *     <ul class="menu-list">
 *       <li class="menu-group">         ← item sem link (grupo/seção)
 *         <span class="group-label">...</span>
 *         <ul class="submenu">
 *           <li class="menu-item [active] [has-badge]">
 *             <a href="...">
 *               <i class="icon">...</i>
 *               <span class="label">...</span>
 *               <span class="badge">3</span>   ← se badge > 0
 *             </a>
 *           </li>
 *         </ul>
 *       </li>
 *       <li class="menu-separator"></li>
 *       <li class="menu-item">           ← item raiz sem grupo
 *         ...
 *       </li>
 *     </ul>
 *   </nav>
 *
 * REGRAS DE RENDERIZAÇÃO:
 *   - is_separator=1 → <li class="menu-separator">
 *   - item sem route_key (grupo) → <li class="menu-group collapsible">
 *     com toggle via JS (data-group-key="workspaces_group")
 *   - item com children → expansível (caret)
 *   - item ativo → adiciona classe 'active' ao <li>
 *   - badge > 0 → exibe badge com a cor definida no catalog
 *   - sidebar collapsed → oculta labels, exibe só ícones + tooltips
 *
 * SEGURANÇA:
 *   - TODOS os valores de $menuTree vêm do MenuService (servidor)
 *   - NÃO interpolar nenhum dado de URL recebido do cliente
 *   - URLs geradas por Router::url() — validadas no backend
 *   - Usar htmlspecialchars() em todos os campos de texto
 */
?>
<nav class="sidebar-nav" role="navigation">
    <ul class="menu-list" role="menubar">
        <?php foreach ($menuTree as $item): ?>
            <?php if ($item['is_separator']): ?>
                <li class="menu-separator" role="separator" aria-hidden="true"></li>

            <?php elseif (empty($item['route_key']) && !empty($item['children'])): ?>
                {{-- GRUPO COLAPSÁVEL --}}
                <li class="menu-group <?= in_array($item['key'], $expandedGroups) ? 'expanded' : '' ?>"
                    data-group-key="<?= htmlspecialchars($item['key']) ?>">
                    <button class="group-toggle" aria-expanded="<?= in_array($item['key'], $expandedGroups) ? 'true' : 'false' ?>">
                        <?php if ($item['icon']): ?>
                            <i class="menu-icon <?= htmlspecialchars($item['icon']) ?>" aria-hidden="true"></i>
                        <?php endif ?>
                        <span class="group-label"><?= htmlspecialchars($item['label']) ?></span>
                        <i class="caret fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <ul class="submenu" role="menu">
                        <?php foreach ($item['children'] as $child): ?>
                            <li class="menu-item <?= $child['key'] === $activeKey ? 'active' : '' ?>"
                                role="none">
                                <a href="<?= htmlspecialchars($child['url'] ?? '#') ?>"
                                   role="menuitem"
                                   title="<?= htmlspecialchars($child['label']) ?>">
                                    <?php if ($child['icon']): ?>
                                        <i class="menu-icon <?= htmlspecialchars($child['icon']) ?>" aria-hidden="true"></i>
                                    <?php endif ?>
                                    <span class="menu-label"><?= htmlspecialchars($child['label']) ?></span>
                                    <?php if (!empty($child['badge']) && $child['badge'] > 0): ?>
                                        <span class="menu-badge"
                                              style="background:<?= htmlspecialchars($child['badge_color'] ?? '#e2445c') ?>">
                                            <?= (int)$child['badge'] > 99 ? '99+' : (int)$child['badge'] ?>
                                        </span>
                                    <?php endif ?>
                                </a>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </li>

            <?php else: ?>
                {{-- ITEM RAIZ (sem grupo) --}}
                <li class="menu-item <?= $item['key'] === $activeKey ? 'active' : '' ?>"
                    role="none">
                    <a href="<?= htmlspecialchars($item['url'] ?? '#') ?>"
                       role="menuitem"
                       <?= $item['is_external'] ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                       title="<?= htmlspecialchars($item['label']) ?>">
                        <?php if ($item['icon']): ?>
                            <i class="menu-icon <?= htmlspecialchars($item['icon']) ?>" aria-hidden="true"></i>
                        <?php endif ?>
                        <span class="menu-label"><?= htmlspecialchars($item['label']) ?></span>
                        <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
                            <span class="menu-badge"
                                  style="background:<?= htmlspecialchars($item['badge_color'] ?? '#e2445c') ?>">
                                <?= (int)$item['badge'] > 99 ? '99+' : (int)$item['badge'] ?>
                            </span>
                        <?php endif ?>
                    </a>
                </li>
            <?php endif ?>
        <?php endforeach ?>
    </ul>
</nav>
