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
                'id'         => $id,
                'nome'       => $info['nome'] ?? "Pokémon #{$id}",
                'tipo'       => $info['tipo'] ?? 'normal',
                'hpMaximo'   => (int)($info['hp'] ?? 45),
                'hpAtual'    => (int)($info['hp'] ?? 45),
                'ataque'     => (int)($info['ataque'] ?? 40),
                'defesa'     => (int)($info['defesa'] ?? 40),
                'velocidade' => (int)($info['velocidade'] ?? 45),
                'nivel'      => 5,
                'movimentos' => PokeApiService::obterMovimentosPokemon($id, $info['nome'] ?? '', $info['tipo'] ?? 'normal')
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

    // Helper: multiplicador de vantagens elementais e imunidades
    $calcularMultiplicador = function($tipoAtk, $tipoDef) {
        $tipoAtk = strtolower(trim($tipoAtk));
        $tipoDef = strtolower(trim($tipoDef));

        $tabela = [
            'fogo' => [
                'planta' => 2.0, 'grass' => 2.0,
                'agua' => 0.5, 'water' => 0.5,
                'fogo' => 0.5, 'fire' => 0.5
            ],
            'fire' => [
                'grass' => 2.0, 'planta' => 2.0,
                'water' => 0.5, 'agua' => 0.5,
                'fire' => 0.5, 'fogo' => 0.5
            ],
            'agua' => [
                'fogo' => 2.0, 'fire' => 2.0, 'terra' => 2.0, 'ground' => 2.0,
                'planta' => 0.5, 'grass' => 0.5, 'agua' => 0.5, 'water' => 0.5
            ],
            'water' => [
                'fire' => 2.0, 'fogo' => 2.0, 'ground' => 2.0, 'terra' => 2.0,
                'grass' => 0.5, 'planta' => 0.5, 'water' => 0.5, 'agua' => 0.5
            ],
            'planta' => [
                'agua' => 2.0, 'water' => 2.0, 'terra' => 2.0, 'ground' => 2.0,
                'fogo' => 0.5, 'fire' => 0.5, 'planta' => 0.5, 'grass' => 0.5
            ],
            'grass' => [
                'water' => 2.0, 'agua' => 2.0, 'ground' => 2.0, 'terra' => 2.0,
                'fire' => 0.5, 'fogo' => 0.5, 'grass' => 0.5, 'planta' => 0.5
            ],
            'eletrico' => [
                'agua' => 2.0, 'water' => 2.0,
                'eletrico' => 0.5, 'electric' => 0.5, 'planta' => 0.5, 'grass' => 0.5,
                'terra' => 0.0, 'ground' => 0.0
            ],
            'electric' => [
                'water' => 2.0, 'agua' => 2.0,
                'electric' => 0.5, 'eletrico' => 0.5, 'grass' => 0.5, 'planta' => 0.5,
                'ground' => 0.0, 'terra' => 0.0
            ],
            'fantasma' => [
                'psiquico' => 2.0, 'psychic' => 2.0, 'fantasma' => 2.0, 'ghost' => 2.0,
                'normal' => 0.0
            ],
            'ghost' => [
                'psychic' => 2.0, 'psiquico' => 2.0, 'ghost' => 2.0, 'fantasma' => 2.0,
                'normal' => 0.0
            ],
            'normal' => [
                'fantasma' => 0.0, 'ghost' => 0.0
            ],
            'psiquico' => [
                'dark' => 0.0, 'sombrio' => 0.0
            ],
            'psychic' => [
                'dark' => 0.0, 'sombrio' => 0.0
            ]
        ];

        return $tabela[$tipoAtk][$tipoDef] ?? 1.0;
    };

    // Helper: calculo oficial do dano (PokeRogue / 9a Geracao)
    $calcularDanoCategoria = function($atacante, $defensor, $movimento, $modsAtacante, $modsDefensor) use ($calcularMultiplicador) {
        $nivel = max(1, (int)($atacante['nivel'] ?? 5));
        $poder = max(1, (int)($movimento['poder'] ?? 40));

        $tipoMov = strtolower(trim($movimento['tipo'] ?? 'normal'));
        $tipoAtacante = strtolower(trim($atacante['tipo'] ?? 'normal'));
        $tipoDefensor = strtolower(trim($defensor['tipo'] ?? 'normal'));

        // 1. Chance de Acerto Critico (1 em 16 = 6.25%)
        $isCritico = (rand(1, 16) === 1);
        $multCritico = $isCritico ? 1.5 : 1.0;

        // Se for critico, ignora penalidades negativas de ataque
        $atkMult = (float)($modsAtacante['atk_mult'] ?? 1.0);
        if ($isCritico && $atkMult < 1.0) {
            $atkMult = 1.0;
        }

        $defMult = (float)($modsDefensor['def_mult'] ?? 1.0);
        if ($isCritico && $defMult > 1.0) {
            $defMult = 1.0;
        }

        // Categoria do golpe (Fisico vs Especial)
        $categoria = strtolower($movimento['damage_class'] ?? $movimento['categoria'] ?? 'physical');
        if ($categoria === 'special' || $categoria === 'especial') {
            $atkEfetivo = max(1, (int)(($atacante['ataque'] ?? 40) * $atkMult * 1.1));
            $defEfetiva = max(1, (int)(($defensor['defesa'] ?? 40) * $defMult));
        } else {
            $atkEfetivo = max(1, (int)(($atacante['ataque'] ?? 40) * $atkMult));
            $defEfetiva = max(1, (int)(($defensor['defesa'] ?? 40) * $defMult));
        }

        // 2. Dano Base oficial
        $danoBase = ((($nivel * 2 / 5) + 2) * $poder * ($atkEfetivo / $defEfetiva)) / 50 + 2;

        // 3. Multiplicador de Tipo
        $multTipo = $calcularMultiplicador($tipoMov, $tipoDefensor);

        // Se for imune (0.0x), nao ha dano
        if ($multTipo == 0.0) {
            return [
                'dano'      => 0,
                'multTipo'  => 0.0,
                'isCritico' => false,
                'isStab'    => false
            ];
        }

        // 4. STAB (Same Type Attack Bonus - 1.5x)
        $isStab = ($tipoMov !== 'normal' && $tipoMov === $tipoAtacante);
        $multStab = $isStab ? 1.5 : 1.0;

        // 5. Variacao Aleatoria oficial (0.85 a 1.00)
        $variacaoRng = rand(85, 100) / 100.0;

        // 6. Dano Final Total
        $danoFinal = (int)floor($danoBase * $variacaoRng * $multStab * $multTipo * $multCritico);

        return [
            'dano'      => max(1, $danoFinal),
            'multTipo'  => $multTipo,
            'isCritico' => $isCritico,
            'isStab'    => $isStab
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
                    $combate['mensagens'][] = "[+{$cura} HP]";
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

                    $combate['mensagens'][] = "[-{$dano} HP]";

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
                        $mult = $calcularMultiplicador($m['tipo'] ?? 'normal', $tipoJog);
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

                        $combate['mensagens'][] = "[-{$dIni} HP]";

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
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>PokéArena - Batalha (PHP Puro)</title>
</head>
<body>

    <h1>POKÉARENA - BATALHA PHP PURO</h1>
    <p>
        <strong>Treinador:</strong> <?= htmlspecialchars($usuarioNome) ?> | 
        <strong>Saldo:</strong> P$ <?= number_format($usuarioDinheiro, 0, ',', '.') ?> | 
        <a href="index.php?rota=menu">Voltar ao Menu Principal</a>
    </p>

    <hr>

    <h2>Progresso: Batalha <?= $batalhaAtual ?> de <?= $totalBatalhas ?></h2>
    <p>
        <strong>Vitórias conquistadas:</strong> <?= $combate['vitorias'] ?> |
        <strong>Adversários restantes no catálogo:</strong> <?= count($combate['inimigos_restantes']) ?>
    </p>

    <?php if ($avisoErro): ?>
        <p><strong>[AVISO DE ERRO]:</strong> <?= htmlspecialchars($avisoErro) ?></p>
    <?php endif; ?>

    <hr>

    <!-- QUADRO DE COMBATE: POKÉMON ATIVO VS INIMIGO SORTEADO -->
    <table border="1" cellpadding="10" cellspacing="0" width="100%">
        <tr>
            <th width="50%">SEU POKÉMON EM CAMPO</th>
            <th width="50%">ADVERSÁRIO SORTEADO</th>
        </tr>
        <tr>
            <td valign="top">
                <h3><?= htmlspecialchars($nomeJog) ?> (Nv. 5)</h3>
                <p><strong>Tipo:</strong> <?= ucfirst($pokemonJogador['tipo'] ?? 'normal') ?></p>
                <p><strong>HP:</strong> <?= $hpAtualJog ?> / <?= $hpMaxJog ?></p>
                <p><strong>Ataque:</strong> <?= $pokemonJogador['ataque'] ?? 40 ?> (Mod: x<?= number_format($combate['mods_jogador']['atk_mult'] ?? 1.0, 1) ?>)</p>
                <p><strong>Defesa:</strong> <?= $pokemonJogador['defesa'] ?? 40 ?> (Mod: x<?= number_format($combate['mods_jogador']['def_mult'] ?? 1.0, 1) ?>)</p>
                <p><strong>Condição:</strong> <?= $combate['mods_jogador']['condicao'] ?? 'Normal' ?></p>
                <p><strong>Estado:</strong> <?= $hpAtualJog > 0 ? 'Consciente' : 'DESMAIADO' ?></p>
            </td>
            <td valign="top">
                <h3><?= htmlspecialchars($nomeIni) ?> (Nv. <?= (int)($pokemonInimigo['nivel'] ?? 5) ?>)</h3>
                <p><strong>Tipo:</strong> <?= ucfirst($pokemonInimigo['tipo'] ?? 'normal') ?></p>
                <p><strong>HP:</strong> <?= $hpAtualIni ?> / <?= $hpMaxIni ?></p>
                <p><strong>Ataque:</strong> <?= $pokemonInimigo['ataque'] ?? 40 ?> (Mod: x<?= number_format($combate['mods_inimigo']['atk_mult'] ?? 1.0, 1) ?>)</p>
                <p><strong>Defesa:</strong> <?= $pokemonInimigo['defesa'] ?? 40 ?> (Mod: x<?= number_format($combate['mods_inimigo']['def_mult'] ?? 1.0, 1) ?>)</p>
                <p><strong>Condição:</strong> <?= $combate['mods_inimigo']['condicao'] ?? 'Normal' ?></p>
                <p><strong>Estado:</strong> <?= $hpAtualIni > 0 ? 'Consciente' : 'DERROTADO' ?></p>
            </td>
        </tr>
    </table>

    <hr>

    <!-- HISTÓRICO DE MENSAGENS (ESTILO LOG POKÉROGUE) -->
    <h3>Histórico da Batalha:</h3>
    <div style="border: 1px solid #aaa; padding: 12px; margin-bottom: 15px; background: #fdfdfd; min-height: 80px; font-family: monospace; font-size: 15px; line-height: 1.6;">
        <?php if (empty($mensagensLog)): ?>
            <em>A batalha começou! Escolha uma ação abaixo.</em>
        <?php else: ?>
            <?php foreach ($mensagensLog as $msg): ?>
                <?php 
                    $estilo = '';
                    if (str_starts_with($msg, '[-')) {
                        $estilo = 'color: #d32f2f; font-weight: bold; font-size: 16px;';
                    } elseif (str_starts_with($msg, '[+')) {
                        $estilo = 'color: #2e7d32; font-weight: bold; font-size: 16px;';
                    } elseif ($msg === 'Um acerto crítico!') {
                        $estilo = 'color: #1976d2; font-weight: bold;';
                    } elseif ($msg === 'É super eficaz!') {
                        $estilo = 'color: #d32f2f; font-weight: bold;';
                    } elseif ($msg === 'Não é muito eficaz...') {
                        $estilo = 'color: #757575;';
                    } elseif (str_contains($msg, 'desmaiou')) {
                        $estilo = 'color: #c2185b; font-weight: bold;';
                    }
                ?>
                <div style="<?= $estilo ?>"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ======================================================================
         MINI MENU: "O que o pokemon deve fazer?"
         ====================================================================== -->
    <h2>O que <?= htmlspecialchars($nomeJog) ?> deve fazer?</h2>

    <?php if ($statusCombate === 'em_combate'): ?>

        <?php if ($hpAtualJog <= 0): ?>

            <p><strong>Seu Pokémon ativo desmaiou! Você deve trocar por outro Pokémon da sua equipe.</strong></p>
            <form method="POST">
                <input type="hidden" name="acao" value="abrir_menu">
                <input type="hidden" name="menu_alvo" value="trocar">
                <button type="submit">🔄 Escolher outro Pokémon da Equipe</button>
            </form>

        <?php elseif ($menuAberto === 'principal'): ?>

            <!-- MINI MENU: LUTAR | POKÉMON (TROCAR) | FUGIR -->
            <form method="POST" style="display:inline-block; margin-right: 10px;">
                <input type="hidden" name="acao" value="abrir_menu">
                <input type="hidden" name="menu_alvo" value="lutar">
                <button type="submit" style="font-size: 16px; padding: 8px 16px;">⚔️ Lutar</button>
            </form>

            <form method="POST" style="display:inline-block; margin-right: 10px;">
                <input type="hidden" name="acao" value="abrir_menu">
                <input type="hidden" name="menu_alvo" value="trocar">
                <button type="submit" style="font-size: 16px; padding: 8px 16px;">🔄 Pokémon (Trocar)</button>
            </form>

            <form method="POST" style="display:inline-block;">
                <input type="hidden" name="acao" value="fugir">
                <button type="submit" style="font-size: 16px; padding: 8px 16px;" onclick="return confirm('Deseja realmente fugir do combate e voltar ao menu?');">🏃 Fugir</button>
            </form>

        <?php elseif ($menuAberto === 'lutar'): ?>

            <!-- SUBMENU: ESCOLHA DO ATAQUE (APENAS O NOME DO GOLPE) -->
            <p><strong>Escolha um ataque para <?= htmlspecialchars($nomeJog) ?>:</strong></p>

            <div style="margin: 15px 0;">
                <?php foreach (['fisico', 'especial', 'status'] as $chave): ?>
                    <?php if (!empty($movimentosJog[$chave]['nome'])): ?>
                        <form method="POST" style="display:inline-block; margin-right: 12px; margin-bottom: 10px;">
                            <input type="hidden" name="acao" value="executar_ataque">
                            <input type="hidden" name="tipo_ataque" value="<?= $chave ?>">
                            <button type="submit" style="font-size: 16px; padding: 10px 22px; cursor: pointer;">
                                <?= htmlspecialchars($movimentosJog[$chave]['nome']) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <form method="POST">
                <input type="hidden" name="acao" value="abrir_menu">
                <input type="hidden" name="menu_alvo" value="principal">
                <button type="submit">← Voltar</button>
            </form>

        <?php elseif ($menuAberto === 'trocar'): ?>

            <!-- SUBMENU DE TROCA DA EQUIPE -->
            <h3>Escolha qual Pokémon colocar em campo:</h3>
            <table border="1" cellpadding="8" cellspacing="0" width="100%">
                <tr>
                    <th>Slot</th>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>HP Atual / Máximo</th>
                    <th>Situação</th>
                    <th>Ação</th>
                </tr>
                <?php foreach ($meuTime as $idx => $pk): ?>
                    <?php 
                        $pkHpMax = (int)($pk['hpMaximo'] ?? $pk['hp'] ?? 40);
                        $pkHpAtual = (int)($pk['hpAtual'] ?? $pkHpMax);
                        $desmaiado = ($pkHpAtual <= 0);
                        $emCampo = ($idx === $ativoIdx);
                    ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><strong><?= htmlspecialchars($pk['nome'] ?? $pk['name']) ?></strong></td>
                        <td><?= ucfirst($pk['tipo'] ?? 'normal') ?></td>
                        <td><?= $pkHpAtual ?> / <?= $pkHpMax ?></td>
                        <td>
                            <?php if ($emCampo): ?>
                                <strong>[EM CAMPO]</strong>
                            <?php elseif ($desmaiado): ?>
                                <em>[DESMAIADO 💀]</em>
                            <?php else: ?>
                                Consciente
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($emCampo): ?>
                                (Ativo)
                            <?php elseif ($desmaiado): ?>
                                <em>Não pode lutar (HP = 0)</em>
                            <?php else: ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="acao" value="trocar">
                                    <input type="hidden" name="novo_idx" value="<?= $idx ?>">
                                    <button type="submit">Colocar em Campo</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <br>
            <?php if ($hpAtualJog > 0): ?>
                <form method="POST">
                    <input type="hidden" name="acao" value="abrir_menu">
                    <input type="hidden" name="menu_alvo" value="principal">
                    <button type="submit">← Voltar às Opções</button>
                </form>
            <?php endif; ?>

        <?php endif; ?>

    <?php elseif ($statusCombate === 'vitoria_rodada'): ?>

        <p><strong>Vitória! Você derrotou o <?= htmlspecialchars($nomeIni) ?>!</strong></p>
        <form method="POST">
            <input type="hidden" name="acao" value="proxima_batalha">
            <button type="submit" style="font-size: 16px; padding: 10px 20px;">
                Avançar para a Próxima Batalha (<?= $batalhaAtual + 1 ?> de <?= $totalBatalhas ?>) →
            </button>
        </form>

    <?php elseif ($statusCombate === 'campeao'): ?>

        <p><strong>🏆 PARABÉNS! VOCÊ CONQUISTOU A POKÉARENA E VENCEU AS 9 BATALHAS!</strong></p>
        <form method="POST">
            <input type="hidden" name="acao" value="reiniciar">
            <button type="submit">Jogar Novamente do Início</button>
        </form>

    <?php elseif ($statusCombate === 'derrota_sem_pokemon'): ?>

        <p><strong>Todos os seus Pokémon desmaiaram!</strong></p>
        <p>Você ainda possui P$ <?= $usuarioDinheiro ?>. Acesse o PokéMart ou o Centro Pokémon para recuperar a saúde do seu time:</p>
        <p>
            <a href="index.php?rota=loja">Ir para a Loja (PokéMart)</a> | 
            <a href="index.php?rota=centro">Ir para o Centro Pokémon</a>
        </p>

    <?php endif; ?>

    <hr>

    <form method="POST">
        <input type="hidden" name="acao" value="reiniciar">
        <button type="submit" onclick="return confirm('Deseja reiniciar o combate de 9 batalhas?');">Reiniciar combate</button>
    </form>

</body>
</html>
