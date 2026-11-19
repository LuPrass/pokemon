<?php
// ==============================================================================
// PROJETO MONSTROBOLSO - TELA DE BATALHA (ARENA)
// Disciplinas: Programação Orientada a Objetos I, Linguagens para a Web II, BD II
// ==============================================================================

require_once __DIR__ . '/../../../Autoload.php';
require_once __DIR__ . '/../../Services/PokeApiService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------------------------------------------------------
// PASSO 1: Verificações de segurança e login
// ------------------------------------------------------------------------------
if (empty($_SESSION['logado'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['meu_time'])) {
    header('Location: index.php?rota=builder');
    exit;
}

// ------------------------------------------------------------------------------
// PASSO 2: Conexão com o banco de dados e busca de dados do treinador
// ------------------------------------------------------------------------------
$pdo = Conexao::getConnection();
$usuarioId = (int)$_SESSION['usuario_id'];

// Busca o nome e o saldo de pokédollars atualizado no banco
$stmtUser = $pdo->prepare("SELECT nome, dinheiro FROM usuario WHERE id = :id");
$stmtUser->execute([':id' => $usuarioId]);
$dadosUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

$usuarioNome = $_SESSION['usuario'];
if (!empty($dadosUser['nome'])) {
    $usuarioNome = $dadosUser['nome'];
}

$usuarioDinheiro = 0;
if (isset($dadosUser['dinheiro'])) {
    $usuarioDinheiro = (int)$dadosUser['dinheiro'];
}

// ------------------------------------------------------------------------------
// PASSO 3: Funções auxiliares simples (evita repetições de código)
// ------------------------------------------------------------------------------

// Prepara o Pokémon garantindo que ele tenha os golpes cadastrados
function prepararPokemon($pokemon) {
    $pokemon = Pokemon::normalizar($pokemon);

    if (empty($pokemon['movimentos']) && !empty($pokemon['id'])) {
        $pokemon['movimentos'] = PokeApiService::obterMovimentosPokemon((int)$pokemon['id']);
    }
    if (empty($pokemon['movimentos'])) {
        $pokemon['movimentos'] = PokeApiService::obterMovimentosPadrao(0, $pokemon['nome'], $pokemon['tipo']);
    }
    return $pokemon;
}

// Sorteia um dos golpes disponíveis para o adversário atacar
function escolherGolpeDoInimigo($pokemon) {
    $golpes = array_values($pokemon['movimentos']);
    $indiceSorteado = array_rand($golpes);
    return $golpes[$indiceSorteado];
}

// Obtém a imagem/sprite oficial do Pokémon
function obterImagemPokemon($pokemon) {
    $id = (int)$pokemon['id'];
    if ($id > 0) {
        $sprite = PokeApiService::buscarPorId($id);
        if (!empty($sprite['gif'])) {
            return $sprite['gif'];
        }
    }
    if (!empty($pokemon['img'])) {
        return $pokemon['img'];
    }
    return '';
}

// Calcula a porcentagem e a cor da barra de vida (Verde, Amarelo ou Vermelho)
function calcularBarraHp($hpAtual, $hpMax) {
    $porcentagem = (int)(($hpAtual / max(1, $hpMax)) * 100);
    if ($porcentagem > 100) $porcentagem = 100;
    if ($porcentagem < 0) $porcentagem = 0;

    $cor = '#ef4444'; // Vermelho (vida baixa)
    if ($porcentagem > 50) {
        $cor = '#10b981'; // Verde (vida boa)
    } elseif ($porcentagem > 20) {
        $cor = '#f59e0b'; // Amarelo (vida média)
    }

    return [
        'porcentagem' => $porcentagem,
        'cor'         => $cor
    ];
}

// Reseta os modificadores temporários de ataque e defesa dos Pokémon
function resetarModificadoresTime(&$time) {
    for ($i = 0; $i < count($time); $i++) {
        $time[$i]['modAtaque'] = 1.0;
        $time[$i]['modDefesa'] = 1.0;
    }
}

// Atualiza o HP, nível e condição de status do Pokémon no banco de dados
function salvarPokemonNoBanco($pdo, $pk, $usuarioId) {
    if (empty($pk['pokemon_db_id'])) {
        return;
    }
    $stmt = $pdo->prepare("UPDATE pokemon SET hp_atual = :hp, status_condicao = :status, nivel = :nivel WHERE id = :id AND usuario_id = :usuario_id");
    $stmt->execute([
        ':hp'         => $pk['hpAtual'],
        ':status'     => $pk['statusCondicao'],
        ':nivel'      => $pk['nivel'],
        ':id'         => $pk['pokemon_db_id'],
        ':usuario_id' => $usuarioId
    ]);
}

// Registra a vitória ou derrota na tabela log_batalha (exigência do trabalho)
function registrarLogBatalha($pdo, $usuarioId, $pokemonDbId, $adversario, $resultado, $dinheiro) {
    $stmt = $pdo->prepare("INSERT INTO log_batalha (usuario_id, pokemon_id, adversario, resultado, dinheiro_ganho) VALUES (:usuario_id, :pokemon_id, :adversario, :resultado, :dinheiro)");
    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':pokemon_id' => $pokemonDbId,
        ':adversario' => $adversario,
        ':resultado'  => $resultado,
        ':dinheiro'   => $dinheiro
    ]);
}

