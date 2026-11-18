<?php

// Vantagens e desvantagens de um pokémon do tipo PSÍQUICO
trait TipoPsiquicoTrait {
    public static function multiplicadorPsiquico($tipoAlvo) {
        if ($tipoAlvo === 'luta' || $tipoAlvo === 'veneno') return 2;
        if ($tipoAlvo === 'psiquico' || $tipoAlvo === 'fantasma') return 0.5;
        return 1;
    }

    // Status que um ataque psíquico pode causar em quem recebe (hipnose)
    public static function statusPsiquico() {
        return 'Dormindo';
    }
}
