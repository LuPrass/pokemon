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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="container">
        <!-- Barra Superior de Navegação -->
        <div class="topo-bar">
            <div>
                <h2>Olá, <?php echo htmlspecialchars($usuarioNome); ?>!</h2>
                <small>Escolha até 3 Pokémons para o seu time clicando nas imagens abaixo.</small>
            </div>
            <div class="acoes-topo">
                <a href="index.php?rota=menu" class="botao">Voltar ao Menu</a>
                <a href="logout.php" class="botao botao-sair">Sair</a>
            </div>
        </div>

        <!-- Painel do Time Selecionado (Exibe até 3 vagas) -->
        <div class="painel-time">
            <div class="painel-time-header">
                <div>
                    <h2>Seu Time <span class="contador-badge"><span id="contador">0</span>/3</span></h2>
                    <small style="color: #718096;">Remova integrantes clicando no botão "✕ Remover" antes de fechar o time.</small>
                </div>
                <button type="button" class="btn-fechar-time" id="btnFecharTime" onclick="fecharTime()">
                    ✓ Fechar Time
                </button>
            </div>

            <!-- As 3 Vagas do Time -->
            <div class="slots-container" id="slotsTime">
                <!-- Preenchido dinamicamente pelo TimeManager.js -->
            </div>
        </div>

        <!-- Título do Catálogo -->
        <div class="TituloCatalogo">
            <h2>Pokémons Disponíveis (20)</h2>
            <p>Clique na imagem para adicionar ao seu time. A remoção é feita exclusivamente clicando no botão Remover.</p>
        </div>

        <!-- Grade de 20 Caixas com os Pokémons -->
        <div class="catalogoPokemon" id="catalogoPokemon">
            <?php foreach ($catalogo as $p): ?>
                <?php $nomeSeguro = htmlspecialchars($p['nome']); ?>
                <div class="poke-card" id="card-<?php echo $nomeSeguro; ?>">
                    <!-- Selo de Selecionado quando estiver no time -->
                    <span class="badge-selecionado">No Time</span>

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

                    <!-- Imagem do Pokémon: Ao ser clicada, adiciona ao time -->
                    <div class="imagem-poke" title="Clique na imagem para adicionar <?php echo $nomeSeguro; ?> ao time" onclick="adicionarAoTime(window.catalogoPokemons['<?php echo $nomeSeguro; ?>'])">
                        <img src="<?php echo htmlspecialchars($p['imagemArtwork']); ?>" alt="<?php echo $nomeSeguro; ?>">
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

                    <!-- Botão de Ação: Adicionar ao Time (a remoção ocorre apenas ao clicar no botão Remover) -->
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
