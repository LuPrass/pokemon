<?php
require_once __DIR__ . '/../../../verifica_login.php';
require_once __DIR__ . '/../../../Autoload.php';

$pdo = Conexao::getConnection();

// Identifica o usuário logado
$usuarioId = $_SESSION['usuario_id'] ?? 1;
$usuarioNome = $_SESSION['usuario'] ?? 'Treinador';

// Busca dados atualizados do usuário (saldo em pokédollars)
$usuario = new Usuario();
$usuario->load($usuarioId);
$saldo = $usuario->getDinheiro() ?? 1000;

// Busca preferencialmente os Pokémons escolhidos no Team Builder ($_SESSION['meu_time'])
$timeBuilder = $_SESSION['meu_time'] ?? [];
$pokemons = [];

if (!empty($timeBuilder)) {
    foreach ($timeBuilder as $p) {
        $hpMax = (int)($p['hp_maximo'] ?? $p['hp'] ?? 50);
        $hpAtual = (int)($p['hp_atual'] ?? $p['hp'] ?? $hpMax);
        $status = $p['status_condicao'] ?? $p['status'] ?? 'Normal';
        $foto = !empty($p['img']) ? $p['img'] : (!empty($p['imagem_frente']) ? $p['imagem_frente'] : '');

        $pokemons[] = [
            'id' => $p['id'] ?? null,
            'nome' => ucfirst($p['name'] ?? $p['nome'] ?? 'Pokémon'),
            'imagem_frente' => $foto,
            'hp_atual' => $hpAtual,
            'hp_maximo' => $hpMax,
            'status_condicao' => $status
        ];
    }
} else {
    // Fallback: se ainda não houver time no builder, verifica no banco de dados
    $pokemonsBanco = Pokemon::porUsuario($pdo, $usuarioId);
    foreach ($pokemonsBanco as $p) {
        $pokemons[] = [
            'id' => $p['id'] ?? null,
            'nome' => ucfirst($p['nome'] ?? 'Pokémon'),
            'imagem_frente' => $p['imagem_frente'] ?? '',
            'hp_atual' => (int)($p['hp_atual'] ?? 0),
            'hp_maximo' => (int)($p['hp_maximo'] ?? 1),
            'status_condicao' => $p['status_condicao'] ?? 'Normal'
        ];
    }
}

// Busca itens cadastrados no banco de dados
$itensBanco = [];
try {
    $itensBanco = Item::all($pdo);
} catch (Throwable $e) {
    $itensBanco = [];
}

// Catálogo com as categorias e descrições exigidas no documento de avaliação
$itensCatalogo = [
    'Restauradores de Vida (HP)' => [
        ['nome' => 'Potion', 'descricao' => 'Recupera 20 HP', 'preco' => 200, 'tipo' => 'restaurador', 'icone' => '🧪'],
        ['nome' => 'Super Potion', 'descricao' => 'Recupera 50 HP', 'preco' => 700, 'tipo' => 'restaurador', 'icone' => '🧴'],
        ['nome' => 'Hyper Potion', 'descricao' => 'Recupera 200 HP', 'preco' => 1200, 'tipo' => 'restaurador', 'icone' => '⚗️'],
        ['nome' => 'Max Potion', 'descricao' => 'Recupera 100% do HP', 'preco' => 2500, 'tipo' => 'restaurador', 'icone' => '✨'],
        ['nome' => 'Full Restore', 'descricao' => 'Recupera 100% do HP + cura todas as condições de status', 'preco' => 3000, 'tipo' => 'restaurador', 'icone' => '💖'],
    ],
    'Cura de Condição' => [
        ['nome' => 'Antidote', 'descricao' => 'Cura envenenamento', 'preco' => 100, 'tipo' => 'cura', 'icone' => '🟢'],
        ['nome' => 'Burn Heal', 'descricao' => 'Cura queimadura', 'preco' => 250, 'tipo' => 'cura', 'icone' => '🔥'],
        ['nome' => 'Ice Heal', 'descricao' => 'Cura congelamento', 'preco' => 250, 'tipo' => 'cura', 'icone' => '❄️'],
        ['nome' => 'Awakening', 'descricao' => 'Cura sono', 'preco' => 250, 'tipo' => 'cura', 'icone' => '⏰'],
        ['nome' => 'Paralyze Heal', 'descricao' => 'Cura paralisia', 'preco' => 200, 'tipo' => 'cura', 'icone' => '⚡'],
        ['nome' => 'Full Heal', 'descricao' => 'Cura todos os status', 'preco' => 600, 'tipo' => 'cura', 'icone' => '⭐'],
    ],
    'Reviver' => [
        ['nome' => 'Revive', 'descricao' => 'Revive um Pokémon desmaiado com metade da vida', 'preco' => 1500, 'tipo' => 'reviver', 'icone' => '💎'],
    ],
    'Evolução' => [
        ['nome' => 'Pedra de Evolução', 'descricao' => 'Evolui o Pokémon para a sua próxima forma', 'preco' => 5000, 'tipo' => 'evolucao', 'icone' => '🔮'],
    ]
];

