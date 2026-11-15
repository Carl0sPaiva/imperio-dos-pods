<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'config.php';

try {
    $stmtConfig = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'whatsapp'");
    $stmtConfig->execute();
    $whatsapp = $stmtConfig->fetchColumn() ?: '5563999999999';

    $categorias = $pdo->query("SELECT slug as id, nome as name FROM categorias ORDER BY id ASC")->fetchAll();

    $sql = "SELECT p.*, c.slug as category 
            FROM produtos p 
            INNER JOIN categorias c ON p.categoria_id = c.id 
            ORDER BY p.id DESC";
    $produtos = $pdo->query($sql)->fetchAll();

    $saboresRaw = $pdo->query("SELECT produto_id, sabor FROM produto_sabores ORDER BY id ASC")->fetchAll();
    $saboresPorProduto = [];
    foreach ($saboresRaw as $row) {
        $saboresPorProduto[$row['produto_id']][] = $row['sabor'];
    }

    $listaProdutos = [];
    foreach ($produtos as $prod) {
        $id = (int)$prod['id'];
        $listaProdutos[] = [
            'id'         => $id,
            'name'       => $prod['nome'],
            'category'   => $prod['category'],
            'price'      => (float)$prod['preco'],
            'oldPrice'   => $prod['preco_antigo'] ? (float)$prod['preco_antigo'] : null,
            'badge'      => $prod['badge'],
            'image'      => !empty($prod['imagem']) ? $prod['imagem'] : 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=600&q=80',
            'desc'       => $prod['descricao'] ?? '',
            'outOfStock' => (bool)$prod['esgotado'],
            'flavors'    => $saboresPorProduto[$id] ?? []
        ];
    }

    echo json_encode([
        'whatsapp'   => $whatsapp,
        'categories' => $categorias,
        'products'   => $listaProdutos
    ], JSON_UNESCAPED_UNICODE);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Erro ao carregar dados do catálogo."]);
}