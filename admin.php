<?php
session_start();
require_once 'config.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$msg = '';
$erro = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'login') {
    $senhaDigitada = $_POST['senha'] ?? '';
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'admin_senha'");
    $stmt->execute();
    $hashSalvo = $stmt->fetchColumn();

    if ($hashSalvo && $senhaDigitada === $hashSalvo) {
        $_SESSION['admin_logado'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $erro = "Senha incorreta!";
    }
}

if (empty($_SESSION['admin_logado'])):
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo - Império dos Pods</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: #08090c; color: #f1f5f9; display: flex; align-items: center; justify-content: center; height: 100vh; padding: 20px; }
        .login-card { background: #12141c; border: 1px solid rgba(255,255,255,0.08); padding: 30px; border-radius: 16px; width: 100%; max-width: 380px; }
        h2 { margin-bottom: 20px; text-align: center; color: #00FF7F; }
        input { width: 100%; background: #1a1d29; border: 1px solid rgba(255,255,255,0.08); padding: 12px; border-radius: 8px; color: #fff; margin-bottom: 15px; }
        button { width: 100%; background: #8A2BE2; color: #fff; border: none; padding: 12px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .erro { background: rgba(255,77,77,0.1); border: 1px solid #ff4d4d; color: #ff4d4d; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem; text-align: center; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Império dos Pods • Admin</h2>
        <?php if ($erro): ?><div class="erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="acao" value="login">
            <input type="password" name="senha" placeholder="Digite a senha do painel" required autofocus>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
<?php
exit;
endif;

function processarUploadImagem($file, $imagemAtual = '') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return $imagemAtual;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($mime, $permitidos)) {
        throw new Exception("Formato de imagem inválido. Use JPG, PNG ou WEBP.");
    }

    if ($file['size'] > 3 * 1024 * 1024) {
        throw new Exception("A imagem deve ter no máximo 3MB.");
    }

    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $novoNome = uniqid('prod_', true) . '.' . $permitidos[$mime];
    $destino = $uploadDir . $novoNome;

    if (move_uploaded_file($file['tmp_name'], $destino)) {
        if (!empty($imagemAtual) && strpos($imagemAtual, 'uploads/') === 0 && file_exists($imagemAtual)) {
            unlink($imagemAtual);
        }
        return $destino;
    }

    throw new Exception("Falha ao salvar a imagem no servidor.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Token de segurança inválido. Recarregue a página.");
    }

    $acao = $_POST['acao'];

    try {
        if ($acao === 'salvar_config') {
            $wa = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');
            if (!empty($wa)) {
                $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'whatsapp'");
                $stmt->execute([$wa]);
            }

            if (!empty($_POST['nova_senha'])) {
                $novaHash = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'admin_senha'");
                $stmt->execute([$novaHash]);
            }
            $msg = "Configurações atualizadas com sucesso!";
        }

        if ($acao === 'cadastrar' || $acao === 'editar') {
            $pdo->beginTransaction();

            $nome = trim($_POST['nome']);
            $categoria_id = (int)$_POST['categoria_id'];
            $preco = (float)str_replace(',', '.', $_POST['preco']);
            $preco_antigo = !empty($_POST['preco_antigo']) ? (float)str_replace(',', '.', $_POST['preco_antigo']) : null;
            $descricao = trim($_POST['descricao']);
            $badge = trim($_POST['badge']) ?: null;
            $sabores_raw = trim($_POST['sabores']);

            if ($acao === 'cadastrar') {
                $imagem_path = processarUploadImagem($_FILES['imagem'] ?? null);
                $stmt = $pdo->prepare("INSERT INTO produtos (categoria_id, nome, preco, preco_antigo, descricao, imagem, badge) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$categoria_id, $nome, $preco, $preco_antigo, $descricao, $imagem_path, $badge]);
                $produto_id = $pdo->lastInsertId();
                $msg = "Produto cadastrado com sucesso!";
            } else {
                $produto_id = (int)$_POST['produto_id'];
                $stmtAtual = $pdo->prepare("SELECT imagem FROM produtos WHERE id = ?");
                $stmtAtual->execute([$produto_id]);
                $imgAtual = $stmtAtual->fetchColumn() ?: '';

                $imagem_path = processarUploadImagem($_FILES['imagem'] ?? null, $imgAtual);
                $stmt = $pdo->prepare("UPDATE produtos SET categoria_id = ?, nome = ?, preco = ?, preco_antigo = ?, descricao = ?, imagem = ?, badge = ? WHERE id = ?");
                $stmt->execute([$categoria_id, $nome, $preco, $preco_antigo, $descricao, $imagem_path, $badge, $produto_id]);

                $pdo->prepare("DELETE FROM produto_sabores WHERE produto_id = ?")->execute([$produto_id]);
                $msg = "Produto atualizado com sucesso!";
            }

            if (!empty($sabores_raw)) {
                $saboresArr = explode(',', $sabores_raw);
                $stmtSabor = $pdo->prepare("INSERT INTO produto_sabores (produto_id, sabor) VALUES (?, ?)");
                foreach ($saboresArr as $sabor) {
                    $saborTrim = trim($sabor);
                    if (!empty($saborTrim)) {
                        $stmtSabor->execute([$produto_id, $saborTrim]);
                    }
                }
            }

            $pdo->commit();
        }

        if ($acao === 'toggle_estoque') {
            $id = (int)$_POST['produto_id'];
            $status = (int)$_POST['status'];
            $stmt = $pdo->prepare("UPDATE produtos SET esgotado = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            header("Location: admin.php");
            exit;
        }

        if ($acao === 'excluir') {
            $id = (int)$_POST['produto_id'];
            $stmtImg = $pdo->prepare("SELECT imagem FROM produtos WHERE id = ?");
            $stmtImg->execute([$id]);
            $prodData = $stmtImg->fetch();
            if ($prodData && !empty($prodData['imagem']) && strpos($prodData['imagem'], 'uploads/') === 0 && file_exists($prodData['imagem'])) {
                unlink($prodData['imagem']);
            }

            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: admin.php");
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = "Erro: " . $e->getMessage();
    }
}

$produtoEdicao = null;
if (isset($_GET['editar'])) {
    $idEdit = (int)$_GET['editar'];
    $stmtEdit = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
    $stmtEdit->execute([$idEdit]);
    $produtoEdicao = $stmtEdit->fetch();

    if ($produtoEdicao) {
        $stmtSab = $pdo->prepare("SELECT sabor FROM produto_sabores WHERE produto_id = ?");
        $stmtSab->execute([$idEdit]);
        $produtoEdicao['sabores_str'] = implode(', ', $stmtSab->fetchAll(PDO::FETCH_COLUMN));
    }
}

$waAtual = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'whatsapp'")->fetchColumn();
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY id ASC")->fetchAll();
$produtos = $pdo->query("SELECT p.*, c.nome as cat_nome FROM produtos p INNER JOIN categorias c ON p.categoria_id = c.id ORDER BY p.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - Império dos Pods</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #8A2BE2;
            --secondary: #00FF7F;
            --bg-body: #08090c;
            --bg-card: #12141c;
            --bg-input: #1a1d29;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1, h2 { margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; font-size: 1.25rem; }
        .card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 25px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media(max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }
        .form-group { margin-bottom: 12px; }
        .form-group.full { grid-column: 1 / -1; }
        label { display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px; }
        input, select, textarea { width: 100%; background: var(--bg-input); border: 1px solid var(--border-color); padding: 10px; border-radius: 8px; color: #fff; font-size: 0.9rem; }
        textarea { resize: vertical; height: 80px; }
        button, .btn-link { background: var(--primary); color: #fff; border: none; padding: 11px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 0.9rem; }
        button:hover, .btn-link:hover { opacity: 0.9; }
        .alert-success { background: rgba(0,255,127,0.1); border: 1px solid var(--secondary); color: var(--secondary); padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-error { background: rgba(255,77,77,0.1); border: 1px solid #ff4d4d; color: #ff4d4d; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.9rem; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; }
        img.thumb { width: 45px; height: 45px; object-fit: cover; border-radius: 6px; }
        .badge-status { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; }
        .status-ok { background: rgba(0,255,127,0.2); color: var(--secondary); }
        .status-out { background: rgba(255,77,77,0.2); color: #ff4d4d; }
        .action-btns { display: flex; gap: 6px; }
        .btn-sm { padding: 6px 10px; font-size: 0.75rem; border-radius: 6px; }
        .btn-danger { background: #ff4d4d; color: #fff; }
        .btn-warning { background: #eab308; color: #000; }
        .btn-info { background: #3b82f6; color: #fff; }
        .btn-muted { background: #334155; color: #fff; }
        .top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 10px; }
        .nav-actions { display: flex; gap: 12px; align-items: center; }
        .link-store { color: var(--secondary); text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-nav">
            <h1><i class="fa-solid fa-crown"></i> Império dos Pods • Painel</h1>
            <div class="nav-actions">
                <a href="index.html" class="link-store" target="_blank"><i class="fa-solid fa-store"></i> Ver Loja</a>
                <a href="admin.php?logout=1" class="btn-link btn-muted btn-sm"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if (!empty($erro)): ?>
            <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2><i class="fa-solid fa-sliders"></i> Configurações da Loja</h2>
            <form action="admin.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="acao" value="salvar_config">
                <div class="form-grid">
                    <div class="form-group">
                        <label>WhatsApp da Loja (com DDD e 55)</label>
                        <input type="text" name="whatsapp" value="<?= htmlspecialchars($waAtual) ?>" required placeholder="5563999999999">
                    </div>
                    <div class="form-group">
                        <label>Alterar Senha do Painel (deixe em branco para manter)</label>
                        <input type="password" name="nova_senha" placeholder="Nova senha">
                    </div>
                </div>
                <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar Configurações</button>
            </form>
        </div>

        <div class="card" id="form-produto">
            <h2>
                <span><?= $produtoEdicao ? 'Editar Produto: ' . htmlspecialchars($produtoEdicao['nome']) : 'Cadastrar Novo Produto' ?></span>
                <?php if ($produtoEdicao): ?>
                    <a href="admin.php" class="btn-link btn-muted btn-sm">Cancelar Edição</a>
                <?php endif; ?>
            </h2>
            <form action="admin.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="acao" value="<?= $produtoEdicao ? 'editar' : 'cadastrar' ?>">
                <?php if ($produtoEdicao): ?>
                    <input type="hidden" name="produto_id" value="<?= $produtoEdicao['id'] ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nome do Produto *</label>
                        <input type="text" name="nome" required value="<?= htmlspecialchars($produtoEdicao['nome'] ?? '') ?>" placeholder="Ex: Ignite V80 (8000 Puffs)">
                    </div>

                    <div class="form-group">
                        <label>Categoria *</label>
                        <select name="categoria_id" required>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($produtoEdicao && $produtoEdicao['categoria_id'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Preço Atual (R$) *</label>
                        <input type="text" name="preco" required value="<?= $produtoEdicao ? number_format($produtoEdicao['preco'], 2, '.', '') : '' ?>" placeholder="120.00">
                    </div>

                    <div class="form-group">
                        <label>Preço Antigo / Riscado (R$)</label>
                        <input type="text" name="preco_antigo" value="<?= ($produtoEdicao && $produtoEdicao['preco_antigo']) ? number_format($produtoEdicao['preco_antigo'], 2, '.', '') : '' ?>" placeholder="140.00">
                    </div>

                    <div class="form-group">
                        <label>Etiqueta de Destaque (Badge)</label>
                        <input type="text" name="badge" value="<?= htmlspecialchars($produtoEdicao['badge'] ?? '') ?>" placeholder="Ex: Mais Vendido, Lançamento">
                    </div>

                    <div class="form-group">
                        <label>Foto do Produto (JPG, PNG ou WEBP)</label>
                        <input type="file" name="imagem" accept="image/jpeg,image/png,image/webp">
                    </div>

                    <div class="form-group full">
                        <label>Sabores / Variações (separados por vírgula)</label>
                        <input type="text" name="sabores" value="<?= htmlspecialchars($produtoEdicao['sabores_str'] ?? '') ?>" placeholder="Ex: Icy Mint, Watermelon Ice, Grape Ice">
                    </div>

                    <div class="form-group full">
                        <label>Descrição Curta</label>
                        <textarea name="descricao" placeholder="Detalhes técnicos, puffs, bateria..."><?= htmlspecialchars($produtoEdicao['descricao'] ?? '') ?></textarea>
                    </div>
                </div>

                <button type="submit">
                    <i class="fa-solid <?= $produtoEdicao ? 'fa-check' : 'fa-plus' ?>"></i> 
                    <?= $produtoEdicao ? 'Atualizar Produto' : 'Salvar Produto no Banco' ?>
                </button>
            </form>
        </div>

        <div class="card">
            <h2>Gerenciar Produtos Cadastrados</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nome</th>
                            <th>Preço</th>
                            <th>Categoria</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produtos)): ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">Nenhum produto cadastrado ainda.</td></tr>
                        <?php else: ?>
                            <?php foreach ($produtos as $p): ?>
                                <tr>
                                    <td><img src="<?= !empty($p['imagem']) ? htmlspecialchars($p['imagem']) : 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=100&q=80' ?>" class="thumb"></td>
                                    <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
                                    <td>R$ <?= number_format($p['preco'], 2, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($p['cat_nome']) ?></td>
                                    <td>
                                        <?php if ($p['esgotado']): ?>
                                            <span class="badge-status status-out">Esgotado</span>
                                        <?php else: ?>
                                            <span class="badge-status status-ok">Disponível</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="admin.php?editar=<?= $p['id'] ?>#form-produto" class="btn-link btn-sm btn-info" title="Editar Produto">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <form action="admin.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="acao" value="toggle_estoque">
                                                <input type="hidden" name="produto_id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="status" value="<?= $p['esgotado'] ? 0 : 1 ?>">
                                                <button type="submit" class="btn-sm btn-warning" title="Alternar Disponibilidade">
                                                    <i class="fa-solid fa-rotate"></i>
                                                </button>
                                            </form>
                                            <form action="admin.php" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este produto?');">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="produto_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn-sm btn-danger" title="Excluir">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>