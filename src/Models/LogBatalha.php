<?php

class LogBatalha {
    private $id;
    private $usuarioId;
    private $pokemonId;
    private $adversario;
    private $resultado;
    private $dinheiroGanho;
    private $dataBatalha;
    private $pdo;

    public function __construct($id = null, $usuarioId = null, $pokemonId = null, $adversario = '', $resultado = '', $dinheiroGanho = 0, $dataBatalha = null) {
        $this->id = $id;
        $this->usuarioId = $usuarioId;
        $this->pokemonId = $pokemonId;
        $this->adversario = $adversario;
        $this->resultado = $resultado;
        $this->dinheiroGanho = $dinheiroGanho;
        $this->dataBatalha = $dataBatalha;
        $this->pdo = Conexao::getConnection();
    }

    // Getters for the properties (o log não é alterado depois de criado)
    public function getId() {
        return $this->id;
    }

    public function getUsuarioId() {
        return $this->usuarioId;
    }

    public function getPokemonId() {
        return $this->pokemonId;
    }

    public function getAdversario() {
        return $this->adversario;
    }

    public function getResultado() {
        return $this->resultado;
    }

    public function getDinheiroGanho() {
        return $this->dinheiroGanho;
    }

    public function getDataBatalha() {
        return $this->dataBatalha;
    }

    // O banco (trigger) soma o dinheiro ao usuário quando o resultado é 'vitoria'
    public function save() {
        $sql = "INSERT INTO log_batalha (usuario_id, pokemon_id, adversario, resultado, dinheiro_ganho)
                VALUES (:usuario_id, :pokemon_id, :adversario, :resultado, :dinheiro_ganho)";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':usuario_id' => $this->usuarioId,
            ':pokemon_id' => $this->pokemonId,
            ':adversario' => $this->adversario,
            ':resultado' => $this->resultado,
            ':dinheiro_ganho' => $this->dinheiroGanho
        ]);

        if ($ok) {
            $this->id = $this->pdo->lastInsertId();
        }
        return $ok;
    }

    public function load($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM log_batalha WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($dados = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $dados['id'];
            $this->usuarioId = $dados['usuario_id'];
            $this->pokemonId = $dados['pokemon_id'];
            $this->adversario = $dados['adversario'];
            $this->resultado = $dados['resultado'];
            $this->dinheiroGanho = $dados['dinheiro_ganho'];
            $this->dataBatalha = $dados['data_batalha'];
            return true;
        }
        return false;
    }

    public static function all(PDO $pdo) {
        $stmt = $pdo->query("SELECT * FROM log_batalha");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Histórico de batalhas de um usuário (mais recentes primeiro)
    public static function porUsuario(PDO $pdo, $usuarioId) {
        $stmt = $pdo->prepare("SELECT * FROM log_batalha WHERE usuario_id = :usuario_id ORDER BY id DESC");
        $stmt->execute([':usuario_id' => $usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
