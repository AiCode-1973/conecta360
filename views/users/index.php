<?php
declare(strict_types=1);

$pdo   = pdo_master();
$flash = flash_get();
$me    = (int)$_SESSION['user_id'];

// Filtros
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

$where  = ['u.deleted_at IS NULL'];
$params = [];

if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if (in_array($status, ['active','inactive','invited','blocked'], true)) {
    $where[]  = 'u.status = ?';
    $params[] = $status;
}

$sql   = 'SELECT u.id, u.name, u.email, u.status, u.job_title, u.last_login_at, u.created_at
          FROM users u
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY u.created_at DESC';
$stmt  = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$totalActive   = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE status="active"  AND deleted_at IS NULL')->fetchColumn();
$totalInactive = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE status="inactive" AND deleted_at IS NULL')->fetchColumn();
$totalInvited  = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE status="invited"  AND deleted_at IS NULL')->fetchColumn();

$baseUrl = rtrim(env('APP_URL', ''), '/');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuários — Conecta360</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--primary:#0073ea;--primary-dark:#0059b3;--sidebar-w:240px;--sidebar-bg:#1f2d3d;--topbar-h:56px;--text:#323338;--muted:#676879;--border:#e6e9ef;--bg:#f6f7fb;--white:#fff;--r:8px;--success:#00c875;--warning:#fdab3d;--danger:#e2445c}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text)}
.sidebar{position:fixed;top:0;left:0;bottom:0;width:var(--sidebar-w);background:var(--sidebar-bg);display:flex;flex-direction:column;z-index:100;overflow-y:auto}
.sb-brand{padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,.08)}
.sb-brand h2{color:#fff;font-size:1.15rem;font-weight:700}
.sb-brand small{color:rgba(255,255,255,.4);font-size:.7rem}
.sb-nav{flex:1;padding:.5rem 0}
.nav-section{padding:.6rem 1.25rem .2rem;font-size:.65rem;font-weight:700;color:rgba(255,255,255,.3);letter-spacing:.1em;text-transform:uppercase}
.nav-item a{display:flex;align-items:center;gap:.7rem;padding:.55rem 1.5rem;color:rgba(255,255,255,.65);text-decoration:none;font-size:.86rem;border-left:3px solid transparent;transition:all .15s}
.nav-item a:hover{background:rgba(255,255,255,.06);color:#fff}
.nav-item.active a{background:rgba(0,115,234,.22);color:#fff;border-left-color:var(--primary)}
.sb-footer{padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.08)}
.u-info{display:flex;align-items:center;gap:.65rem}
.u-av{width:32px;height:32px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;flex-shrink:0}
.u-det strong{display:block;color:#fff;font-size:.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.u-det span{color:rgba(255,255,255,.4);font-size:.7rem}
.lnk-logout{display:block;margin-top:.6rem;color:rgba(255,255,255,.35);font-size:.75rem;text-decoration:none}
.lnk-logout:hover{color:var(--danger)}
.main{margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column}
.topbar{position:sticky;top:0;z-index:50;height:var(--topbar-h);background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:1rem;padding:0 1.5rem}
.topbar-title{font-size:1.05rem;font-weight:600;flex:1}
.btn{display:inline-flex;align-items:center;gap:.35rem;padding:.45rem .9rem;border-radius:var(--r);font-size:.82rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .15s;white-space:nowrap}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:var(--primary-dark)}
.btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border)}
.btn-ghost:hover{border-color:#aaa;color:var(--text)}
.btn-danger{background:var(--danger);color:#fff}
.btn-sm{padding:.3rem .65rem;font-size:.75rem}
.page{padding:1.5rem;flex:1}
.alert{border-radius:var(--r);padding:.8rem 1rem;margin-bottom:1.25rem;font-size:.88rem;font-weight:500}
.alert-error{background:#fff0f0;color:#c0392b;border-left:4px solid var(--danger)}
.alert-success{background:#f0fff4;color:#1a7e3f;border-left:4px solid var(--success)}
/* Stats chips */
.user-stats{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
.ustat{background:var(--white);border:1px solid var(--border);border-radius:var(--r);padding:.75rem 1.1rem;display:flex;flex-direction:column;gap:.1rem;min-width:110px}
.ustat-val{font-size:1.4rem;font-weight:700}
.ustat-lbl{font-size:.72rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em}
/* Toolbar */
.toolbar{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap}
.search-box{display:flex;align-items:center;gap:.5rem;background:var(--white);border:1.5px solid var(--border);border-radius:var(--r);padding:.4rem .8rem;flex:1;max-width:320px}
.search-box input{border:none;outline:none;font-size:.875rem;width:100%;background:transparent;color:var(--text)}
.filter-select{padding:.42rem .8rem;border:1.5px solid var(--border);border-radius:var(--r);font-size:.82rem;outline:none;background:var(--white);cursor:pointer;color:var(--text)}
.filter-select:focus{border-color:var(--primary)}
/* Table */
.table-card{background:var(--white);border:1px solid var(--border);border-radius:10px;overflow:hidden}
.data-table{width:100%;border-collapse:collapse}
.data-table th{padding:.7rem 1rem;font-size:.75rem;font-weight:700;color:var(--muted);text-align:left;border-bottom:2px solid var(--border);background:#fafbfc;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap}
.data-table td{padding:.75rem 1rem;font-size:.85rem;border-bottom:1px solid var(--border);vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr:hover{background:#fafbff}
.avatar{width:34px;height:34px;border-radius:50%;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;flex-shrink:0}
.user-name{font-weight:600;font-size:.9rem}
.user-email{font-size:.78rem;color:var(--muted);margin-top:.1rem}
.user-cell{display:flex;align-items:center;gap:.65rem}
.badge{display:inline-flex;align-items:center;padding:.2rem .65rem;border-radius:100px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.badge-active{background:#d4f4e6;color:#1a7e3f}
.badge-inactive{background:#ffe8e8;color:#c0392b}
.badge-invited{background:#fff3cd;color:#856404}
.badge-blocked{background:#f0f0f0;color:#555}
.actions{display:flex;gap:.4rem;align-items:center}
.empty-state{text-align:center;padding:3rem;color:var(--muted)}
.empty-state h3{margin-bottom:.5rem;font-size:1rem;color:var(--text)}
/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal{background:var(--white);border-radius:12px;padding:1.75rem;width:440px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal h3{font-size:1.05rem;font-weight:700;margin-bottom:1.25rem}
.form-group{margin-bottom:1.1rem}
.form-group label{display:block;font-size:.8rem;font-weight:600;margin-bottom:.35rem;color:var(--text)}
.form-group input,.form-group select{width:100%;padding:.62rem .8rem;border:1.5px solid var(--border);border-radius:var(--r);font-size:.875rem;outline:none;transition:border-color .2s;color:var(--text)}
.form-group input:focus,.form-group select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,115,234,.1)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.modal-actions{display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem}
small.hint{color:var(--muted);font-size:.72rem;display:block;margin-top:.2rem}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><h2>Conecta360</h2><small>Gestão Hospitalar</small></div>
    <nav class="sb-nav">
        <p class="nav-section">Principal</p>
        <div class="nav-item"><a href="<?= $baseUrl ?>/dashboard">🏠&nbsp; Dashboard</a></div>
        <div class="nav-item"><a href="<?= $baseUrl ?>/boards">📋&nbsp; Meus Boards</a></div>
        <p class="nav-section" style="margin-top:.5rem">Administração</p>
        <div class="nav-item active"><a href="<?= $baseUrl ?>/users">👥&nbsp; Usuários</a></div>
        <div class="nav-item"><a href="<?= $baseUrl ?>/settings">⚙️&nbsp; Configurações</a></div>
    </nav>
    <div class="sb-footer">
        <div class="u-info">
            <div class="u-av"><?= strtoupper(mb_substr($_SESSION['user_name'], 0, 1)) ?></div>
            <div class="u-det">
                <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>
                <span><?= htmlspecialchars($_SESSION['user_email']) ?></span>
            </div>
        </div>
        <a href="<?= $baseUrl ?>/logout" class="lnk-logout">🚪 Sair da conta</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <span class="topbar-title">Usuários</span>
        <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('show')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Novo Usuário
        </button>
    </header>

    <div class="page">
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif ?>

        <!-- Stats -->
        <div class="user-stats">
            <div class="ustat">
                <span class="ustat-val"><?= count($users) ?></span>
                <span class="ustat-lbl">Total</span>
            </div>
            <div class="ustat">
                <span class="ustat-val" style="color:var(--success)"><?= $totalActive ?></span>
                <span class="ustat-lbl">Ativos</span>
            </div>
            <div class="ustat">
                <span class="ustat-val" style="color:var(--warning)"><?= $totalInvited ?></span>
                <span class="ustat-lbl">Convidados</span>
            </div>
            <div class="ustat">
                <span class="ustat-val" style="color:var(--danger)"><?= $totalInactive ?></span>
                <span class="ustat-lbl">Inativos</span>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <form method="GET" action="/users" style="display:contents">
                <div class="search-box">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por nome ou e-mail..." oninput="this.form.submit()">
                </div>
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">Todos os status</option>
                    <option value="active"   <?= $status==='active'  ?'selected':'' ?>>✅ Ativos</option>
                    <option value="invited"  <?= $status==='invited' ?'selected':'' ?>>📧 Convidados</option>
                    <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>⛔ Inativos</option>
                    <option value="blocked"  <?= $status==='blocked' ?'selected':'' ?>>🚫 Bloqueados</option>
                </select>
            </form>
            <?php if ($search || $status): ?>
                <a href="/users" class="btn btn-ghost btn-sm">✕ Limpar filtros</a>
            <?php endif ?>
        </div>

        <!-- Table -->
        <div class="table-card">
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <div style="font-size:2.5rem;margin-bottom:.75rem">👥</div>
                    <h3>Nenhum usuário encontrado</h3>
                    <p style="margin-bottom:1rem">
                        <?= $search || $status ? 'Tente outros filtros.' : 'Crie o primeiro usuário clicando em "Novo Usuário".' ?>
                    </p>
                    <?php if (!$search && !$status): ?>
                    <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('show')">Criar primeiro usuário</button>
                    <?php endif ?>
                </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Cargo</th>
                        <th>Status</th>
                        <th>Último Acesso</th>
                        <th>Criado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                <?php foreach ($users as $u): ?>
                <tr id="user-row-<?= $u['id'] ?>">
                    <td>
                        <div class="user-cell">
                            <div class="avatar" style="background:<?= '#' . substr(md5($u['email']), 0, 6) ?>">
                                <?= strtoupper(mb_substr($u['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="user-name">
                                    <?= htmlspecialchars($u['name']) ?>
                                    <?php if ((int)$u['id'] === $me): ?>
                                        <span style="font-size:.68rem;color:var(--primary);font-weight:600;background:#e8f0fe;padding:.1rem .4rem;border-radius:4px;margin-left:.3rem">Você</span>
                                    <?php endif ?>
                                </div>
                                <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--muted);font-size:.82rem"><?= htmlspecialchars($u['job_title'] ?? '—') ?></td>
                    <td>
                        <span class="badge badge-<?= htmlspecialchars($u['status']) ?>">
                            <?= match($u['status']) {
                                'active'   => 'Ativo',
                                'inactive' => 'Inativo',
                                'invited'  => 'Convidado',
                                'blocked'  => 'Bloqueado',
                                default    => htmlspecialchars($u['status'])
                            } ?>
                        </span>
                    </td>
                    <td style="color:var(--muted);font-size:.82rem">
                        <?= $u['last_login_at'] ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '—' ?>
                    </td>
                    <td style="color:var(--muted);font-size:.82rem">
                        <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                    </td>
                    <td>
                        <div class="actions">
                            <button class="btn btn-ghost btn-sm"
                                    onclick="openEdit(<?= $u['id'] ?>, <?= htmlspecialchars(json_encode($u)) ?>)"
                                    title="Editar">
                                ✏️ Editar
                            </button>
                            <?php if ((int)$u['id'] !== $me): ?>
                            <button class="btn btn-ghost btn-sm"
                                    onclick="toggleStatus(<?= $u['id'] ?>, '<?= htmlspecialchars($u['status']) ?>', this)"
                                    title="<?= $u['status'] === 'active' ? 'Desativar' : 'Ativar' ?>">
                                <?= $u['status'] === 'active' ? '⛔' : '✅' ?>
                            </button>
                            <button class="btn btn-ghost btn-sm"
                                    onclick="deleteUser(<?= $u['id'] ?>, this)"
                                    style="color:var(--danger);border-color:var(--danger)"
                                    title="Excluir">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            <?php endif ?>
        </div>
    </div>
</div>

<!-- Modal: Editar Usuário -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="width:500px">
        <h3>✏️ Editar Usuário</h3>
        <div class="form-row">
            <div class="form-group">
                <label>Nome completo *</label>
                <input type="text" id="edit_name" placeholder="Nome" maxlength="200">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="edit_status">
                    <option value="active">✅ Ativo</option>
                    <option value="invited">📧 Convidado</option>
                    <option value="inactive">⛔ Inativo</option>
                    <option value="blocked">🚫 Bloqueado</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>E-mail *</label>
            <input type="email" id="edit_email" placeholder="email@hospital.com">
        </div>
        <div class="form-row">
            <div class="form-group" style="margin-bottom:0">
                <label>Cargo</label>
                <input type="text" id="edit_job_title" placeholder="Ex: Enfermeiro Chefe">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Departamento</label>
                <input type="text" id="edit_department" placeholder="Ex: UTI">
            </div>
        </div>
        <div class="form-group" style="margin-top:1rem">
            <label>Nova Senha <span style="color:var(--muted);font-weight:400">(deixe em branco para não alterar)</span></label>
            <input type="password" id="edit_password" placeholder="Mínimo 6 caracteres" minlength="6">
        </div>
        <div id="editError" style="display:none;color:var(--danger);font-size:.82rem;margin-top:.5rem"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('editModal').classList.remove('show')">Cancelar</button>
            <button type="button" class="btn btn-primary" id="editSaveBtn" onclick="saveEdit()">Salvar Alterações</button>
        </div>
    </div>
</div>

<!-- Modal: Criar Usuário -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <h3>👤 Novo Usuário</h3>
        <form method="POST" action="/users/create">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Nome completo *</label>
                <input type="text" name="name" required placeholder="Ex: João Silva" maxlength="200">
            </div>
            <div class="form-group">
                <label>E-mail *</label>
                <input type="email" name="email" required placeholder="joao@hospital.com">
            </div>
            <div class="form-row">
                <div class="form-group" style="margin-bottom:0">
                    <label>Senha *</label>
                    <input type="password" name="password" required placeholder="Mínimo 6 caracteres" minlength="6">
                    <small class="hint">O usuário poderá alterar depois.</small>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">✅ Ativo</option>
                        <option value="invited" selected>📧 Convidado</option>
                        <option value="inactive">⛔ Inativo</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('createModal').classList.remove('show')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar Usuário</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF = '<?= csrf_token() ?>';
let editUserId = null;

async function api(url, extra = {}) {
    const body = new URLSearchParams({ _csrf: CSRF, ...extra });
    const r = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
        body
    });
    return r.json();
}

function openEdit(id, user) {
    editUserId = id;
    document.getElementById('edit_name').value       = user.name       || '';
    document.getElementById('edit_email').value      = user.email      || '';
    document.getElementById('edit_job_title').value  = user.job_title  || '';
    document.getElementById('edit_department').value = user.department || '';
    document.getElementById('edit_status').value     = user.status     || 'active';
    document.getElementById('edit_password').value   = '';
    document.getElementById('editError').style.display = 'none';
    document.getElementById('editModal').classList.add('show');
    document.getElementById('edit_name').focus();
}

async function saveEdit() {
    const btn = document.getElementById('editSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Salvando...';
    const d = await api(`/users/${editUserId}/update`, {
        name:         document.getElementById('edit_name').value,
        email:        document.getElementById('edit_email').value,
        job_title:    document.getElementById('edit_job_title').value,
        department:   document.getElementById('edit_department').value,
        status:       document.getElementById('edit_status').value,
        new_password: document.getElementById('edit_password').value,
    });
    btn.disabled = false;
    btn.textContent = 'Salvar Alterações';
    if (d.error) {
        const errEl = document.getElementById('editError');
        errEl.textContent = d.error;
        errEl.style.display = 'block';
        return;
    }
    // Atualiza a linha na tabela sem recarregar
    const u = d.user;
    const row = document.getElementById('user-row-' + editUserId);
    if (row) {
        row.querySelector('.user-name').childNodes[0].textContent = u.name;
        row.querySelector('.user-email').textContent = u.email;
        const td = row.querySelectorAll('td')[2]; // coluna Status
        td.innerHTML = `<span class="badge badge-${u.status}">${{active:'Ativo',inactive:'Inativo',invited:'Convidado',blocked:'Bloqueado'}[u.status]||u.status}</span>`;
        const tdJob = row.querySelectorAll('td')[1];
        tdJob.textContent = u.job_title || '—';
    }
    document.getElementById('editModal').classList.remove('show');
}

async function toggleStatus(id, currentStatus, btn) {
    const action = currentStatus === 'active' ? 'desativar' : 'ativar';
    if (!confirm(`Deseja ${action} este usuário?`)) return;
    const d = await api(`/users/${id}/toggle-status`);
    if (d.ok) {
        location.reload();
    } else {
        alert(d.error || 'Erro ao alterar status.');
    }
}

async function deleteUser(id, btn) {
    if (!confirm('Excluir este usuário? Esta ação não pode ser desfeita.')) return;
    const d = await api(`/users/${id}/delete`);
    if (d.ok) {
        document.getElementById('user-row-' + id).remove();
    } else {
        alert(d.error || 'Erro ao excluir usuário.');
    }
}

// Fecha modal clicando fora
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
});
</script>
</body>
</html>
