<?php

class CentroPokemonController extends Controller {

    // Cura todos os pokémons do usuário (HP cheio e status Normal), de graça.
    // Recebe JSON: { "usuario_id": 1 }
    public function curarTodos() {
        $dados = $this->lerJson();
        $pdo = Conexao::getConnection();

        if (empty($dados['usuario_id'])) {
            $this->responder(['sucesso' => false, 'mensagem' => 'Usuário não informado.'], 400);
        }

        $stmt = $pdo->prepare("UPDATE pokemon SET hp_atual = hp_maximo, status_condicao = 'Normal' WHERE usuario_id = :usuario_id");
        $stmt->execute([':usuario_id' => $dados['usuario_id']]);

        $this->responder(['sucesso' => true, 'mensagem' => 'Seus pokémons foram curados!']);
    }
}
