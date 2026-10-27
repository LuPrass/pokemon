<?php

// Vantagens e desvantagens de um pokémon do tipo PSÍQUICO
trait TipoPsiquicoTrait {
    public function multiplicadorPsiquico($tipoAlvo) {
        if ($tipoAlvo == 'luta' || $tipoAlvo == 'fighting') return 2;
        if ($tipoAlvo == 'veneno' || $tipoAlvo == 'poison') return 2;
        if ($tipoAlvo == 'psiquico' || $tipoAlvo == 'psychic') return 0.5;
        if ($tipoAlvo == 'fantasma' || $tipoAlvo == 'ghost') return 0.5;
        return 1;
    }
}
