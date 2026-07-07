<?php
declare(strict_types=1);
require_once BASE_PATH . '/src/Modules/Board/BoardRepository.php';
require_once BASE_PATH . '/src/Modules/Board/GroupRepository.php';
require_once BASE_PATH . '/src/Modules/Board/ItemRepository.php';

$boardRepo  = new BoardRepository(pdo_master());
$groupRepo  = new GroupRepository(pdo_master());
$itemRepo   = new ItemRepository(pdo_master());

$board = $boardRepo->findById($boardId);
if (!$board) { http_response_code(404); require BASE_PATH . '/views/errors/404.php'; exit; }

// Permissão: board público ou membro
$userRole = $boardRepo->getMemberRole($boardId, (int)$_SESSION['user_id']);
if ($board['visibility'] !== 'public' && !$userRole) {
    flash_set('error', 'Sem permissão para acessar este board.');
    redirect('/boards');
}

$columns  = $boardRepo->getColumns($boardId);
$groups   = $groupRepo->allByBoard($boardId);
$boardData = $itemRepo->loadBoard($boardId);
$itemsByGroup = $boardData['items'];
$values       = $boardData['values'];

$activeView = $_GET['view'] ?? 'table'; // 'table' | 'kanban'
$baseUrl    = rtrim(env('APP_URL', ''), '/');
$flash      = flash_get();

// Coluna status para kanban
$statusColumn = null;
foreach ($columns as $col) {
    if ($col['type'] === 'status') { $statusColumn = $col; break; }
}

// Helpers de valor
function cell_value(array $values, int $itemId, int $colId, string $type): string {
    $v = $values[$itemId][$colId] ?? null;
    if (!$v) return '';
    return match($type) {
        'number', 'rating' => (string)($v['value_number'] ?? ''),
        'date', 'datetime' => $v['value_date'] ? date('d/m/Y', strtotime($v['value_date'])) : '',
        'checkbox'         => $v['value_number'] ? '✓' : '',
        'status', 'dropdown', 'text', 'email', 'phone', 'link', 'long_text' => (string)($v['value_text'] ?? ''),
        'person'           => $v['value_json'] ? json_decode($v['value_json'], true)['name'] ?? '' : '',
        default            => (string)($v['value_text'] ?? $v['value_number'] ?? ''),
    };
}

function cell_raw(array $values, int $itemId, int $colId): string {
    $v = $values[$itemId][$colId] ?? null;
    if (!$v) return '';
    return $v['value_text'] ?? $v['value_number'] ?? $v['value_date'] ?? $v['value_json'] ?? '';
}

function status_options(array $col): array {
    $s = json_decode($col['settings'] ?? '{}', true);
    return $s['options'] ?? [];
}

function status_color(array $col, string $slug): string {
    foreach (status_options($col) as $o) {
        if ($o['slug'] === $slug) return $o['color'];
    }
    return '#c4c4c4';
}

function status_label(array $col, string $slug): string {
    foreach (status_options($col) as $o) {
        if ($o['slug'] === $slug) return $o['label'];
    }
    return $slug;
}

