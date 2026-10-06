<?php

class PokemonDesmaiadoException extends Exception {
    public function __construct(string $mensagem = "Este Pokémon está desmaiado e não pode lutar!") {
        parent::__construct($mensagem);
    }
}
