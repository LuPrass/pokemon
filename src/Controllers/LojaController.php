<?php

class LojaController extends Controller {

    // Lista os itens do Poké Mart
    public function listarItens() {
        $pdo = Conexao::getConnection();
        $this->responder(['sucesso' => true, 'itens' => Item::all($pdo)]);
    }

    // Compra um item da loja.
    // Se nenhum pokemon_id for fornecido, guarda na Mochila (sessão).
    // Se pokemon_id for fornecido, aplica diretamente no Pokémon.
    public function comprar() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $dados = $this->lerJson();
        if (empty($dados)) {
            $dados = $_POST;
        }

        $pdo = Conexao::getConnection();

        try {
            $usuarioId = (int)($_SESSION['usuario_id'] ?? $dados['usuario_id'] ?? 1);
            $itemId = (int)($dados['item_id'] ?? 0);
            $pokemonId = !empty($dados['pokemon_id']) ? (int)$dados['pokemon_id'] : null;

            $usuario = new Usuario();
            if (!$usuario->load($usuarioId)) {
                throw new Exception('Usuário não encontrado.');
            }

            $item = new Item();
            if (!$item->load($itemId)) {
                throw new Exception('Item não encontrado na loja.');
            }

            // Verifica se o usuário tem saldo suficiente
            if ($usuario->getDinheiro() < $item->getPreco()) {
                throw new SaldoInsuficienteException(
                    "Saldo insuficiente! Você tem ₽ " . number_format($usuario->getDinheiro(), 0, ',', '.') . 
                    " e o item " . $item->getNome() . " custa ₽ " . number_format($item->getPreco(), 0, ',', '.') . "."
                );
            }

            // Caso 1: Compra com aplicação direta em um Pokémon
            if ($pokemonId !== null) {
                $pokemon = new Pokemon();
                if (!$pokemon->load($pokemonId) || $pokemon->getUsuarioId() != $usuario->getId()) {
                    throw new Exception('Pokémon não encontrado neste perfil.');
                }

                $this->aplicarEfeito($item, $pokemon);
                $usuario->gastar($item->getPreco());

                $pdo->beginTransaction();
                $usuario->save();
                $pokemon->save();
                $log = new LogLoja(null, $usuario->getId(), $item->getId(), $pokemon->getId(), $item->getPreco());
                $log->save();
                $pdo->commit();

                $_SESSION['dinheiro'] = $usuario->getDinheiro();

                $this->responder([
                    'sucesso' => true,
                    'mensagem' => $item->getNome() . ' usado em ' . $pokemon->getNome() . '!',
                    'dinheiro' => $usuario->getDinheiro(),
                    'saldo_formatado' => number_format($usuario->getDinheiro(), 0, ',', '.'),
                    'hpAtual' => $pokemon->getHpAtual(),
                    'status' => $pokemon->getStatusCondicao()
                ]);
            } else {
                // Caso 2: Compra para guardar na Mochila (Padrão do PokéMart)
                $usuario->gastar($item->getPreco());

                $pdo->beginTransaction();
                $usuario->save();
                $log = new LogLoja(null, $usuario->getId(), $item->getId(), null, $item->getPreco());
                $log->save();
                $pdo->commit();

                $_SESSION['dinheiro'] = $usuario->getDinheiro();

                // Armazena na Mochila (sessão)
                if (!isset($_SESSION['mochila']) || !is_array($_SESSION['mochila'])) {
                    $_SESSION['mochila'] = [];
                }

                $icones = [
                    'restaurador' => '🧪',
                    'cura'        => '💊',
                    'reviver'     => '💖',
                    'evolucao'    => '✨'
                ];
                $icone = $icones[$item->getTipo()] ?? '📦';

                $jaExiste = false;
                foreach ($_SESSION['mochila'] as &$itemMochila) {
                    if ((int)($itemMochila['id'] ?? 0) === (int)$item->getId()) {
                        $itemMochila['quantidade'] = (int)($itemMochila['quantidade'] ?? 1) + 1;
                        $jaExiste = true;
                        break;
                    }
                }
                unset($itemMochila);

                if (!$jaExiste) {
                    $_SESSION['mochila'][] = [
                        'id'         => (int)$item->getId(),
                        'nome'       => $item->getNome(),
                        'tipo'       => $item->getTipo(),
                        'icone'      => $icone,
                        'descricao'  => $item->getDescricao(),
                        'preco'      => (int)$item->getPreco(),
                        'quantidade' => 1
                    ];
                }

                $this->responder([
                    'sucesso' => true,
                    'mensagem' => 'Parabéns! Você comprou 1x ' . $item->getNome() . ' e o item foi guardado na sua Mochila!',
                    'dinheiro' => $usuario->getDinheiro(),
                    'saldo_formatado' => number_format($usuario->getDinheiro(), 0, ',', '.'),
                    'item' => [
                        'id' => (int)$item->getId(),
                        'nome' => $item->getNome(),
                        'tipo' => $item->getTipo(),
                        'preco' => (int)$item->getPreco()
                    ]
                ]);
            }
        } catch (SaldoInsuficienteException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->responder([
                'sucesso' => false,
                'erro_tipo' => 'saldo_insuficiente',
                'mensagem' => $e->getMessage()
            ], 400);
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
