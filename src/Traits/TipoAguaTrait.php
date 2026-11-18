<?php

// Vantagens e desvantagens de um pokémon do tipo ÁGUA
trait TipoAguaTrait {
    public static function multiplicadorAgua($tipoAlvo) {
        if ($tipoAlvo === 'fogo' || $tipoAlvo === 'terra') return 2;
        if ($tipoAlvo === 'planta' || $tipoAlvo === 'agua') return 0.5;
        return 1;
    }

    // Status que um ataque de água pode causar em quem recebe (água gelada)
    public static function statusAgua() {
        return 'Congelado';
    }
}
