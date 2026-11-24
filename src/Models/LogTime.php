<?php

/**
 * Modelo LogTime
 * Representa o histórico permanente de cada escalação montada pelos treinadores.
 */
class LogTime {
    private $id;
    private $usuarioId;
    private $pokemonId;
    private $posicao;
    private $dataCriacao;
    private $pdo;

    public function __construct($id = null, $usuarioId = null, $pokemonId = null, $posicao = 1, $dataCriacao = null) {
        $this->id = $id;
        $this->usuarioId = $usuarioId;
        $this->pokemonId = $pokemonId;
        $this->posicao = $posicao;
        $this->dataCriacao = $dataCriacao;
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

    public function getPokemonId() {
        return $this->pokemonId;
    }

    public function getPosicao() {
        return $this->posicao;
    }

    public function getDataCriacao() {
        return $this->dataCriacao;
    }

    public function save() {
        if (!$this->pdo) {
            return false;
        }

        $sql = "INSERT INTO log_time (usuario_id, pokemon_id, posicao) VALUES (:usuario_id, :pokemon_id, :posicao)";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':usuario_id' => $this->usuarioId,
            ':pokemon_id' => $this->pokemonId,
            ':posicao'    => $this->posicao
        ]);

        if ($ok) {
            $this->id = $this->pdo->lastInsertId();
        }
        return $ok;
    }

    public static function porUsuario(PDO $pdo, $usuarioId) {
        $stmt = $pdo->prepare("
            SELECT lt.*, p.nome AS pokemon, p.tipo AS tipo
            FROM log_time lt
            INNER JOIN pokemon p ON p.id = lt.pokemon_id
            WHERE lt.usuario_id = :usuario_id
            ORDER BY lt.id DESC
        ");
        $stmt->execute([':usuario_id' => $usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
