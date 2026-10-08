<?php

class LojaController extends Controller {

    // Lista os itens do Poké Mart
    public function listarItens() {
        $pdo = Conexao::getConnection();
        $this->responder(['sucesso' => true, 'itens' => Item::all($pdo)]);
    }

    // Compra um item e usa em um pokémon.
    // Recebe JSON: { "usuario_id": 1, "item_id": 1, "pokemon_id": 1 }
    public function comprar() {
        $dados = $this->lerJson();
        $pdo = Conexao::getConnection();

        try {
            $usuario = new Usuario();
            $item = new Item();
            $pokemon = new Pokemon();

            if (!$usuario->load($dados['usuario_id'] ?? 0)) {
                throw new Exception('Usuário não encontrado.');
            }
            if (!$item->load($dados['item_id'] ?? 0)) {
                throw new Exception('Item não encontrado.');
            }
            if (!$pokemon->load($dados['pokemon_id'] ?? 0) || $pokemon->getUsuarioId() != $usuario->getId()) {
                throw new Exception('Pokémon não encontrado neste perfil.');
            }

            // Valida e aplica o efeito ANTES de cobrar (se der erro, ninguém perde dinheiro)
            $this->aplicarEfeito($item, $pokemon);
            $usuario->gastar($item->getPreco());

            $pdo->beginTransaction();
            $usuario->save();
            $pokemon->save();
            $log = new LogLoja(null, $usuario->getId(), $item->getId(), $pokemon->getId(), $item->getPreco());
            $log->save();
            $pdo->commit();

            $this->responder([
                'sucesso' => true,
                'mensagem' => $item->getNome() . ' usado em ' . $pokemon->getNome() . '!',
                'dinheiro' => $usuario->getDinheiro(),
                'hpAtual' => $pokemon->getHpAtual(),
                'status' => $pokemon->getStatusCondicao()
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->responder(['sucesso' => false, 'mensagem' => $e->getMessage()], 400);
        }
    }

    // Histórico de compras. Ex: index.php?rota=historico-loja&usuario_id=1
    public function historico($usuarioId) {
        $pdo = Conexao::getConnection();
        $this->responder(['sucesso' => true, 'compras' => LogLoja::porUsuario($pdo, $usuarioId)]);
    }

    // Efeito de cada item no pokémon
    private function aplicarEfeito(Item $item, Pokemon $pokemon) {
        $nome = $item->getNome();

        if ($item->getTipo() == 'evolucao') {
            throw new Exception('A evolução ainda não está disponível.');
        }

        if ($item->getTipo() == 'reviver') {
            if (!$pokemon->estaDesmaiado()) {
                throw new Exception($pokemon->getNome() . ' não está desmaiado.');
            }
            $pokemon->curar((int) ($pokemon->getHpMaximo() / 2));
            return;
        }

        // Os demais itens não funcionam em pokémon desmaiado
        if ($pokemon->estaDesmaiado()) {
            throw new PokemonDesmaiadoException();
        }

        if ($item->getTipo() == 'restaurador') {
            if ($pokemon->getHpAtual() >= $pokemon->getHpMaximo() && $nome != 'Full Restore') {
                throw new Exception($pokemon->getNome() . ' já está com o HP cheio.');
            }
            if ($nome == 'Potion') $pokemon->curar(20);
            if ($nome == 'Super Potion') $pokemon->curar(50);
            if ($nome == 'Hyper Potion') $pokemon->curar(200);
            if ($nome == 'Max Potion') $pokemon->curar($pokemon->getHpMaximo());
            if ($nome == 'Full Restore') {
                $pokemon->curar($pokemon->getHpMaximo());
                $pokemon->setStatusCondicao('Normal');
            }
            return;
        }

        if ($item->getTipo() == 'cura') {
            $curas = [
                'Antidote' => 'Envenenado',
                'Burn Heal' => 'Queimado',
                'Ice Heal' => 'Congelado',
                'Awakening' => 'Dormindo',
                'Paralyze Heal' => 'Paralisado'
            ];

            if ($nome == 'Full Heal' && $pokemon->getStatusCondicao() != 'Normal') {
                $pokemon->setStatusCondicao('Normal');
            } elseif (isset($curas[$nome]) && $pokemon->getStatusCondicao() == $curas[$nome]) {
                $pokemon->setStatusCondicao('Normal');
            } else {
                throw new Exception('Este item não tem efeito em ' . $pokemon->getNome() . '.');
            }
        }
    }
}
