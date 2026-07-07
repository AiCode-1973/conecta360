<?php
declare(strict_types=1);
require_once BASE_PATH . '/src/Modules/Board/BoardRepository.php';
$repo   = new BoardRepository(pdo_master());
$boards = $repo->allByUser((int)$_SESSION['user_id']);
$flash  = flash_get();

// Agrupa por workspace
$byWs = [];
foreach ($boards as $b) {
    $byWs[$b['workspace_name']][] = $b;
}

$baseUrl = rtrim(env('APP_URL', ''), '/');
$user    = ['name' => $_SESSION['user_name'], 'email' => $_SESSION['user_email']];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boards — Conecta360</title>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --primary:#0073ea;--sidebar-w:240px;--sidebar-bg:#1f2d3d;
            --topbar-h:56px;--text:#323338;--muted:#676879;--border:#e6e9ef;
            --bg:#f6f7fb;--white:#fff;--r:8px;
            --success:#00c875;--warning:#fdab3d;--danger:#e2445c;
        }
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text)}
        .sidebar{position:fixed;top:0;left:0;bottom:0;width:var(--sidebar-w);background:var(--sidebar-bg);display:flex;flex-direction:column;z-index:100;overflow-y:auto}
        .sb-brand{padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,.08)}
        .sb-brand h2{color:#fff;font-size:1.15rem;font-weight:700}
        .sb-brand small{color:rgba(255,255,255,.4);font-size:.7rem}
        .sb-nav{padding:.75rem 0;flex:1}
        .sb-nav a{display:flex;align-items:center;gap:.6rem;padding:.6rem 1.5rem;color:rgba(255,255,255,.7);text-decoration:none;font-size:.88rem;transition:background .15s}
        .sb-nav a:hover,.sb-nav a.active{background:rgba(255,255,255,.08);color:#fff}
        .sb-nav a svg{width:16px;height:16px;flex-shrink:0}
        .sb-section{padding:.25rem 1.5rem .1rem;font-size:.7rem;font-weight:600;text-transform:uppercase;color:rgba(255,255,255,.3);letter-spacing:.06em;margin-top:.5rem}
        .sb-footer{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.08)}
        .sb-footer a{display:flex;align-items:center;gap:.5rem;color:rgba(255,255,255,.5);text-decoration:none;font-size:.82rem}
        .sb-footer a:hover{color:#fff}
        .topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 1.5rem;gap:1rem;z-index:90}
        .topbar-title{font-size:1rem;font-weight:600;color:var(--text)}
        .topbar-spacer{flex:1}
        .btn{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1rem;border-radius:var(--r);font-size:.875rem;font-weight:500;cursor:pointer;text-decoration:none;border:none;transition:background .15s,opacity .15s}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-primary:hover{background:#0059b3}
        .btn-ghost{background:transparent;color:var(--primary);border:1.5px solid var(--primary)}
        .btn-ghost:hover{background:#e8f0fe}
        .main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h)}
        .page-content{padding:2rem}
        .alert{padding:.75rem 1rem;border-radius:var(--r);margin-bottom:1.25rem;font-size:.875rem;font-weight:500}
        .alert-success{background:#f0fff4;color:#1a7e3f;border-left:4px solid var(--success)}
        .alert-error{background:#fff0f0;color:#c0392b;border-left:4px solid var(--danger)}
        .ws-section{margin-bottom:2.5rem}
        .ws-header{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem}
        .ws-icon{width:32px;height:32px;border-radius:6px;background:var(--primary);display:grid;place-items:center;font-size:1rem;color:#fff}
        .ws-name{font-size:1rem;font-weight:600}
        .boards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem}
        .board-card{background:var(--white);border-radius:10px;border:1px solid var(--border);overflow:hidden;text-decoration:none;color:var(--text);transition:box-shadow .15s,transform .1s;display:block}
        .board-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.1);transform:translateY(-2px)}
        .board-card-top{height:8px}
        .board-card-body{padding:1rem}
        .board-card-name{font-size:.95rem;font-weight:600;margin-bottom:.35rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .board-card-meta{font-size:.78rem;color:var(--muted)}
        .board-card-footer{display:flex;align-items:center;justify-content:space-between;padding:.6rem 1rem;border-top:1px solid var(--border);font-size:.75rem;color:var(--muted)}
        .badge-type{background:#e8f0fe;color:var(--primary);padding:.15rem .5rem;border-radius:100px;font-size:.7rem;font-weight:600}
        .create-card{border:1.5px dashed var(--border);background:transparent;display:flex;align-items:center;justify-content:center;gap:.5rem;color:var(--primary);font-size:.875rem;font-weight:500;cursor:pointer;min-height:100px;border-radius:10px;text-decoration:none;transition:background .15s}
        .create-card:hover{background:#e8f0fe}
        .empty-state{text-align:center;padding:4rem 2rem;color:var(--muted)}
        .empty-state h2{font-size:1.25rem;margin-bottom:.5rem;color:var(--text)}
    </style>
</head>
<body>
<!-- Sidebar -->
<aside class="sidebar">
    <div class="sb-brand">
        <h2>Conecta360</h2>
        <small>Gestão Hospitalar</small>
    </div>
    <nav class="sb-nav">
        <a href="<?= $baseUrl ?>/dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="<?= $baseUrl ?>/boards" class="active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
            Meus Boards
        </a>
    </nav>
    <div class="sb-footer">
        <a href="<?= $baseUrl ?>/logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sair
        </a>
    </div>
</aside>

<!-- Topbar -->
<header class="topbar">
    <span class="topbar-title">Boards</span>
    <span class="topbar-spacer"></span>
    <a href="<?= $baseUrl ?>/boards/create" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Novo Board
    </a>
</header>

<!-- Main -->
<main class="main">
    <div class="page-content">
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif ?>

        <?php if (empty($boards)): ?>
            <div class="empty-state">
                <div style="font-size:3rem;margin-bottom:1rem">📋</div>
                <h2>Nenhum board encontrado</h2>
                <p style="margin-bottom:1.5rem">Crie seu primeiro board para começar a organizar tarefas</p>
                <a href="<?= $baseUrl ?>/boards/create" class="btn btn-primary">Criar primeiro board</a>
            </div>
        <?php else: ?>
            <?php foreach ($byWs as $wsName => $wsBoards): ?>
            <div class="ws-section">
                <div class="ws-header">
                    <div class="ws-icon">🏥</div>
                    <span class="ws-name"><?= htmlspecialchars($wsName) ?></span>
                    <span class="badge-type"><?= count($wsBoards) ?> board<?= count($wsBoards) > 1 ? 's' : '' ?></span>
                </div>
                <div class="boards-grid">
                    <?php foreach ($wsBoards as $b): ?>
                    <a href="<?= $baseUrl ?>/boards/<?= $b['id'] ?>" class="board-card">
                        <div class="board-card-top" style="background:<?= htmlspecialchars($b['color']) ?>"></div>
                        <div class="board-card-body">
                            <div class="board-card-name">
                                <?= htmlspecialchars($b['icon'] ?? '📋') ?>
                                <?= htmlspecialchars($b['name']) ?>
                            </div>
                            <?php if ($b['description']): ?>
                            <div class="board-card-meta"><?= htmlspecialchars(mb_substr($b['description'], 0, 60)) ?>…</div>
                            <?php endif ?>
                        </div>
                        <div class="board-card-footer">
                            <span><?= (int)$b['item_count'] ?> itens</span>
                            <span class="badge-type"><?= htmlspecialchars(ucfirst($b['visibility'])) ?></span>
                        </div>
                    </a>
                    <?php endforeach ?>
                    <a href="<?= $baseUrl ?>/boards/create" class="create-card">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Novo Board
                    </a>
                </div>
            </div>
            <?php endforeach ?>
        <?php endif ?>
    </div>
</main>
</body>
</html>
