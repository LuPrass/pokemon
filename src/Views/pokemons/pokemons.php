<?php
require_once __DIR__ . '/../../../verifica_login.php';

$pokemons = Pokemon::porUsuario(Conexao::getConnection(), $_SESSION['usuario_id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Menu de Pokémons</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/menu.css">
</head>
<body>

    <div class="menu-container">
        <h1>Menu de Pokémons</h1>

        <!-- Consulta na PokéAPI (apenas informação) -->
        <h2>Pesquisar Pokémon</h2>
        <input type="text" id="campoBusca" placeholder="Nome ou número (ex: pikachu)">
        <button onclick="buscar()">Buscar</button>

        <div id="resultado"></div>

        <!-- Pokémons do usuário -->
        <h2>Meus Pokémons</h2>

        <?php if (count($pokemons) === 0): ?>
            <p>Você ainda não tem pokémons. Monte seu time em <b>Batalha</b>.</p>
        <?php endif; ?>

        <?php foreach ($pokemons as $p): ?>
            <div class="pokemon-card">
                <b><?php echo htmlspecialchars($p['nome']); ?></b> (<?php echo htmlspecialchars($p['tipo']); ?>)
                - Nv <?php echo (int)$p['nivel']; ?>
                - HP <?php echo (int)$p['hp_atual']; ?>/<?php echo (int)$p['hp_maximo']; ?>
                - <?php echo htmlspecialchars($p['status_condicao']); ?>
            </div>
        <?php endforeach; ?>

        <a class="menu-sair" href="index.php?rota=menu">Voltar ao Menu</a>
    </div>

    <script src="assets/js/ApiClient.js"></script>
    <script>
        async function buscar() {
            var nome = document.getElementById("campoBusca").value;

            try {
                var pokemon = await buscarPokemon(nome);
                mostrarPokemon(pokemon);
            } catch (erro) {
                alert(erro.message);
            }
        }

        function mostrarPokemon(pokemon) {
            document.getElementById("resultado").innerHTML =
                "<img src='" + pokemon.imagemArtwork + "' width='150'><br>" +
                "<b>" + pokemon.nome + "</b> (" + pokemon.tipo + ")<br>" +
                "HP: " + pokemon.hpMaximo + "<br>" +
                "Ataque: " + pokemon.ataque + "<br>" +
                "Defesa: " + pokemon.defesa + "<br>" +
                "Velocidade: " + pokemon.velocidade;
        }
    </script>

</body>
</html>
