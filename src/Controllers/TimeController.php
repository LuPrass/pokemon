<?php

/**
 * TimeController
 * Gerencia o salvamento e consulta do time Pokémon do usuário
 */
class TimeController {

    /**
     * Recebe requisição assíncrona POST em JSON e salva o time no banco via PDO
     */
    public function salvarTime(): void {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            if (!is_array($dados)) {
                throw new Exception("Dados da requisição inválidos ou usuário não informado.");
            }

            if (empty($_SESSION['logado']) || $_SESSION['logado'] !== true) {
                http_response_code(401);
                echo json_encode([
                    'sucesso' => false,
                    'mensagem' => 'Faça login antes de salvar seu time.'
                ]);
                exit;
            }

            $usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
            if ($usuarioId <= 0 || !isset($dados['time']) || !is_array($dados['time'])) {
                throw new Exception("Dados da requisição inválidos ou usuário não informado.");
            }

            $pokemons = $dados['time'];

            // Validação de limite máximo de 6 Pokémons
            if (count($pokemons) > 6) {
                throw new TimeCheioException();
            }

            if (count($pokemons) === 0) {
                throw new Exception("Você precisa selecionar pelo menos 1 Pokémon para o time.");
            }

            $pdo = Conexao::getConnection();
            $stmtUsuario = $pdo->prepare("SELECT id FROM usuario WHERE id = :usuario_id");
            $stmtUsuario->execute([':usuario_id' => $usuarioId]);
            if (!$stmtUsuario->fetchColumn()) {
                $stmtUsuario = $pdo->prepare("SELECT id FROM usuario WHERE nome = :nome");
                $stmtUsuario->execute([':nome' => $_SESSION['usuario'] ?? '']);
                $usuarioIdBanco = $stmtUsuario->fetchColumn();

                if (!$usuarioIdBanco) {
                    http_response_code(401);
                    echo json_encode([
                        'sucesso' => false,
                        'mensagem' => 'Seu perfil não foi encontrado no banco. Saia da conta e entre novamente para atualizar a sessão.'
                    ]);
                    exit;
                }

                $usuarioId = (int)$usuarioIdBanco;
                $_SESSION['usuario_id'] = $usuarioId;
            }

            $pdo->beginTransaction();

            // Garante que o usuário possua ao menos o saldo inicial (100) caso tenha ficado zerado em game over anterior
            $stmtUserDinheiro = $pdo->prepare("SELECT dinheiro FROM usuario WHERE id = :usuario_id");
            $stmtUserDinheiro->execute([':usuario_id' => $usuarioId]);
            $dinheiroAtual = (int)$stmtUserDinheiro->fetchColumn();
            if ($dinheiroAtual < 25) {
                $stmtFixDinheiro = $pdo->prepare("UPDATE usuario SET dinheiro = 100 WHERE id = :usuario_id");
                $stmtFixDinheiro->execute([':usuario_id' => $usuarioId]);
            }

            // Remove o time anterior do usuário para atualizar com a nova escalação
            $stmtLimparTime = $pdo->prepare("DELETE FROM time WHERE usuario_id = :usuario_id");
            $stmtLimparTime->execute([':usuario_id' => $usuarioId]);

            $posicao = 1;
            $pokemonsPersistidos = [];
            foreach ($pokemons as $pk) {
                // Valida atributos obrigatórios
                if (
                    !is_array($pk)
                    || empty($pk['nome'])
                    || empty($pk['tipo'])
                ) {
                    throw new PokemonInvalidoException("Dados incompletos para um dos Pokémons selecionados.");
                }

                $hpMaximo = (int)($pk['hpMaximo'] ?? $pk['hp'] ?? 35);
                $nivel = (int)($pk['nivel'] ?? 5);
                $ataque = (int)($pk['ataque'] ?? $pk['attack'] ?? 40);
                $defesa = (int)($pk['defesa'] ?? $pk['defense'] ?? 40);
                $velocidade = (int)($pk['velocidade'] ?? $pk['speed'] ?? 50);
                $prioridade = (int)($pk['prioridade'] ?? 0);

                // Localiza o Pokémon salvo para este usuário e sempre reinicia seus atributos para o padrão
                $stmtPokemonExistente = $pdo->prepare("
                    SELECT id FROM pokemon
                    WHERE usuario_id = :usuario_id AND LOWER(nome) = LOWER(:nome)
                    ORDER BY id LIMIT 1
                ");
                $stmtPokemonExistente->execute([
                    ':usuario_id' => $usuarioId,
                    ':nome' => $pk['nome']
                ]);
                $pokemonId = $stmtPokemonExistente->fetchColumn();

                if ($pokemonId) {
                    $stmtAtualizarPokemon = $pdo->prepare("
                        UPDATE pokemon 
                        SET tipo = :tipo, nivel = :nivel, hp_maximo = :hp_maximo, hp_atual = :hp_atual,
                            ataque = :ataque, defesa = :defesa, velocidade = :velocidade,
                            prioridade = :prioridade, status_condicao = 'Normal'
                        WHERE id = :id AND usuario_id = :usuario_id
                    ");
                    $stmtAtualizarPokemon->execute([
                        ':tipo'            => $pk['tipo'],
                        ':nivel'           => $nivel,
                        ':hp_maximo'       => $hpMaximo,
                        ':hp_atual'        => $hpMaximo,
                        ':ataque'          => $ataque,
                        ':defesa'          => $defesa,
                        ':velocidade'      => $velocidade,
                        ':prioridade'      => $prioridade,
                        ':id'              => $pokemonId,
                        ':usuario_id'      => $usuarioId
                    ]);
                } else {
                    $stmtPokemon = $pdo->prepare("
                        INSERT INTO pokemon (usuario_id, nome, tipo, nivel, hp_maximo, hp_atual, ataque, defesa, velocidade, prioridade, status_condicao)
                        VALUES (:usuario_id, :nome, :tipo, :nivel, :hp_maximo, :hp_atual, :ataque, :defesa, :velocidade, :prioridade, 'Normal')
                    ");

                    $stmtPokemon->execute([
                        ':usuario_id'       => $usuarioId,
                        ':nome'             => $pk['nome'],
                        ':tipo'             => $pk['tipo'],
                        ':nivel'            => $nivel,
                        ':hp_maximo'        => $hpMaximo,
                        ':hp_atual'         => $hpMaximo,
                        ':ataque'           => $ataque,
                        ':defesa'           => $defesa,
                        ':velocidade'       => $velocidade,
                        ':prioridade'       => $prioridade
                    ]);

                    $pokemonId = $pdo->lastInsertId();
                }
                $pokemonsPersistidos[] = $pokemonId;

                // Insere na tabela 'time' com a posição (1 a 6)
                $stmtTime = $pdo->prepare("
                    INSERT INTO time (usuario_id, pokemon_id, posicao)
                    VALUES (:usuario_id, :pokemon_id, :posicao)
                ");
                $stmtTime->execute([
                    ':usuario_id'  => $usuarioId,
                    ':pokemon_id'  => $pokemonId,
                    ':posicao'     => $posicao
                ]);

                $posicao++;
            }

            $pdo->commit();

            $timeDaSessao = $dados['meuTime'] ?? $pokemons;
            if (!is_array($timeDaSessao) || count($timeDaSessao) !== count($pokemonsPersistidos)) {
                $timeDaSessao = $pokemons;
            }
            foreach ($timeDaSessao as $indice => &$pokemon) {
                if (is_array($pokemon) && isset($pokemonsPersistidos[$indice])) {
                    $pokemon['pokemon_db_id'] = $pokemonsPersistidos[$indice];
                }
                if (is_array($pokemon)) {
                    $idPk = (int)($pokemon['id'] ?? $pokemon['id_pokedex'] ?? 0);
                    $nomePk = $pokemon['nome'] ?? $pokemon['name'] ?? '';
                    $tipoPk = $pokemon['tipo'] ?? 'normal';
                    $hpMax = (int)($pokemon['hpMaximo'] ?? $pokemon['hp'] ?? 35);
                    $pokemon['hpMaximo'] = $hpMax;
                    $pokemon['hpAtual'] = $hpMax;
                    $pokemon['hp_atual'] = $hpMax;
                    $pokemon['nivel'] = 5;
                    $pokemon['statusCondicao'] = 'Normal';
                    $pokemon['status_condicao'] = 'Normal';
                    $pokemon['movimentos'] = PokeApiService::obterMovimentosPadrao($idPk, $nomePk, $tipoPk);
                }
            }
            unset($pokemon);

            $inimigosCompletos = is_array($dados['inimigos'] ?? null) ? $dados['inimigos'] : [];
            foreach ($inimigosCompletos as &$inimigo) {
                if (is_array($inimigo)) {
                    $idIni = (int)($inimigo['id'] ?? 0);
                    $nomeIni = $inimigo['nome'] ?? $inimigo['name'] ?? '';
                    $tipoIni = $inimigo['tipo'] ?? 'normal';
                    $inimigo['movimentos'] = PokeApiService::obterMovimentosPadrao($idIni, $nomeIni, $tipoIni);
                }
            }
            unset($inimigo);

            $_SESSION['meu_time'] = array_values($timeDaSessao);
            $_SESSION['inimigos_restantes'] = is_array($dados['restantes'] ?? null) ? $dados['restantes'] : [];
            $_SESSION['inimigos_time'] = array_values($inimigosCompletos);
            unset($_SESSION['combate'], $_SESSION['batalha_iniciada'], $_SESSION['aviso_derrota']);

            echo json_encode([
                'sucesso'  => true,
                'mensagem' => 'Time salvo com sucesso!',
                'redirect' => 'index.php?rota=batalha'
            ]);
        } catch (TimeCheioException | PokemonInvalidoException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo json_encode([
                'sucesso'  => false,
                'mensagem' => $e->getMessage()
            ]);
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode([
                'sucesso'  => false,
                'mensagem' => 'Erro interno ao salvar time: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}
