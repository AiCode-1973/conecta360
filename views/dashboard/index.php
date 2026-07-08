<?php
/**
 * Dashboard — Página inicial após login
 */
declare(strict_types=1);

$user = [
    'id'    => $_SESSION['user_id'],
    'name'  => $_SESSION['user_name'],
    'email' => $_SESSION['user_email'],
];

try {
    $pdo = pdo_master();

    $stmtTasks = $pdo->prepare('SELECT COUNT(*) FROM items WHERE assignee_id = ? AND deleted_at IS NULL AND is_archived = 0');
    $stmtTasks->execute([$user['id']]);
    $myTasksCount = (int)$stmtTasks->fetchColumn();

    $stmtNotif = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmtNotif->execute([$user['id']]);
    $unreadNotifs = (int)$stmtNotif->fetchColumn();

    $stmtBoards = $pdo->prepare(
        'SELECT COUNT(*) FROM boards b WHERE b.deleted_at IS NULL
         AND (b.created_by = ? OR EXISTS (SELECT 1 FROM board_members bm WHERE bm.board_id = b.id AND bm.user_id = ?))'
    );
    $stmtBoards->execute([$user['id'], $user['id']]);
    $totalBoards = (int)$stmtBoards->fetchColumn();

    $stmtUsers = $pdo->query('SELECT COUNT(*) FROM users WHERE status = "active" AND deleted_at IS NULL');
    $totalUsers = (int)$stmtUsers->fetchColumn();

    $stmtActivity = $pdo->query(
        'SELECT al.action, al.entity_type, al.created_at, u.name as user_name
         FROM activity_logs al
         LEFT JOIN users u ON u.id = al.user_id
         ORDER BY al.created_at DESC LIMIT 8'
    );
    $recentActivity = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);

    $stmtMyBoards = $pdo->prepare(
        'SELECT b.id, b.name, b.type, b.color,
                (SELECT COUNT(*) FROM items i WHERE i.board_id = b.id AND i.deleted_at IS NULL) as item_count
         FROM boards b
         WHERE b.deleted_at IS NULL
           AND (b.created_by = ? OR EXISTS (SELECT 1 FROM board_members bm WHERE bm.board_id = b.id AND bm.user_id = ?))
         ORDER BY b.updated_at DESC LIMIT 6'
    );
    $stmtMyBoards->execute([$user['id'], $user['id']]);
    $myBoards = $stmtMyBoards->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('[dashboard] ' . $e->getMessage());
    $myTasksCount = $unreadNotifs = $totalBoards = $totalUsers = 0;
    $recentActivity = $myBoards = [];
}

