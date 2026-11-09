<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Validações de acesso
if (empty($_SESSION['logado'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['meu_time'])) {
    header('Location: index.php?rota=builder');
    exit;
}

if (!class_exists('Conexao')) {
    require_once __DIR__ . '/../../../Autoload.php';
}

require_once __DIR__ . '/../../Services/PokeApiService.php';

// Conexão com o banco de dados
$pdo = Conexao::getConnection();
$usuarioId = (int)($_SESSION['usuario_id'] ?? 1);

// Consulta o saldo atual de dinheiro do usuário no banco
$stmtUser = $pdo->prepare("SELECT nome, dinheiro FROM usuario WHERE id = :id");
$stmtUser->execute([':id' => $usuarioId]);
$dadosUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
$usuarioNome = $dadosUser['nome'] ?? ($_SESSION['usuario'] ?? 'Treinador');
$usuarioDinheiro = (int)($dadosUser['dinheiro'] ?? 1000);

// Sincroniza dados da equipe do jogador e garante que cada um possua seus movimentos anexados
$meuTime = array_values($_SESSION['meu_time']);
foreach ($meuTime as &$pk) {
    if (empty($pk['movimentos'])) {
        $idPk = (int)($pk['id'] ?? $pk['id_pokedex'] ?? 0);
        $nomePk = $pk['nome'] ?? $pk['name'] ?? '';
        $tipoPk = $pk['tipo'] ?? 'normal';
        $pk['movimentos'] = PokeApiService::obterMovimentosPokemon($idPk, $nomePk, $tipoPk);
    }
}
unset($pk);
$_SESSION['meu_time'] = $meuTime;

// 2. Inicialização do combate de 9 batalhas (caso não exista)
if (empty($_SESSION['combate']) || !isset($_SESSION['combate']['batalha_atual'])) {
    $inimigosTime = array_values($_SESSION['inimigos_time'] ?? []);

    if (empty($inimigosTime)) {
        $idsEscolhidos = array_map(fn($p) => (int)($p['id'] ?? 0), $meuTime);
        $idsRestantes = array_values(array_diff(PokeApiService::IDS, $idsEscolhidos));
        $inimigosTime = array_map(function($id) {
            $info = PokeApiService::buscarPorId($id) ?? [];
            return [
                'id'           => $id,
                'nome'         => $info['nome'] ?? "Pokémon #{$id}",
                'tipo'         => $info['tipo'] ?? 'normal',
                'hpMaximo'     => (int)($info['hp'] ?? 45),
                'hpAtual'      => (int)($info['hp'] ?? 45),
                'ataque'       => (int)($info['ataque'] ?? 40),
                'defesa'       => (int)($info['defesa'] ?? 40),
                'velocidade'   => (int)($info['velocidade'] ?? 45),
                'nivel'        => 5,
                'gif'          => $info['gif'] ?? "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/showdown/{$id}.gif",
                'imagem'       => $info['imagemFrente'] ?? ($info['imagem'] ?? "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/{$id}.png"),
                'imagemFrente' => $info['imagemFrente'] ?? ($info['imagem'] ?? "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/{$id}.png"),
                'movimentos'   => PokeApiService::obterMovimentosPokemon($id, $info['nome'] ?? '', $info['tipo'] ?? 'normal')
            ];
        }, $idsRestantes);
        $_SESSION['inimigos_time'] = $inimigosTime;
    }

    // Sorteia 1 Pokémon do seu time para iniciar a primeira batalha
    $indicesVivos = [];
    foreach ($meuTime as $idx => $pk) {
        $hp = (int)($pk['hpAtual'] ?? $pk['hpMaximo'] ?? $pk['hp'] ?? 40);
        if ($hp > 0) {
            $indicesVivos[] = $idx;
        }
    }
    $idxJogadorSorteado = !empty($indicesVivos) ? $indicesVivos[array_rand($indicesVivos)] : 0;

    // Sorteia 1 Pokémon dentro dos 9 restantes para a Batalha 1
    $inimigosPool = $inimigosTime;
    $idxInimigoSorteado = array_rand($inimigosPool);
    $inimigoAtual = $inimigosPool[$idxInimigoSorteado];
    unset($inimigosPool[$idxInimigoSorteado]);

    // Garante que o inimigo sorteado tenha os movimentos próprios anexados
    if (empty($inimigoAtual['movimentos'])) {
        $inimigoAtual['movimentos'] = PokeApiService::obterMovimentosPokemon((int)($inimigoAtual['id'] ?? 0), $inimigoAtual['nome'] ?? $inimigoAtual['name'] ?? '', $inimigoAtual['tipo'] ?? 'normal');
    }

    $_SESSION['combate'] = [
        'batalha_atual'      => 1,
        'total_batalhas'     => 9,
        'vitorias'           => 0,
        'pokemon_ativo_idx'  => $idxJogadorSorteado,
        'inimigo_atual'      => $inimigoAtual,
        'inimigos_restantes' => array_values($inimigosPool),
        'status'             => 'em_combate',
        'menu_aberto'        => 'principal', // 'principal', 'lutar', 'trocar'
        'mods_jogador'       => ['atk_mult' => 1.0, 'def_mult' => 1.0, 'condicao' => 'Normal'],
        'mods_inimigo'       => ['atk_mult' => 1.0, 'def_mult' => 1.0, 'condicao' => 'Normal'],
        'mensagens'          => [
            "Início do combate! Batalha 1 de 9.",
            "O seu Pokémon sorteado foi: " . ($meuTime[$idxJogadorSorteado]['nome'] ?? $meuTime[$idxJogadorSorteado]['name']) . "!",
            "O adversário sorteado entre os 9 restantes foi: " . ($inimigoAtual['nome'] ?? $inimigoAtual['name']) . "!"
        ]
    ];
}

// 3. Processamento das Ações em PHP Puro (POST)
$avisoErro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $combate = &$_SESSION['combate'];
    $ativoIdx = (int)$combate['pokemon_ativo_idx'];
    $pokemonJogador = &$meuTime[$ativoIdx];
    $pokemonInimigo = &$combate['inimigo_atual'];

    // 1. Vantagens elementais simples (Fogo > Planta > Agua > Fogo, etc.)
    $obterVantagemTipo = function($tipoAtaque, $tipoDefesa) {
        $atk = strtolower(trim($tipoAtaque));
        $def = strtolower(trim($tipoDefesa));

        // Fogo ganha de Planta e perde para Agua
        if (in_array($atk, ['fogo', 'fire'])) {
            if (in_array($def, ['planta', 'grass'])) return 2.0;
            if (in_array($def, ['agua', 'water', 'fogo', 'fire'])) return 0.5;
        }

        // Agua ganha de Fogo e perde para Planta
        if (in_array($atk, ['agua', 'water'])) {
            if (in_array($def, ['fogo', 'fire', 'terra', 'ground'])) return 2.0;
            if (in_array($def, ['planta', 'grass', 'agua', 'water'])) return 0.5;
        }

        // Planta ganha de Agua e perde para Fogo
        if (in_array($atk, ['planta', 'grass'])) {
            if (in_array($def, ['agua', 'water', 'terra', 'ground'])) return 2.0;
            if (in_array($def, ['fogo', 'fire', 'planta', 'grass'])) return 0.5;
        }

        // Eletrico ganha de Agua e nao afeta Terra
        if (in_array($atk, ['eletrico', 'electric'])) {
            if (in_array($def, ['agua', 'water'])) return 2.0;
            if (in_array($def, ['terra', 'ground'])) return 0.0; // Imunidade
            if (in_array($def, ['eletrico', 'electric', 'planta', 'grass'])) return 0.5;
        }

        // Fantasma e Psiquico
        if (in_array($atk, ['fantasma', 'ghost']) && in_array($def, ['psiquico', 'psychic'])) return 2.0;
        if (in_array($atk, ['normal']) && in_array($def, ['fantasma', 'ghost'])) return 0.0;

        return 1.0; // Dano neutro
    };

    // 2. Calculo de dano simples, didatico e equilibrado
    $calcularDanoCategoria = function($atacante, $defensor, $movimento, $modsAtacante, $modsDefensor) use ($obterVantagemTipo) {
        $poder   = max(10, (int)($movimento['poder'] ?? 40));
        $ataque  = (int)($atacante['ataque'] ?? 40) * (float)($modsAtacante['atk_mult'] ?? 1.0);
        $defesa  = (int)($defensor['defesa'] ?? 40) * (float)($modsDefensor['def_mult'] ?? 1.0);
        $defesa  = max(1, $defesa); // Evita divisao por zero

        // Dano base simples: (Poder * Ataque) / (Defesa * 2.5)
        $danoBase = ($poder * $ataque) / ($defesa * 2.5);

        // Vantagem de tipo
        $tipoGolpe = strtolower(trim($movimento['tipo'] ?? 'normal'));
        $tipoAlvo  = strtolower(trim($defensor['tipo'] ?? 'normal'));
        $multTipo  = $obterVantagemTipo($tipoGolpe, $tipoAlvo);

        if ($multTipo == 0.0) {
            return ['dano' => 0, 'multTipo' => 0.0, 'isCritico' => false];
        }

        // Bonus se o golpe for do mesmo tipo do atacante (STAB = 1.5x)
        $tipoAtacante = strtolower(trim($atacante['tipo'] ?? 'normal'));
        $multStab = ($tipoGolpe !== 'normal' && $tipoGolpe === $tipoAtacante) ? 1.5 : 1.0;

        // Chance simples de acerto critico (1 chance em 10 = 10%)
        $isCritico = (rand(1, 10) === 1);
        $multCritico = $isCritico ? 1.5 : 1.0;

        // Pequena variacao aleatoria (90% a 100%)
        $variacao = rand(90, 100) / 100.0;

        // Dano final simples arredondado
        $danoFinal = (int)round($danoBase * $multTipo * $multStab * $multCritico * $variacao);

        return [
            'dano'      => max(1, $danoFinal),
            'multTipo'  => $multTipo,
            'isCritico' => $isCritico
        ];
    };

    // Helper: salvar HP no banco
    $salvarHpBanco = function($pk) use ($pdo, $usuarioId) {
        $nomePk = $pk['nome'] ?? $pk['name'] ?? '';
        $hpAtual = (int)($pk['hpAtual'] ?? 0);
        if ($nomePk !== '') {
            $stmt = $pdo->prepare("UPDATE pokemon SET hp_atual = :hp WHERE usuario_id = :usuario_id AND LOWER(nome) = LOWER(:nome)");
            $stmt->execute([
                ':hp'         => $hpAtual,
                ':usuario_id' => $usuarioId,
                ':nome'       => $nomePk
            ]);
        }
    };

    // Helper: verificar condição de Game Over (sem Pokémon e sem dinheiro para curar)
    $verificarGameOver = function() use (&$meuTime, $usuarioDinheiro) {
        $todosDesmaiados = true;
        foreach ($meuTime as $pk) {
            $hp = (int)($pk['hpAtual'] ?? $pk['hpMaximo'] ?? $pk['hp'] ?? 40);
            if ($hp > 0) {
                $todosDesmaiados = false;
                break;
            }
        }

        if ($todosDesmaiados) {
            $custoMinimoCura = 200;
            if ($usuarioDinheiro < $custoMinimoCura) {
                unset($_SESSION['combate']);
                $_SESSION['aviso_derrota'] = "FIM DE JOGO: Todos os seus Pokémon desmaiaram e você não possui dinheiro suficiente para comprar HP ou curá-los! Você foi retirado da batalha.";
                header("Location: index.php?rota=menu");
                exit;
            }
        }
        return $todosDesmaiados;
    };

    // -------------------------------------------------------------
    // CONTROLE DE NAVEGAÇÃO DO MINI MENU ("O que o pokemon deve fazer?")
    // -------------------------------------------------------------
    if ($acao === 'abrir_menu') {
        $menuAlvo = $_POST['menu_alvo'] ?? 'principal';
        $combate['menu_aberto'] = $menuAlvo;
    }

    // -------------------------------------------------------------
    // AÇÃO DE LUTAR: EXECUTA O ATAQUE ARMAZENADO NO PRÓPRIO POKÉMON
    // -------------------------------------------------------------
    elseif ($acao === 'executar_ataque' && $combate['status'] === 'em_combate') {
        $tipoAtaqueEscolhido = $_POST['tipo_ataque'] ?? 'fisico';
        $combate['mensagens'] = [];

        $nomeJogador = $pokemonJogador['nome'] ?? $pokemonJogador['name'];
        $nomeInimigo = $pokemonInimigo['nome'] ?? $pokemonInimigo['name'];

        // Le os movimentos armazenados no proprio Pokemon
        $golpesJogador = $pokemonJogador['movimentos'] ?? PokeApiService::obterMovimentosPokemon((int)($pokemonJogador['id'] ?? 0));
        $golpeUsado = $golpesJogador[$tipoAtaqueEscolhido] ?? $golpesJogador['fisico'] ?? reset($golpesJogador);

        $podeAtacar = true;
        if (($combate['mods_jogador']['condicao'] ?? 'Normal') === 'Paralisado') {
            if (rand(1, 100) <= 25) {
                $podeAtacar = false;
                $combate['mensagens'][] = "⚡ {$nomeJogador} está paralisado! Ele não conseguiu se mover!";
            }
        }

        if ($podeAtacar) {
            $combate['mensagens'][] = "{$nomeJogador} usou {$golpeUsado['nome']}!";
            $cat = strtolower($golpeUsado['damage_class'] ?? $golpeUsado['categoria'] ?? $tipoAtaqueEscolhido);

            if ($cat === 'status') {
                $efeito = $golpeUsado['efeito'] ?? 'reduzir_atk';

                if ($efeito === 'reduzir_atk') {
                    $combate['mods_inimigo']['atk_mult'] = max(0.4, ($combate['mods_inimigo']['atk_mult'] ?? 1.0) * 0.8);
                    $combate['mensagens'][] = "O Ataque de {$nomeInimigo} caiu!";
                } elseif ($efeito === 'reduzir_def') {
                    $combate['mods_inimigo']['def_mult'] = max(0.4, ($combate['mods_inimigo']['def_mult'] ?? 1.0) * 0.8);
                    $combate['mensagens'][] = "A Defesa de {$nomeInimigo} caiu!";
                } elseif ($efeito === 'paralisar') {
                    $combate['mods_inimigo']['condicao'] = 'Paralisado';
                    $combate['mensagens'][] = "{$nomeInimigo} foi paralisado! Talvez não consiga atacar!";
                } elseif ($efeito === 'curar_proprio') {
                    $hpMax = (int)($pokemonJogador['hpMaximo'] ?? $pokemonJogador['hp'] ?? 40);
                    $hpAtual = (int)($pokemonJogador['hpAtual'] ?? $hpMax);
                    $cura = max(10, (int)round($hpMax * 0.35));
                    $novoHp = min($hpMax, $hpAtual + $cura);
                    $pokemonJogador['hpAtual'] = $novoHp;
                    $salvarHpBanco($pokemonJogador);
                    $combate['mensagens'][] = "Vida recuperada: +{$cura} HP";
                    $combate['mensagens'][] = "{$nomeJogador} recuperou vida!";
                } else {
                    $combate['mensagens'][] = "O movimento teve efeito!";
                }
            } else {
                // Golpe com dano direto (Fisico ou Especial)
                $res = $calcularDanoCategoria($pokemonJogador, $pokemonInimigo, $golpeUsado, $combate['mods_jogador'], $combate['mods_inimigo']);
                $dano = $res['dano'];

                if ($res['multTipo'] == 0.0) {
                    $combate['mensagens'][] = "Não afeta {$nomeInimigo}...";
                } else {
                    $hpInimigo = max(0, (int)($pokemonInimigo['hpAtual'] ?? $pokemonInimigo['hpMaximo'] ?? 45) - $dano);
                    $pokemonInimigo['hpAtual'] = $hpInimigo;

                    $combate['mensagens'][] = "Dano causado: -{$dano} HP";

                    if ($res['isCritico']) {
                        $combate['mensagens'][] = "Um acerto crítico!";
                    }

                    if ($res['multTipo'] > 1.0) {
                        $combate['mensagens'][] = "É super eficaz!";
                    } elseif ($res['multTipo'] < 1.0) {
                        $combate['mensagens'][] = "Não é muito eficaz...";
                    }
                }
            }
        }

        // Verifica se o adversario desmaiou
        $hpInimigoRestante = (int)($pokemonInimigo['hpAtual'] ?? 0);
        if ($hpInimigoRestante <= 0) {
            $combate['mensagens'][] = "💥 O {$nomeInimigo} adversário desmaiou!";
            $combate['vitorias']++;

            $recompensa = 300;
            $stmtMoney = $pdo->prepare("UPDATE usuario SET dinheiro = dinheiro + :recompensa WHERE id = :id");
            $stmtMoney->execute([':recompensa' => $recompensa, ':id' => $usuarioId]);
            $usuarioDinheiro += $recompensa;
            $combate['mensagens'][] = "💰 Recompensa: +{$recompensa} Pokédollars adicionados ao seu saldo!";

            try {
                $stmtLog = $pdo->prepare("INSERT INTO log_batalha (usuario_id, pokemon_id, adversario, resultado, dinheiro_ganho) VALUES (:usuario_id, :pokemon_id, :adversario, 'vitoria', :dinheiro)");
                $stmtLog->execute([
                    ':usuario_id' => $usuarioId,
                    ':pokemon_id' => $pokemonJogador['pokemon_db_id'] ?? null,
                    ':adversario' => $nomeInimigo,
                    ':dinheiro'   => $recompensa
                ]);
            } catch (Exception $e) {}

            if ($combate['batalha_atual'] >= $combate['total_batalhas']) {
                $combate['status'] = 'campeao';
                $combate['mensagens'][] = "🏆 PARABÉNS! Você derrotou todos os 9 adversários da PokéArena e se tornou o GRANDE CAMPEÃO!";
            } else {
                $combate['status'] = 'vitoria_rodada';
            }
        } else {
            // CONTRA-ATAQUE DO ADVERSARIO
            $golpesInimigo = $pokemonInimigo['movimentos'] ?? PokeApiService::obterMovimentosPokemon((int)($pokemonInimigo['id'] ?? 0));

            $inimigoPodeAtacar = true;
            if (($combate['mods_inimigo']['condicao'] ?? 'Normal') === 'Paralisado') {
                if (rand(1, 100) <= 25) {
                    $inimigoPodeAtacar = false;
                    $combate['mensagens'][] = "⚡ {$nomeInimigo} está paralisado! Ele não conseguiu se mover!";
                }
            }

            if ($inimigoPodeAtacar) {
                // IA do Inimigo: se tiver golpe super eficaz contra o jogador, prioriza usar
                $melhorGolpe = 'fisico';
                $tipoJog = $pokemonJogador['tipo'] ?? 'normal';
                foreach (['fisico', 'especial'] as $t) {
                    if (!empty($golpesInimigo[$t])) {
                        $m = $golpesInimigo[$t];
                        $mult = $obterVantagemTipo($m['tipo'] ?? 'normal', $tipoJog);
                        if ($mult > 1.0) {
                            $melhorGolpe = $t;
                            break;
                        }
                    }
                }
                // 65% de chance de usar o golpe com vantagem, senao escolhe entre os 3
                if (rand(1, 100) <= 65) {
                    $escolhaIa = $melhorGolpe;
                } else {
                    $opcoesIa = ['fisico', 'especial', 'status'];
                    $escolhaIa = $opcoesIa[array_rand($opcoesIa)];
                }
                $golpeIni = $golpesInimigo[$escolhaIa] ?? $golpesInimigo['fisico'] ?? reset($golpesInimigo);

                $combate['mensagens'][] = "{$nomeInimigo} adversário usou {$golpeIni['nome']}!";
                $catIni = strtolower($golpeIni['damage_class'] ?? $golpeIni['categoria'] ?? $escolhaIa);

                if ($catIni === 'status') {
                    $ef = $golpeIni['efeito'] ?? 'reduzir_atk';
                    if ($ef === 'reduzir_atk') {
                        $combate['mods_jogador']['atk_mult'] = max(0.4, ($combate['mods_jogador']['atk_mult'] ?? 1.0) * 0.8);
                        $combate['mensagens'][] = "O Ataque de {$nomeJogador} caiu!";
                    } elseif ($ef === 'reduzir_def') {
                        $combate['mods_jogador']['def_mult'] = max(0.4, ($combate['mods_jogador']['def_mult'] ?? 1.0) * 0.8);
                        $combate['mensagens'][] = "A Defesa de {$nomeJogador} caiu!";
                    } elseif ($ef === 'paralisar') {
                        $combate['mods_jogador']['condicao'] = 'Paralisado';
                        $combate['mensagens'][] = "{$nomeJogador} foi paralisado! Talvez não consiga atacar!";
                    } else {
                        $combate['mensagens'][] = "O movimento teve efeito!";
                    }
                } else {
                    $resIni = $calcularDanoCategoria($pokemonInimigo, $pokemonJogador, $golpeIni, $combate['mods_inimigo'], $combate['mods_jogador']);
                    $dIni = $resIni['dano'];

                    if ($resIni['multTipo'] == 0.0) {
                        $combate['mensagens'][] = "Não afeta {$nomeJogador}...";
                    } else {
                        $hpJog = max(0, (int)($pokemonJogador['hpAtual'] ?? $pokemonJogador['hpMaximo'] ?? 40) - $dIni);
                        $pokemonJogador['hpAtual'] = $hpJog;
                        $salvarHpBanco($pokemonJogador);

                        $combate['mensagens'][] = "Dano sofrido: -{$dIni} HP";

                        if ($resIni['isCritico']) {
                            $combate['mensagens'][] = "Um acerto crítico!";
                        }

                        if ($resIni['multTipo'] > 1.0) {
                            $combate['mensagens'][] = "É super eficaz!";
                        } elseif ($resIni['multTipo'] < 1.0) {
                            $combate['mensagens'][] = "Não é muito eficaz...";
                        }
                    }
                }

                if ((int)($pokemonJogador['hpAtual'] ?? 0) <= 0) {
                    $combate['mensagens'][] = "💀 Seu Pokémon {$nomeJogador} desmaiou!";
                    $todosDesmaiados = $verificarGameOver();
                    if (!$todosDesmaiados) {
                        $combate['mensagens'][] = "Seu Pokémon desmaiou! Vá até 'Pokémon (Trocar)' para escolher outro Pokémon consciente.";
                    } else {
                        $combate['status'] = 'derrota_sem_pokemon';
                    }
                }
            }
        }

        $combate['menu_aberto'] = 'principal';
        $_SESSION['meu_time'] = $meuTime;
    }

    elseif ($acao === 'trocar' && ($combate['status'] === 'em_combate' || $combate['status'] === 'derrota_sem_pokemon')) {
        $novoIdx = isset($_POST['novo_idx']) ? (int)$_POST['novo_idx'] : -1;

        if (!isset($meuTime[$novoIdx])) {
            $avisoErro = "Pokémon inválido selecionado para troca!";
        } else {
            $candidato = $meuTime[$novoIdx];
            $hpCandidato = (int)($candidato['hpAtual'] ?? $candidato['hpMaximo'] ?? $candidato['hp'] ?? 40);

            if ($hpCandidato <= 0) {
                $avisoErro = "O Pokémon " . ($candidato['nome'] ?? $candidato['name']) . " está desmaiado (HP = 0) e não pode entrar em combate!";
            } elseif ($novoIdx === $combate['pokemon_ativo_idx']) {
                $avisoErro = "Este Pokémon já está na batalha!";
            } else {
                $combate['pokemon_ativo_idx'] = $novoIdx;
                $combate['status'] = 'em_combate';
                $combate['menu_aberto'] = 'principal';
                
                $combate['mods_jogador'] = ['atk_mult' => 1.0, 'def_mult' => 1.0, 'condicao' => 'Normal'];

                $nomeNovo = $candidato['nome'] ?? $candidato['name'];
                $nomeInimigo = $pokemonInimigo['nome'] ?? $pokemonInimigo['name'];
                $combate['mensagens'] = ["🔄 Você colocou {$nomeNovo} em campo!"];

                $golpesInimigo = $pokemonInimigo['movimentos'] ?? PokeApiService::obterMovimentosPokemon((int)($pokemonInimigo['id'] ?? 0), $nomeInimigo, $pokemonInimigo['tipo'] ?? 'normal');
                $resIni = $calcularDanoCategoria($pokemonInimigo, $candidato, $golpesInimigo['fisico'], $combate['mods_inimigo'], $combate['mods_jogador']);
                $danoInimigo = $resIni['dano'];
                $hpRestante = max(0, $hpCandidato - $danoInimigo);
                $meuTime[$novoIdx]['hpAtual'] = $hpRestante;
                $salvarHpBanco($meuTime[$novoIdx]);

                $combate['mensagens'][] = "⚔️ {$nomeInimigo} atacou {$nomeNovo} na entrada com {$golpesInimigo['fisico']['nome']} causando {$danoInimigo} de dano! (HP restante: {$hpRestante})";

                if ($hpRestante <= 0) {
                    $combate['mensagens'][] = "💀 {$nomeNovo} desmaiou logo na entrada!";
                    $verificarGameOver();
                }

                $_SESSION['meu_time'] = $meuTime;
            }
        }
    }

    // -------------------------------------------------------------
    // AÇÃO: AVANÇAR PARA A PRÓXIMA BATALHA
    // -------------------------------------------------------------
    elseif ($acao === 'proxima_batalha' && $combate['status'] === 'vitoria_rodada') {
        if (!empty($combate['inimigos_restantes'])) {
            $combate['batalha_atual']++;
            $idxSorteado = array_rand($combate['inimigos_restantes']);
            $novoInimigo = $combate['inimigos_restantes'][$idxSorteado];
            unset($combate['inimigos_restantes'][$idxSorteado]);
            $combate['inimigos_restantes'] = array_values($combate['inimigos_restantes']);

            // Escalonamento simples de nivel (Batalha 1 = Nv. 5 ate Batalha 9 = Nv. 13)
            $nivelInimigo = 4 + $combate['batalha_atual'];
            $fator = $nivelInimigo / 5.0; // Multiplicador proporcional simples

            $novoInimigo['nivel'] = $nivelInimigo;
            $novoInimigo['hpMaximo'] = (int)round(($novoInimigo['hpMaximo'] ?? 45) * $fator);
            $novoInimigo['hpAtual'] = $novoInimigo['hpMaximo'];
            $novoInimigo['ataque'] = (int)round(($novoInimigo['ataque'] ?? 40) * $fator);
            $novoInimigo['defesa'] = (int)round(($novoInimigo['defesa'] ?? 40) * $fator);
            $novoInimigo['velocidade'] = (int)round(($novoInimigo['velocidade'] ?? 45) * $fator);

            if (empty($novoInimigo['movimentos'])) {
                $novoInimigo['movimentos'] = PokeApiService::obterMovimentosPokemon((int)($novoInimigo['id'] ?? 0));
            }

            $combate['inimigo_atual'] = $novoInimigo;
            $combate['status'] = 'em_combate';
            $combate['menu_aberto'] = 'principal';

            $combate['mods_jogador'] = ['atk_mult' => 1.0, 'def_mult' => 1.0, 'condicao' => 'Normal'];
            $combate['mods_inimigo'] = ['atk_mult' => 1.0, 'def_mult' => 1.0, 'condicao' => 'Normal'];

            $combate['mensagens'] = [
                "Batalha {$combate['batalha_atual']} de {$combate['total_batalhas']} iniciada!",
                "Um novo adversário surgiu: {$novoInimigo['nome']} (Nível {$nivelInimigo})!"
            ];
        }
    }

    elseif ($acao === 'fugir') {
        header("Location: index.php?rota=menu");
        exit;
    }

    // -------------------------------------------------------------
    // AÇÃO: REINICIAR COMBATE
    // -------------------------------------------------------------
    elseif ($acao === 'reiniciar') {
        unset($_SESSION['combate']);
        header("Location: index.php?rota=batalha");
        exit;
    }
}