// Para kanban: agrupa itens por valor da coluna status
$kanbanCols = [];
if ($activeView === 'kanban' && $statusColumn) {
    $options = status_options($statusColumn);
    foreach ($options as $opt) {
        $kanbanCols[$opt['slug']] = ['label' => $opt['label'], 'color' => $opt['color'], 'items' => []];
    }
    $kanbanCols['__none__'] = ['label' => 'Sem status', 'color' => '#c4c4c4', 'items' => []];

    foreach ($itemsByGroup as $gid => $gitems) {
        foreach ($gitems as $item) {
            $slug = cell_raw($values, (int)$item['id'], (int)$statusColumn['id']);
            $key  = isset($kanbanCols[$slug]) ? $slug : '__none__';
            $kanbanCols[$key]['items'][] = $item;
        }
    }
    // Remove coluna vazia
    foreach ($kanbanCols as $k => $kc) {
        if (empty($kc['items']) && $k === '__none__') unset($kanbanCols[$k]);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($board['name']) ?> — Conecta360</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --primary:#0073ea;--primary-dark:#0059b3;
  --sidebar-w:240px;--sidebar-bg:#1f2d3d;
  --topbar-h:56px;--board-header-h:56px;
  --text:#323338;--muted:#676879;--border:#e6e9ef;
  --bg:#f6f7fb;--white:#fff;--r:6px;
  --success:#00c875;--warning:#fdab3d;--danger:#e2445c;
}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);overflow:hidden}
/* Sidebar */
.sidebar{position:fixed;top:0;left:0;bottom:0;width:var(--sidebar-w);background:var(--sidebar-bg);display:flex;flex-direction:column;z-index:100;overflow-y:auto}
.sb-brand{padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,.08)}
.sb-brand h2{color:#fff;font-size:1.15rem;font-weight:700}
.sb-brand small{color:rgba(255,255,255,.4);font-size:.7rem}
.sb-nav{padding:.75rem 0;flex:1}
.sb-nav a{display:flex;align-items:center;gap:.6rem;padding:.6rem 1.5rem;color:rgba(255,255,255,.7);text-decoration:none;font-size:.88rem;transition:background .15s}
.sb-nav a:hover,.sb-nav a.active{background:rgba(255,255,255,.08);color:#fff}
.sb-nav a svg{width:16px;height:16px;flex-shrink:0}
.sb-footer{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.08)}
.sb-footer a{display:flex;align-items:center;gap:.5rem;color:rgba(255,255,255,.5);text-decoration:none;font-size:.82rem}
.sb-footer a:hover{color:#fff}
/* Topbar */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 1.25rem;gap:.75rem;z-index:90}
.topbar-back{color:var(--muted);text-decoration:none;display:flex;align-items:center;gap:.25rem;font-size:.82rem}
.topbar-back:hover{color:var(--text)}
.topbar-sep{color:var(--border);font-size:1.2rem}
.topbar-board-name{font-size:1rem;font-weight:600;color:var(--text)}
.topbar-spacer{flex:1}
.view-tabs{display:flex;gap:2px;background:#f0f1f4;padding:3px;border-radius:7px}
.view-tab{padding:.3rem .8rem;border-radius:5px;font-size:.8rem;font-weight:500;cursor:pointer;color:var(--muted);text-decoration:none;border:none;background:transparent;display:flex;align-items:center;gap:.35rem;transition:all .15s}
.view-tab.active,.view-tab:hover{background:var(--white);color:var(--text);box-shadow:0 1px 3px rgba(0,0,0,.1)}
.view-tab.active{color:var(--primary)}
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;border-radius:var(--r);font-size:.82rem;font-weight:500;cursor:pointer;text-decoration:none;border:none;transition:background .15s;white-space:nowrap}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:var(--primary-dark)}
.btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border)}
.btn-ghost:hover{border-color:#aaa;color:var(--text)}
.btn-icon{background:transparent;border:none;cursor:pointer;padding:.35rem;border-radius:var(--r);color:var(--muted);display:grid;place-items:center}
.btn-icon:hover{background:#f0f1f4;color:var(--text)}
/* Main */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);height:100vh;display:flex;flex-direction:column;overflow:hidden}
.board-toolbar{display:flex;align-items:center;gap:.75rem;padding:.6rem 1.25rem;border-bottom:1px solid var(--border);background:var(--white);flex-shrink:0}
.board-toolbar input[type=search]{padding:.4rem .8rem;border:1.5px solid var(--border);border-radius:var(--r);font-size:.82rem;width:200px;outline:none;background:#fafafa}
.board-toolbar input[type=search]:focus{border-color:var(--primary);background:var(--white)}
.board-content{flex:1;overflow:auto;padding:0}
/* Alert */
.flash-bar{padding:.65rem 1.25rem;font-size:.85rem;font-weight:500}
.flash-bar.success{background:#f0fff4;color:#1a7e3f;border-bottom:1px solid #b2dfdb}
.flash-bar.error{background:#fff0f0;color:#c0392b;border-bottom:1px solid #ffcdd2}
/* ════════ TABLE VIEW ════════ */
.table-wrap{min-width:100%}
.group-block{margin-bottom:0}
.group-header{display:flex;align-items:center;gap:.5rem;padding:.5rem 1rem .5rem .75rem;position:sticky;top:0;background:var(--bg);z-index:10;border-bottom:1px solid var(--border)}
.group-color-bar{width:4px;height:20px;border-radius:2px;flex-shrink:0}
.group-toggle{background:none;border:none;cursor:pointer;font-size:.75rem;color:var(--muted);padding:.1rem .3rem;border-radius:3px;transition:background .1s}
.group-toggle:hover{background:#e6e9ef}
.group-name{font-size:.88rem;font-weight:600;flex:1;cursor:pointer;padding:.1rem .25rem;border-radius:3px}
.group-name:hover{background:rgba(0,0,0,.04)}
.group-name[contenteditable=true]{outline:2px solid var(--primary);background:var(--white);padding:.1rem .25rem}
.group-count{font-size:.72rem;color:var(--muted);background:#f0f1f4;padding:.1rem .45rem;border-radius:100px}
/* Table */
.board-table{width:100%;border-collapse:collapse;table-layout:fixed}
.board-table thead th{position:sticky;top:40px;background:var(--bg);z-index:9;padding:.45rem .75rem;font-size:.75rem;font-weight:600;color:var(--muted);text-align:left;border-bottom:2px solid var(--border);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.board-table thead th:first-child{width:320px;min-width:220px}
.board-table thead th.col-add{width:48px;cursor:pointer;text-align:center}
.board-table thead th.col-add:hover{color:var(--primary)}
.board-table tbody tr{border-bottom:1px solid var(--border);transition:background .1s}
.board-table tbody tr:hover{background:rgba(0,115,234,.025)}
.board-table td{padding:0;vertical-align:middle;height:40px}
.cell-name{display:flex;align-items:center;gap:.5rem;padding:.3rem .75rem;height:100%}
.cell-name .item-bullet{width:6px;height:6px;border-radius:50%;background:var(--border);flex-shrink:0}
.cell-name .item-text{flex:1;font-size:.875rem;cursor:pointer;padding:.2rem .3rem;border-radius:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cell-name .item-text[contenteditable=true]{outline:2px solid var(--primary);background:var(--white);white-space:normal}
.cell-name .item-actions{display:none;gap:2px}
.board-table tbody tr:hover .item-actions{display:flex}
.cell-value{padding:.3rem .75rem;font-size:.82rem;color:var(--text);height:100%;display:flex;align-items:center;cursor:pointer;min-height:40px}
.cell-value:hover{background:rgba(0,115,234,.06)}
/* Status badge */
.status-badge{padding:.2rem .65rem;border-radius:100px;font-size:.72rem;font-weight:600;color:#fff;white-space:nowrap;cursor:pointer}
/* Add item */
.add-item-row td{padding:0;border-bottom:none}
.add-item-btn{display:flex;align-items:center;gap:.5rem;padding:.4rem .75rem;font-size:.82rem;color:var(--muted);cursor:pointer;width:100%;background:none;border:none;text-align:left;transition:background .1s}
.add-item-btn:hover{background:rgba(0,115,234,.04);color:var(--primary)}
.add-item-form{display:none;align-items:center;gap:.5rem;padding:.4rem .75rem}
.add-item-form.show{display:flex}
.add-item-form input{flex:1;padding:.35rem .6rem;border:1.5px solid var(--primary);border-radius:var(--r);font-size:.875rem;outline:none}
/* Add group */
.add-group-bar{padding:.75rem 1.25rem}
/* ════════ KANBAN VIEW ════════ */
.kanban-wrap{display:flex;gap:1rem;padding:1.25rem;align-items:flex-start;height:100%;overflow-x:auto}
.kanban-col{flex-shrink:0;width:270px;background:#f0f1f4;border-radius:10px;display:flex;flex-direction:column;max-height:calc(100vh - 160px)}
.kanban-col-header{padding:.75rem 1rem;display:flex;align-items:center;gap:.5rem;flex-shrink:0}
.kanban-col-color{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.kanban-col-name{font-size:.85rem;font-weight:600;flex:1}
.kanban-col-count{font-size:.72rem;color:var(--muted);background:rgba(0,0,0,.06);padding:.1rem .4rem;border-radius:100px}
.kanban-cards{flex:1;overflow-y:auto;padding:.25rem .75rem .75rem}
.kanban-card{background:var(--white);border-radius:8px;padding:.75rem;margin-bottom:.5rem;cursor:pointer;border:1px solid var(--border);box-shadow:0 1px 2px rgba(0,0,0,.04);transition:box-shadow .15s,transform .1s}
.kanban-card:hover{box-shadow:0 3px 12px rgba(0,0,0,.1);transform:translateY(-1px)}
.kanban-card-title{font-size:.85rem;font-weight:500;margin-bottom:.4rem}
.kanban-card-meta{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.kanban-card-date{font-size:.72rem;color:var(--muted)}
.kanban-card-assignee{font-size:.72rem;color:var(--muted);background:#e8f0fe;color:var(--primary);padding:.1rem .4rem;border-radius:100px}
/* Modals */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal{background:var(--white);border-radius:12px;padding:1.5rem;width:400px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal h3{font-size:1rem;font-weight:700;margin-bottom:1.25rem}
.modal .form-group{margin-bottom:1rem}
.modal label{display:block;font-size:.8rem;font-weight:600;margin-bottom:.35rem;color:var(--text)}
.modal input,.modal select{width:100%;padding:.6rem .8rem;border:1.5px solid var(--border);border-radius:var(--r);font-size:.875rem;outline:none}
.modal input:focus,.modal select:focus{border-color:var(--primary)}
.modal .modal-actions{display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.25rem}
/* Dropdown */
.status-dropdown{position:absolute;background:var(--white);border:1px solid var(--border);border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,.12);z-index:150;min-width:180px;padding:.3rem 0;display:none}
.status-dropdown.show{display:block}
.status-dropdown-item{display:flex;align-items:center;gap:.6rem;padding:.5rem .75rem;cursor:pointer;font-size:.82rem;transition:background .1s}
.status-dropdown-item:hover{background:#f6f7fb}
.status-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
</style>
</head>
<body>
<!-- Sidebar -->
<aside class="sidebar">
    <div class="sb-brand"><h2>Conecta360</h2><small>Gestão Hospitalar</small></div>
    <nav class="sb-nav">
        <a href="<?= $baseUrl ?>/dashboard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard</a>
        <a href="<?= $baseUrl ?>/boards" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>Meus Boards</a>
    </nav>
    <div class="sb-footer">
        <a href="<?= $baseUrl ?>/logout"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sair</a>
    </div>
</aside>

<!-- Topbar -->
<header class="topbar">
    <a href="<?= $baseUrl ?>/boards" class="topbar-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Boards
    </a>
    <span class="topbar-sep">/</span>
    <span class="topbar-board-name" style="color:<?= htmlspecialchars($board['color']) ?>">
        <?= htmlspecialchars($board['icon'] ?? '📋') ?>
        <?= htmlspecialchars($board['name']) ?>
    </span>
    <span class="topbar-spacer"></span>
    <div class="view-tabs">
        <a href="?view=table" class="view-tab <?= $activeView==='table'?'active':'' ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2h-4M9 3v18M9 3h6M15 3v18"/></svg>
            Tabela
        </a>
        <a href="?view=kanban" class="view-tab <?= $activeView==='kanban'?'active':'' ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="18"/><rect x="10" y="3" width="5" height="18"/><rect x="17" y="3" width="5" height="18"/></svg>
            Kanban
        </a>
    </div>
    <button class="btn btn-primary" onclick="openAddColumn()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Coluna
    </button>
    <button class="btn btn-ghost" onclick="openAddGroup()">+ Grupo</button>
</header>

<!-- Flash -->
<?php if ($flash): ?>
<div class="flash-bar <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif ?>

<!-- Main -->
<main class="main">
    <!-- Toolbar -->
    <div class="board-toolbar">
        <input type="search" id="searchInput" placeholder="🔍 Buscar itens..." oninput="filterItems(this.value)">
        <span style="font-size:.78rem;color:var(--muted)"><?= count($groups) ?> grupo<?= count($groups)!==1?'s':'' ?> · <?= array_sum(array_map('count',$itemsByGroup)) ?> itens</span>
    </div>

    <!-- Board Content -->
    <div class="board-content" id="boardContent">

    <?php if ($activeView === 'kanban'): ?>
    <!-- ══════════ KANBAN ══════════ -->
    <div class="kanban-wrap">
        <?php foreach ($kanbanCols as $slug => $kcol): ?>
        <div class="kanban-col" data-slug="<?= htmlspecialchars($slug) ?>">
            <div class="kanban-col-header">
                <div class="kanban-col-color" style="background:<?= htmlspecialchars($kcol['color']) ?>"></div>
                <span class="kanban-col-name"><?= htmlspecialchars($kcol['label']) ?></span>
                <span class="kanban-col-count"><?= count($kcol['items']) ?></span>
            </div>
            <div class="kanban-cards">
                <?php foreach ($kcol['items'] as $item): ?>
                <div class="kanban-card" data-item-id="<?= $item['id'] ?>">
                    <div class="kanban-card-title"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="kanban-card-meta">
                        <?php if (!empty($item['assignee_name'])): ?>
                            <span class="kanban-card-assignee">👤 <?= htmlspecialchars($item['assignee_name']) ?></span>
                        <?php endif ?>
                        <?php
                        $dateVal = '';
                        foreach ($columns as $col) {
                            if ($col['type'] === 'date') {
                                $v = $values[(int)$item['id']][(int)$col['id']] ?? null;
                                if ($v && $v['value_date']) { $dateVal = date('d/m/Y', strtotime($v['value_date'])); break; }
                            }
                        }
                        if ($dateVal): ?>
                            <span class="kanban-card-date">📅 <?= $dateVal ?></span>
                        <?php endif ?>
                    </div>
                </div>
                <?php endforeach ?>
            </div>
        </div>
        <?php endforeach ?>
        <?php if (empty($kanbanCols)): ?>
        <div style="padding:2rem;color:var(--muted);font-size:.875rem">Nenhum item encontrado. Adicione itens na visão de tabela.</div>
        <?php endif ?>
    </div>

    <?php else: ?>
    <!-- ══════════ TABELA ══════════ -->
    <div class="table-wrap">
        <table class="board-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <?php foreach ($columns as $col): ?>
                    <th style="width:<?= (int)($col['width'] ?? 160) ?>px"><?= htmlspecialchars($col['name']) ?></th>
                    <?php endforeach ?>
                    <th class="col-add" onclick="openAddColumn()" title="Adicionar coluna">＋</th>
                </tr>
            </thead>
            <tbody id="boardTableBody">
            <?php foreach ($groups as $group): ?>
                <?php $gItems = $itemsByGroup[(int)$group['id']] ?? [] ?>
                <!-- Group header row -->
                <tr class="group-header-row" data-group-id="<?= $group['id'] ?>">
                    <td colspan="<?= count($columns) + 2 ?>" style="padding:0;background:var(--bg)">
                        <div class="group-header">
                            <div class="group-color-bar" style="background:<?= htmlspecialchars($group['color']) ?>"></div>
                            <button class="group-toggle" onclick="toggleGroup(<?= $group['id'] ?>)" id="gtoggle-<?= $group['id'] ?>">▼</button>
                            <span class="group-name"
                                  data-group-id="<?= $group['id'] ?>"
                                  ondblclick="editGroupName(this)"
                                  onblur="saveGroupName(this)"
                                  onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur()}"
                            ><?= htmlspecialchars($group['name']) ?></span>
                            <span class="group-count"><?= count($gItems) ?></span>
                            <button class="btn-icon" style="margin-left:auto" onclick="deleteGroup(<?= $group['id'] ?>,this)" title="Excluir grupo">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <!-- Item rows -->
                <tbody id="group-body-<?= $group['id'] ?>" class="group-items">
                <?php foreach ($gItems as $item): ?>
                <tr data-item-id="<?= $item['id'] ?>" data-group-id="<?= $group['id'] ?>">
                    <td>
                        <div class="cell-name">
                            <div class="item-bullet" style="background:<?= htmlspecialchars($group['color']) ?>"></div>
                            <span class="item-text"
                                  data-item-id="<?= $item['id'] ?>"
                                  ondblclick="editItemName(this)"
                                  onblur="saveItemName(this)"
                                  onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur()}"
                            ><?= htmlspecialchars($item['name']) ?></span>
                            <div class="item-actions">
                                <button class="btn-icon" onclick="archiveItem(<?= $item['id'] ?>,this)" title="Arquivar">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                                </button>
                                <button class="btn-icon" onclick="deleteItem(<?= $item['id'] ?>,this)" title="Excluir" style="color:var(--danger)">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                                </button>
                            </div>
                        </div>
                    </td>
                    <?php foreach ($columns as $col): ?>
                    <td>
                        <?php
                        $raw   = cell_raw($values, (int)$item['id'], (int)$col['id']);
                        $disp  = cell_value($values, (int)$item['id'], (int)$col['id'], $col['type']);
                        $colId = (int)$col['id'];
                        $iid   = (int)$item['id'];
                        ?>
                        <?php if ($col['type'] === 'status' || $col['type'] === 'dropdown'): ?>
                            <?php $opts = status_options($col) ?>
                            <div class="cell-value" onclick="openStatusDropdown(event,<?= $iid ?>,<?= $colId ?>,'<?= htmlspecialchars(json_encode($opts)) ?>')">
                                <?php if ($raw): ?>
                                    <span class="status-badge" style="background:<?= htmlspecialchars(status_color($col,$raw)) ?>">
                                        <?= htmlspecialchars(status_label($col,$raw)) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:var(--muted);font-size:.75rem">—</span>
                                <?php endif ?>
                            </div>
                        <?php elseif ($col['type'] === 'date' || $col['type'] === 'datetime'): ?>
                            <div class="cell-value" style="position:relative">
                                <input type="date"
                                       style="border:none;background:transparent;font-size:.82rem;cursor:pointer;width:100%;outline:none;color:var(--text)"
                                       value="<?= htmlspecialchars($values[$iid][$colId]['value_date'] ? date('Y-m-d', strtotime($values[$iid][$colId]['value_date'])) : '') ?>"
                                       onchange="saveValue(<?= $iid ?>,<?= $colId ?>,'date',this.value)">
                            </div>
                        <?php elseif ($col['type'] === 'checkbox'): ?>
                            <div class="cell-value" style="justify-content:center">
                                <input type="checkbox"
                                       <?= $raw ? 'checked' : '' ?>
                                       onchange="saveValue(<?= $iid ?>,<?= $colId ?>,'number',this.checked?1:0)"
                                       style="width:16px;height:16px;cursor:pointer">
                            </div>
                        <?php elseif ($col['type'] === 'number' || $col['type'] === 'rating'): ?>
                            <div class="cell-value">
                                <input type="number"
                                       style="border:none;background:transparent;font-size:.82rem;width:100%;outline:none;color:var(--text)"
                                       value="<?= htmlspecialchars($values[$iid][$colId]['value_number'] ?? '') ?>"
                                       onblur="saveValue(<?= $iid ?>,<?= $colId ?>,'number',this.value)"
                                       onkeydown="if(event.key==='Enter')this.blur()"
                                       placeholder="—">
                            </div>
                        <?php else: ?>
                            <div class="cell-value">
                                <input type="text"
                                       style="border:none;background:transparent;font-size:.82rem;width:100%;outline:none;color:var(--text)"
                                       value="<?= htmlspecialchars($raw) ?>"
                                       onblur="saveValue(<?= $iid ?>,<?= $colId ?>,'text',this.value)"
                                       onkeydown="if(event.key==='Enter')this.blur()"
                                       placeholder="—">
                            </div>
                        <?php endif ?>
                    </td>
                    <?php endforeach ?>
                    <td></td>
                </tr>
                <?php endforeach ?>
                <!-- Add item row -->
                <tr class="add-item-row" data-group-id="<?= $group['id'] ?>">
                    <td colspan="<?= count($columns) + 2 ?>">
                        <div class="add-item-form" id="add-form-<?= $group['id'] ?>">
                            <input type="text" placeholder="Título do novo item..." id="add-input-<?= $group['id'] ?>"
                                   onkeydown="if(event.key==='Enter')quickAddItem(<?= $boardId ?>,<?= $group['id'] ?>);if(event.key==='Escape')cancelAdd(<?= $group['id'] ?>)">
                            <button class="btn btn-primary" onclick="quickAddItem(<?= $boardId ?>,<?= $group['id'] ?>)">Adicionar</button>
                            <button class="btn btn-ghost" onclick="cancelAdd(<?= $group['id'] ?>)">Cancelar</button>
                        </div>
                        <button class="add-item-btn" id="add-btn-<?= $group['id'] ?>" onclick="showAddForm(<?= $group['id'] ?>)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Adicionar item
                        </button>
                    </td>
                </tr>
                </tbody>
            <?php endforeach ?>
            </tbody>
        </table>

        <!-- Add group -->
        <div class="add-group-bar">
            <button class="btn btn-ghost" onclick="openAddGroup()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Adicionar grupo
            </button>
        </div>
    </div>
    <?php endif ?>

    </div><!-- /board-content -->
</main>

<!-- Modal: Adicionar Grupo -->
<div class="modal-overlay" id="groupModal">
    <div class="modal">
        <h3>Novo Grupo</h3>
        <div class="form-group">
            <label>Nome do grupo</label>
            <input type="text" id="newGroupName" placeholder="Ex: Em Andamento" onkeydown="if(event.key==='Enter')submitAddGroup()">
        </div>
        <div class="form-group">
            <label>Cor</label>
            <input type="color" id="newGroupColor" value="#579bfc" style="width:50px;height:36px;border:none;cursor:pointer;padding:0">
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal('groupModal')">Cancelar</button>
            <button class="btn btn-primary" onclick="submitAddGroup()">Criar</button>
        </div>
    </div>
</div>

<!-- Modal: Adicionar Coluna -->
<div class="modal-overlay" id="columnModal">
    <div class="modal">
        <h3>Nova Coluna</h3>
        <div class="form-group">
            <label>Nome da coluna</label>
            <input type="text" id="newColName" placeholder="Ex: Prioridade">
        </div>
        <div class="form-group">
            <label>Tipo</label>
            <select id="newColType">
                <option value="text">Texto</option>
                <option value="long_text">Texto longo</option>
                <option value="number">Número</option>
                <option value="date">Data</option>
                <option value="status">Status</option>
                <option value="person">Pessoa</option>
                <option value="checkbox">Checkbox</option>
                <option value="dropdown">Dropdown</option>
                <option value="email">E-mail</option>
                <option value="phone">Telefone</option>
                <option value="link">Link</option>
                <option value="rating">Avaliação</option>
                <option value="file">Arquivo</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal('columnModal')">Cancelar</button>
            <button class="btn btn-primary" onclick="submitAddColumn()">Criar</button>
        </div>
    </div>
</div>

<!-- Status Dropdown (global, positioned by JS) -->
<div id="statusDropdown" class="status-dropdown" onclick.stop="true"></div>

<script>
const CSRF  = '<?= csrf_token() ?>';
const BOARD = <?= $boardId ?>;

// ── Fetch helper ─────────────────────────────────────────────
async function api(url, data = {}) {
    data['_csrf'] = CSRF;
    const body = new URLSearchParams(data);
    const r = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
        body
    });
    return r.json();
}

// ── Item name inline edit ──────────────────────────────────────
function editItemName(el) {
    el.contentEditable = 'true';
    el.focus();
    const range = document.createRange();
    range.selectNodeContents(el);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
}
async function saveItemName(el) {
    el.contentEditable = 'false';
    const name = el.textContent.trim();
    const id   = el.dataset.itemId;
    if (!name) { el.textContent = el.dataset.orig || 'Item'; return; }
    await api(`/items/${id}/update`, { name });
}

// ── Group name inline edit ─────────────────────────────────────
function editGroupName(el) {
    el.contentEditable = 'true';
    el.focus();
}
async function saveGroupName(el) {
    el.contentEditable = 'false';
    const name = el.textContent.trim();
    const id   = el.dataset.groupId;
    if (!name) return;
    await api(`/groups/${id}/update`, { name });
}

// ── Toggle group ──────────────────────────────────────────────
function toggleGroup(gid) {
    const body = document.getElementById('group-body-' + gid);
    const btn  = document.getElementById('gtoggle-' + gid);
    const addRow = document.querySelector(`.add-item-row[data-group-id="${gid}"]`);
    const hidden = body.style.display === 'none';
    body.style.display = hidden ? '' : 'none';
    if (addRow) addRow.style.display = hidden ? '' : 'none';
    btn.textContent = hidden ? '▼' : '▶';
}

// ── Delete group ──────────────────────────────────────────────
async function deleteGroup(gid, btn) {
    if (!confirm('Excluir este grupo e todos os itens?')) return;
    const d = await api(`/groups/${gid}/delete`, {});
    if (d.ok) {
        const rows = document.querySelectorAll(`[data-group-id="${gid}"]`);
        rows.forEach(r => r.remove());
    }
}

// ── Quick add item ─────────────────────────────────────────────
function showAddForm(gid) {
    document.getElementById('add-btn-' + gid).style.display = 'none';
    const f = document.getElementById('add-form-' + gid);
    f.classList.add('show');
    document.getElementById('add-input-' + gid).focus();
}
function cancelAdd(gid) {
    document.getElementById('add-btn-' + gid).style.display = '';
    document.getElementById('add-form-' + gid).classList.remove('show');
    document.getElementById('add-input-' + gid).value = '';
}
async function quickAddItem(boardId, gid) {
    const input = document.getElementById('add-input-' + gid);
    const name  = input.value.trim();
    if (!name) return;
    const d = await api(`/boards/${boardId}/items/create`, { group_id: gid, name });
    if (d.id) {
        // Injeta nova linha na tabela
        const tbody = document.getElementById('group-body-' + gid);
        const addRow = document.querySelector(`.add-item-row[data-group-id="${gid}"]`);
        const tr = document.createElement('tr');
        tr.dataset.itemId  = d.id;
        tr.dataset.groupId = gid;
        const colCount = <?= count($columns) ?>;
        let cells = `<td><div class="cell-name">
            <div class="item-bullet"></div>
            <span class="item-text" data-item-id="${d.id}"
                ondblclick="editItemName(this)" onblur="saveItemName(this)"
                onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur()}"
            >${escHtml(name)}</span>
            <div class="item-actions">
                <button class="btn-icon" onclick="archiveItem(${d.id},this)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg></button>
                <button class="btn-icon" onclick="deleteItem(${d.id},this)" style="color:var(--danger)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg></button>
            </div>
        </div></td>`;
        for (let i = 0; i < colCount; i++) cells += `<td><div class="cell-value" style="color:var(--muted)">—</div></td>`;
        cells += '<td></td>';
        tr.innerHTML = cells;
        tbody.insertBefore(tr, addRow);
        input.value = '';
        input.focus();
    }
}

// ── Save cell value ────────────────────────────────────────────
async function saveValue(itemId, colId, fieldType, val) {
    const data = { column_id: colId };
    if (fieldType === 'text')   data['value_text']   = val;
    if (fieldType === 'number') data['value_number']  = val;
    if (fieldType === 'date')   data['value_date']    = val;
    await api(`/items/${itemId}/values`, data);
}

// ── Status dropdown ────────────────────────────────────────────
let currentStatusCell = null;
function openStatusDropdown(event, itemId, colId, optsJson) {
    event.stopPropagation();
    const opts  = JSON.parse(optsJson);
    const dd    = document.getElementById('statusDropdown');
    currentStatusCell = { itemId, colId, el: event.currentTarget };

    dd.innerHTML = opts.map(o =>
        `<div class="status-dropdown-item" onclick="pickStatus(${itemId},${colId},'${escHtml(o.slug)}','${escHtml(o.color)}','${escHtml(o.label)}')">
            <div class="status-dot" style="background:${escHtml(o.color)}"></div>
            ${escHtml(o.label)}
        </div>`
    ).join('') + `<div class="status-dropdown-item" onclick="pickStatus(${itemId},${colId},'','#c4c4c4','—')" style="color:var(--muted)">
        <div class="status-dot" style="background:#c4c4c4"></div>— Limpar
    </div>`;

    const rect = event.currentTarget.getBoundingClientRect();
    dd.style.top  = (rect.bottom + window.scrollY + 4) + 'px';
    dd.style.left = rect.left + 'px';
    dd.style.position = 'fixed';
    dd.classList.add('show');
}
async function pickStatus(itemId, colId, slug, color, label) {
    document.getElementById('statusDropdown').classList.remove('show');
    await saveValue(itemId, colId, 'text', slug);
    // Atualiza badge visual
    if (currentStatusCell) {
        const cell = currentStatusCell.el;
        cell.innerHTML = slug
            ? `<span class="status-badge" style="background:${escHtml(color)}">${escHtml(label)}</span>`
            : `<span style="color:var(--muted);font-size:.75rem">—</span>`;
    }
}
document.addEventListener('click', () => document.getElementById('statusDropdown').classList.remove('show'));

// ── Archive / Delete item ──────────────────────────────────────
async function archiveItem(id, btn) {
    if (!confirm('Arquivar este item?')) return;
    const d = await api(`/items/${id}/archive`, {});
    if (d.ok) btn.closest('tr').remove();
}
async function deleteItem(id, btn) {
    if (!confirm('Excluir permanentemente?')) return;
    const d = await api(`/items/${id}/delete`, {});
    if (d.ok) btn.closest('tr').remove();
}

// ── Modals ─────────────────────────────────────────────────────
function openAddGroup()  { document.getElementById('groupModal').classList.add('show'); document.getElementById('newGroupName').focus(); }
function openAddColumn() { document.getElementById('columnModal').classList.add('show'); document.getElementById('newColName').focus(); }
function closeModal(id)  { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
});

async function submitAddGroup() {
    const name  = document.getElementById('newGroupName').value.trim();
    const color = document.getElementById('newGroupColor').value;
    if (!name) return;
    const d = await api(`/boards/${BOARD}/groups/create`, { name, color });
    if (d.id) { closeModal('groupModal'); location.reload(); }
}
async function submitAddColumn() {
    const name = document.getElementById('newColName').value.trim();
    const type = document.getElementById('newColType').value;
    if (!name) return;
    const d = await api(`/boards/${BOARD}/columns/create`, { name, type });
    if (d.id) { closeModal('columnModal'); location.reload(); }
}

// ── Search ─────────────────────────────────────────────────────
function filterItems(q) {
    q = q.toLowerCase();
    document.querySelectorAll('[data-item-id]').forEach(tr => {
        const text = tr.querySelector('.item-text')?.textContent.toLowerCase() || '';
        tr.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}

// ── Escape HTML ────────────────────────────────────────────────
function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
</script>
</body>
</html>