// Verifica se todos os Pokémon do jogador estão desmaiados
function todosPokemonDesmaiados($time) {
    foreach ($time as $pk) {
        if ($pk['hpAtual'] > 0) {
            return false;
        }
    }
    return true;
}

// Mapeamento das condições de status para exibição visual
$mapaBadgesStatus = [
    'Queimado'   => ['classe' => 'badge-queimado',   'texto' => '🔥 Queimado'],
    'Congelado'  => ['classe' => 'badge-congelado',  'texto' => '❄️ Congelado'],
    'Paralisado' => ['classe' => 'badge-paralisado', 'texto' => '⚡ Paralisado'],
    'Envenenado' => ['classe' => 'badge-envenenado', 'texto' => '☠️ Envenenado'],
    'Dormindo'   => ['classe' => 'badge-dormindo',   'texto' => '💤 Dormindo'],
];

// Gera o badge de status do Pokémon de forma simples e sem duplicar HTML
function renderizarBadgeStatus($statusCondicao, $mapaBadgesStatus) {
    if (empty($statusCondicao)) {
        return '';
    }
    $st = ucfirst(strtolower($statusCondicao));
    if ($st !== 'Normal' && isset($mapaBadgesStatus[$st])) {
        return '<span class="badge-status ' . $mapaBadgesStatus[$st]['classe'] . '">' . $mapaBadgesStatus[$st]['texto'] . '</span>';
    }
    return '';
}

// ------------------------------------------------------------------------------
// PASSO 4: Carrega o time do jogador e sincroniza com o banco de dados
// ------------------------------------------------------------------------------
$meuTime = array_values($_SESSION['meu_time']);

for ($i = 0; $i < count($meuTime); $i++) {
    $pk = $meuTime[$i];

    // Se o Pokémon tem ID no banco, pega o HP e status atualizados mais recentes
    if (!empty($pk['pokemon_db_id'])) {
        $stmtSync = $pdo->prepare("SELECT hp_atual, status_condicao, nivel FROM pokemon WHERE id = :id AND usuario_id = :uid");
        $stmtSync->execute([
            ':id'  => $pk['pokemon_db_id'],
            ':uid' => $usuarioId
        ]);
        $dadosDb = $stmtSync->fetch(PDO::FETCH_ASSOC);

        if ($dadosDb) {
            $pk['hpAtual'] = (int)$dadosDb['hp_atual'];
            if (!empty($dadosDb['status_condicao'])) {
                $pk['statusCondicao'] = $dadosDb['status_condicao'];
            } else {
                $pk['statusCondicao'] = 'Normal';
            }
            $pk['status_condicao'] = $pk['statusCondicao'];
            $pk['nivel'] = (int)$dadosDb['nivel'];
        }
    }

    $meuTime[$i] = prepararPokemon($pk);
}

$_SESSION['meu_time'] = $meuTime;
$_SESSION['batalha_iniciada'] = true;

// ------------------------------------------------------------------------------
// PASSO 5: Inicialização da campanha de 9 batalhas (se ainda não existir)
// ------------------------------------------------------------------------------
if (empty($_SESSION['combate']) || !isset($_SESSION['combate']['batalha_atual'])) {

    $inimigosTime = [];
    if (!empty($_SESSION['inimigos_time'])) {
        $inimigosTime = array_values($_SESSION['inimigos_time']);
    }

    // Se não tiver inimigos na sessão, pega os Pokémon do catálogo que não estão no nosso time
    if (empty($inimigosTime)) {
        $idsEscolhidos = [];
        foreach ($meuTime as $p) {
            $idsEscolhidos[] = (int)$p['id'];
        }

        foreach (PokeApiService::IDS as $idCatalogo) {
            if (!in_array($idCatalogo, $idsEscolhidos)) {
                $info = PokeApiService::buscarPorId($idCatalogo);
                if ($info) {
                    $info['id'] = $idCatalogo;
                    $inimigosTime[] = $info;
                }
            }
        }
    }

    for ($i = 0; $i < count($inimigosTime); $i++) {
        $inimigosTime[$i] = prepararPokemon($inimigosTime[$i]);
    }
    $_SESSION['inimigos_time'] = $inimigosTime;

    // Sorteia um dos nossos Pokémon com HP > 0 para iniciar o combate
    $indicesVivos = [];
    for ($i = 0; $i < count($meuTime); $i++) {
        if ($meuTime[$i]['hpAtual'] > 0) {
            $indicesVivos[] = $i;
        }
    }

    $idxJogadorSorteado = 0;
    if (!empty($indicesVivos)) {
        $idxJogadorSorteado = $indicesVivos[array_rand($indicesVivos)];
    }

    // Sorteia o primeiro adversário entre os 9 restantes
    $inimigosPool = $inimigosTime;
    $idxInimigoSorteado = array_rand($inimigosPool);
    $inimigoAtual = $inimigosPool[$idxInimigoSorteado];
    unset($inimigosPool[$idxInimigoSorteado]);

    $_SESSION['combate'] = [
        'batalha_atual'      => 1,
        'total_batalhas'     => 9,
        'vitorias'           => 0,
        'pokemon_ativo_idx'  => $idxJogadorSorteado,
        'inimigo_atual'      => $inimigoAtual,
        'inimigos_restantes' => array_values($inimigosPool),
        'status'             => 'em_combate',
        'menu_aberto'        => 'principal',
        'mensagens'          => [
            "Início do combate! Batalha 1 de 9.",
            "O seu Pokémon sorteado foi: " . $meuTime[$idxJogadorSorteado]['nome'] . "!",
            "O adversário sorteado foi: " . $inimigoAtual['nome'] . "!"
        ]
    ];
}

