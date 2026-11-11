<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuarioNome = $_SESSION['usuario'] ?? 'Treinador';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mochila - PokéArena</title>
    <link rel="stylesheet" href="assets/css/mochila.css">
</head>
<body class="mochila-page">

    <header>
        <h1>🎒 Mochila do Treinador</h1>
        <p>Treinador: <strong><?php echo htmlspecialchars($usuarioNome); ?></strong></p>
        <nav>
            <a href="index.php?rota=loja">Ir ao PokéMart</a> |
            <a href="index.php?rota=menu">Menu Principal</a> |
            <a href="index.php?rota=batalha">Batalha</a>
        </nav>
    </header>

    <hr>

    <section>
        <h2>Compartimentos da Mochila</h2>
        <p>Os itens comprados no PokéMart (mart.php) serão guardados aqui.</p>

        <!-- Filtros visuais simples -->
        <div>
            <label>Filtrar por tipo:</label>
            <select>
                <option value="todos">Todos os Itens</option>
                <option value="restaurador">Restauradores de HP</option>
                <option value="cura">Cura de Status</option>
                <option value="reviver">Reviver</option>
                <option value="evolucao">Pedras de Evolução</option>
            </select>
        </div>
    </section>

    <br>

    <section>
        <h2>Itens na Mochila</h2>

        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Ícone</th>
                    <th>Nome do Item</th>
                    <th>Tipo</th>
                    <th>Quantidade</th>
                    <th>Efeito / Descrição</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>🧪</td>
                    <td><strong>Potion</strong></td>
                    <td>Restaurador</td>
                    <td>x3</td>
                    <td>Recupera 20 pontos de HP de um Pokémon.</td>
                    <td>
                        <button type="button">Usar</button>
                    </td>
                </tr>
                <tr>
                    <td>🧪</td>
                    <td><strong>Super Potion</strong></td>
                    <td>Restaurador</td>
                    <td>x1</td>
                    <td>Recupera 50 pontos de HP de um Pokémon.</td>
                    <td>
                        <button type="button">Usar</button>
                    </td>
                </tr>
                <tr>
                    <td>💊</td>
                    <td><strong>Antidote</strong></td>
                    <td>Cura de Status</td>
                    <td>x2</td>
                    <td>Cura a condição de envenenamento.</td>
                    <td>
                        <button type="button">Usar</button>
                    </td>
                </tr>
                <tr>
                    <td>💊</td>
                    <td><strong>Paralyze Heal</strong></td>
                    <td>Cura de Status</td>
                    <td>x1</td>
                    <td>Cura a condição de paralisia.</td>
                    <td>
                        <button type="button">Usar</button>
                    </td>
                </tr>
                <tr>
                    <td>💖</td>
                    <td><strong>Revive</strong></td>
                    <td>Reviver</td>
                    <td>x1</td>
                    <td>Revive um Pokémon desmaiado com 50% de HP.</td>
                    <td>
                        <button type="button">Usar</button>
                    </td>
                </tr>
                <tr>
                    <td>✨</td>
                    <td><strong>Pedra de Evolução</strong></td>
                    <td>Evolução</td>
                    <td>x1</td>
                    <td>Usado para evoluir certas espécies de Pokémon.</td>
                    <td>
                        <button type="button">Usar</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>

    <br>
    <hr>

    <!-- Estrutura de escolha de Pokémon para quando a lógica for implementada -->
    <section>
        <h3>Usar Item em um Pokémon (Front-end)</h3>
        <form>
            <p>
                <label for="pokemon_alvo">Escolha o Pokémon da sua equipe:</label>
                <select id="pokemon_alvo" name="pokemon_id">
                    <option value="">-- Selecione um Pokémon --</option>
                    <option value="1">Pokémon 1</option>
                    <option value="2">Pokémon 2</option>
                    <option value="3">Pokémon 3</option>
                    <option value="4">Pokémon 4</option>
                    <option value="5">Pokémon 5</option>
                    <option value="6">Pokémon 6</option>
                </select>
            </p>
            <p>
                <button type="button" onclick="alert('A lógica de uso de itens ainda será implementada!')">
                    Confirmar Uso do Item
                </button>
            </p>
        </form>
    </section>

</body>
</html>
