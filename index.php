<?php

require_once __DIR__ . '/Autoload.php';

// Roteador simples da aplicação
$rota = $_GET['rota'] ?? 'builder';

switch ($rota) {
    // API Endpoints
    case 'salvar-time':
        (new TimeController())->salvarTime();
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
