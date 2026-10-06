<?php

class PokemonInvalidoException extends Exception {
    public function __construct(string $mensagem = "Dados do Pokémon inválidos ou incompletos!") {
        parent::__construct($mensagem);
    }
}
