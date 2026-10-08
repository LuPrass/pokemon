<?php

class UsuarioController extends Controller {

    // Lista todos os perfis (tela de troca de perfil)
    public function listar() {
        $pdo = Conexao::getConnection();
        $this->responder(['sucesso' => true, 'usuarios' => Usuario::all($pdo)]);
    }

    // Busca um perfil pelo id
    public function buscar($id) {
        $usuario = new Usuario();

        if (!$usuario->load($id)) {
            $this->responder(['sucesso' => false, 'mensagem' => 'Usuário não encontrado.'], 404);
        }

        $this->responder([
            'sucesso' => true,
            'usuario' => [
                'id' => $usuario->getId(),
                'nome' => $usuario->getNome(),
                'dinheiro' => $usuario->getDinheiro(),
                'dataCriacao' => $usuario->getDataCriacao()
            ]
        ]);
    }

    // Cria um novo perfil. Recebe JSON: { "nome": "Gary" }
    public function criar() {
        $dados = $this->lerJson();

        if (empty($dados['nome'])) {
            $this->responder(['sucesso' => false, 'mensagem' => 'Informe o nome do treinador.'], 400);
        }

        $usuario = new Usuario(null, $dados['nome']);
        $usuario->save();

        $this->responder(['sucesso' => true, 'mensagem' => 'Perfil criado!', 'id' => $usuario->getId()]);
    }
}