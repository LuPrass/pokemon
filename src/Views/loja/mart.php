<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redireciona para o login se a sessão não estiver ativa
if (empty($_SESSION['logado'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../Autoload.php';

$pdo = Conexao::getConnection();

// Identifica o usuário logado
$usuarioId = $_SESSION['usuario_id'] ?? 1;
$usuarioNome = $_SESSION['usuario'] ?? 'Treinador';

// Busca dados atualizados do usuário (saldo em pokédollars)
$usuario = new Usuario();
$usuario->load($usuarioId);
$saldo = $usuario->getDinheiro() ?? 100;

$categorias = [
    'restaurador' => ['nome' => 'Restauradores de HP', 'icone' => '🧪'],
    'cura' => ['nome' => 'Cura de Status', 'icone' => '💊'],
    'reviver' => ['nome' => 'Reviver', 'icone' => '💖'],
    'evolucao' => ['nome' => 'Evolução', 'icone' => '✨']
];
$itensCatalogo = [];

foreach (Item::all($pdo) as $item) {
    $tipo = $item['tipo'];
    $categoria = $categorias[$tipo]['nome'] ?? ucfirst($tipo);
    $item['icone'] = $categorias[$tipo]['icone'] ?? '✨';
    $itensCatalogo[$categoria][] = $item;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokéArena - PokéMart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/mart.css?v=<?= time() ?>">
</head>
<body>

    <div class="mart-viewport">
        <div class="mart-container">

            <!-- Cabeçalho PokéMart -->
            <header class="mart-header-card">
                <div class="mart-brand">
                    <img src="assets/img/pokearena_title.png" alt="PokéArena" class="mart-logo">
                    <div class="mart-title-text">
                        <span class="mart-badge-title">POKÉMART - LOJA DE ITENS</span>
                        <span class="mart-welcome">Treinador: <b><?php echo htmlspecialchars($usuarioNome); ?></b></span>
                    </div>
                </div>

                <div class="mart-actions">
                    <div class="carteira-hud">
                        <span class="carteira-label">Saldo:</span>
                        <span class="carteira-valor">₽ <?php echo number_format($saldo, 0, ',', '.'); ?></span>
                    </div>
                    <a class="btn-nav-mart btn-nav-mochila" href="index.php?rota=mochila" style="background: linear-gradient(180deg, #0284c7 0%, #0369a1 100%);">🎒 Mochila</a>
                    <a class="btn-nav-mart btn-nav-batalha" href="index.php?rota=batalha">⚔ Batalha</a>
                    <a class="btn-nav-mart btn-nav-menu" href="index.php?rota=menu">← Menu</a>
                </div>
            </header>

            <!-- Vitrine de Itens Organizados por Categoria -->
            <main class="vitrine-itens">
                <?php foreach ($itensCatalogo as $categoria => $itens): ?>
                    <section class="secao-categoria">
                        <h2 class="categoria-titulo"><?php echo htmlspecialchars($categoria); ?></h2>
                        <div class="grid-itens">
                            <?php foreach ($itens as $item): ?>
                                <div class="item-card" data-item-id="<?php echo htmlspecialchars($item['id'] ?? ''); ?>" data-item-tipo="<?php echo htmlspecialchars($item['tipo']); ?>">
                                    <div>
                                        <div class="item-topo">
                                            <span class="item-icone"><?php echo $item['icone']; ?></span>
                                            <span class="item-nome"><?php echo htmlspecialchars($item['nome']); ?></span>
                                        </div>
                                        <div class="item-descricao">
                                            <?php echo htmlspecialchars($item['descricao']); ?>
                                        </div>
                                    </div>
                                    <div class="item-rodape">
                                        <span class="item-preco">₽ <?php echo number_format($item['preco'], 0, ',', '.'); ?></span>
                                        <button type="button" class="btn-comprar" title="Comprar <?php echo htmlspecialchars($item['nome']); ?>">
                                            Comprar
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </main>

        </div>
    </div>

</body>
</html>
