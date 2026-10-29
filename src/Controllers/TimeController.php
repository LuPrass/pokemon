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

            $usuarioId = (int)($_SESSION['usuario_id'] ?? $dados['usuario_id'] ?? 0);
            if ($usuarioId <= 0 || !isset($dados['time']) || !is_array($dados['time'])) {
                throw new Exception("Dados da requisição inválidos ou usuário não informado.");
            }

            $pokemons = $dados['time'];

            // Validação de limite máximo de 3 Pokémons
            if (count($pokemons) > 3) {
                throw new TimeCheioException();
            }

            if (count($pokemons) === 0) {
                throw new Exception("Você precisa selecionar pelo menos 1 Pokémon para o time.");
            }

            $pdo = Conexao::getConnection();
            $pdo->beginTransaction();

            // Remove o time anterior do usuário para atualizar com a nova escalação
            $stmtLimparTime = $pdo->prepare("DELETE FROM time WHERE usuario_id = :usuario_id");
            $stmtLimparTime->execute([':usuario_id' => $usuarioId]);

            $posicao = 1;
            $pokemonsPersistidos = [];
            foreach ($pokemons as $pk) {
                // Valida atributos obrigatórios
                if (!is_array($pk) || empty($pk['nome']) || empty($pk['tipo'])) {
                    throw new PokemonInvalidoException("Dados incompletos para um dos Pokémons selecionados.");
                }

                // Reaproveita o registro do Pokémon para manter HP e condição entre partidas.
                $stmtPokemonExistente = $pdo->prepare("
                    SELECT id FROM pokemon
                    WHERE usuario_id = :usuario_id AND nome = :nome
                    ORDER BY id LIMIT 1
                ");
                $stmtPokemonExistente->execute([
                    ':usuario_id' => $usuarioId,
                    ':nome' => $pk['nome']
                ]);
                $pokemonId = $stmtPokemonExistente->fetchColumn();

                if (!$pokemonId) {
                    $stmtPokemon = $pdo->prepare("
                        INSERT INTO pokemon (usuario_id, nome, tipo, nivel, hp_maximo, hp_atual, ataque, defesa, velocidade, prioridade, status_condicao)
                        VALUES (:usuario_id, :nome, :tipo, :nivel, :hp_maximo, :hp_atual, :ataque, :defesa, :velocidade, :prioridade, :status_condicao)
                    ");

                    $stmtPokemon->execute([
                        ':usuario_id'       => $usuarioId,
                        ':nome'             => $pk['nome'],
                        ':tipo'             => $pk['tipo'],
                        ':nivel'            => $pk['nivel'] ?? 5,
                        ':hp_maximo'        => $pk['hpMaximo'] ?? 35,
                        ':hp_atual'         => $pk['hpAtual'] ?? ($pk['hpMaximo'] ?? 35),
                        ':ataque'           => $pk['ataque'] ?? 40,
                        ':defesa'           => $pk['defesa'] ?? 40,
                        ':velocidade'       => $pk['velocidade'] ?? 50,
                        ':prioridade'       => $pk['prioridade'] ?? 0,
                        ':status_condicao'  => $pk['statusCondicao'] ?? 'Normal'
                    ]);

                    $pokemonId = $pdo->lastInsertId();
                }
                $pokemonsPersistidos[] = $pokemonId;

                // Insere na tabela 'time' com a posição (1 a 3)
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
            }
            unset($pokemon);

            $_SESSION['meu_time'] = array_values($timeDaSessao);
            $_SESSION['inimigos_restantes'] = is_array($dados['restantes'] ?? null) ? $dados['restantes'] : [];
            $_SESSION['inimigos_time'] = is_array($dados['inimigos'] ?? null) ? $dados['inimigos'] : [];

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
