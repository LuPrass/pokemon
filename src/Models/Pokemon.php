<?php

class Pokemon {
    private $id;
    private $usuarioId;
    private $nome;
    private $tipo;
    private $nivel;
    private $hpMaximo;
    private $hpAtual;
    private $ataque;
    private $defesa;
    private $velocidade;
    private $prioridade;
    private $statusCondicao;
    private $pdo;

    public function __construct($id = null, $usuarioId = null, $nome = '', $tipo = '', $nivel = 5, $hpMaximo = 1, $hpAtual = 1, $ataque = 1, $defesa = 1, $velocidade = 1, $prioridade = 0, $statusCondicao = 'Normal') {
        $this->id = $id;
        $this->usuarioId = $usuarioId;
        $this->nome = $nome;
        $this->tipo = $tipo;
        $this->nivel = $nivel;
        $this->hpMaximo = $hpMaximo;
        $this->hpAtual = $hpAtual;
        $this->ataque = $ataque;
        $this->defesa = $defesa;
        $this->velocidade = $velocidade;
        $this->prioridade = $prioridade;
        $this->statusCondicao = $statusCondicao;
        $this->pdo = Conexao::getConnection();
    }

    // Getters and setters for the properties
    public function getId() {
        return $this->id;
    }

    public function getUsuarioId() {
        return $this->usuarioId;
    }

    public function getNome() {
        return $this->nome;
    }

    public function getTipo() {
        return $this->tipo;
    }

    public function getNivel() {
        return $this->nivel;
    }

    public function getHpMaximo() {
        return $this->hpMaximo;
    }

    public function getHpAtual() {
        return $this->hpAtual;
    }

    public function getAtaque() {
        return $this->ataque;
    }

    public function getDefesa() {
        return $this->defesa;
    }

    public function getVelocidade() {
        return $this->velocidade;
    }

    public function getPrioridade() {
        return $this->prioridade;
    }

    public function getStatusCondicao() {
        return $this->statusCondicao;
    }

    public function setUsuarioId($usuarioId) {
        $this->usuarioId = $usuarioId;
    }

    public function setNome($nome) {
        $this->nome = $nome;
    }

    public function setHpAtual($hpAtual) {
        $this->hpAtual = $hpAtual;
    }

    public function setStatusCondicao($statusCondicao) {
        $this->statusCondicao = $statusCondicao;
    }

    // Regras do pokémon
    public function estaDesmaiado() {
        return $this->hpAtual <= 0;
    }

    public function receberDano($dano) {
        $this->hpAtual = $this->hpAtual - $dano;
        if ($this->hpAtual < 0) {
            $this->hpAtual = 0;
        }
    }

    public function curar($quantidade) {
        $this->hpAtual = $this->hpAtual + $quantidade;
        if ($this->hpAtual > $this->hpMaximo) {
            $this->hpAtual = $this->hpMaximo;
        }
    }

    public function save() {
        if ($this->id) {
            // Update existing pokemon in the database
            $sql = "UPDATE pokemon SET nome = :nome, tipo = :tipo, nivel = :nivel, hp_maximo = :hp_maximo,
                    hp_atual = :hp_atual, ataque = :ataque, defesa = :defesa, velocidade = :velocidade,
                    prioridade = :prioridade, status_condicao = :status_condicao WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':nome' => $this->nome,
                ':tipo' => $this->tipo,
                ':nivel' => $this->nivel,
                ':hp_maximo' => $this->hpMaximo,
                ':hp_atual' => $this->hpAtual,
                ':ataque' => $this->ataque,
                ':defesa' => $this->defesa,
                ':velocidade' => $this->velocidade,
                ':prioridade' => $this->prioridade,
                ':status_condicao' => $this->statusCondicao,
                ':id' => $this->id
            ]);
        } else {
            // Insert new pokemon into the database
            $sql = "INSERT INTO pokemon (usuario_id, nome, tipo, nivel, hp_maximo, hp_atual, ataque, defesa, velocidade, prioridade, status_condicao)
                    VALUES (:usuario_id, :nome, :tipo, :nivel, :hp_maximo, :hp_atual, :ataque, :defesa, :velocidade, :prioridade, :status_condicao)";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([
                ':usuario_id' => $this->usuarioId,
                ':nome' => $this->nome,
                ':tipo' => $this->tipo,
                ':nivel' => $this->nivel,
                ':hp_maximo' => $this->hpMaximo,
                ':hp_atual' => $this->hpAtual,
                ':ataque' => $this->ataque,
                ':defesa' => $this->defesa,
                ':velocidade' => $this->velocidade,
                ':prioridade' => $this->prioridade,
                ':status_condicao' => $this->statusCondicao
            ]);

            if ($ok) {
                $this->id = $this->pdo->lastInsertId();
            }
            return $ok;
        }
    }

    public function load($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM pokemon WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($dados = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $dados['id'];
            $this->usuarioId = $dados['usuario_id'];
            $this->nome = $dados['nome'];
            $this->tipo = $dados['tipo'];
            $this->nivel = $dados['nivel'];
            $this->hpMaximo = $dados['hp_maximo'];
            $this->hpAtual = $dados['hp_atual'];
            $this->ataque = $dados['ataque'];
            $this->defesa = $dados['defesa'];
            $this->velocidade = $dados['velocidade'];
            $this->prioridade = $dados['prioridade'];
            $this->statusCondicao = $dados['status_condicao'];
            return true;
        }
        return false;
    }

    public function delete() {
        if (!$this->id) return false;
        $stmt = $this->pdo->prepare("DELETE FROM pokemon WHERE id = :id");
        return $stmt->execute([':id' => $this->id]);
    }

    public static function all(PDO $pdo) {
        $stmt = $pdo->query("SELECT * FROM pokemon");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lista os pokémons de um usuário
    public static function porUsuario(PDO $pdo, $usuarioId) {
        $stmt = $pdo->prepare("SELECT * FROM pokemon WHERE usuario_id = :usuario_id");
        $stmt->execute([':usuario_id' => $usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
