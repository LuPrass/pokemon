<?php

// Controlador responsável por salvar a escalação do time escolhido pelo usuário
class TimeController extends Controller {

    // Salva o time no banco de dados e guarda na sessão para a batalha
    public function salvarTime() {
        $pdo = null;

        try {
            // 1. Verifica se o usuário está logado
            if (empty($_SESSION['logado'])) {
                $this->responder(['sucesso' => false, 'mensagem' => 'Faça login antes de salvar seu time.'], 401);
            }

            $usuarioId = (int)($_SESSION['usuario_id'] ?? 1);
            $dados = $this->lerJson();
            $pokemons = $dados['time'] ?? [];

            // 2. Validações da quantidade de Pokémons (1 a 6)
            if (empty($pokemons)) {
                throw new Exception("Você precisa escolher pelo menos 1 Pokémon para o time.");
            }

            if (count($pokemons) > 6) {
                throw new TimeCheioException();
            }

            $pdo = Conexao::getConnection();
            $pdo->beginTransaction();

            // 3. Remove a escalação anterior desse treinador
            $stmtLimpar = $pdo->prepare("DELETE FROM time WHERE usuario_id = :usuario_id");
            $stmtLimpar->execute([':usuario_id' => $usuarioId]);

            $posicao = 1;
            $pokemonsSalvos = [];

            // 4. Cadastra ou atualiza cada Pokémon e vincula na tabela time
            foreach ($pokemons as $pk) {
                if (empty($pk['nome']) || empty($pk['tipo'])) {
                    throw new PokemonInvalidoException("Dados incompletos para um dos Pokémons.");
                }

                // Verifica se o usuário já tem esse Pokémon cadastrado
                $stmtBusca = $pdo->prepare("SELECT id FROM pokemon WHERE usuario_id = :usuario_id AND LOWER(nome) = LOWER(:nome) LIMIT 1");
                $stmtBusca->execute([
                    ':usuario_id' => $usuarioId,
                    ':nome'       => $pk['nome']
                ]);
                $pokemonId = $stmtBusca->fetchColumn();

                // Se não existir, cadastra no banco
                if (!$pokemonId) {
                    $stmtNovo = $pdo->prepare("
                        INSERT INTO pokemon (usuario_id, nome, tipo, nivel, hp_maximo, hp_atual, ataque, defesa, velocidade, prioridade, status_condicao)
                        VALUES (:usuario_id, :nome, :tipo, :nivel, :hp_maximo, :hp_atual, :ataque, :defesa, :velocidade, :prioridade, :status_condicao)
                    ");
                    $stmtNovo->execute([
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
                } else {
                    // Se já existir, ele começa o novo time renovado (vida cheia, nível inicial e sem status)
                    $stmtRenovar = $pdo->prepare("
                        UPDATE pokemon SET nivel = :nivel, hp_maximo = :hp_maximo, hp_atual = :hp_atual, ataque = :ataque,
                            defesa = :defesa, velocidade = :velocidade, status_condicao = 'Normal', evoluido = 0
                        WHERE id = :id
                    ");
                    $stmtRenovar->execute([
                        ':nivel'      => $pk['nivel'],
                        ':hp_maximo'  => $pk['hpMaximo'],
                        ':hp_atual'   => $pk['hpMaximo'],
                        ':ataque'     => $pk['ataque'],
                        ':defesa'     => $pk['defesa'],
                        ':velocidade' => $pk['velocidade'],
                        ':id'         => $pokemonId
                    ]);
                }

                $pokemonsSalvos[] = $pokemonId;

                // Salva a vaga no time (posição de 1 a 6)
                $stmtTime = $pdo->prepare("INSERT INTO time (usuario_id, pokemon_id, posicao) VALUES (:usuario_id, :pokemon_id, :posicao)");
                $stmtTime->execute([
                    ':usuario_id' => $usuarioId,
                    ':pokemon_id' => $pokemonId,
                    ':posicao'    => $posicao
                ]);

                $posicao++;
            }

            $pdo->commit();

            // 5. Prepara os dados para a sessão da batalha
            $meuTime = $dados['meuTime'] ?? $pokemons;
            foreach ($meuTime as $i => &$poke) {
                $poke = Pokemon::normalizar($poke);
                if (isset($pokemonsSalvos[$i])) {
                    $poke['pokemon_db_id'] = $pokemonsSalvos[$i];
                }
                $poke['movimentos'] = PokeApiService::obterMovimentosPadrao($poke['id'] ?? 0, $poke['nome'], $poke['tipo']);
            }
            unset($poke);

            // Prepara os 9 adversários da arena
            $inimigos = $dados['inimigos'] ?? [];
            foreach ($inimigos as &$ini) {
                $ini = Pokemon::normalizar($ini);
                $ini['movimentos'] = PokeApiService::obterMovimentosPadrao($ini['id'] ?? 0, $ini['nome'], $ini['tipo']);
            }
            unset($ini);

            // Guarda na sessão para iniciar a arena com o time renovado
            $_SESSION['meu_time'] = array_values($meuTime);
            $_SESSION['inimigos_restantes'] = $dados['restantes'] ?? [];
            $_SESSION['inimigos_time'] = array_values($inimigos);
            unset($_SESSION['combate']); // Inicia nova campanha de combate com os Pokémon 100% prontos e saudáveis

            $this->responder([
                'sucesso'  => true,
                'mensagem' => 'Time salvo com sucesso!',
                'redirect' => 'index.php?rota=batalha'
            ]);

        } catch (TimeCheioException | PokemonInvalidoException $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->responder(['sucesso' => false, 'mensagem' => $e->getMessage()], 400);

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->responder(['sucesso' => false, 'mensagem' => $e->getMessage()], 500);
        }
    }
}
