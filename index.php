<?php
require_once '../ControleDePatrimonio/config/conexao.php';
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Sistema de Patrimônio</title>
</head>

<body>
    <h1>Controle de Patrimônio</h1>
    <p>Registro de bens e controle de depreciação</p>
    <div>
        <p><a href="bens.php">Gerenciar Bens</a></p>
        <p><a href="relatorios.php">Relatórios</a></p>
    </div>

    <?php
    $sql = "SELECT COUNT(*) as total, SUM(valor_inicial) as soma FROM bens WHERE situacao='ativo'";
    $res = $conexao->query($sql)->fetch_assoc();
    ?>

    <p>Total de bens: <strong><?php echo $res['total']; ?></strong></p>
    <p>Valor total (inicial): R$ <strong><?php echo number_format($res['soma'], 2, ',', '.'); ?></strong></p>
</body>

</html>