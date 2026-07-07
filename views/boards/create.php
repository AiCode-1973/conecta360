<?php
declare(strict_types=1);
require_once BASE_PATH . '/src/Modules/Board/BoardRepository.php';
$repo       = new BoardRepository(pdo_master());
$workspaces = $repo->allWorkspaces();
$flash      = flash_get();
$baseUrl    = rtrim(env('APP_URL', ''), '/');

$COLORS  = ['#0073ea','#00c875','#fdab3d','#e2445c','#a25ddc','#037f4c','#579bfc','#ff7575','#333333'];
$ICONS   = ['📋','🏥','📊','✅','🗂️','📌','🔬','💊','🩺','🧾','📁','⚡'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Board — Conecta360</title>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--primary:#0073ea;--sidebar-w:240px;--sidebar-bg:#1f2d3d;--topbar-h:56px;--text:#323338;--muted:#676879;--border:#e6e9ef;--bg:#f6f7fb;--white:#fff;--r:8px;--danger:#e2445c}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text)}
        .sidebar{position:fixed;top:0;left:0;bottom:0;width:var(--sidebar-w);background:var(--sidebar-bg);display:flex;flex-direction:column;z-index:100;overflow-y:auto}
        .sb-brand{padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,.08)}
        .sb-brand h2{color:#fff;font-size:1.15rem;font-weight:700}
        .sb-brand small{color:rgba(255,255,255,.4);font-size:.7rem}
        .sb-nav{padding:.75rem 0;flex:1}
        .sb-nav a{display:flex;align-items:center;gap:.6rem;padding:.6rem 1.5rem;color:rgba(255,255,255,.7);text-decoration:none;font-size:.88rem;transition:background .15s}
        .sb-nav a:hover{background:rgba(255,255,255,.08);color:#fff}
        .sb-nav a svg{width:16px;height:16px;flex-shrink:0}
        .sb-footer{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.08)}
        .sb-footer a{display:flex;align-items:center;gap:.5rem;color:rgba(255,255,255,.5);text-decoration:none;font-size:.82rem}
        .topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 1.5rem;gap:1rem;z-index:90}
        .topbar-title{font-size:1rem;font-weight:600}
        .topbar-spacer{flex:1}
        .btn{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1rem;border-radius:var(--r);font-size:.875rem;font-weight:500;cursor:pointer;text-decoration:none;border:none;transition:background .15s}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-primary:hover{background:#0059b3}
        .btn-ghost{background:transparent;color:var(--muted);border:1.5px solid var(--border)}
        .btn-ghost:hover{border-color:#aaa}
        .main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h)}
        .page-content{padding:2rem;max-width:640px}
        .form-card{background:var(--white);border-radius:10px;border:1px solid var(--border);padding:2rem}
        .form-title{font-size:1.2rem;font-weight:700;margin-bottom:1.5rem}
        .form-group{margin-bottom:1.25rem}
        label{display:block;font-size:.82rem;font-weight:600;color:var(--text);margin-bottom:.4rem}
        input[type=text],input[type=url],textarea,select{width:100%;padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:var(--r);font-size:.9rem;color:var(--text);outline:none;transition:border-color .2s;background:var(--white);font-family:inherit}
        input:focus,textarea:focus,select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,115,234,.12)}
        textarea{resize:vertical;min-height:80px}
        .color-grid{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.4rem}
        .color-dot{width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:transform .1s}
        .color-dot.selected,.color-dot:hover{border-color:#fff;outline:2px solid var(--primary);transform:scale(1.1)}
        .icon-grid{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.4rem}
        .icon-btn{width:36px;height:36px;border-radius:6px;border:1.5px solid var(--border);background:var(--white);font-size:1.1rem;cursor:pointer;display:grid;place-items:center;transition:border-color .1s}
        .icon-btn.selected,.icon-btn:hover{border-color:var(--primary);background:#e8f0fe}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .alert-error{background:#fff0f0;color:#c0392b;border-left:4px solid var(--danger);padding:.75rem 1rem;border-radius:var(--r);margin-bottom:1.25rem;font-size:.875rem}
        .form-actions{display:flex;gap:.75rem;margin-top:1.5rem;justify-content:flex-end}
        .preview-bar{height:10px;border-radius:var(--r);margin-top:.5rem;transition:background .2s}
        .ws-create{display:none;margin-top:.5rem;align-items:center;gap:.5rem}
        .ws-create.show{display:flex}
        .ws-create input{flex:1;margin:0}
        small.hint{color:var(--muted);font-size:.75rem;display:block;margin-top:.25rem}
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sb-brand"><h2>Conecta360</h2><small>Gestão Hospitalar</small></div>
    <nav class="sb-nav">
        <a href="<?= $baseUrl ?>/dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard
        </a>
        <a href="<?= $baseUrl ?>/boards">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>Meus Boards
        </a>
    </nav>
    <div class="sb-footer">
        <a href="<?= $baseUrl ?>/logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sair
        </a>
    </div>
</aside>

<header class="topbar">
    <a href="<?= $baseUrl ?>/boards" class="btn btn-ghost" style="padding:.4rem .75rem">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <span class="topbar-title">Novo Board</span>
    <span class="topbar-spacer"></span>
</header>

<main class="main">
    <div class="page-content">
        <?php if ($flash && $flash['type'] === 'error'): ?>
            <div class="alert-error"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif ?>

        <div class="form-card">
            <div class="form-title">Criar novo board</div>

            <form method="POST" action="/boards/create" id="boardForm">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label>Nome do board *</label>
                    <input type="text" name="name" id="boardName" placeholder="Ex: Gestão de Pacientes UTI" required maxlength="120" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Workspace *</label>
                    <?php if (empty($workspaces)): ?>
                        <input type="hidden" name="workspace_id" id="wsHidden" value="">
                        <div id="wsNewInline" style="display:flex;gap:.5rem">
                            <input type="text" id="wsNewName" placeholder="Nome do workspace (ex: Principal)" style="flex:1">
                            <button type="button" class="btn btn-primary" onclick="createWorkspace()">Criar</button>
                        </div>
                        <small class="hint">Nenhum workspace encontrado. Crie um primeiro.</small>
                    <?php else: ?>
                        <select name="workspace_id" id="wsSelect">
                            <option value="">Selecione um workspace</option>
                            <?php foreach ($workspaces as $ws): ?>
                                <option value="<?= $ws['id'] ?>"><?= htmlspecialchars($ws['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                        <div id="ws-create-row" class="ws-create">
                            <input type="text" id="wsNewName" placeholder="Nome do novo workspace">
                            <button type="button" class="btn btn-primary" onclick="createWorkspace()" style="white-space:nowrap">Criar</button>
                            <button type="button" class="btn btn-ghost" onclick="document.getElementById('ws-create-row').classList.remove('show')">✕</button>
                        </div>
                        <small><a href="#" style="color:var(--primary);font-size:.75rem" onclick="document.getElementById('ws-create-row').classList.add('show');return false">+ Criar novo workspace</a></small>
                    <?php endif ?>
                </div>

                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="description" placeholder="Descreva o propósito deste board..." maxlength="500"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group" style="margin-bottom:0">
                        <label>Visibilidade</label>
                        <select name="visibility">
                            <option value="public">🌐 Público (todos do workspace)</option>
                            <option value="private">🔒 Privado (somente membros)</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label>Ícone</label>
                        <input type="hidden" name="icon" id="iconInput" value="📋">
                        <div class="icon-grid">
                            <?php foreach ($ICONS as $ic): ?>
                            <button type="button" class="icon-btn<?= $ic === '📋' ? ' selected' : '' ?>" onclick="selectIcon(this,'<?= $ic ?>')"><?= $ic ?></button>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.25rem">
                    <label>Cor de identificação</label>
                    <input type="hidden" name="color" id="colorInput" value="#0073ea">
                    <div class="color-grid">
                        <?php foreach ($COLORS as $c): ?>
                        <div class="color-dot<?= $c === '#0073ea' ? ' selected' : '' ?>"
                             style="background:<?= $c ?>"
                             onclick="selectColor(this,'<?= $c ?>')"></div>
                        <?php endforeach ?>
                    </div>
                    <div class="preview-bar" id="previewBar" style="background:#0073ea"></div>
                </div>

                <div class="form-actions">
                    <a href="<?= $baseUrl ?>/boards" class="btn btn-ghost">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Criar Board</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
function selectColor(el, hex) {
    document.querySelectorAll('.color-dot').forEach(d => d.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('colorInput').value = hex;
    document.getElementById('previewBar').style.background = hex;
}
function selectIcon(el, icon) {
    document.querySelectorAll('.icon-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('iconInput').value = icon;
}
function createWorkspace() {
    const name = document.getElementById('wsNewName').value.trim();
    if (!name) return alert('Informe um nome para o workspace.');
    fetch('/workspaces/create', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
        body: '_csrf=<?= csrf_token() ?>&name=' + encodeURIComponent(name)
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) { alert(d.error); return; }
        // Adiciona à select ou seta no hidden
        const sel = document.getElementById('wsSelect');
        const hid = document.getElementById('wsHidden');
        if (sel) {
            const opt = new Option(d.name, d.id, true, true);
            sel.add(opt);
            document.getElementById('ws-create-row').classList.remove('show');
        } else if (hid) {
            hid.value = d.id;
            document.getElementById('wsNewInline').innerHTML = '<span style="color:var(--primary);font-weight:600">✓ ' + d.name + '</span>';
        }
    })
    .catch(() => alert('Erro ao criar workspace.'));
}
</script>
</body>
</html>