// 4. Preparação das Variáveis de Exibição
$combate = $_SESSION['combate'];
$batalhaAtual = (int)$combate['batalha_atual'];
$totalBatalhas = (int)$combate['total_batalhas'];
$ativoIdx = (int)$combate['pokemon_ativo_idx'];
$pokemonJogador = $meuTime[$ativoIdx] ?? $meuTime[0];
$pokemonInimigo = $combate['inimigo_atual'];

$hpMaxJog = (int)($pokemonJogador['hpMaximo'] ?? $pokemonJogador['hp'] ?? 40);
$hpAtualJog = (int)($pokemonJogador['hpAtual'] ?? $hpMaxJog);

$hpMaxIni = (int)($pokemonInimigo['hpMaximo'] ?? $pokemonInimigo['hp'] ?? 45);
$hpAtualIni = (int)($pokemonInimigo['hpAtual'] ?? $hpMaxIni);

$statusCombate = $combate['status'] ?? 'em_combate';
$menuAberto = $combate['menu_aberto'] ?? 'principal';
$mensagensLog = $combate['mensagens'] ?? [];

$nomeJog = $pokemonJogador['nome'] ?? $pokemonJogador['name'];
$nomeIni = $pokemonInimigo['nome'] ?? $pokemonInimigo['name'];