// ------------------------------------------------------------------------------
// PASSO 6: Processamento das ações do formulário (POST)
// ------------------------------------------------------------------------------
$avisoErro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = '';
    if (isset($_POST['acao'])) {
        $acao = $_POST['acao'];
    }

    $combate = &$_SESSION['combate'];
    $ativoIdx = (int)$combate['pokemon_ativo_idx'];

    // 6.1: Navegação do mini-menu de botões (Lutar, Trocar, Principal)
    if ($acao === 'abrir_menu') {
        $menuAlvo = 'principal';
        if (isset($_POST['menu_alvo'])) {
            $menuAlvo = $_POST['menu_alvo'];
        }
        $combate['menu_aberto'] = $menuAlvo;
    }

    // 6.2: Ação de Ataque
    elseif ($acao === 'executar_ataque' && $combate['status'] === 'em_combate') {
        $tipoAtaqueEscolhido = 'fisico';
        if (isset($_POST['tipo_ataque'])) {
            $tipoAtaqueEscolhido = $_POST['tipo_ataque'];
        }
        if (!in_array($tipoAtaqueEscolhido, ['fisico', 'especial', 'status'], true)) {
            $tipoAtaqueEscolhido = 'fisico';
        }

        $jogador = Pokemon::fromArray($meuTime[$ativoIdx]);
        $inimigo = Pokemon::fromArray($combate['inimigo_atual']);

        $golpesJogador = $meuTime[$ativoIdx]['movimentos'];
        if (isset($golpesJogador[$tipoAtaqueEscolhido])) {
            $golpeJogador = $golpesJogador[$tipoAtaqueEscolhido];
        } else {
            $golpeJogador = reset($golpesJogador);
        }

        $golpeInimigo = escolherGolpeDoInimigo($combate['inimigo_atual']);

        // A classe Batalha calcula os turnos, golpes, vantagens e status
        $batalha = new Batalha($jogador, $inimigo);
        $batalha->turno($golpeJogador, $golpeInimigo);
        $combate['mensagens'] = $batalha->getTurnos();

        // Guarda as alterações de HP e status de volta nos arrays
        $meuTime[$ativoIdx] = array_merge($meuTime[$ativoIdx], $jogador->paraArray());
        $combate['inimigo_atual'] = array_merge($combate['inimigo_atual'], $inimigo->paraArray());
        salvarPokemonNoBanco($pdo, $meuTime[$ativoIdx], $usuarioId);

        // Se o inimigo desmaiou, vencemos esta rodada!
        if ($inimigo->estaDesmaiado()) {
            $combate['vitorias']++;

            // O Pokémon que venceu sobe 1 nível
            $jogador->subirNivel();
            $meuTime[$ativoIdx]['nivel'] = $jogador->getNivel();
            salvarPokemonNoBanco($pdo, $meuTime[$ativoIdx], $usuarioId);
            $combate['mensagens'][] = "⬆️ " . $jogador->getNome() . " subiu para o nível " . $jogador->getNivel() . "!";

            // Vitória rende 100 Pokédollars
            $recompensa = 100;
            registrarLogBatalha($pdo, $usuarioId, $meuTime[$ativoIdx]['pokemon_db_id'], $combate['inimigo_atual']['nome'], 'vitoria', $recompensa);
            $usuarioDinheiro = $usuarioDinheiro + $recompensa;
            $combate['mensagens'][] = "💰 Recompensa: +" . $recompensa . " Pokédollars adicionados ao seu saldo!";

            // Se venceu todas as 9 batalhas, é o grande campeão
            if ($combate['batalha_atual'] >= $combate['total_batalhas']) {
                $combate['status'] = 'campeao';
                $combate['mensagens'][] = "🏆 PARABÉNS! Você derrotou todos os 9 adversários e se tornou o GRANDE CAMPEÃO!";
            } else {
                $combate['status'] = 'vitoria_rodada';
            }
        } elseif ($jogador->estaDesmaiado()) {
            // Nosso Pokémon desmaiou: registra derrota no log
            registrarLogBatalha($pdo, $usuarioId, $meuTime[$ativoIdx]['pokemon_db_id'], $combate['inimigo_atual']['nome'], 'derrota', 0);

            // Verifica se ainda tem algum outro Pokémon vivo no time
            if (todosPokemonDesmaiados($meuTime)) {
                // Se não tem nenhum vivo e não tem dinheiro para curar, encerra o jogo
                if ($usuarioDinheiro < 25) {
                    unset($_SESSION['combate'], $_SESSION['meu_time'], $_SESSION['inimigos_time'], $_SESSION['batalha_iniciada']);
                    $_SESSION['aviso_derrota'] = "FIM DE JOGO: Todos os seus Pokémon desmaiaram e você não possui saldo suficiente para curá-los! Monte um novo time para recomeçar.";
                    header("Location: index.php?rota=menu");
                    exit;
                } else {
                    $combate['status'] = 'derrota_sem_pokemon';
                }
            } else {
                $combate['mensagens'][] = "Vá até 'Trocar' para escolher outro Pokémon da sua equipe.";
            }
        }

        $combate['menu_aberto'] = 'principal';
        $_SESSION['meu_time'] = $meuTime;
    }

    // 6.3: Ação de Trocar de Pokémon
    elseif ($acao === 'trocar' && ($combate['status'] === 'em_combate' || $combate['status'] === 'derrota_sem_pokemon')) {
        $novoIdx = -1;
        if (isset($_POST['novo_idx'])) {
            $novoIdx = (int)$_POST['novo_idx'];
        }

        if (!isset($meuTime[$novoIdx])) {
            $avisoErro = "Pokémon inválido selecionado para troca!";
        } elseif ($meuTime[$novoIdx]['hpAtual'] <= 0) {
            $avisoErro = "O Pokémon " . $meuTime[$novoIdx]['nome'] . " está desmaiado (HP = 0) e não pode entrar em combate!";
        } elseif ($novoIdx === $combate['pokemon_ativo_idx']) {
            $avisoErro = "Este Pokémon já está na batalha!";
        } else {
            $anteriorIdx = $combate['pokemon_ativo_idx'];
            $anteriorVivo = ($meuTime[$anteriorIdx]['hpAtual'] > 0);

            // Quem sai da batalha reseta as alterações temporárias de ataque e defesa
            $meuTime[$anteriorIdx]['modAtaque'] = 1.0;
            $meuTime[$anteriorIdx]['modDefesa'] = 1.0;

            $combate['pokemon_ativo_idx'] = $novoIdx;
            $combate['status'] = 'em_combate';
            $combate['menu_aberto'] = 'principal';
            $combate['mensagens'] = ["🔄 Você colocou " . $meuTime[$novoIdx]['nome'] . " em campo!"];

            // Se trocamos com o Pokémon anterior ainda vivo, o adversário tem direito a 1 ataque
            if ($anteriorVivo) {
                $novo = Pokemon::fromArray($meuTime[$novoIdx]);
                $inimigo = Pokemon::fromArray($combate['inimigo_atual']);

                $batalha = new Batalha($novo, $inimigo);
                $batalha->agir($inimigo, $novo, escolherGolpeDoInimigo($combate['inimigo_atual']));
                $combate['mensagens'] = array_merge($combate['mensagens'], $batalha->getTurnos());

                $meuTime[$novoIdx] = array_merge($meuTime[$novoIdx], $novo->paraArray());
                $combate['inimigo_atual'] = array_merge($combate['inimigo_atual'], $inimigo->paraArray());
                salvarPokemonNoBanco($pdo, $meuTime[$novoIdx], $usuarioId);

                if ($novo->estaDesmaiado()) {
                    registrarLogBatalha($pdo, $usuarioId, $meuTime[$novoIdx]['pokemon_db_id'], $combate['inimigo_atual']['nome'], 'derrota', 0);
                    if (todosPokemonDesmaiados($meuTime)) {
                        $combate['status'] = 'derrota_sem_pokemon';
                    }
                }
            }

            $_SESSION['meu_time'] = $meuTime;
        }
    }

    // 6.4: Avança para a próxima das 9 batalhas
    elseif ($acao === 'proxima_batalha' && $combate['status'] === 'vitoria_rodada') {
        if (!empty($combate['inimigos_restantes'])) {
            $combate['batalha_atual'] = $combate['batalha_atual'] + 1;

            $idxSorteado = array_rand($combate['inimigos_restantes']);
            $novoInimigo = $combate['inimigos_restantes'][$idxSorteado];
            unset($combate['inimigos_restantes'][$idxSorteado]);
            $combate['inimigos_restantes'] = array_values($combate['inimigos_restantes']);

            // Sobe o nível do inimigo conforme avançamos nas 9 batalhas (Batalha 1 = Nv 5 ... Batalha 9 = Nv 13)
            $nivelInimigo = 4 + $combate['batalha_atual'];
            $fator = $nivelInimigo / 5.0;

            $novoInimigo['nivel'] = $nivelInimigo;
            $novoInimigo['hpMaximo'] = (int)round($novoInimigo['hpMaximo'] * $fator);
            $novoInimigo['hpAtual'] = $novoInimigo['hpMaximo'];
            $novoInimigo['ataque'] = (int)round($novoInimigo['ataque'] * $fator);
            $novoInimigo['defesa'] = (int)round($novoInimigo['defesa'] * $fator);
            $novoInimigo['velocidade'] = (int)round($novoInimigo['velocidade'] * $fator);

            $combate['inimigo_atual'] = $novoInimigo;
            $combate['status'] = 'em_combate';
            $combate['menu_aberto'] = 'principal';

            // Reseta modificadores de ataque e defesa dos nossos Pokémon para a nova batalha
            resetarModificadoresTime($meuTime);
            $_SESSION['meu_time'] = $meuTime;

            $combate['mensagens'] = [
                "Batalha " . $combate['batalha_atual'] . " de " . $combate['total_batalhas'] . " iniciada!",
                "Um novo adversário surgiu: " . $novoInimigo['nome'] . " (Nível " . $nivelInimigo . ")!"
            ];
        }
    }

    // 6.5: Fugir da batalha
    elseif ($acao === 'fugir') {
        header("Location: index.php?rota=menu");
        exit;
    }

    // 6.6: Reiniciar a campanha
    elseif ($acao === 'reiniciar') {
        unset($_SESSION['combate']);
        header("Location: index.php?rota=batalha");
        exit;
    }
}

