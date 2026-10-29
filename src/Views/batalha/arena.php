<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../Services/PokemonCatalogo.php';

$meuTime = $_SESSION['meu_time'] ?? [];
$inimigosRestantes = $_SESSION['inimigos_restantes'] ?? [];
// Mapeamento visual e tradução dos tipos para a Arena
$mapaTipos = [
    'grass' => ['nome' => 'Planta', 'icone' => '🌿', 'classe' => 'grass'],
    'fire' => ['nome' => 'Fogo', 'icone' => '🔥', 'classe' => 'fire'],
    'water' => ['nome' => 'Água', 'icone' => '💧', 'classe' => 'water'],
    'electric' => ['nome' => 'Elétrico', 'icone' => '⚡', 'classe' => 'electric'],
    'normal' => ['nome' => 'Normal', 'icone' => '⚪', 'classe' => 'normal'],
    'psychic' => ['nome' => 'Psíquico', 'icone' => '🔮', 'classe' => 'psychic'],
    'ghost' => ['nome' => 'Fantasma', 'icone' => '👻', 'classe' => 'ghost']
];
//sortear 1 inimigo aleatório do array de inimigos restantes
    $inimigoAtual = $inimigosRestantes[array_rand($inimigosRestantes)];
    
