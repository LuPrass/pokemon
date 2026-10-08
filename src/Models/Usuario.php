<?php

class Usuario {
    private $id;
    private $nome;
    private $dinheiro;
    private $dataCriacao;
    private $pdo;

    public function __construct($id = null, $nome = '', $dinheiro = 1000, $dataCriacao = null) {
        $this->id = $id;
        $this->nome = $nome;
        $this->dinheiro = $dinheiro;
        $this->dataCriacao = $dataCriacao;
        $this->pdo = Conexao::getConnection();
    }

    // Getters and setters for the properties
    public function getId() {
        return $this->id;
    }

    public function getNome() {
        return $this->nome;
    }

    public function getDinheiro() {
        return $this->dinheiro;
    }

    public function getDataCriacao() {
        return $this->dataCriacao;
    }

    public function setNome($nome) {
        $this->nome = $nome;
    }

    public function setDinheiro($dinheiro) {
        $this->dinheiro = $dinheiro;
    }

    public function setDataCriacao($dataCriacao) {
        $this->dataCriacao = $dataCriacao;
    }

    // Desconta dinheiro do usuário (usado na loja)
    public function gastar($valor) {
        if ($this->dinheiro < $valor) {
            throw new SaldoInsuficienteException();
        }
        $this->dinheiro = $this->dinheiro - $valor;
    }

    public function save() {
        if ($this->id) {
            // Update existing user in the database
            $sql = "UPDATE usuario SET nome = :nome, dinheiro = :dinheiro WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':nome' => $this->nome,
                ':dinheiro' => $this->dinheiro,
                ':id' => $this->id
            ]);
        } else {
            // Insert new user into the database
            $sql = "INSERT INTO usuario (nome, dinheiro) VALUES (:nome, :dinheiro)";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([
                ':nome' => $this->nome,
                ':dinheiro' => $this->dinheiro
            ]);

            if ($ok) {
                $this->id = $this->pdo->lastInsertId();
            }
            return $ok;
        }
    }

    public function load($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM usuario WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($dados = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $dados['id'];
            $this->nome = $dados['nome'];
            $this->dinheiro = $dados['dinheiro'];
            $this->dataCriacao = $dados['data_criacao'];
            return true;
        }
        return false;
    }

    public function delete() {
        if (!$this->id) return false;
        $stmt = $this->pdo->prepare("DELETE FROM usuario WHERE id = :id");
        return $stmt->execute([':id' => $this->id]);
    }

    public static function all(PDO $pdo) {
        $stmt = $pdo->query("SELECT * FROM usuario");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}