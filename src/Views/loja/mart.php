<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../Autoload.php';

$pdo = Conexao::getConnection();

// Identifica o usuário logado
$usuarioId = $_SESSION['usuario_id'] ?? 1;
$usuarioNome = $_SESSION['usuario'] ?? 'Treinador';

// Busca dados atualizados do usuário (saldo em pokédollars)
$usuario = new Usuario();
$usuario->load($usuarioId);
$saldo = $usuario->getDinheiro() ?? 1000;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokeArena - Poké Mart</title>
    <link rel="stylesheet" href="assets/css/mart.css">
</head>
<body>

    <div class="mart-wrapper">

        <!-- Cabeçalho da Poké Mart em tela cheia -->
        <header class="mart-header">
            <div class="titulo-box">
                <h1>Poké Mart</h1>
                <p>Bem-vindo, Treinador <b><?php echo htmlspecialchars($usuarioNome); ?></b>! Abasteça seus itens para as batalhas.</p>
            </div>
            <div class="mart-header-acoes">
                <div class="carteira">
                    <span class="carteira-rotulo">Seu Saldo:</span>
                    <span class="carteira-saldo">₽ <?php echo number_format($saldo, 0, ',', '.'); ?></span>
                </div>
                <a class="btn-voltar" href="index.php?rota=batalha">⚔ Voltar à Batalha</a>
                <a class="btn-voltar" href="index.php?rota=menu">← Menu Principal</a>
            </div>
        </header>

        <!-- Conteúdo Principal em Tela Cheia -->
        <div class="mart-conteudo">
            <!-- Vitrine de Produtos da Loja Organizados por Categoria -->
            <main class="vitrine-itens">
                <?php foreach ($itensCatalogo as $categoria => $itens): ?>
                    <section class="secao-categoria">
                        <h2><?php echo htmlspecialchars($categoria); ?></h2>
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
