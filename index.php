<?php
require_once __DIR__ . '/Autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rota = $_GET['rota'] ?? (isset($_SESSION['logado']) && $_SESSION['logado'] === true ? 'menu' : 'login');

// Mapa de rotas (Views e Ações)
$rotas = [
    'login'             => ['type' => 'view', 'path' => '/login.php'],
    'logout'            => ['type' => 'view', 'path' => '/logout.php'],
    'menu'              => ['type' => 'view', 'path' => '/src/Views/menu/menu.php'],
    'pokemons'          => ['type' => 'view', 'path' => '/src/Views/pokemons/pokemons.php'],
    'builder'           => ['type' => 'view', 'path' => '/src/Views/time/builder.php'],
    'time'              => ['type' => 'view', 'path' => '/src/Views/time/builder.php'],
    'batalha'           => ['type' => 'view', 'path' => '/src/Views/batalha/arena.php'],
    'combate'           => ['type' => 'view', 'path' => '/src/Views/batalha/combate.php'],
    'loja'              => ['type' => 'view', 'path' => '/src/Views/loja/mart.php'],
    'centro'            => ['type' => 'view', 'path' => '/src/Views/centro_pokemon/centro.php'],
    'logs'              => ['type' => 'view', 'path' => '/src/Views/logs/historico.php'],
    
    // Controladores
    'salvar-time'       => ['type' => 'action', 'class' => TimeController::class, 'method' => 'salvarTime'],
    'usuarios'          => ['type' => 'action', 'class' => UsuarioController::class, 'method' => 'listar'],
    'comprar'           => ['type' => 'action', 'class' => LojaController::class, 'method' => 'comprar'],
    'lutar'             => ['type' => 'action', 'class' => BatalhaController::class, 'method' => 'lutar'],
    'curar'             => ['type' => 'action', 'class' => CentroPokemonController::class, 'method' => 'curarTodos'],
];

// Executa a rota se existir
if (isset($rotas[$rota])) {
    $r = $rotas[$rota];
    if ($r['type'] === 'view') {
        require_once __DIR__ . $r['path'];
    } else {
        $controller = new $r['class']();
        $param = $_GET['id'] ?? $_GET['usuario_id'] ?? null;
        if ($param !== null) {
            $controller->{$r['method']}($param);
        } else {
            $controller->{$r['method']}();
        }
    }
} else {
    require_once __DIR__ . '/src/Views/time/builder.php';
}