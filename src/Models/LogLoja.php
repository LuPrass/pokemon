<?php

class LogLoja {
    private $id;
    private $usuarioId;
    private $itemId;
    private $pokemonId;
    private $precoPago;
    private $dataCompra;
    private $pdo;

    public function __construct($id = null, $usuarioId = null, $itemId = null, $pokemonId = null, $precoPago = 0, $dataCompra = null) {
        $this->id = $id;
        $this->usuarioId = $usuarioId;
        $this->itemId = $itemId;
        $this->pokemonId = $pokemonId;
        $this->precoPago = $precoPago;
        $this->dataCompra = $dataCompra;
        $this->pdo = Conexao::getConnection();
    }

    // Getters for the properties (o log não é alterado depois de criado)
    public function getId() {
        return $this->id;
    }

    public function getUsuarioId() {
        return $this->usuarioId;
    }

    public function getItemId() {
        return $this->itemId;
    }

    public function getPokemonId() {
        return $this->pokemonId;
    }

    public function getPrecoPago() {
        return $this->precoPago;
    }

    public function getDataCompra() {
        return $this->dataCompra;
    }

    public function save() {
        $sql = "INSERT INTO log_loja (usuario_id, item_id, pokemon_id, preco_pago)
                VALUES (:usuario_id, :item_id, :pokemon_id, :preco_pago)";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':usuario_id' => $this->usuarioId,
            ':item_id' => $this->itemId,
            ':pokemon_id' => $this->pokemonId,
            ':preco_pago' => $this->precoPago
        ]);

        if ($ok) {
            $this->id = $this->pdo->lastInsertId();
        }
        return $ok;
    }

    public function load($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM log_loja WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($dados = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $dados['id'];
            $this->usuarioId = $dados['usuario_id'];
            $this->itemId = $dados['item_id'];
            $this->pokemonId = $dados['pokemon_id'];
            $this->precoPago = $dados['preco_pago'];
            $this->dataCompra = $dados['data_compra'];
            return true;
        }
        return false;
    }

    public static function all(PDO $pdo) {
        $stmt = $pdo->query("SELECT * FROM log_loja");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Histórico de compras de um usuário (mais recentes primeiro)
    public static function porUsuario(PDO $pdo, $usuarioId) {
        $sql = "SELECT l.*, i.nome AS item FROM log_loja l
                INNER JOIN loja i ON i.id = l.item_id
                WHERE l.usuario_id = :usuario_id ORDER BY l.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':usuario_id' => $usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
