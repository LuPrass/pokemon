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

            if (!$dados || empty($dados['usuario_id']) || !isset($dados['time'])) {
                throw new Exception("Dados da requisição inválidos ou usuário não informado.");
            }

            $usuarioId = (int)$dados['usuario_id'];
            $pokemons = (array)$dados['time'];

            // Validação de limite máximo de 6 Pokémons
            if (count($pokemons) > 6) {
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
            foreach ($pokemons as $pk) {
                // Valida atributos obrigatórios
                if (empty($pk['nome']) || empty($pk['tipo'])) {
                    throw new PokemonInvalidoException("Dados incompletos para um dos Pokémons selecionados.");
                }

                // Insere o Pokémon vinculado ao usuário
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

            echo json_encode([
                'sucesso'  => true,
                'mensagem' => 'Time salvo com sucesso!'
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
