<?php
declare(strict_types=1);
require_once BASE_PATH . '/src/Modules/Board/BoardRepository.php';
$repo      = new BoardRepository(pdo_master());
$userId    = (int)$_SESSION['user_id'];
$boards    = $repo->allByUser($userId);
$userWs    = $repo->allWorkspacesByUser($userId);
$flash     = flash_get();

// Agrupa boards por workspace_id
$byWsId = [];
foreach ($boards as $b) {
    $byWsId[(int)$b['workspace_id']][] = $b;
}

// Monta mapa de workspaces do usuário indexado por id
$wsMap = [];
foreach ($userWs as $w) {
    $wsMap[(int)$w['id']] = $w;
}

// Workspaces que o usuário pode gerenciar (criador ou owner/admin)
$manageableWs = [];
foreach ($userWs as $w) {
    $manageableWs[(int)$w['id']] = ((int)$w['created_by'] === $userId);
}

$baseUrl = rtrim(env('APP_URL', ''), '/');
$csrfToken = csrf_token();
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
        .btn-danger{background:var(--danger);color:#fff}
        .btn-danger:hover{background:#c0392b}
        .btn-sm{padding:.3rem .65rem;font-size:.78rem}
        .main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h)}
        .page-content{padding:2rem}
        .alert{padding:.75rem 1rem;border-radius:var(--r);margin-bottom:1.25rem;font-size:.875rem;font-weight:500}
        .alert-success{background:#f0fff4;color:#1a7e3f;border-left:4px solid var(--success)}
        .alert-error{background:#fff0f0;color:#c0392b;border-left:4px solid var(--danger)}
        .ws-section{margin-bottom:2.5rem}
        .ws-header{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap}
        .ws-icon{width:32px;height:32px;border-radius:6px;background:var(--primary);display:grid;place-items:center;font-size:1rem;color:#fff;flex-shrink:0}
        .ws-name{font-size:1rem;font-weight:600}
        .ws-header-spacer{flex:1}
        .ws-members-btn{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .75rem;border-radius:var(--r);font-size:.78rem;font-weight:500;cursor:pointer;background:var(--bg);border:1.5px solid var(--border);color:var(--muted);transition:border-color .15s,color .15s}
        .ws-members-btn:hover{border-color:var(--primary);color:var(--primary)}
        .member-avatar{width:26px;height:26px;border-radius:50%;background:var(--primary);color:#fff;display:inline-grid;place-items:center;font-size:.7rem;font-weight:700;margin-left:-6px;border:2px solid var(--white)}
        .member-avatar:first-child{margin-left:0}
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
        /* Modal */
        .modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center}
        .modal-backdrop.open{display:flex}
        .modal{background:var(--white);border-radius:12px;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden;max-height:90vh;display:flex;flex-direction:column}
        .modal-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
        .modal-title{font-size:1rem;font-weight:700}
        .modal-close{background:none;border:none;cursor:pointer;color:var(--muted);font-size:1.2rem;padding:.25rem;border-radius:4px}
        .modal-close:hover{background:var(--bg)}
        .modal-body{padding:1.5rem;overflow-y:auto;flex:1}
        .modal-footer{padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:.75rem}
        .members-list{display:flex;flex-direction:column;gap:.5rem;margin-bottom:1.25rem}
        .member-row{display:flex;align-items:center;gap:.75rem;padding:.6rem .75rem;border-radius:var(--r);background:var(--bg);border:1px solid var(--border)}
        .member-info{flex:1;min-width:0}
        .member-name{font-size:.875rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .member-email{font-size:.75rem;color:var(--muted)}
        .role-badge{padding:.2rem .5rem;border-radius:4px;font-size:.7rem;font-weight:600}
        .role-owner{background:#fff0e0;color:#b45309}
        .role-admin{background:#e8f0fe;color:var(--primary)}
        .role-member{background:#f0fff4;color:#1a7e3f}
        .role-viewer{background:#f8f8f8;color:var(--muted)}
        .invite-form{display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap}
        .invite-form select,.invite-form input{flex:1;padding:.55rem .75rem;border:1.5px solid var(--border);border-radius:var(--r);font-size:.875rem;min-width:0}
        .invite-form select:focus,.invite-form input:focus{outline:none;border-color:var(--primary)}
        label.field-label{display:block;font-size:.78rem;font-weight:600;margin-bottom:.3rem;color:var(--text)}
        .empty-members{text-align:center;padding:1.5rem;color:var(--muted);font-size:.875rem}
        #modalMsg{font-size:.82rem;margin-bottom:.75rem;padding:.5rem .75rem;border-radius:6px;display:none}
        #modalMsg.ok{background:#f0fff4;color:#1a7e3f;display:block}
        #modalMsg.err{background:#fff0f0;color:#c0392b;display:block}
    </style>
</head>
<body>
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
        <a href="<?= $baseUrl ?>/users">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            Usuários
        </a>
    </nav>
    <div class="sb-footer">
        <a href="<?= $baseUrl ?>/logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sair
        </a>
    </div>
</aside>

<header class="topbar">
    <span class="topbar-title">Boards</span>
    <span class="topbar-spacer"></span>
    <a href="<?= $baseUrl ?>/boards/create" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Novo Board
    </a>
</header>

<main class="main">
    <div class="page-content">
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif ?>

        <?php if (empty($userWs)): ?>
            <div class="empty-state">
                <div style="font-size:3rem;margin-bottom:1rem">📋</div>
                <h2>Nenhum board encontrado</h2>
                <p style="margin-bottom:1.5rem">Crie seu primeiro board ou peça para ser convidado a um workspace</p>
                <a href="<?= $baseUrl ?>/boards/create" class="btn btn-primary">Criar primeiro board</a>
            </div>
        <?php else: ?>
            <?php foreach ($userWs as $ws):
                $wsId    = (int)$ws['id'];
                $wsBoards = $byWsId[$wsId] ?? [];
                $canManage = $manageableWs[$wsId] ?? false;
            ?>
            <div class="ws-section">
                <div class="ws-header">
                    <div class="ws-icon">🏥</div>
                    <span class="ws-name"><?= htmlspecialchars($ws['name']) ?></span>
                    <span class="badge-type"><?= count($wsBoards) ?> board<?= count($wsBoards) !== 1 ? 's' : '' ?></span>
                    <span class="ws-header-spacer"></span>
                    <?php if ($canManage): ?>
                    <button class="ws-members-btn" onclick="openMembersModal(<?= $wsId ?>, <?= htmlspecialchars(json_encode($ws['name'])) ?>)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        Gerenciar Equipe
                    </button>
                    <?php endif ?>
                </div>
                <?php if (empty($wsBoards)): ?>
                    <div style="color:var(--muted);font-size:.875rem;padding:.5rem 0 1rem">
                        Nenhum board neste workspace ainda.
                        <a href="<?= $baseUrl ?>/boards/create" style="color:var(--primary)">Criar board</a>
                    </div>
                <?php else: ?>
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
                <?php endif ?>
            </div>
            <?php endforeach ?>
        <?php endif ?>
    </div>
</main>

<!-- Modal Gerenciar Equipe -->
<div class="modal-backdrop" id="membersModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Equipe do Workspace</span>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <div id="modalMsg"></div>
            <div id="membersListWrap">
                <div class="empty-members">Carregando...</div>
            </div>

            <div id="inviteSection" style="display:none;margin-top:1.25rem">
                <p class="field-label" style="font-size:.88rem;font-weight:700;margin-bottom:.75rem">Convidar pessoa</p>
                <div class="invite-form">
                    <div style="flex:2;min-width:180px">
                        <label class="field-label">Usuário</label>
                        <select id="inviteUserId" style="width:100%;padding:.55rem .75rem;border:1.5px solid var(--border);border-radius:8px;font-size:.875rem">
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                    <div style="flex:1;min-width:120px">
                        <label class="field-label">Papel</label>
                        <select id="inviteRole" style="width:100%;padding:.55rem .75rem;border:1.5px solid var(--border);border-radius:8px;font-size:.875rem">
                            <option value="member">Membro</option>
                            <option value="admin">Admin</option>
                            <option value="viewer">Visualizador</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" onclick="inviteMember()" style="align-self:flex-end">Convidar</button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal()">Fechar</button>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;
const BASE = <?= json_encode($baseUrl) ?>;
let currentWsId = null;
let allUsers    = [];

function openMembersModal(wsId, wsName) {
    currentWsId = wsId;
    document.getElementById('modalTitle').textContent = 'Equipe — ' + wsName;
    document.getElementById('membersListWrap').innerHTML = '<div class="empty-members">Carregando...</div>';
    document.getElementById('inviteSection').style.display = 'none';
    document.getElementById('modalMsg').className = '';
    document.getElementById('modalMsg').textContent = '';
    document.getElementById('membersModal').classList.add('open');
    loadMembers(wsId);
}

function closeModal() {
    document.getElementById('membersModal').classList.remove('open');
    currentWsId = null;
}

document.getElementById('membersModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

async function loadMembers(wsId) {
    try {
        const r = await fetch(`${BASE}/workspaces/${wsId}/members`, {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        const data = await r.json();
        if (!r.ok) { showMsg(data.error || 'Erro ao carregar', 'err'); return; }

        allUsers = data.all_users || [];
        renderMembers(data.members || [], data.workspace);
        renderInviteSelect(data.members || []);
        document.getElementById('inviteSection').style.display = '';
    } catch(e) {
        showMsg('Erro de conexão', 'err');
    }
}

const ROLE_LABELS = {owner:'Owner', admin:'Admin', member:'Membro', viewer:'Visualizador'};
const ROLE_CLASSES = {owner:'role-owner', admin:'role-admin', member:'role-member', viewer:'role-viewer'};

function renderMembers(members, ws) {
    const wrap = document.getElementById('membersListWrap');
    if (!members.length) {
        wrap.innerHTML = '<div class="empty-members">Nenhum membro ainda. Convide alguém abaixo.</div>';
        return;
    }
    let html = '<div class="members-list">';
    members.forEach(m => {
        const initials = m.user_name.split(' ').slice(0,2).map(p=>p[0]).join('').toUpperCase();
        const isCreator = ws && (parseInt(ws.created_by) === parseInt(m.user_id));
        html += `<div class="member-row" id="mrow-${m.user_id}">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:grid;place-items:center;font-size:.8rem;font-weight:700;flex-shrink:0">${initials}</div>
            <div class="member-info">
                <div class="member-name">${esc(m.user_name)}</div>
                <div class="member-email">${esc(m.user_email)}</div>
            </div>
            <span class="role-badge ${ROLE_CLASSES[m.role] || 'role-viewer'}">${ROLE_LABELS[m.role] || m.role}</span>
            ${!isCreator ? `<button class="btn btn-sm" style="background:#fff0f0;color:var(--danger);border:1px solid #fcc" onclick="removeMember(${m.user_id},'${esc(m.user_name)}')">Remover</button>` : ''}
        </div>`;
    });
    html += '</div>';
    wrap.innerHTML = html;
}

function renderInviteSelect(members) {
    const existing = new Set(members.map(m => parseInt(m.user_id)));
    const sel = document.getElementById('inviteUserId');
    sel.innerHTML = '<option value="">Selecione um usuário...</option>';
    allUsers.filter(u => !existing.has(parseInt(u.id))).forEach(u => {
        sel.innerHTML += `<option value="${u.id}">${esc(u.name)} (${esc(u.email)})</option>`;
    });
}

async function inviteMember() {
    const userId = document.getElementById('inviteUserId').value;
    const role   = document.getElementById('inviteRole').value;
    if (!userId) { showMsg('Selecione um usuário', 'err'); return; }

    const fd = new FormData();
    fd.append('_csrf', CSRF);
    fd.append('user_id', userId);
    fd.append('role', role);

    try {
        const r = await fetch(`${BASE}/workspaces/${currentWsId}/members/invite`, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}});
        const data = await r.json();
        if (!r.ok) { showMsg(data.error || 'Erro ao convidar', 'err'); return; }
        showMsg('Usuário convidado com sucesso!', 'ok');
        loadMembers(currentWsId);
    } catch(e) {
        showMsg('Erro de conexão', 'err');
    }
}

async function removeMember(userId, name) {
    if (!confirm(`Remover "${name}" do workspace?`)) return;
    const fd = new FormData();
    fd.append('_csrf', CSRF);

    try {
        const r = await fetch(`${BASE}/workspaces/${currentWsId}/members/${userId}/remove`, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}});
        const data = await r.json();
        if (!r.ok) { showMsg(data.error || 'Erro ao remover', 'err'); return; }
        showMsg('Membro removido.', 'ok');
        loadMembers(currentWsId);
    } catch(e) {
        showMsg('Erro de conexão', 'err');
    }
}

function showMsg(msg, type) {
    const el = document.getElementById('modalMsg');
    el.textContent = msg;
    el.className = type;
}

function esc(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>

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
