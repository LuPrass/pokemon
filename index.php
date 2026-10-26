<?php

require_once __DIR__ . '/Autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Roteador simples da aplicação
$rota = $_GET['rota'] ?? (isset($_SESSION['logado']) && $_SESSION['logado'] === true ? 'menu' : 'login');

switch ($rota) {
    // Autenticação
    case 'login':
        require_once __DIR__ . '/login.php';
        break;

    case 'logout':
        require_once __DIR__ . '/logout.php';
        break;

    // API Endpoints
    case 'salvar-time':
        (new TimeController())->salvarTime();
        break;

    case 'usuarios':
        (new UsuarioController())->listar();
        break;

    case 'usuario':
        (new UsuarioController())->buscar($_GET['id'] ?? 0);
        break;

    case 'criar-usuario':
        (new UsuarioController())->criar();
        break;

    case 'loja-itens':
        (new LojaController())->listarItens();
        break;

    case 'comprar':
        (new LojaController())->comprar();
        break;

    case 'historico-loja':
        (new LojaController())->historico($_GET['usuario_id'] ?? 0);
        break;

    case 'lutar':
        (new BatalhaController())->lutar();
        break;

    case 'historico-batalha':
        (new BatalhaController())->historico($_GET['usuario_id'] ?? 0);
        break;

    case 'curar':
        (new CentroPokemonController())->curarTodos();
        break;

    // Views
    case 'menu':
        require_once __DIR__ . '/src/Views/menu/menu.php';
        break;

    case 'pokemons':
        require_once __DIR__ . '/src/Views/pokemons/pokemons.php';
        break;

    case 'builder':
    case 'time':
        require_once __DIR__ . '/src/Views/time/builder.php';
        break;

    case 'batalha':
        require_once __DIR__ . '/src/Views/batalha/arena.php';
        break;

    case 'combate':
        require_once __DIR__ . '/src/Views/batalha/combate.php';
        break;

    case 'loja':
        require_once __DIR__ . '/src/Views/loja/mart.php';
        break;

    case 'centro':
        require_once __DIR__ . '/src/Views/centro_pokemon/centro.php';
        break;

    case 'logs':
        require_once __DIR__ . '/src/Views/logs/historico.php';
        break;

    default:
        require_once __DIR__ . '/src/Views/time/builder.php';
        break;
}