// ------------------------------------------------------------------------------
// PASSO 7: Prepara variáveis limpas para exibir na tela (HTML)
// ------------------------------------------------------------------------------
$combate = $_SESSION['combate'];

// Se todo mundo estava desmaiado mas o jogador curou na Mochila, retoma a batalha
if (isset($combate['status']) && $combate['status'] === 'derrota_sem_pokemon') {
    foreach ($meuTime as $pkVivo) {
        if ($pkVivo['hpAtual'] > 0) {
            $_SESSION['combate']['status'] = 'em_combate';
            $_SESSION['combate']['menu_aberto'] = 'principal';
            $combate = $_SESSION['combate'];
            break;
        }
    }
}

$batalhaAtual = (int)$combate['batalha_atual'];
$totalBatalhas = (int)$combate['total_batalhas'];
$ativoIdx = (int)$combate['pokemon_ativo_idx'];

$pokemonJogador = $meuTime[$ativoIdx];
$pokemonInimigo = $combate['inimigo_atual'];

$hpMaxJog = (int)$pokemonJogador['hpMaximo'];
$hpAtualJog = (int)$pokemonJogador['hpAtual'];

$hpMaxIni = (int)$pokemonInimigo['hpMaximo'];
$hpAtualIni = (int)$pokemonInimigo['hpAtual'];

