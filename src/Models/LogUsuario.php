<?php

/**
 * Modelo LogUsuario
 * Registra o histórico de atividades e acessos dos usuários (Login, Logout, Cadastro).
 * Atende ao requisito do projeto: "Criar tabelas de logs de batalhas, perfis ou usuários e loja"
 */
class LogUsuario {
    private $id;
    private $usuarioId;
    private $nomeUsuario;
    private $acao;
    private $dataRegistro;
    private $pdo;

    public function __construct($id = null, $usuarioId = null, $nomeUsuario = '', $acao = 'Login', $dataRegistro = null) {
        $this->id = $id;
        $this->usuarioId = $usuarioId;
        $this->nomeUsuario = $nomeUsuario;
        $this->acao = $acao;
        $this->dataRegistro = $dataRegistro;
        try {
            $this->pdo = Conexao::getConnection();
        } catch (Throwable $e) {
            $this->pdo = null;
        }
    }

    public function getId() {
        return $this->id;
    }

    public function getUsuarioId() {
        return $this->usuarioId;
    }

    public function getNomeUsuario() {
        return $this->nomeUsuario;
    }

    public function getAcao() {
        return $this->acao;
    }

    public function getDataRegistro() {
        return $this->dataRegistro;
    }

    public function save() {
        if (!$this->pdo) {
            return false;
        }

        $sql = "INSERT INTO log_usuario (usuario_id, nome_usuario, acao) 
                VALUES (:usuario_id, :nome_usuario, :acao)";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':usuario_id'   => $this->usuarioId,
            ':nome_usuario' => $this->nomeUsuario,
            ':acao'         => $this->acao
        ]);

        if ($ok) {
            $this->id = $this->pdo->lastInsertId();
        }
        return $ok;
    }

    public static function registrar($usuarioId, $nomeUsuario, $acao = 'Login') {
        try {
            $log = new self(null, $usuarioId, $nomeUsuario, $acao);
            return $log->save();
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function listarRecentes(PDO $pdo, $limite = 100) {
        $stmt = $pdo->prepare("
            SELECT * FROM log_usuario 
            ORDER BY id DESC 
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
