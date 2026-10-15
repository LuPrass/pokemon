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

</body>
</html>
