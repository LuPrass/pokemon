<?php

class BatalhaController extends Controller {

    // Faz a batalha completa contra um pokémon da PokéAPI.
    // Recebe JSON: { "usuario_id": 1, "pokemon_id": 1, "adversario": "geodude" }
    public function lutar() {
        $dados = $this->lerJson();

        try {
            $jogador = new Pokemon();
            if (!$jogador->load($dados['pokemon_id'] ?? 0) || $jogador->getUsuarioId() != ($dados['usuario_id'] ?? 0)) {
                throw new Exception('Pokémon não encontrado neste perfil.');
            }

            // Busca o inimigo na PokéAPI (lado do servidor)
            $api = new PokeApiService();
            $info = $api->buscarPokemon($dados['adversario'] ?? '');
            if ($info === false) {
                throw new PokemonInvalidoException('Adversário não encontrado na PokéAPI.');
            }

            // O inimigo não é salvo no banco, só existe durante a luta
            $inimigo = new Pokemon(null, null, $info['nome'], $info['tipo'], $info['nivel'],
                $info['hp'], $info['hp'], $info['ataque'], $info['defesa'], $info['velocidade']);

            $batalha = new Batalha($jogador, $inimigo);
            $resultado = $batalha->lutar();

            // Vitória rende 300 Pokédollars (o trigger do banco soma no usuário)
            $dinheiro = 0;
            if ($resultado == 'vitoria') {
                $dinheiro = 300;
            }

            $jogador->save(); // grava o HP que sobrou
            $log = new LogBatalha(null, $jogador->getUsuarioId(), $jogador->getId(), $info['nome'], $resultado, $dinheiro);
            $log->save();

            $this->responder([
                'sucesso' => true,
                'resultado' => $resultado,
                'dinheiroGanho' => $dinheiro,
                'turnos' => $batalha->getTurnos(),
                'hpRestante' => $jogador->getHpAtual(),
                'imagemInimigo' => $info['imagem'],
                'imagemJogadorCostas' => $info['imagemCostas']
            ]);
        } catch (Exception $e) {
            $this->responder(['sucesso' => false, 'mensagem' => $e->getMessage()], 400);
        }
    }

    // Histórico de batalhas. Ex: index.php?rota=historico-batalha&usuario_id=1
    public function historico($usuarioId) {
        $pdo = Conexao::getConnection();
        $this->responder(['sucesso' => true, 'batalhas' => LogBatalha::porUsuario($pdo, $usuarioId)]);
    }
}
