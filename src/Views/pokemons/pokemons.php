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

$usuarioId = $_SESSION['usuario_id'] ?? 1;
$usuarioNome = $_SESSION['usuario'] ?? 'Treinador';

$pdo = Conexao::getConnection();
$pokemons = Pokemon::porUsuario($pdo, $usuarioId);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokéArena - Menu de Pokémons</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pokemons.css?v=<?= time() ?>">
</head>
<body>

    <div class="pokemons-viewport">
        <div class="pokemons-container">

            <!-- Cabeçalho -->
            <header class="pokemons-header-card">
                <div class="pokemons-brand">
                    <img src="assets/img/pokearena_title.png" alt="PokéArena" class="pokemons-logo">
                    <div class="pokemons-title-text">
                        <span class="pokemons-badge-title">CATÁLOGO & POKÉDEX</span>
                        <span class="pokemons-subtitle">Consulte dados da PokéAPI e veja seus Pokémon</span>
                    </div>
                </div>

                <div class="pokemons-header-acoes">
                    <div class="treinador-badge">
                        Treinador: <b><?php echo htmlspecialchars($usuarioNome); ?></b>
                    </div>
                    <a href="index.php?rota=menu" class="btn-voltar">← Menu Principal</a>
                </div>
            </header>

            <!-- Seção 1: Pesquisa na PokéAPI -->
            <section class="pokemons-card">
                <h2 class="secao-titulo">🔍 Pesquisar na PokéAPI</h2>
                <div class="search-form-group">
                    <div class="search-input-box">
                        <input type="text" id="campoBusca" placeholder="Digite o nome ou número (ex: pikachu, mewtwo, charizard)" onkeydown="if(event.key==='Enter') buscar();">
                    </div>
                    <button type="button" class="btn-search" onclick="buscar()">Buscar</button>
                </div>

                <div id="resultado"></div>
            </section>

            <!-- Seção 2: Pokémons do Treinador -->
            <section class="pokemons-card">
                <h2 class="secao-titulo">👥 Meus Pokémon Salvos</h2>

                <?php if (empty($pokemons)): ?>
                    <div class="empty-poke-box">
                        <p>Você ainda não possui Pokémons salvos no seu perfil.</p>
                        <a href="index.php?rota=builder" class="btn-ir-builder">⚔ Montar Equipe no Builder</a>
                    </div>
                <?php else: ?>
                    <div class="user-pokemons-grid">
                        <?php foreach ($pokemons as $p): ?>
                            <?php
                                $hpMax = max((int)($p['hp_maximo'] ?? 1), 1);
                                $hpAtual = max((int)($p['hp_atual'] ?? $hpMax), 0);
                                $porcHp = min(($hpAtual / $hpMax) * 100, 100);
                                $status = $p['status_condicao'] ?? 'Normal';
                                $isDesmaiado = ($hpAtual <= 0 || strtolower($status) === 'desmaiado');
                                $nomeSanitizado = strtolower(trim($p['nome']));
                                $sprite = "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/showdown/{$nomeSanitizado}.gif";
                                $fallback = "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/{$p['id']}.png";
                            ?>
                            <div class="trainer-poke-card">
                                <div class="poke-card-header">
                                    <span class="poke-card-title"><?php echo htmlspecialchars($p['nome']); ?></span>
                                    <span class="poke-card-level">Nv. <?php echo (int)($p['nivel'] ?? 5); ?></span>
                                </div>
                                <div class="poke-card-body">
                                    <img src="<?php echo $sprite; ?>" alt="<?php echo htmlspecialchars($p['nome']); ?>" class="poke-card-sprite" onerror="this.onerror=null; this.src='https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/poke-ball.png';">
                                    <div class="poke-card-details">
                                        <span class="badge-tipo"><?php echo htmlspecialchars($p['tipo']); ?></span>
                                        <div class="poke-hp-bar-box">
                                            <div class="poke-hp-text">
                                                <span>HP</span>
                                                <span><?php echo "{$hpAtual}/{$hpMax}"; ?></span>
                                            </div>
                                            <div class="poke-hp-bar">
                                                <div class="poke-hp-fill" style="width: <?php echo $porcHp; ?>%;"></div>
                                            </div>
                                        </div>
                                        <span class="badge-status-pill <?php echo $isDesmaiado ? 'badge-status-desmaiado' : 'badge-status-normal'; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </div>

    <script src="assets/js/ApiClient.js"></script>
    <script>
        async function buscar() {
            const input = document.getElementById("campoBusca");
            const nome = input.value.trim();
            const resultado = document.getElementById("resultado");

            if (!nome) {
                alert("Por favor, digite o nome ou número de um Pokémon.");
                return;
            }

            resultado.innerHTML = `
                <div style="text-align: center; padding: 20px; color: #34d399; font-weight: 700;">
                    Consultando Pokédex na PokéAPI...
                </div>
            `;

            try {
                const pokemon = await buscarPokemon(nome);
                mostrarPokemon(pokemon);
            } catch (erro) {
                resultado.innerHTML = `
                    <div style="text-align: center; padding: 16px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 12px; color: #fca5a5; font-weight: 700;">
                        ${erro.message || 'Pokémon não encontrado na PokéAPI.'}
                    </div>
                `;
            }
        }

        function mostrarPokemon(pokemon) {
            const resultado = document.getElementById("resultado");
            resultado.innerHTML = `
                <div class="pokemon-preview-card">
                    <img src="${pokemon.imagemArtwork}" alt="${pokemon.nome}" class="preview-img" onerror="this.src='${pokemon.imagemFrente}'">
                    <div class="preview-info">
                        <div class="preview-nome">
                            <span>${pokemon.nome}</span>
                            <span class="badge-tipo">${pokemon.tipo}</span>
                        </div>
                        <div class="preview-stats-grid">
                            <div class="preview-stat-item">
                                <span>HP Máx:</span>
                                <b>${pokemon.hpMaximo}</b>
                            </div>
                            <div class="preview-stat-item">
                                <span>Ataque:</span>
                                <b>${pokemon.ataque}</b>
                            </div>
                            <div class="preview-stat-item">
                                <span>Defesa:</span>
                                <b>${pokemon.defesa}</b>
                            </div>
                            <div class="preview-stat-item">
                                <span>Velocidade:</span>
                                <b>${pokemon.velocidade}</b>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
    </script>
</body>
</html>
