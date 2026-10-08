<?php

class Item {
    private $id;
    private $nome;
    private $descricao;
    private $preco;
    private $tipo;
    private $pdo;

    public function __construct($id = null, $nome = '', $descricao = '', $preco = 0, $tipo = '') {
        $this->id = $id;
        $this->nome = $nome;
        $this->descricao = $descricao;
        $this->preco = $preco;
        $this->tipo = $tipo;
        $this->pdo = Conexao::getConnection();
    }

    // Getters and setters for the properties
    public function getId() {
        return $this->id;
    }

    public function getNome() {
        return $this->nome;
    }

    public function getDescricao() {
        return $this->descricao;
    }

    public function getPreco() {
        return $this->preco;
    }

    public function getTipo() {
        return $this->tipo;
    }

    public function setNome($nome) {
        $this->nome = $nome;
    }

    public function setDescricao($descricao) {
        $this->descricao = $descricao;
    }

    public function setPreco($preco) {
        $this->preco = $preco;
    }

    public function setTipo($tipo) {
        $this->tipo = $tipo;
    }

    public function save() {
        if ($this->id) {
            // Update existing item in the database
            $sql = "UPDATE loja SET nome = :nome, descricao = :descricao, preco = :preco, tipo = :tipo WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':nome' => $this->nome,
                ':descricao' => $this->descricao,
                ':preco' => $this->preco,
                ':tipo' => $this->tipo,
                ':id' => $this->id
            ]);
        } else {
            // Insert new item into the database
            $sql = "INSERT INTO loja (nome, descricao, preco, tipo) VALUES (:nome, :descricao, :preco, :tipo)";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([
                ':nome' => $this->nome,
                ':descricao' => $this->descricao,
                ':preco' => $this->preco,
                ':tipo' => $this->tipo
            ]);

            if ($ok) {
                $this->id = $this->pdo->lastInsertId();
            }
            return $ok;
        }
    }

    public function load($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM loja WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($dados = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $dados['id'];
            $this->nome = $dados['nome'];
            $this->descricao = $dados['descricao'];
            $this->preco = $dados['preco'];
            $this->tipo = $dados['tipo'];
            return true;
        }
        return false;
    }

    public function delete() {
        if (!$this->id) return false;
        $stmt = $this->pdo->prepare("DELETE FROM loja WHERE id = :id");
        return $stmt->execute([':id' => $this->id]);
    }

    public static function all(PDO $pdo) {
        $stmt = $pdo->query("SELECT * FROM loja");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
