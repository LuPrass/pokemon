<?php

// Classe base com o que todo controller precisa: responder e ler JSON
class Controller {

    // Envia a resposta em JSON para o navegador e encerra
    protected function responder($dados, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados);
        exit;
    }

    // Lê o JSON enviado pelo JavaScript (fetch com POST)
    protected function lerJson() {
        $dados = json_decode(file_get_contents('php://input'), true);
        if (!$dados) {
            return [];
        }
        return $dados;
    }
}