$statusCombate = $combate['status'];
$menuAberto = $combate['menu_aberto'];
$mensagensLog = $combate['mensagens'];

$nomeJog = $pokemonJogador['nome'];
$nomeIni = $pokemonInimigo['nome'];

$movimentosJog = $pokemonJogador['movimentos'];

// Calcula porcentagem e cor da barra de vida com a função auxiliar (sem duplicar código)
$barraJog = calcularBarraHp($hpAtualJog, $hpMaxJog);
$barraIni = calcularBarraHp($hpAtualIni, $hpMaxIni);

// Imagens dos Pokémon
$imgJog = obterImagemPokemon($pokemonJogador);
$imgIni = obterImagemPokemon($pokemonInimigo);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokéArena - Batalha <?php echo $batalhaAtual; ?> de <?php echo $totalBatalhas; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/arena.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="arena-viewport">
        <div class="arena-container">

            <!-- Cabeçalho Superior -->
            <header class="arena-header-card">
                <div class="arena-brand">
                    <img src="assets/img/pokearena_title.png" alt="PokéArena" class="arena-logo">
                    <div class="arena-title-text">
                        <span class="arena-badge-title">BATALHA <?php echo $batalhaAtual; ?> DE <?php echo $totalBatalhas; ?></span>
                        <span class="arena-subtitle">Vitórias na Campanha: <b><?php echo $combate['vitorias']; ?></b></span>
                    </div>
                </div>

                <div class="arena-header-acoes">
                    <div class="treinador-badge">
                        Treinador: <b><?php echo htmlspecialchars($usuarioNome); ?></b>
                    </div>
                    <div class="saldo-badge">
                        Saldo: <b>₽ <?php echo number_format($usuarioDinheiro, 0, ',', '.'); ?></b>
                    </div>
                    <a href="index.php?rota=menu" class="btn-header-nav btn-nav-blue">Menu</a>
                    <a href="index.php?rota=loja" class="btn-header-nav btn-nav-green">PokéMart</a>
                    <a href="index.php?rota=mochila" class="btn-header-nav btn-nav-blue">Mochila</a>
                </div>
            </header>

            <?php if (!empty($avisoErro)): ?>
                <div style="background: rgba(239, 68, 68, 0.2); border: 1.5px solid #ef4444; color: #fca5a5; padding: 14px 20px; border-radius: 12px; font-weight: 700; text-align: center; margin-bottom: 12px;">
                    <?php echo htmlspecialchars($avisoErro); ?>
                </div>
            <?php endif; ?>

            <!-- Card Principal da Arena -->
            <div class="arena-card">

                <!-- Campo de Batalha com os dois Pokémon -->
                <div class="campo-batalha">
                    
                    <!-- LADO DO OPONENTE -->
                    <div class="lado lado-oponente">
                        <div class="caixa-status">
                            <div class="status-topo">
                                <span class="status-nome"><?php echo htmlspecialchars($nomeIni); ?></span>
                                <?php echo renderizarBadgeStatus($pokemonInimigo['statusCondicao'], $mapaBadgesStatus); ?>
                                <span class="status-nivel">Nv. <?php echo (int)$pokemonInimigo['nivel']; ?></span>
                            </div>
                            <div class="hp-linha">
                                <span class="hp-rotulo">HP</span>
                                <div class="hp-barra-bg">
                                    <div class="hp-barra" style="width: <?php echo $barraIni['porcentagem']; ?>%; background: <?php echo $barraIni['cor']; ?>;"></div>
                                </div>
                            </div>
                            <div class="hp-numeros">
                                <?php echo $hpAtualIni; ?> / <?php echo $hpMaxIni; ?> HP (<?php echo ucfirst($pokemonInimigo['tipo']); ?>)
                            </div>
                        </div>

                        <div class="sprite-box">
                            <?php if (!empty($imgIni)): ?>
                                <img src="<?php echo htmlspecialchars($imgIni); ?>" alt="<?php echo htmlspecialchars($nomeIni); ?>">
                            <?php endif; ?>
                            <div class="sombra"></div>
                        </div>
                    </div>

                    <!-- LADO DO JOGADOR -->
                    <div class="lado lado-jogador">
                        <div class="sprite-box">
                            <?php if (!empty($imgJog)): ?>
                                <img src="<?php echo htmlspecialchars($imgJog); ?>" alt="<?php echo htmlspecialchars($nomeJog); ?>">
                            <?php endif; ?>
                            <div class="sombra"></div>
                        </div>

                        <?php $ehEvoluido = !empty($pokemonJogador['evoluido']); ?>
                        <div class="caixa-status <?php if ($ehEvoluido) echo 'caixa-evoluido'; ?>">
                            <div class="status-topo">
                                <span class="status-nome">
                                    <?php echo htmlspecialchars($nomeJog); ?>
                                    <?php if ($ehEvoluido): ?>
                                        <span class="badge-evoluido">✨ Evoluído</span>
                                    <?php endif; ?>
                                </span>
                                <?php echo renderizarBadgeStatus($pokemonJogador['statusCondicao'], $mapaBadgesStatus); ?>
                                <span class="status-nivel">Nv. <?php echo (int)$pokemonJogador['nivel']; ?></span>
                            </div>
                            <div class="hp-linha">
                                <span class="hp-rotulo">HP</span>
                                <div class="hp-barra-bg">
                                    <div class="hp-barra" style="width: <?php echo $barraJog['porcentagem']; ?>%; background: <?php echo $barraJog['cor']; ?>;"></div>
                                </div>
                            </div>
                            <div class="hp-numeros">
                                <?php echo $hpAtualJog; ?> / <?php echo $hpMaxJog; ?> HP (<?php echo ucfirst($pokemonJogador['tipo']); ?>)
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Painel Inferior: Histórico de Turnos e Botões de Ação -->
                <div class="painel-combate">

                    <!-- Histórico da Batalha (Terminal) -->
                    <div class="battle-log">
                        <div class="battle-log-header">Histórico da Batalha</div>
                        <?php if (empty($mensagensLog)): ?>
                            <p style="margin: 0; color: #94a3b8; font-size: 0.88rem;"><em>A batalha começou! Escolha uma ação abaixo.</em></p>
                        <?php else: ?>
                            <ul class="battle-log-list">
                                <?php foreach ($mensagensLog as $msg): ?>
                                    <?php 
                                        $cor = '#e2e8f0';
                                        if (strpos($msg, 'super eficaz') !== false) {
                                            $cor = '#fbbf24';
                                        } elseif (strpos($msg, 'de dano') !== false) {
                                            $cor = '#34d399';
                                        } elseif (strpos($msg, 'recuperou') !== false) {
                                            $cor = '#38bdf8';
                                        } elseif (strpos($msg, 'desmaiou') !== false) {
                                            $cor = '#f43f5e';
                                        } elseif (strpos($msg, 'foi ') !== false || strpos($msg, 'adormeceu') !== false) {
                                            $cor = '#f59e0b';
                                        }
                                    ?>
                                    <li style="color: <?php echo $cor; ?>;">• <?php echo htmlspecialchars($msg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Comandos / Botões de Ação -->
                    <div class="painel-acoes">

                        <!-- CENÁRIO 1: Vitória na rodada atual -->
                        <?php if ($statusCombate === 'vitoria_rodada'): ?>

                            <div class="banner-fim banner-vitoria">
                                <h3>🎉 Vitória na Batalha <?php echo $batalhaAtual; ?>!</h3>
                                <p style="margin: 0; color: #cbd5e1;">Você derrotou o <b><?php echo htmlspecialchars($nomeIni); ?></b>!</p>
                                <form method="POST" style="width: 100%;">
                                    <input type="hidden" name="acao" value="proxima_batalha">
                                    <button type="submit" class="btn-acao btn-trocar" style="font-size: 1rem;">
                                        Avançar para a Batalha <?php echo $batalhaAtual + 1; ?> de <?php echo $totalBatalhas; ?> →
                                    </button>
                                </form>
                            </div>

                        <!-- CENÁRIO 2: Grande campeão das 9 batalhas -->
                        <?php elseif ($statusCombate === 'campeao'): ?>

                            <div class="banner-fim banner-campeao">
                                <h3>🏆 GRANDE CAMPEÃO!</h3>
                                <p style="margin: 0; color: #cbd5e1;">Parabéns! Você venceu todos os 9 adversários da PokéArena!</p>
                                <form method="POST" style="width: 100%;">
                                    <input type="hidden" name="acao" value="reiniciar">
                                    <button type="submit" class="btn-acao btn-lutar">Jogar Novamente</button>
                                </form>
                            </div>

                        <!-- CENÁRIO 3: Todos os Pokémon desmaiados -->
                        <?php elseif ($statusCombate === 'derrota_sem_pokemon'): ?>

                            <div class="banner-fim banner-derrota">
                                <h3>💀 Toda a sua equipe desmaiou!</h3>
                                <p style="margin: 0; color: #cbd5e1;">Você precisa de poções ou curar seu time na loja:</p>
                                <div style="display: flex; gap: 10px; width: 100%;">
                                    <a href="index.php?rota=loja" class="btn-acao btn-trocar" style="flex:1;">Ir ao PokéMart</a>
                                    <a href="index.php?rota=menu" class="btn-acao btn-voltar" style="flex:1;">Menu Principal</a>
                                </div>
                            </div>

                        <!-- CENÁRIO 4: Em combate ativo -->
                        <?php elseif ($statusCombate === 'em_combate'): ?>

                            <?php if ($hpAtualJog <= 0): ?>

                                <div class="banner-fim banner-derrota" style="padding: 16px;">
                                    <h4 style="margin: 0; color: #f87171;">Seu Pokémon desmaiou!</h4>
                                    <p style="margin: 0; color: #cbd5e1; font-size: 0.88rem;">Escolha outro Pokémon consciente na lista abaixo.</p>
                                </div>

                            <?php elseif ($menuAberto === 'principal'): ?>

                                <!-- Menu Principal: Lutar, Trocar e Fugir -->
                                <div class="acoes-grid">
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="acao" value="abrir_menu">
                                        <input type="hidden" name="menu_alvo" value="lutar">
                                        <button type="submit" class="btn-acao btn-lutar">⚔️ Lutar</button>
                                    </form>

                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="acao" value="abrir_menu">
                                        <input type="hidden" name="menu_alvo" value="trocar">
                                        <button type="submit" class="btn-acao btn-trocar">🔄 Trocar</button>
                                    </form>

                                    <form method="POST" style="grid-column: span 2; margin:0;">
                                        <input type="hidden" name="acao" value="fugir">
                                        <button type="submit" class="btn-acao btn-fugir" onclick="return confirm('Deseja fugir do combate?');">🏃 Fugir</button>
                                    </form>
                                </div>

                            <?php elseif ($menuAberto === 'lutar'): ?>

                                <!-- Submenu Lutar: Escolha de Golpe (Físico, Especial ou Status) -->
                                <div class="painel-lutar-pokeprogue">
                                    <div class="acoes-grid-golpes">
                                        <?php foreach (['fisico', 'especial', 'status'] as $chave): ?>
                                            <?php if (!empty($movimentosJog[$chave]['nome'])): ?>
                                                <?php
                                                    $mov = $movimentosJog[$chave];
                                                    $nomeGolpe = $mov['nome'];
                                                    $tipoGolpe = strtolower($mov['tipo']);
                                                    $catGolpe = strtolower($mov['damage_class']);
                                                    $poderGolpe = (int)$mov['poder'];
                                                    $tipoInimigo = strtolower($pokemonInimigo['tipo']);

                                                    // Indicador visual de eficácia
                                                    if ($catGolpe === 'status') {
                                                        $badgeTexto = 'Efeito';
                                                        $badgeClasse = 'eficacia-status';
                                                        $badgeDesc = 'Movimento de Status';
                                                    } else {
                                                        $mult = Batalha::multiplicador($tipoGolpe, $tipoInimigo);

                                                        if ($mult >= 2.0) {
                                                            $badgeTexto = 'x' . (int)$mult;
                                                            $badgeClasse = 'eficacia-super';
                                                            $badgeDesc = 'Superefetivo (2x)';
                                                        } elseif ($mult == 1.0) {
                                                            $badgeTexto = 'x1';
                                                            $badgeClasse = 'eficacia-neutro';
                                                            $badgeDesc = 'Dano Normal (1x)';
                                                        } elseif ($mult > 0.0) {
                                                            $badgeTexto = 'x0.5';
                                                            $badgeClasse = 'eficacia-pouco';
                                                            $badgeDesc = 'Pouco Efetivo (0.5x)';
                                                        } else {
                                                            $badgeTexto = 'x0';
                                                            $badgeClasse = 'eficacia-imune';
                                                            $badgeDesc = 'Imune (0 dano)';
                                                        }
                                                    }
                                                ?>
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="acao" value="executar_ataque">
                                                    <input type="hidden" name="tipo_ataque" value="<?php echo $chave; ?>">
                                                    <button type="submit" class="btn-golpe-card">
                                                        <div class="golpe-topo">
                                                            <span class="golpe-nome"><?php echo htmlspecialchars($nomeGolpe); ?></span>
                                                            <span class="badge-eficacia <?php echo $badgeClasse; ?>" title="<?php echo htmlspecialchars($badgeDesc); ?>">
                                                                <?php echo htmlspecialchars($badgeTexto); ?>
                                                            </span>
                                                        </div>
                                                        <div class="golpe-rodape">
                                                            <span class="badge-tipo-mini badge-tipo-<?php echo htmlspecialchars($tipoGolpe); ?>">
                                                                <?php echo strtoupper(htmlspecialchars($tipoGolpe)); ?>
                                                            </span>
                                                            <span class="golpe-poder-info">
                                                                <?php if ($catGolpe === 'status'): ?>
                                                                    Efeito
                                                                <?php else: ?>
                                                                    Poder: <b><?php echo $poderGolpe; ?></b>
                                                                <?php endif; ?>
                                                            </span>
                                                        </div>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>

                                        <!-- Botão Voltar -->
                                        <form method="POST" style="margin:0; grid-column: 1 / -1;">
                                            <input type="hidden" name="acao" value="abrir_menu">
                                            <input type="hidden" name="menu_alvo" value="principal">
                                            <button type="submit" class="btn-acao btn-voltar">← Voltar</button>
                                        </form>
                                    </div>
                                </div>

                            <?php elseif ($menuAberto === 'trocar'): ?>

                                <!-- Botão Voltar da tela de troca -->
                                <form method="POST">
                                    <input type="hidden" name="acao" value="abrir_menu">
                                    <input type="hidden" name="menu_alvo" value="principal">
                                    <button type="submit" class="btn-acao btn-voltar">← Voltar ao Combate</button>
                                </form>

                            <?php endif; ?>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

            <!-- Tabela de Troca de Pokémon da Equipe -->
            <?php if ($menuAberto === 'trocar' || $hpAtualJog <= 0): ?>
                <div class="team-swap-panel">
                    <div class="team-swap-title">
                        Equipe Pokémon - Escolha quem entra em campo:
                    </div>
                    <table class="swap-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pokémon</th>
                                <th>Tipo</th>
                                <th>HP</th>
                                <th>Situação</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($meuTime as $idx => $pk): ?>
                                <?php 
                                    $pkHpMax = (int)$pk['hpMaximo'];
                                    $pkHpAtual = (int)$pk['hpAtual'];
                                    $desmaiado = ($pkHpAtual <= 0);
                                    $emCampo = ($idx === $ativoIdx);
                                ?>
                                <tr>
                                    <td><strong><?php echo $idx + 1; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($pk['nome']); ?></strong>
                                        <?php if (!empty($pk['evoluido'])): ?>
                                            <span style="color: #f59e0b; font-size: 0.75rem; font-weight: 800;">✨ [EVO]</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo ucfirst($pk['tipo']); ?></td>
                                    <td><?php echo $pkHpAtual; ?> / <?php echo $pkHpMax; ?></td>
                                    <td>
                                        <!-- Situação base do Pokémon -->
                                        <?php if ($desmaiado): ?>
                                            <span class="badge-status badge-desmaiado">Desmaiado</span>
                                        <?php elseif ($emCampo): ?>
                                            <span class="badge-status badge-ativo">Em Campo</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-pronto">Pronto</span>
                                        <?php endif; ?>

                                        <!-- Badge de condição de status -->
                                        <?php echo renderizarBadgeStatus($pk['statusCondicao'], $mapaBadgesStatus); ?>
                                    </td>
                                    <td>
                                        <?php if ($desmaiado): ?>
                                            <span style="color: #64748b;">Sem HP</span>
                                        <?php elseif ($emCampo): ?>
                                            <em>(Ativo)</em>
                                        <?php else: ?>
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="acao" value="trocar">
                                                <input type="hidden" name="novo_idx" value="<?php echo $idx; ?>">
                                                <button type="submit" class="btn-acao btn-trocar btn-entrar">Entrar em Campo</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Botão discreto para reiniciar a campanha inteira caso queira -->
            <div style="text-align: right; margin-top: 10px;">
                <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="acao" value="reiniciar">
                    <button type="submit" style="background: none; border: none; color: #64748b; text-decoration: underline; cursor: pointer; font-size: 0.82rem;" onclick="return confirm('Deseja reiniciar a campanha de 9 batalhas?');">
                        Reiniciar Campanha
                    </button>
                </form>
            </div>

        </div>
    </div>

</body>
</html>
