<?php
require_once '../ControleDePatrimonio/config/conexao.php';

$id = $_GET['id'] ?? 0;
$sql = "UPDATE bens SET situacao='baixado', data_baixa=CURDATE() WHERE id=$id";
$conexao->query($sql);
header("Location: bens.php");
exit;
?>