// Lê os movimentos armazenados no próprio Pokémon
$movimentosJog = $pokemonJogador['movimentos'] ?? PokeApiService::obterMovimentosPokemon((int)($pokemonJogador['id'] ?? 0), $nomeJog, $pokemonJogador['tipo'] ?? 'normal');

$porcHpJog = max(0, min(100, (int)round(($hpAtualJog / max(1, $hpMaxJog)) * 100)));
$porcHpIni = max(0, min(100, (int)round(($hpAtualIni / max(1, $hpMaxIni)) * 100)));

$corHpJog = ($porcHpJog > 50) ? '#10b981' : (($porcHpJog > 20) ? '#f59e0b' : '#ef4444');
$corHpIni = ($porcHpIni > 50) ? '#10b981' : (($porcHpIni > 20) ? '#f59e0b' : '#ef4444');

// IDs para fallbacks de imagem
$idJog = (int)($pokemonJogador['id'] ?? $pokemonJogador['id_pokedex'] ?? 0);
$idIni = (int)($pokemonInimigo['id'] ?? $pokemonInimigo['id_pokedex'] ?? 0);

// GIFs dos personagens (PokeAPI Showdown animado e fallbacks seguros)
$imgJog = !empty($pokemonJogador['gif']) ? $pokemonJogador['gif'] : 
          (!empty($pokemonJogador['imagemAnimada']) ? $pokemonJogador['imagemAnimada'] : 
          (!empty($idJog) ? "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/showdown/{$idJog}.gif" : 
          ($pokemonJogador['imagemCostas'] ?? ($pokemonJogador['imagem'] ?? ($pokemonJogador['img'] ?? '')))));

