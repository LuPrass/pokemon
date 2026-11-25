<?php

/**
 * Trait TipoPlantaTrait
 * Regras do Tipo PLANTA:
 * - Bate em: Água e Terra (x2)
 * - Apanha para: Fogo e Elétrico (toma x2 de Fogo e Elétrico)
 * - Resistência: Planta, Água e Terra (toma x0.5 de Planta, Água e Terra)
 * - Status elemental: Envenenado
 */
trait TipoPlantaTrait {
    public static function multiplicadorPlanta($tipoAlvo) {
        $tipoAlvo = strtolower(trim((string)$tipoAlvo));

        // Vantagem (dá o dobro de dano x2 em Água e Terra)
        if ($tipoAlvo === 'agua' || $tipoAlvo === 'água' || $tipoAlvo === 'water' || $tipoAlvo === 'terra' || $tipoAlvo === 'ground') {
            return 2.0;
        }

        // Resistência do alvo (Fogo e Planta resistem a Planta -> tomam x0.5)
        if ($tipoAlvo === 'fogo' || $tipoAlvo === 'fire' || $tipoAlvo === 'planta' || $tipoAlvo === 'grass') {
            return 0.5;
        }

        // Dano neutro contra Elétrico
        return 1.0;
    }

    public static function statusPlanta() {
        return 'Envenenado';
    }
}
