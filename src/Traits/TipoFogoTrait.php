<?php

// Vantagens e desvantagens de um pokémon do tipo FOGO
trait TipoFogoTrait {
    public static function multiplicadorFogo($tipoAlvo) {
        if ($tipoAlvo === 'planta') return 2;
        if ($tipoAlvo === 'agua' || $tipoAlvo === 'fogo') return 0.5;
        return 1;
    }

    // Status que um ataque de fogo pode causar em quem recebe
    public static function statusFogo() {
        return 'Queimado';
    }
}