$imgIni = !empty($pokemonInimigo['gif']) ? $pokemonInimigo['gif'] : 
          (!empty($pokemonInimigo['imagemAnimada']) ? $pokemonInimigo['imagemAnimada'] : 
          (!empty($idIni) ? "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/showdown/{$idIni}.gif" : 
          ($pokemonInimigo['imagemFrente'] ?? ($pokemonInimigo['imagem'] ?? ($pokemonInimigo['img'] ?? '')))));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokéArena - Batalha <?= $batalhaAtual ?> de <?= $totalBatalhas ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/arena.css?v=<?= time() ?>">
</head>
<body>

    <div class="arena-viewport">
        <div class="arena-container">

            <!-- Cabeçalho (Mesmo padrão visual do Builder e Menu) -->
            <header class="arena-header-card">
                <div class="arena-brand">
                    <img src="assets/img/pokearena_title.png" alt="PokéArena" class="arena-logo">
                    <div class="arena-title-text">
                        <span class="arena-badge-title">BATALHA <?= $batalhaAtual ?> DE <?= $totalBatalhas ?></span>
                        <span class="arena-subtitle">Vitórias na Campanha: <b><?= $combate['vitorias'] ?></b></span>
                    </div>
                </div>

                <div class="arena-header-acoes">
                    <div class="treinador-badge">
                        Treinador: <b><?= htmlspecialchars($usuarioNome) ?></b>
                    </div>
                    <div class="saldo-badge">
                        Saldo: <b>P$ <?= number_format($usuarioDinheiro, 0, ',', '.') ?></b>
                    </div>
                    <a href="index.php?rota=menu" class="btn-header-nav btn-nav-blue">Menu</a>
                    <a href="index.php?rota=loja" class="btn-header-nav btn-nav-green">PokéMart</a>
                </div>
            </header>

            <?php if ($avisoErro): ?>
                <div style="background: rgba(239, 68, 68, 0.2); border: 1.5px solid #ef4444; color: #fca5a5; padding: 14px 20px; border-radius: 12px; font-weight: 700; text-align: center;">
                    <?= htmlspecialchars($avisoErro) ?>
                </div>
            <?php endif; ?>

            <!-- Card Principal da Arena (Mesmo padrão dark glass) -->
            <div class="arena-card">

                <!-- Campo de Batalha (Cenário Central) -->
                <div class="campo-batalha">
                    
                    <!-- LADO OPONENTE -->
                    <div class="lado lado-oponente">
                        <div class="caixa-status">
                            <div class="status-topo">
                                <span class="status-nome"><?= htmlspecialchars($nomeIni) ?></span>
                                <span class="status-nivel">Nv. <?= (int)($pokemonInimigo['nivel'] ?? 5) ?></span>
                            </div>
                            <div class="hp-linha">
                                <span class="hp-rotulo">HP</span>
                                <div class="hp-barra-bg">
                                    <div class="hp-barra" style="width: <?= $porcHpIni ?>%; background: <?= $corHpIni ?>;"></div>
                                </div>
                            </div>
                            <div class="hp-numeros">
                                <?= $hpAtualIni ?> / <?= $hpMaxIni ?> HP (<?= ucfirst($pokemonInimigo['tipo'] ?? 'normal') ?>)
                                <?php if (($combate['mods_inimigo']['condicao'] ?? 'Normal') === 'Paralisado'): ?>
                                    <span style="color: #f59e0b; font-weight: 800;">[PARALISADO]</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="sprite-box">
                            <?php if (!empty($imgIni)): ?>
                                <img src="<?= htmlspecialchars($imgIni) ?>" alt="<?= htmlspecialchars($nomeIni) ?>" onerror="this.onerror=null; this.src='https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/<?= $idIni ?>.png';">
                            <?php endif; ?>
                            <div class="sombra"></div>
                        </div>
                    </div>

                    <!-- LADO JOGADOR -->
                    <div class="lado lado-jogador">
                        <div class="sprite-box">
                            <?php if (!empty($imgJog)): ?>
                                <img src="<?= htmlspecialchars($imgJog) ?>" alt="<?= htmlspecialchars($nomeJog) ?>" onerror="this.onerror=null; this.src='https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/<?= $idJog ?>.png';">
                            <?php endif; ?>
                            <div class="sombra"></div>
                        </div>

                        <div class="caixa-status">
                            <div class="status-topo">
                                <span class="status-nome"><?= htmlspecialchars($nomeJog) ?></span>
                                <span class="status-nivel">Nv. 5</span>
                            </div>
                            <div class="hp-linha">
                                <span class="hp-rotulo">HP</span>
                                <div class="hp-barra-bg">
                                    <div class="hp-barra" style="width: <?= $porcHpJog ?>%; background: <?= $corHpJog ?>;"></div>
                                </div>
                            </div>
                            <div class="hp-numeros">
                                <?= $hpAtualJog ?> / <?= $hpMaxJog ?> HP (<?= ucfirst($pokemonJogador['tipo'] ?? 'normal') ?>)
                                <?php if (($combate['mods_jogador']['condicao'] ?? 'Normal') === 'Paralisado'): ?>
                                    <span style="color: #f59e0b; font-weight: 800;">[PARALISADO]</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Painel Inferior: Log de Combate + Ações -->
                <div class="painel-combate">

                    <!-- Log da Batalha (Estilo Terminal RPG) -->
                    <div class="battle-log">
                        <div class="battle-log-header">Histórico da Batalha</div>
                        <?php if (empty($mensagensLog)): ?>
                            <p style="margin: 0; color: #94a3b8; font-size: 0.88rem;"><em>A batalha começou! Escolha uma ação abaixo.</em></p>
                        <?php else: ?>
                            <ul class="battle-log-list">
                                <?php foreach ($mensagensLog as $msg): ?>
                                    <?php 
                                        $cor = '#e2e8f0';
                                        if (str_contains($msg, 'Dano causado:')) $cor = '#34d399';
                                        elseif (str_contains($msg, 'Dano sofrido:')) $cor = '#f87171';
                                        elseif (str_contains($msg, 'Vida recuperada:')) $cor = '#38bdf8';
                                        elseif ($msg === 'É super eficaz!') $cor = '#fbbf24';
                                        elseif ($msg === 'Um acerto crítico!') $cor = '#60a5fa';
                                        elseif (str_contains($msg, 'desmaiou')) $cor = '#f43f5e';
                                    ?>
                                    <li style="color: <?= $cor ?>;">• <?= htmlspecialchars($msg) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Painel de Ações / Comandos -->
                    <div class="painel-acoes">

                        <?php if ($statusCombate === 'vitoria_rodada'): ?>

                            <div class="banner-fim banner-vitoria">
                                <h3>🎉 Vitória na Batalha <?= $batalhaAtual ?>!</h3>
                                <p style="margin: 0; color: #cbd5e1;">Você derrotou o <b><?= htmlspecialchars($nomeIni) ?></b>!</p>
                                <form method="POST" style="width: 100%;">
                                    <input type="hidden" name="acao" value="proxima_batalha">
                                    <button type="submit" class="btn-acao btn-trocar" style="font-size: 1rem;">
                                        Avançar para a Batalha <?= $batalhaAtual + 1 ?> de <?= $totalBatalhas ?> →
                                    </button>
                                </form>
                            </div>

                        <?php elseif ($statusCombate === 'campeao'): ?>

                            <div class="banner-fim banner-campeao">
                                <h3>🏆 GRANDE CAMPEÃO!</h3>
                                <p style="margin: 0; color: #cbd5e1;">Parabéns! Você venceu todos os 9 adversários da PokéArena!</p>
                                <form method="POST" style="width: 100%;">
                                    <input type="hidden" name="acao" value="reiniciar">
                                    <button type="submit" class="btn-acao btn-lutar">Jogar Novamente</button>
                                </form>
                            </div>

                        <?php elseif ($statusCombate === 'derrota_sem_pokemon'): ?>

                            <div class="banner-fim banner-derrota">
                                <h3>💀 Toda a sua equipe desmaiou!</h3>
                                <p style="margin: 0; color: #cbd5e1;">Você precisa de poções ou curar seu time:</p>
                                <div style="display: flex; gap: 10px; width: 100%;">
                                    <a href="index.php?rota=loja" class="btn-acao btn-trocar" style="flex:1;">PokéMart</a>
                                    <a href="index.php?rota=centro" class="btn-acao btn-voltar" style="flex:1;">Centro</a>
                                </div>
                            </div>

                        <?php elseif ($statusCombate === 'em_combate'): ?>

                            <?php if ($hpAtualJog <= 0): ?>

                                <div class="banner-fim banner-derrota" style="padding: 16px;">
                                    <h4 style="margin: 0; color: #f87171;">Seu Pokémon desmaiou!</h4>
                                    <p style="margin: 0; color: #cbd5e1; font-size: 0.88rem;">Escolha outro Pokémon consciente na lista abaixo.</p>
                                </div>

                            <?php elseif ($menuAberto === 'principal'): ?>

                                <!-- Mini Menu de Ações (Estilo Oficial dos Botões do Menu) -->
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

                                <!-- Submenu Lutar: Botões com Nome Específico do Golpe -->
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div class="acoes-grid">
                                        <?php foreach (['fisico', 'especial', 'status'] as $chave): ?>
                                            <?php if (!empty($movimentosJog[$chave]['nome'])): ?>
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="acao" value="executar_ataque">
                                                    <input type="hidden" name="tipo_ataque" value="<?= $chave ?>">
                                                    <button type="submit" class="btn-acao btn-golpe">
                                                        <?= htmlspecialchars($movimentosJog[$chave]['nome']) ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>

                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="acao" value="abrir_menu">
                                            <input type="hidden" name="menu_alvo" value="principal">
                                            <button type="submit" class="btn-acao btn-voltar">← Voltar</button>
                                        </form>
                                    </div>
                                </div>

                            <?php elseif ($menuAberto === 'trocar'): ?>

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

            <!-- TABELA DE TROCA DE EQUIPE (ESTILO DARK GLASS DO BUILDER) -->
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
                                    $pkHpMax = (int)($pk['hpMaximo'] ?? $pk['hp'] ?? 40);
                                    $pkHpAtual = (int)($pk['hpAtual'] ?? $pkHpMax);
                                    $desmaiado = ($pkHpAtual <= 0);
                                    $emCampo = ($idx === $ativoIdx);
                                ?>
                                <tr>
                                    <td><strong><?= $idx + 1 ?></strong></td>
                                    <td><strong><?= htmlspecialchars($pk['nome'] ?? $pk['name']) ?></strong></td>
                                    <td><?= ucfirst($pk['tipo'] ?? 'normal') ?></td>
                                    <td><?= $pkHpAtual ?> / <?= $pkHpMax ?></td>
                                    <td>
                                        <?php if ($emCampo): ?>
                                            <span class="badge-status badge-ativo">Em Campo</span>
                                        <?php elseif ($desmaiado): ?>
                                            <span class="badge-status badge-desmaiado">Desmaiado</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-pronto">Pronto</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($emCampo): ?>
                                            <em>(Ativo)</em>
                                        <?php elseif ($desmaiado): ?>
                                            <span style="color: #64748b;">Sem HP</span>
                                        <?php else: ?>
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="acao" value="trocar">
                                                <input type="hidden" name="novo_idx" value="<?= $idx ?>">
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
