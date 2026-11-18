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
    private $evoluido = 0; // 0 = estágio base, 1 = já evoluiu com a Pedra de Evolução
    private $modAtaque = 1.0; // cai quando o adversário usa um golpe que reduz o ataque
    private $modDefesa = 1.0; // cai quando o adversário usa um golpe que reduz a defesa
    private $pdo;

    public function __construct($id = null, $usuarioId = null, $nome = '', $tipo = '', $nivel = 5, $hpMaximo = 1, $hpAtual = 1, $ataque = 1, $defesa = 1, $velocidade = 1, $prioridade = 0, $statusCondicao = 'Normal', $evoluido = 0) {
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
        $this->evoluido = (int)$evoluido;
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

    public function getEvoluido() {
        return (int)$this->evoluido;
    }

    public function setEvoluido($evoluido) {
        $this->evoluido = (int)$evoluido;
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

    // Ataque e defesa usados na batalha
    // Se estiver com status negativo (Queimado ou Envenenado), o ataque cai pela metade (50%)
    public function getAtaqueAtual() {
        $atk = $this->ataque * $this->modAtaque;

        if ($this->statusCondicao == 'Queimado' || $this->statusCondicao == 'Envenenado') {
            $atk = $atk * 0.5;
        }

        return max(1, (int)round($atk));
    }

    public function getDefesaAtual() {
        return max(1, (int)round($this->defesa * $this->modDefesa));
    }

    public function setModificadores($modAtaque, $modDefesa) {
        $this->modAtaque = $modAtaque;
        $this->modDefesa = $modDefesa;
    }

    // Cada golpe tira 20% (no máximo até 40% do valor original)
    public function reduzirAtaque() {
        $this->modAtaque = max(0.4, $this->modAtaque - 0.2);
    }

    public function reduzirDefesa() {
        $this->modDefesa = max(0.4, $this->modDefesa - 0.2);
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

    // Evolui o Pokémon usando a Pedra de Evolução
    // Regra: só pode evoluir uma única vez. Ganha bônus de atributos (+30% vida e ataque).
    public function evoluir() {
        if ($this->evoluido == 1) {
            throw new Exception($this->nome . ' já atingiu sua evolução e não pode evoluir novamente!');
        }

        $this->evoluido = 1;
        $this->nivel += 5; // sobe 5 níveis ao evoluir
        $this->hpMaximo = (int)round($this->hpMaximo * 1.3);
        $this->hpAtual = $this->hpMaximo; // restaura a vida cheia ao evoluir
        $this->ataque = (int)round($this->ataque * 1.3);
        $this->defesa = (int)round($this->defesa * 1.2);
        $this->velocidade = (int)round($this->velocidade * 1.2);

        // Se o nome ainda não tiver o indicador, adiciona para ficar claro
        if (strpos($this->nome, ' (Evoluído)') === false) {
            $this->nome = $this->nome . ' (Evoluído)';
        }

        return true;
    }

    public function save() {
        if ($this->id) {
            // Update existing pokemon in the database
            $sql = "UPDATE pokemon SET nome = :nome, tipo = :tipo, nivel = :nivel, hp_maximo = :hp_maximo,
                    hp_atual = :hp_atual, ataque = :ataque, defesa = :defesa, velocidade = :velocidade,
                    prioridade = :prioridade, status_condicao = :status_condicao, evoluido = :evoluido
                    WHERE id = :id AND usuario_id = :usuario_id";
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
                ':evoluido' => $this->evoluido,
                ':id' => $this->id,
                ':usuario_id' => $this->usuarioId
            ]);
        } else {
            // Insert new pokemon into the database
            $sql = "INSERT INTO pokemon (usuario_id, nome, tipo, nivel, hp_maximo, hp_atual, ataque, defesa, velocidade, prioridade, status_condicao, evoluido)
                    VALUES (:usuario_id, :nome, :tipo, :nivel, :hp_maximo, :hp_atual, :ataque, :defesa, :velocidade, :prioridade, :status_condicao, :evoluido)";
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
                ':status_condicao' => $this->statusCondicao,
                ':evoluido' => $this->evoluido
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
            $this->evoluido = (int)($dados['evoluido'] ?? 0);
            return true;
        }
        return false;
    }

    public function delete() {
        if (!$this->id) return false;
        $stmt = $this->pdo->prepare("DELETE FROM pokemon WHERE id = :id");
        return $stmt->execute([':id' => $this->id]);
    }

    // Os dados do pokémon podem vir em português (banco) ou em inglês (JavaScript/PokéAPI).
    // Aqui tudo fica no mesmo formato: nome, hpMaximo, hpAtual, ataque, defesa...
    public static function normalizar(array $dados) {
        $hpMaximo = (int)($dados['hpMaximo'] ?? $dados['hp'] ?? 35);

        $dados['nome'] = ucfirst($dados['nome'] ?? $dados['name'] ?? '');
        $dados['tipo'] = $dados['tipo'] ?? 'normal';
        $dados['nivel'] = (int)($dados['nivel'] ?? 5);
        $dados['hpMaximo'] = $hpMaximo;
        $dados['hpAtual'] = (int)($dados['hpAtual'] ?? $hpMaximo);
        $dados['ataque'] = (int)($dados['ataque'] ?? $dados['attack'] ?? 40);
        $dados['defesa'] = (int)($dados['defesa'] ?? $dados['defense'] ?? 40);
        $dados['velocidade'] = (int)($dados['velocidade'] ?? $dados['speed'] ?? 50);
        $dados['prioridade'] = (int)($dados['prioridade'] ?? 0);
        $dados['statusCondicao'] = $dados['statusCondicao'] ?? $dados['status_condicao'] ?? 'Normal';
        $dados['evoluido'] = (int)($dados['evoluido'] ?? 0);
        $dados['modAtaque'] = (float)($dados['modAtaque'] ?? 1);
        $dados['modDefesa'] = (float)($dados['modDefesa'] ?? 1);
        return $dados;
    }

    // Cria o objeto Pokemon a partir dos dados guardados na sessão
    public static function fromArray(array $dados) {
        $dados = self::normalizar($dados);
        $pokemon = new Pokemon($dados['pokemon_db_id'] ?? null, $_SESSION['usuario_id'] ?? null,
            $dados['nome'], $dados['tipo'], $dados['nivel'], $dados['hpMaximo'], $dados['hpAtual'],
            $dados['ataque'], $dados['defesa'], $dados['velocidade'], $dados['prioridade'], $dados['statusCondicao'], $dados['evoluido']);
        $pokemon->setModificadores($dados['modAtaque'], $dados['modDefesa']);
        return $pokemon;
    }

    // O que muda durante a batalha, para guardar de volta na sessão
    public function paraArray() {
        return [
            'nome' => $this->nome,
            'nivel' => $this->nivel,
            'hpMaximo' => $this->hpMaximo,
            'hpAtual' => $this->hpAtual,
            'ataque' => $this->ataque,
            'defesa' => $this->defesa,
            'velocidade' => $this->velocidade,
            'statusCondicao' => $this->statusCondicao,
            'evoluido' => $this->evoluido,
            'modAtaque' => $this->modAtaque,
            'modDefesa' => $this->modDefesa
        ];
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
