<?php
require_once __DIR__ . '/../../../verifica_login.php';

$pdo = Conexao::getConnection();
$usuarioId = (int)$_SESSION['usuario_id'];
$usuarioNome = $_SESSION['usuario'] ?? 'Treinador';

// Lista os 20 Pokémons cadastrados no catálogo
$catalogo = PokemonCatalogo::listar();

// Cria um mapa indexado pelo nome do Pokémon para facilitar o acesso no JavaScript
$catalogoIndexado = [];
foreach ($catalogo as $p) {
    $catalogoIndexado[$p['nome']] = $p;
}

// Busca o time atual do usuário no banco de dados (se já tiver salvo antes)
$timeAtual = Time::porUsuario($pdo, $usuarioId);
$timeInicial = [];

if (!empty($timeAtual)) {
    foreach ($timeAtual as $tp) {
        $pCatalogo = PokemonCatalogo::buscarPorNome($tp['nome']);
        if ($pCatalogo) {
            $timeInicial[] = $pCatalogo;
        } else {
            $timeInicial[] = [
                'nome' => $tp['nome'],
                'tipo' => $tp['tipo'],
                'nivel' => (int)$tp['nivel'],
                'hpMaximo' => (int)$tp['hp_maximo'],
                'hpAtual' => (int)$tp['hp_atual'],
                'ataque' => (int)$tp['ataque'],
                'defesa' => (int)$tp['defesa'],
                'velocidade' => (int)$tp['velocidade'],
                'prioridade' => (int)$tp['prioridade'],
                'statusCondicao' => $tp['status_condicao'],
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/25.png'
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monte seu Time Pokémon</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;600;700&family=Press+Start+2P&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="container">
        <!-- Barra Superior de Navegação -->
        <div class="topo-bar">
            <div>
                <h2>Olá, <?php echo htmlspecialchars($usuarioNome); ?>!</h2>
                <small>Escolha até 3 Pokémons para o seu time clicando no botão "Adicionar ao Time" abaixo.</small>
            </div>
            <div class="acoes-topo">
                <a href="index.php?rota=menu" class="botao">Voltar ao Menu</a>
                <a href="logout.php" class="botao botao-sair">Sair</a>
            </div>
        </div>

        <!-- Seção "Seu Time" (Apenas o título e as caixinhas dos Pokémons selecionados) -->
        <div class="painel-time">
            <div class="painel-time-header">
                <h2>Seu Time <span class="contador-badge"><span id="contador">0</span>/3</span></h2>
                <button type="button" class="btn-fechar-time" id="btnFecharTime" onclick="fecharTime()">
                    ✓ Fechar Time
                </button>
            </div>

            <!-- Área onde aparecem as caixinhas dos Pokémons selecionados -->
            <div class="slots-container" id="slotsTime">
                <!-- Preenchido dinamicamente pelo TimeManager.js com a mesma caixinha -->
            </div>
        </div>

        <!-- Título do Catálogo -->
        <div class="TituloCatalogo">
            <h2>Pokémons Disponíveis (20)</h2>
            <p>Analise os atributos e golpes. Para adicionar um Pokémon à equipe, clique no botão <b>"Adicionar ao Time"</b>.</p>
        </div>

        <!-- Grade de 20 Caixas com os Pokémons do Catálogo -->
        <div class="catalogoPokemon" id="catalogoPokemon">
            <?php foreach ($catalogo as $p): ?>
                <?php $nomeSeguro = htmlspecialchars($p['nome']); ?>
                <div class="poke-card" id="card-<?php echo $nomeSeguro; ?>">

                    <!-- Topo da Caixa: Número, Nome e Tipo -->
                    <div class="titulo-cartao">
                        <div>
                            <span class="numero-poke">#<?php echo str_pad($p['id_pokedex'], 3, '0', STR_PAD_LEFT); ?></span>
                            <div class="nome-poke"><?php echo $nomeSeguro; ?></div>
                        </div>
                        <span class="tipo-<?php echo htmlspecialchars($p['tipo']); ?>">
                            <?php echo htmlspecialchars($p['tipo']); ?>
                        </span>
                    </div>

                    <!-- GIF Animado do Pokémon -->
                    <div class="imagem-poke">
                        <img src="<?php echo htmlspecialchars($p['gif']); ?>" alt="<?php echo $nomeSeguro; ?>" loading="lazy" onerror="this.src='<?php echo htmlspecialchars($p['imagemArtwork']); ?>'">
                    </div>

                    <!-- Atributos do Pokémon (HP, ATK, DEF, SPD) -->
                    <div class="poke-stats-grid">
                        <div class="stat-item">
                            <span class="stat-rotulo">HP:</span>
                            <span class="stat-valor" style="color: #28a745;"><?php echo (int)$p['hpMaximo']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-rotulo">ATK:</span>
                            <span class="stat-valor" style="color: #e53e3e;"><?php echo (int)$p['ataque']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-rotulo">DEF:</span>
                            <span class="stat-valor" style="color: #dd6b20;"><?php echo (int)$p['defesa']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-rotulo">SPD:</span>
                            <span class="stat-valor" style="color: #3182ce;"><?php echo (int)$p['velocidade']; ?></span>
                        </div>
                    </div>

                    <!-- Golpes do Pokémon -->
                    <div class="poke-golpes-box">
                        <b>Ataques:</b>
                        <div class="golpes-chips">
                            <?php foreach ($p['golpes'] as $g): ?>
                                <span class="golpe-chip" title="Tipo: <?php echo htmlspecialchars($g['tipo']); ?> | Poder: <?php echo (int)$g['poder']; ?>">
                                    <?php echo htmlspecialchars($g['nome']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Botão de Ação: O Pokémon é adicionado APENAS clicando neste botão -->
                    <button type="button" class="btn-acao-card adicionar" id="btn-<?php echo $nomeSeguro; ?>" data-nome="<?php echo $nomeSeguro; ?>" onclick="adicionarAoTime(window.catalogoPokemons['<?php echo $nomeSeguro; ?>'])">
                        + Adicionar ao Time
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Scripts da Aplicação -->
    <script src="assets/js/ApiClient.js"></script>
    <script src="assets/js/TimeManager.js"></script>
    <script>
        // Catálogo e dados do usuário disponibilizados para o JavaScript
        window.catalogoPokemons = <?php echo json_encode($catalogoIndexado); ?>;
        var usuarioId = <?php echo $usuarioId; ?>;
        var dadosIniciais = <?php echo json_encode($timeInicial); ?>;

        // Inicia o gerenciador com os Pokémons do usuário
        inicializarTime(dadosIniciais);
    </script>
</body>
</html>
