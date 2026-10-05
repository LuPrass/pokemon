<?php

spl_autoload_register(function ($classe) {
    // Ajusta o nome caso a classe venha com namespace (ex: Models\Usuario -> Usuario)
    $nomeClasse = basename(str_replace('\\', '/', $classe));

    // Lista de diretórios onde o Autoload deve buscar
    $diretorios = [
        __DIR__ . '/src/Controllers/',
        __DIR__ . '/src/Models/',
        __DIR__ . '/src/Services/',
        __DIR__ . '/src/Exceptions/',
        __DIR__ . '/src/Traits/',
        __DIR__ . '/config/'
    ];

    foreach ($diretorios as $diretorio) {
        // Tenta buscar diretamente pelo nome da classe
        $arquivo = $diretorio . $classe . '.php';

        if (file_exists($arquivo)) {
            require_once $arquivo;
            return;
        }

        // Tenta buscar pelo nome da classe sem o prefixo de namespace
        $arquivoPorNome = $diretorio . $nomeClasse . '.php';

        if (file_exists($arquivoPorNome)) {
            require_once $arquivoPorNome;
            return;
        }
    }
});