// Se houver itens no banco, sincroniza os IDs reais para facilitar a futura compra
if (!empty($itensBanco)) {
    $mapaBanco = [];
    foreach ($itensBanco as $ib) {
        $mapaBanco[strtolower(trim($ib['nome']))] = $ib;
    }

    foreach ($itensCatalogo as $categoria => &$itens) {
        foreach ($itens as &$it) {
            $chave = strtolower(trim($it['nome']));
            if (isset($mapaBanco[$chave])) {
                $it['id'] = $mapaBanco[$chave]['id'];
                $it['preco'] = $mapaBanco[$chave]['preco'];
                if (!empty($mapaBanco[$chave]['descricao'])) {
                    $it['descricao'] = $mapaBanco[$chave]['descricao'];
                }
            } else {
                $it['id'] = null;
            }
        }
    }
    unset($itens);
}
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

            <!-- Painel: Visualização dos Pokémons do Treinador -->
            <section class="painel-time">
                <h3>👥 Seu Time Atual</h3>
                <?php if (empty($pokemons)): ?>
                    <p style="margin: 0; color: #777; font-size: 14px;">Você ainda não montou seu time na tela de Batalha. <a href="index.php?rota=builder" style="color: #8259af; font-weight: bold; text-decoration: underline;">Clique aqui para escolher seus 3 Pokémons no Builder</a>.</p>
                <?php else: ?>
                    <div class="grid-pokemons-usuario">
                        <?php foreach ($pokemons as $p): ?>
                            <?php
                                $hpAtual = (int)($p['hp_atual'] ?? 0);
                                $hpMax = (int)($p['hp_maximo'] ?? 1);
                                $status = $p['status_condicao'] ?? 'Normal';
                                $desmaiado = ($hpAtual <= 0 || strtolower($status) === 'desmaiado');
                                $classeStatus = $desmaiado ? 'badge-desmaiado' : (strtolower($status) === 'normal' ? 'badge-normal' : 'badge-alerta');
                                $foto = !empty($p['imagem_frente']) ? $p['imagem_frente'] : 'assets/img/pokeball.png';
                            ?>
                            <div class="card-pokemon-mini <?php echo $desmaiado ? 'desmaiado' : ''; ?>">
                                <img src="<?php echo htmlspecialchars($foto); ?>" alt="<?php echo htmlspecialchars($p['nome']); ?>" onerror="this.src='https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/poke-ball.png'">
                                <div class="pokemon-mini-info">
                                    <b><?php echo htmlspecialchars($p['nome']); ?></b>
                                    <div>HP: <?php echo $hpAtual; ?> / <?php echo $hpMax; ?></div>
                                    <span class="badge-status <?php echo $classeStatus; ?>"><?php echo htmlspecialchars($status); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

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

        <!-- Rodapé em Barra de Largura Total -->
        <footer class="rodape-nav">
            <span><b>PokéArena</b> - Poké Mart Oficial</span>
            <div>
                <a class="btn-voltar" href="index.php?rota=batalha" style="margin-right: 10px;">⚔ Voltar à Batalha</a>
                <a class="btn-voltar" href="index.php?rota=menu">← Voltar ao Menu</a>
            </div>
        </footer>

    </div>

</body>
</html>
