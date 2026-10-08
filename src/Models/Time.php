<?php

class Time {
    private $id;
    private $usuarioId;
    private $pokemonId;
    private $posicao;
    private $pdo;

    public function __construct($id = null, $usuarioId = null, $pokemonId = null, $posicao = 1) {
        $this->id = $id;
        $this->usuarioId = $usuarioId;
        $this->pokemonId = $pokemonId;
        $this->posicao = $posicao;
        $this->pdo = Conexao::getConnection();
    }

    // Getters and setters for the properties
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

    public function setUsuarioId($usuarioId) {
        $this->usuarioId = $usuarioId;
    }

    public function setPokemonId($pokemonId) {
        $this->pokemonId = $pokemonId;
    }

    public function setPosicao($posicao) {
        $this->posicao = $posicao;
    }

    public function save() {
        if ($this->id) {
            // Update existing team slot in the database
            $sql = "UPDATE time SET posicao = :posicao WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':posicao' => $this->posicao,
                ':id' => $this->id
            ]);
        } else {
            // O time só pode ter 6 pokémons
            if (Time::contar($this->pdo, $this->usuarioId) >= 6) {
                throw new TimeCheioException();
            }

            // Insert new team slot into the database
            $sql = "INSERT INTO time (usuario_id, pokemon_id, posicao) VALUES (:usuario_id, :pokemon_id, :posicao)";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([
                ':usuario_id' => $this->usuarioId,
                ':pokemon_id' => $this->pokemonId,
                ':posicao' => $this->posicao
            ]);

            if ($ok) {
                $this->id = $this->pdo->lastInsertId();
            }
            return $ok;
        }
    }

    public function load($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM time WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($dados = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $dados['id'];
            $this->usuarioId = $dados['usuario_id'];
            $this->pokemonId = $dados['pokemon_id'];
            $this->posicao = $dados['posicao'];
            return true;
        }
        return false;
    }

    public function delete() {
        if (!$this->id) return false;
        $stmt = $this->pdo->prepare("DELETE FROM time WHERE id = :id");
        return $stmt->execute([':id' => $this->id]);
    }

    public static function all(PDO $pdo) {
        $stmt = $pdo->query("SELECT * FROM time");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Quantos pokémons o usuário já tem no time
    public static function contar(PDO $pdo, $usuarioId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM time WHERE usuario_id = :usuario_id");
        $stmt->execute([':usuario_id' => $usuarioId]);
        return (int) $stmt->fetchColumn();
    }

    // Time completo do usuário, já com os dados de cada pokémon
    public static function porUsuario(PDO $pdo, $usuarioId) {
        $sql = "SELECT t.id, t.posicao, p.* FROM time t
                INNER JOIN pokemon p ON p.id = t.pokemon_id
                WHERE t.usuario_id = :usuario_id ORDER BY t.posicao";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':usuario_id' => $usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
