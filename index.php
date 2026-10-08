<?php

require_once __DIR__ . '/Autoload.php';

// Roteador simples da aplicação
$rota = $_GET['rota'] ?? 'builder';

switch ($rota) {
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
    case 'builder':
    case 'time':
        require_once __DIR__ . '/src/Views/time/builder.php';
        break;

    case 'batalha':
        require_once __DIR__ . '/src/Views/batalha/arena.php';
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
