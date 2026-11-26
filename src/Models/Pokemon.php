<?php

class Pokemon {
    // Requisito: Pokémon herda (trait) os tipos elementais
    use TipoFogoTrait, TipoAguaTrait, TipoPlantaTrait, TipoTerraTrait, TipoEletricoTrait;

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
    private $gif = ''; // Armazena a URL do GIF animado da PokéAPI
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
        try {
            $this->pdo = Conexao::getConnection();
        } catch (Throwable $e) {
            $this->pdo = null;
        }
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

    public function getGif() {
        return $this->gif;
    }

    public function setGif($gif) {
        $this->gif = (string)$gif;
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

    // =========================================================================
    // Métodos que usam as Traits herdadas (Requisito: "herdar (trait) os tipos")
    // =========================================================================

    /**
     * Calcula o multiplicador estático entre dois tipos quaisquer usando as Traits herdadas
     */
    public static function calcularMultiplicadorTipos($tipoAtacante, $tipoAlvo) {
        $atacante = self::normalizarTipoParaTrait($tipoAtacante);
        $alvo = self::normalizarTipoParaTrait($tipoAlvo);

        switch ($atacante) {
            case 'fogo':     return self::multiplicadorFogo($alvo);
            case 'agua':     return self::multiplicadorAgua($alvo);
            case 'planta':   return self::multiplicadorPlanta($alvo);
            case 'terra':    return self::multiplicadorTerra($alvo);
            case 'eletrico': return self::multiplicadorEletrico($alvo);
            default:         return 1.0;
        }
    }

    /**
     * Retorna a condição de status de um tipo qualquer usando as Traits herdadas
     */
    public static function obterStatusPorTipo($tipo) {
        $t = self::normalizarTipoParaTrait($tipo);

        switch ($t) {
            case 'fogo':     return self::statusFogo();
            case 'agua':     return self::statusAgua();
            case 'planta':   return self::statusPlanta();
            case 'terra':    return self::statusTerra();
            case 'eletrico': return self::statusEletrico();
            default:         return 'Normal';
        }
    }

    /**
     * Calcula o multiplicador de dano deste Pokémon contra um tipo alvo usando as Traits herdadas
     */
    public function calcularVantagemContra($tipoAlvo) {
        return self::calcularMultiplicadorTipos($this->tipo, $tipoAlvo);
    }

    /**
     * Retorna a condição de status que o tipo deste Pokémon pode infligir usando as Traits herdadas
     */
    public function obterStatusDoTipo() {
        return self::obterStatusPorTipo($this->tipo);
    }

    private static function normalizarTipoParaTrait($tipo) {
        $t = strtolower(trim((string)$tipo));
        $mapa = [
            'água'     => 'agua',
            'water'    => 'agua',
            'agua'     => 'agua',
            'fire'     => 'fogo',
            'fogo'     => 'fogo',
            'grass'    => 'planta',
            'planta'   => 'planta',
            'electric' => 'eletrico',
            'elétrico' => 'eletrico',
            'eletrico' => 'eletrico',
            'ground'   => 'terra',
            'terra'    => 'terra'
        ];
        if (isset($mapa[$t])) {
            return $mapa[$t];
        }
        return $t;
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

    // Sobrecarga de método (Requisito POO): cura valor específico ou 100% do HP se omitido
    public function curar($quantidade = null) {
        if ($quantidade === null) {
            // Sem parâmetro: restaura 100% do HP
            $this->hpAtual = $this->hpMaximo;
        } else {
            // Com parâmetro: restaura a quantidade informada
            $this->hpAtual = $this->hpAtual + (int)$quantidade;
            if ($this->hpAtual > $this->hpMaximo) {
                $this->hpAtual = $this->hpMaximo;
            }
        }
    }

    // Cada vitória em batalha faz o Pokémon subir 1 nível
    public function subirNivel() {
        $this->nivel = $this->nivel + 1;
    }

    // Evolui o Pokémon usando a Pedra de Evolução e a PokéAPI
    // Regra: só pode evoluir uma única vez.
    public function evoluir($idPokedex = null) {
        if ($this->evoluido == 1) {
            throw new Exception($this->nome . ' já atingiu sua evolução e não pode evoluir novamente!');
        }

        // 1. Descobre o ID da evolução na PokéAPI através do mapeamento
        $idEvolucao = PokeApiService::obterIdEvolucao($idPokedex ?? $this->id);

        if ($idEvolucao) {
            $api = new PokeApiService();
            $dadosEvo = $api->buscarPokemon($idEvolucao);

            if ($dadosEvo) {
                // Atualiza o nome oficial, GIF e atributos vindos diretamente da PokéAPI!
                $this->nome = ucfirst($dadosEvo['nome']);
                $this->gif = $dadosEvo['imagem'];
                $this->hpMaximo = (int)$dadosEvo['hp'];
                $this->hpAtual = (int)$dadosEvo['hp']; // restaura a vida cheia ao evoluir
                $this->ataque = (int)$dadosEvo['ataque'];
                $this->defesa = (int)$dadosEvo['defesa'];
                $this->velocidade = (int)$dadosEvo['velocidade'];
                $this->nivel += 5; // sobe 5 níveis ao evoluir
                $this->evoluido = 1;

                return true;
            }
        }

        // Fallback clássico caso a PokéAPI esteja sem conexão
        $this->evoluido = 1;
        $this->nivel += 5;
        $this->hpMaximo = (int)round($this->hpMaximo * 1.3);
        $this->hpAtual = $this->hpMaximo;
        $this->ataque = (int)round($this->ataque * 1.3);
        $this->defesa = (int)round($this->defesa * 1.2);
        $this->velocidade = (int)round($this->velocidade * 1.2);

        if ($idEvolucao && empty($this->gif)) {
            $this->gif = "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/showdown/{$idEvolucao}.gif";
        }

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
            if (isset($dados['evoluido'])) {
                $this->evoluido = (int)$dados['evoluido'];
            } else {
                $this->evoluido = 0;
            }
            return true;
        }
        return false;
    }

    public function delete() {
        if (!$this->id) return false;
        $stmt = $this->pdo->prepare("DELETE FROM pokemon WHERE id = :id");
        return $stmt->execute([':id' => $this->id]);
    }

    // Padroniza os dados do Pokémon diretamente
    public static function normalizar(array $dados) {
        $dados['nome'] = ucfirst($dados['nome']);
        $dados['tipo'] = $dados['tipo'];
        $dados['nivel'] = (int)$dados['nivel'];

        // Pega o HP máximo
        if (isset($dados['hpMaximo'])) {
            $dados['hpMaximo'] = (int)$dados['hpMaximo'];
        } elseif (isset($dados['hp_maximo'])) {
            $dados['hpMaximo'] = (int)$dados['hp_maximo'];
        }

        // Pega o HP atual
        if (isset($dados['hpAtual'])) {
            $dados['hpAtual'] = (int)$dados['hpAtual'];
        } elseif (isset($dados['hp_atual'])) {
            $dados['hpAtual'] = (int)$dados['hp_atual'];
        } else {
            $dados['hpAtual'] = $dados['hpMaximo'];
        }

        $dados['ataque'] = (int)$dados['ataque'];
        $dados['defesa'] = (int)$dados['defesa'];
        $dados['velocidade'] = (int)$dados['velocidade'];

        if (isset($dados['prioridade'])) {
            $dados['prioridade'] = (int)$dados['prioridade'];
        } else {
            $dados['prioridade'] = 0;
        }

        // Condição de status
        if (!empty($dados['statusCondicao'])) {
            $dados['statusCondicao'] = ucfirst(strtolower($dados['statusCondicao']));
        } elseif (!empty($dados['status_condicao'])) {
            $dados['statusCondicao'] = ucfirst(strtolower($dados['status_condicao']));
        } else {
            $dados['statusCondicao'] = 'Normal';
        }
        $dados['status_condicao'] = $dados['statusCondicao'];

        if (isset($dados['evoluido'])) {
            $dados['evoluido'] = (int)$dados['evoluido'];
        } else {
            $dados['evoluido'] = 0;
        }

        if (isset($dados['modAtaque'])) {
            $dados['modAtaque'] = (float)$dados['modAtaque'];
        } else {
            $dados['modAtaque'] = 1.0;
        }

        if (isset($dados['modDefesa'])) {
            $dados['modDefesa'] = (float)$dados['modDefesa'];
        } else {
            $dados['modDefesa'] = 1.0;
        }

        return $dados;
    }

    // Cria o objeto Pokemon a partir dos dados guardados na sessão
    public static function fromArray(array $dados) {
        $dados = self::normalizar($dados);
        
        $pkDbId = null;
        if (isset($dados['pokemon_db_id'])) {
            $pkDbId = $dados['pokemon_db_id'];
        }

        $userId = null;
        if (isset($_SESSION['usuario_id'])) {
            $userId = $_SESSION['usuario_id'];
        }

        $pokemon = new Pokemon($pkDbId, $userId,
            $dados['nome'], $dados['tipo'], $dados['nivel'], $dados['hpMaximo'], $dados['hpAtual'],
            $dados['ataque'], $dados['defesa'], $dados['velocidade'], $dados['prioridade'], $dados['statusCondicao'], $dados['evoluido']);
        $pokemon->setModificadores($dados['modAtaque'], $dados['modDefesa']);

        if (!empty($dados['gif'])) {
            $pokemon->setGif($dados['gif']);
        } elseif (!empty($dados['img'])) {
            $pokemon->setGif($dados['img']);
        }

        return $pokemon;
    }

    // O que muda durante a batalha, para guardar de volta na sessão
    public function paraArray() {
        $status = 'Normal';
        if (!empty($this->statusCondicao)) {
            $status = $this->statusCondicao;
        }

        $dados = [
            'nome' => $this->nome,
            'nivel' => $this->nivel,
            'hpMaximo' => $this->hpMaximo,
            'hpAtual' => $this->hpAtual,
            'ataque' => $this->ataque,
            'defesa' => $this->defesa,
            'velocidade' => $this->velocidade,
            'statusCondicao' => $status,
            'status_condicao' => $status,
            'evoluido' => $this->evoluido,
            'modAtaque' => $this->modAtaque,
            'modDefesa' => $this->modDefesa
        ];

        if (!empty($this->gif)) {
            $dados['img'] = $this->gif;
            $dados['gif'] = $this->gif;
        }

        return $dados;
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
