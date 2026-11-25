<?php

/**
 * Trait TipoEletricoTrait
 * Regras do Tipo ELÉTRICO:
 * - Bate em: Água e Planta (x2)
 * - Apanha para: Terra (toma x2 de Terra)
 * - Resistência: Elétrico (toma x0.5 de Elétrico)
 * - Imunidade do alvo: Terra é imune a Elétrico (x0)
 * - Status elemental: Paralisado
 */
trait TipoEletricoTrait {
    public static function multiplicadorEletrico($tipoAlvo) {
        $tipoAlvo = strtolower(trim((string)$tipoAlvo));

        // Imunidade de Terra contra Elétrico (Terra não recebe dano x0)
        if ($tipoAlvo === 'terra' || $tipoAlvo === 'ground') {
            return 0.0;
        }

        // Vantagem (dá o dobro de dano x2 em Água e Planta)
        if ($tipoAlvo === 'agua' || $tipoAlvo === 'água' || $tipoAlvo === 'water' || $tipoAlvo === 'planta' || $tipoAlvo === 'grass') {
            return 2.0;
        }

        // Resistência do alvo (Elétrico resiste a Elétrico -> toma x0.5)
        if ($tipoAlvo === 'eletrico' || $tipoAlvo === 'elétrico' || $tipoAlvo === 'electric') {
            return 0.5;
        }

        // Dano neutro contra Fogo
        return 1.0;
    }

    public static function statusEletrico() {
        return 'Paralisado';
    }
}
