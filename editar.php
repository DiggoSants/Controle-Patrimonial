<?php 
require_once '../ControleDePatrimonio/config/conexao.php';

$id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $descricao = $_POST['descricao'];
    $categoria = $_POST['categoria'];
    $valor = $_POST['valor_inicial'];
    $data = $_POST['data_aquisicao'];
    $vida = $_POST['vida_util'];

    $sql = "UPDATE bens SET descricao='$descricao', categoria='$categoria', valor_inicial='$valor',
            data_aquisicao='$data', vida_util='$vida' WHERE id=$id";
    $conexao->query($sql);
    header("Location: bens.php");
    exit;
}

$bem = $conexao->query("SELECT * FROM bens WHERE id=$id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="UTF-8"><title>Editar Bem</title></head>
<body>
<h1>Editar Bem</h1>
<form method="post">
  <label>Descrição:</label><br>
  <input type="text" name="descricao" value="<?php echo $bem['descricao']; ?>"><br><br>

  <label>Categoria:</label><br>
  <input type="text" name="categoria" value="<?php echo $bem['categoria']; ?>"><br><br>

  <label>Valor inicial:</label><br>
  <input type="number" step="0.01" name="valor_inicial" value="<?php echo $bem['valor_inicial']; ?>"><br><br>

  <label>Data aquisição:</label><br>
  <input type="date" name="data_aquisicao" value="<?php echo $bem['data_aquisicao']; ?>"><br><br>

  <label>Vida útil:</label><br>
  <input type="number" name="vida_util" value="<?php echo $bem['vida_util']; ?>"><br><br>

  <button type="submit">Salvar</button>
</form>
<p><a href="bens.php">Voltar</a></p>
</body>
</html>
