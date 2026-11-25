<?php

/**
 * Trait TipoTerraTrait
 * Regras do Tipo TERRA:
 * - Bate em: Elétrico e Fogo (x2)
 * - Apanha para: Água e Planta (toma x2 de Água e Planta)
 * - Resistência: Imune a Elétrico (toma x0 de Elétrico)
 * - Status elemental: Normal
 */
trait TipoTerraTrait {
    public static function multiplicadorTerra($tipoAlvo) {
        $tipoAlvo = strtolower(trim((string)$tipoAlvo));

        // Vantagem (dá o dobro de dano x2 em Elétrico e Fogo)
        if ($tipoAlvo === 'eletrico' || $tipoAlvo === 'elétrico' || $tipoAlvo === 'electric' || $tipoAlvo === 'fogo' || $tipoAlvo === 'fire') {
            return 2.0;
        }

        // Resistência do alvo (Planta resiste a Terra -> toma x0.5)
        if ($tipoAlvo === 'planta' || $tipoAlvo === 'grass') {
            return 0.5;
        }

        // Dano neutro contra Água e Terra
        return 1.0;
    }

    public static function statusTerra() {
        return 'Normal';
    }
}