$flash     = flash_get();
$firstName = explode(' ', $user['name'])[0];
$baseUrl   = rtrim(env('APP_URL', ''), '/');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Conecta360</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary:#0073ea; --primary-dark:#0059b3;
            --sidebar-w:240px; --sidebar-bg:#1f2d3d; --topbar-h:56px;
            --text:#323338; --muted:#676879; --border:#e6e9ef;
            --bg:#f6f7fb; --white:#fff;
            --success:#00c875; --warning:#fdab3d; --danger:#e2445c;
            --r:8px;
        }
        body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:var(--bg); color:var(--text); }

        /* Sidebar */
        .sidebar { position:fixed; top:0; left:0; bottom:0; width:var(--sidebar-w); background:var(--sidebar-bg); display:flex; flex-direction:column; z-index:100; overflow-y:auto; }
        .sb-brand { padding:1.25rem 1.5rem; border-bottom:1px solid rgba(255,255,255,.08); }
        .sb-brand h2 { color:#fff; font-size:1.15rem; font-weight:700; }
        .sb-brand small { color:rgba(255,255,255,.4); font-size:.7rem; }
        .sb-nav { flex:1; padding:.5rem 0; }
        .nav-section { padding:.6rem 1.25rem .2rem; font-size:.65rem; font-weight:700; color:rgba(255,255,255,.3); letter-spacing:.1em; text-transform:uppercase; }
        .nav-item a { display:flex; align-items:center; gap:.7rem; padding:.55rem 1.5rem; color:rgba(255,255,255,.65); text-decoration:none; font-size:.86rem; border-left:3px solid transparent; transition:all .15s; }
        .nav-item a:hover { background:rgba(255,255,255,.06); color:#fff; }
        .nav-item.active a { background:rgba(0,115,234,.22); color:#fff; border-left-color:var(--primary); }
        .n-badge { margin-left:auto; background:var(--danger); color:#fff; font-size:.65rem; font-weight:700; padding:.1rem .4rem; border-radius:99px; }
        .sb-footer { padding:1rem 1.25rem; border-top:1px solid rgba(255,255,255,.08); }
        .u-info { display:flex; align-items:center; gap:.65rem; }
        .u-av { width:32px; height:32px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.82rem; flex-shrink:0; }
        .u-det { flex:1; min-width:0; }
        .u-det strong { display:block; color:#fff; font-size:.8rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .u-det span { color:rgba(255,255,255,.4); font-size:.7rem; }
        .lnk-logout { display:block; margin-top:.6rem; color:rgba(255,255,255,.35); font-size:.75rem; text-decoration:none; }
        .lnk-logout:hover { color:var(--danger); }

        /* Main */
        .main { margin-left:var(--sidebar-w); min-height:100vh; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; height:var(--topbar-h); background:var(--white); border-bottom:1px solid var(--border); display:flex; align-items:center; gap:1rem; padding:0 1.5rem; }
        .topbar-title { font-size:1.05rem; font-weight:600; flex:1; }
        .btn { display:inline-flex; align-items:center; gap:.35rem; padding:.45rem .9rem; border-radius:var(--r); font-size:.82rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; transition:all .15s; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-primary:hover { background:var(--primary-dark); }
        .btn-outline { background:transparent; color:var(--primary); border:1.5px solid var(--primary); }
        .btn-outline:hover { background:var(--primary); color:#fff; }

        .page { padding:1.5rem; flex:1; }
        .alert { border-radius:var(--r); padding:.8rem 1rem; margin-bottom:1.25rem; font-size:.88rem; font-weight:500; }
        .alert-error { background:#fff0f0; color:#c0392b; border-left:4px solid var(--danger); }
        .alert-success { background:#f0fff4; color:#1a7e3f; border-left:4px solid var(--success); }
        .ph { margin-bottom:1.5rem; }
        .ph h1 { font-size:1.4rem; font-weight:700; }
        .ph p { color:var(--muted); font-size:.87rem; margin-top:.2rem; }

        /* Stats */
        .stats { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:1rem; margin-bottom:1.5rem; }
        .sc { background:var(--white); border-radius:var(--r); padding:1.1rem 1.25rem; border:1px solid var(--border); }
        .sc-lbl { font-size:.72rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; }
        .sc-val { font-size:1.9rem; font-weight:700; line-height:1.1; margin:.25rem 0 .15rem; }
        .sc-sub { font-size:.75rem; color:var(--muted); }

        /* Grid */
        .dg { display:grid; grid-template-columns:1fr 320px; gap:1.25rem; }
        @media(max-width:900px){ .dg{grid-template-columns:1fr;} }
        .card { background:var(--white); border-radius:var(--r); border:1px solid var(--border); overflow:hidden; margin-bottom:1.25rem; }
        .ch { padding:.9rem 1.1rem; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
        .ch h3 { font-size:.9rem; font-weight:700; }
        .cb { padding:1.1rem; }
        .cl { font-size:.78rem; color:var(--primary); text-decoration:none; font-weight:600; }
        .cl:hover { text-decoration:underline; }

        /* Boards */
        .bg { display:grid; grid-template-columns:repeat(auto-fill,minmax(155px,1fr)); gap:.7rem; }
        .bc { border-radius:var(--r); padding:.85rem .9rem; text-decoration:none; color:var(--text); border:1px solid var(--border); background:var(--white); transition:box-shadow .15s,transform .15s; display:block; }
        .bc:hover { box-shadow:0 4px 14px rgba(0,0,0,.1); transform:translateY(-2px); }
        .bc-bar { height:3px; border-radius:2px; margin-bottom:.55rem; }
        .bc strong { display:block; font-size:.84rem; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .bc small { color:var(--muted); font-size:.72rem; }
        .bc-new { border:1.5px dashed var(--border); display:flex; align-items:center; justify-content:center; gap:.4rem; color:var(--muted); min-height:68px; text-decoration:none; border-radius:var(--r); font-size:.82rem; transition:all .15s; }
        .bc-new:hover { border-color:var(--primary); color:var(--primary); }

        /* Activity */
        .al { list-style:none; }
        .ai { display:flex; gap:.65rem; padding:.6rem 0; border-bottom:1px solid var(--border); font-size:.8rem; }
        .ai:last-child { border:none; }
        .ad { width:7px; height:7px; border-radius:50%; background:var(--primary); margin-top:.35rem; flex-shrink:0; }
        .at { color:var(--muted); font-size:.72rem; margin-top:.1rem; }
        .empty { text-align:center; padding:1.5rem; color:var(--muted); font-size:.85rem; }

        @media(max-width:768px){ .sidebar{transform:translateX(-100%);} .main{margin-left:0;} .stats{grid-template-columns:repeat(2,1fr);} }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand">
        <h2>Conecta360</h2>
        <small>Gestão hospitalar</small>
    </div>

    <nav class="sb-nav">
        <p class="nav-section">Principal</p>
        <div class="nav-item active"><a href="<?= $baseUrl ?>/dashboard">🏠&nbsp; Dashboard</a></div>
        <div class="nav-item"><a href="<?= $baseUrl ?>/boards">📋&nbsp; Meus Boards</a></div>
        <div class="nav-item">
            <a href="<?= $baseUrl ?>/boards">✅&nbsp; Minhas Tarefas
                <?php if ($myTasksCount > 0): ?><span class="n-badge"><?= min($myTasksCount, 99) ?></span><?php endif ?>
            </a>
        </div>
        <div class="nav-item"><a href="<?= $baseUrl ?>/boards?view=calendar">📅&nbsp; Calendário</a></div>
        <div class="nav-item">
            <a href="<?= $baseUrl ?>/notifications">🔔&nbsp; Notificações
                <?php if ($unreadNotifs > 0): ?><span class="n-badge"><?= min($unreadNotifs, 99) ?></span><?php endif ?>
            </a>
        </div>

        <p class="nav-section" style="margin-top:.5rem">Análises</p>
        <div class="nav-item"><a href="<?= $baseUrl ?>/reports">📊&nbsp; Relatórios</a></div>
        <div class="nav-item"><a href="<?= $baseUrl ?>/automations">🤖&nbsp; Automações</a></div>

        <p class="nav-section" style="margin-top:.5rem">Administração</p>
        <div class="nav-item"><a href="<?= $baseUrl ?>/users">👥&nbsp; Usuários</a></div>
        <div class="nav-item"><a href="<?= $baseUrl ?>/settings">⚙️&nbsp; Configurações</a></div>
        <div class="nav-item"><a href="<?= $baseUrl ?>/audit">🛡️&nbsp; Auditoria</a></div>
    </nav>

    <div class="sb-footer">
        <div class="u-info">
            <div class="u-av"><?= strtoupper(mb_substr($user['name'], 0, 1)) ?></div>
            <div class="u-det">
                <strong><?= htmlspecialchars($user['name']) ?></strong>
                <span><?= htmlspecialchars($user['email']) ?></span>
            </div>
        </div>
        <a href="<?= $baseUrl ?>/logout" class="lnk-logout">🚪 Sair da conta</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <span class="topbar-title">Dashboard</span>
        <a href="<?= $baseUrl ?>/boards" class="btn btn-outline">📋 Boards</a>
        <a href="<?= $baseUrl ?>/boards/create" class="btn btn-primary">+ Novo Board</a>
    </header>

    <div class="page">

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                <?= htmlspecialchars($flash['msg']) ?>
            </div>
        <?php endif ?>

        <div class="ph">
            <h1>Olá, <?= htmlspecialchars($firstName) ?> 👋</h1>
            <p><?= date('d/m/Y') ?> — Bem-vindo ao Conecta360</p>
        </div>

        <!-- Estatísticas -->
        <div class="stats">
            <div class="sc">
                <div class="sc-lbl">Minhas Tarefas</div>
                <div class="sc-val"><?= $myTasksCount ?></div>
                <div class="sc-sub">atribuídas a mim</div>
            </div>
            <div class="sc">
                <div class="sc-lbl">Boards Ativos</div>
                <div class="sc-val"><?= $totalBoards ?></div>
                <div class="sc-sub">no workspace</div>
            </div>
            <div class="sc">
                <div class="sc-lbl">Usuários</div>
                <div class="sc-val"><?= $totalUsers ?></div>
                <div class="sc-sub">ativos no sistema</div>
            </div>
            <div class="sc">
                <div class="sc-lbl">Notificações</div>
                <div class="sc-val"><?= $unreadNotifs ?></div>
                <div class="sc-sub">não lidas</div>
            </div>
        </div>

        <div class="dg">
            <!-- Coluna principal: Boards -->
            <div>
                <div class="card">
                    <div class="ch">
                        <h3>Meus Boards</h3>
                        <a href="<?= $baseUrl ?>/boards" class="cl">Ver todos →</a>
                    </div>
                    <div class="cb">
                        <?php if (empty($myBoards)): ?>
                            <div class="empty">📋 Nenhum board ainda. <a href="<?= $baseUrl ?>/boards/create" style="color:var(--primary)">Crie o primeiro!</a></div>
                        <?php else: ?>
                            <div class="bg">
                                <?php foreach ($myBoards as $b): ?>
                                    <a href="<?= $baseUrl ?>/boards/<?= $b['id'] ?>" class="bc">
                                        <div class="bc-bar" style="background:<?= htmlspecialchars($b['color'] ?? '#0073ea') ?>"></div>
                                        <strong><?= htmlspecialchars($b['name']) ?></strong>
                                        <small><?= (int)$b['item_count'] ?> ite<?= $b['item_count'] != 1 ? 'ns' : 'm' ?></small>
                                    </a>
                                <?php endforeach ?>
                                <a href="<?= $baseUrl ?>/boards/create" class="bc-new">+ Novo Board</a>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>

            <!-- Coluna lateral: Atividade -->
            <div>
                <div class="card">
                    <div class="ch"><h3>Atividade Recente</h3></div>
                    <div class="cb" style="padding:0 1rem">
                        <?php if (empty($recentActivity)): ?>
                            <div class="empty">📝 Nenhuma atividade ainda.</div>
                        <?php else: ?>
                            <ul class="al">
                                <?php foreach ($recentActivity as $a): ?>
                                    <li class="ai">
                                        <div class="ad"></div>
                                        <div>
                                            <div><strong><?= htmlspecialchars($a['user_name'] ?? 'Sistema') ?></strong>
                                            <?= htmlspecialchars($a['action']) ?>
                                            <em><?= htmlspecialchars($a['entity_type']) ?></em></div>
                                            <div class="at"><?= date('d/m H:i', strtotime($a['created_at'])) ?></div>
                                        </div>
                                    </li>
                                <?php endforeach ?>
                            </ul>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.page -->
</div><!-- /.main -->

</body>
</html>
