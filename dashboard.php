<?php
require_once '../Controle-Patrimonial/config/conexao.php';

/* =============================
   🆕 CADASTRO DE NOVO BEM
   ============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['descricao'])) {
  $descricao = $_POST['descricao'];
  $id_categoria = $_POST['id_categoria'];
  $valor_inicial = $_POST['valor_inicial'];
  $data_aquisicao = $_POST['data_aquisicao'];
  $vida_util = $_POST['vida_util'];

  $valor_atual = $valor_inicial;
  $status = 'ativo';

  $sql_insert = "INSERT INTO bens (descricao, id_categoria, valor_inicial, valor_atual, data_aquisicao, vida_util, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($sql_insert);

  if ($stmt->execute([$descricao, $id_categoria, $valor_inicial, $valor_atual, $data_aquisicao, $vida_util, $status])) {
    echo "<script>alert('✅ Bem cadastrado com sucesso!'); window.location='?';</script>";
  } else {
    echo "<script>alert('❌ Erro ao cadastrar o bem!');</script>";
  }
}

/* =============================
   📉 DAR BAIXA EM UM BEM
   ============================= */
/* =============================
   📦 DAR BAIXA EM UM BEM (com data e motivo)
   ============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_baixa'])) {
  $id_bem = (int) $_POST['id_bem_baixa'];
  $data_baixa = $_POST['data_baixa'];
  $motivo = $_POST['motivo_baixa'];

  // Atualiza o status do bem
  $conn->query("UPDATE bens SET status='baixado' WHERE id_bem = $id_bem");

  // Registra a baixa na tabela baixa_bens
  $stmt = $conn->prepare("INSERT INTO baixa_bens (id_bem, data_baixa, motivo) VALUES (?, ?, ?)");
  $stmt->execute([$id_bem, $data_baixa, $motivo]);

  echo "<script>alert('📦 Baixa registrada com sucesso!'); window.location='?';</script>";
}


/* =============================
   ✏️ EDITAR UM BEM
   ============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id_bem'])) {
  $id_bem = $_POST['id_bem'];
  $descricao = $_POST['descricao'];
  $id_categoria = $_POST['id_categoria'];
  $valor_inicial = $_POST['valor_inicial'];
  $data_aquisicao = $_POST['data_aquisicao'];
  $vida_util = $_POST['vida_util'];

  $sql = "UPDATE bens 
          SET descricao=?, id_categoria=?, valor_inicial=?, data_aquisicao=?, vida_util=?, valor_atual=? 
          WHERE id_bem=?";
  $stmt = $conn->prepare($sql);
  $stmt->execute([$descricao, $id_categoria, $valor_inicial, $data_aquisicao, $vida_util, $valor_inicial, $id_bem]);
}

/* =============================
   📊 CONSULTAS PRINCIPAIS DO DASHBOARD
   ============================= */
$sql_total = "SELECT SUM(valor_inicial) AS total_bens, COUNT(*) AS qtd_bens FROM bens";
$res_total = $conn->query($sql_total);
$total = $res_total->fetch_assoc();

$sql_contabil = "
  SELECT 
    SUM(valor_atual) AS valor_contabil,
    COUNT(*) AS qtd_ativos
  FROM bens 
  WHERE status = 'ativo'
";
$res_contabil = $conn->query($sql_contabil);
$contabil = $res_contabil->fetch_assoc();

$sql_dep = "SELECT 
    SUM(b.valor_inicial - COALESCE(b.valor_atual, 0)) AS dep_total
  FROM bens b
  WHERE b.status = 'ativo'
";
$res_dep = $conn->query($sql_dep);
$dep = $res_dep->fetch_assoc();

$sql_depreciados = "
  SELECT COUNT(*) AS qtd_depreciados 
  FROM bens 
  WHERE valor_atual = 0 AND status = 'ativo'
";
$res_depreciados = $conn->query($sql_depreciados);
$depreciados = $res_depreciados->fetch_assoc();

$sql_baixados = "SELECT COUNT(*) AS qtd_baixados FROM bens WHERE status = 'baixado'";
$res_baixados = $conn->query($sql_baixados);
$baixados = $res_baixados->fetch_assoc();

$sql_relatorio = "SELECT 
    c.nome AS categoria,
    COUNT(b.id_bem) AS quantidade,
    SUM(b.valor_inicial) AS valor_total,
    SUM(b.valor_atual) AS valor_contabil
  FROM bens b
  JOIN categorias c ON b.id_categoria = c.id_categoria
  WHERE b.status = 'ativo'
  GROUP BY c.nome
  ORDER BY quantidade DESC
";
$res_relatorio = $conn->query($sql_relatorio);

$sql_recentes = "SELECT 
    b.descricao, 
    c.nome AS categoria, 
    b.data_aquisicao, 
    b.valor_inicial, 
    b.vida_util
  FROM bens b
  JOIN categorias c ON b.id_categoria = c.id_categoria
  WHERE b.data_aquisicao >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
  ORDER BY b.data_aquisicao DESC
";
$res_recentes = $conn->query($sql_recentes);

/* =============================
   ⚙️ CONSULTAS SECUNDÁRIAS
   ============================= */
$sql_bens = "SELECT 
  b.id_bem,
  b.descricao AS nome,
  c.id_categoria,
  c.nome AS categoria,
  b.valor_inicial,
  b.data_aquisicao,
  b.vida_util,
  COALESCE(b.valor_atual, 0) AS valor_atual,
  COALESCE(SUM(d.valor_depreciado), (b.valor_inicial - COALESCE(b.valor_atual,0))) AS valor_depreciado,
  b.status
FROM bens b
LEFT JOIN categorias c ON b.id_categoria = c.id_categoria
LEFT JOIN depreciacoes d ON b.id_bem = d.id_bem
GROUP BY b.id_bem, b.descricao, c.id_categoria, c.nome, b.valor_inicial, b.data_aquisicao, b.valor_atual, b.status, b.vida_util
ORDER BY status ASC, b.id_bem ASC";

$res_bens = $conn->query($sql_bens);

/* =============================
   📈 DEPRECIAÇÃO ANUAL
   ============================= */
$dep_anual = "SELECT 
    b.id_bem,
    b.descricao AS nome,
    c.nome AS categoria,
    b.data_aquisicao,
    b.valor_inicial,
    ROUND((1 / b.vida_util) * 100, 2) AS taxa_anual,
    ROUND(b.valor_inicial / b.vida_util, 2) AS depreciacao_anual,
    TIMESTAMPDIFF(YEAR, b.data_aquisicao, CURDATE()) AS anos_decorridos,
    GREATEST(b.vida_util - TIMESTAMPDIFF(YEAR, b.data_aquisicao, CURDATE()), 0) AS anos_restantes
  FROM bens b
  JOIN categorias c ON b.id_categoria = c.id_categoria
  WHERE b.status = 'ativo'
";
$res_dep_anual = $conn->query($dep_anual);
$result_dep_anual = $res_dep_anual->fetch_all(MYSQLI_ASSOC);
$total_dep_anual = array_sum(array_column($result_dep_anual, 'depreciacao_anual'));

// quantidade de bens totalmente depreciados
$totalmente_dep = "SELECT COUNT(*) AS qtd_depreciados FROM bens WHERE valor_atual <= 0 AND status = 'ativo';";
$res_totalmente_dep = $conn->query($totalmente_dep);
$totalmente_dep = $res_totalmente_dep->fetch_assoc();


$contabil_total = "SELECT SUM(valor_atual) AS valor_contabil_total FROM bens WHERE status = 'ativo'";
$res_contabil_total = $conn->query($contabil_total);
$cont_total = $res_contabil_total->fetch_assoc();

// Consulta completa para o Relatório de Novos Bens (últimos 12 meses)
// Consulta principal: bens adicionados nos últimos 12 meses
$novos_bens = "SELECT 
    b.id_bem,
    b.descricao AS nome,
    c.nome AS categoria,
    b.data_aquisicao,
    b.valor_inicial,
    b.vida_util
  FROM bens b
  JOIN categorias c ON b.id_categoria = c.id_categoria
  WHERE b.data_aquisicao >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND b.status = 'ativo'
  ORDER BY b.data_aquisicao DESC
";
$res_novos_bens = $conn->query($novos_bens);
$n_bens = $res_novos_bens->fetch_all(MYSQLI_ASSOC); // fetch_all para pegar todos

// Valor total dos novos bens (últimos 12 meses)
$total_novos_bens = "SELECT SUM(b.valor_inicial) AS valor_total_novos
  FROM bens b
  WHERE b.data_aquisicao >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND b.status = 'ativo'
";
$res_total_novos_bens = $conn->query($total_novos_bens);
$valorT_novos_bens = $res_total_novos_bens->fetch_assoc();

//Quantidade total de novos bens (últimos 12 meses)
$qtd_novos_bens = "SELECT COUNT(*) AS qtd_novos
  FROM bens b
  WHERE b.data_aquisicao >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND b.status = 'ativo'
";
$res_qtd_novos_bens = $conn->query($qtd_novos_bens);
$qtd_novos = $res_qtd_novos_bens->fetch_assoc();

// Consulta de bens baixados com data da tabela baixa_bens
$baixas_bens = "
  SELECT 
    b.id_bem,
    b.descricao AS nome,
    c.nome AS categoria,
    b.data_aquisicao,
    b.valor_inicial,
    b.valor_atual,
    b.vida_util,
    bb.data_baixa
  FROM bens b
  JOIN categorias c ON b.id_categoria = c.id_categoria
  JOIN baixa_bens bb ON b.id_bem = bb.id_bem
  WHERE b.status = 'baixado'
  ORDER BY bb.data_baixa DESC
";
$res_baixas_bens = $conn->query($baixas_bens);
$bens_baixados = $res_baixas_bens ? $res_baixas_bens->fetch_all(MYSQLI_ASSOC) : [];

// Total e quantidade de bens baixados
$total_baixas = "
  SELECT 
    COUNT(*) AS qtd_baixados,
    SUM(b.valor_inicial) AS valor_total_baixados
  FROM bens b
  JOIN baixa_bens bb ON b.id_bem = bb.id_bem
  WHERE b.status = 'baixado'
";
$res_total_baixas = $conn->query($total_baixas);
$info_baixas = $res_total_baixas ? $res_total_baixas->fetch_assoc() : ['qtd_baixados' => 0, 'valor_total_baixados' => 0];


?>


<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Controle Patrimonial</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f5f5f5;
    }

    nav ul {
      display: flex;
      list-style: none;
      gap: 20px;
      margin: 0;
      padding: 0;
    }

    nav li {
      cursor: pointer;
      padding: 8px 14px;
      border-radius: 4px;
    }

    nav li:hover,
    nav li.active {
      background: #34495e;
      color: white;
    }

    main {
      padding: 30px;
    }

    section {
      display: none;
    }

    section.active {
      display: block;
    }

    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
    }

    .card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .card h3,
    .card h4 {
      margin: 0;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }

    th,
    td {
      border-bottom: 1px solid #ddd;
      padding: 10px;
      text-align: left;
    }

    .sub-menu ul {
      display: flex;
      gap: 15px;
      list-style: none;
      padding: 0;
      margin: 15px 0;
    }

    .sub-menu li {
      padding: 6px 12px;
      border-radius: 4px;
      background: #e0e0e0;
      cursor: pointer;
      transition: background 0.2s;
    }

    .sub-menu li:hover {
      background: #34495e;
      color: white;
    }

    .sub-menu li.active {
      background: #34495e;
      color: white;
    }

    .subsection {
      display: none;
      margin-top: 20px;
    }

    .subsection.active {
      display: block;
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.6);
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background: #fff;
      padding: 25px;
      border-radius: 10px;
      width: 400px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
      text-align: left;
      animation: fadeIn 0.2s ease;
    }

    .modal-content h3 {
      text-align: center;
      margin-top: 0;
    }

    .modal-content label {
      display: block;
      margin-top: 10px;
      font-weight: bold;
    }

    .modal-content input,
    .modal-content select {
      width: 100%;
      padding: 8px;
      margin-top: 4px;
      border: 1px solid #ccc;
      border-radius: 5px;
    }

    .modal-actions {
      display: flex;
      justify-content: space-between;
      margin-top: 20px;
    }

    .modal-actions button {
      padding: 8px 14px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }

    .modal-actions button[type="submit"] {
      background: #2ecc71;
      color: white;
    }

    .modal-actions button#fechar-modal {
      background: #e74c3c;
      color: white;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: scale(0.9);
      }

      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    img {
      width: 20px;
      height: 20px;
    }
  </style>
</head>

<body>
  <header>
    <div class="card-header">
      <h1>Controle Patrimonial</h1>
      <p>Registro de bens e controle de depreciação</p>
      <button>Exportar</button>
      <button id="abrir-modal">Novo bem</button>
    </div>
  </header>

  <main>
    <nav>
      <ul>
        <li data-target="dashboard" class="active">Dashboard</li>
        <li data-target="bens">Bens Patrimoniais</li>
        <li data-target="relatorios">
          <!-- <img src="public/icon_relatorio.png" alt=""> -->
          Relatórios
        </li>
      </ul>
    </nav>

    <!-- DASHBOARD -->
    <section id="dashboard" class="active">
      <div class="cards">
        <div class="card">
          <h4>Valor Total dos Bens</h4>
          <h2>R$ <?= number_format($total['total_bens'] ?? 0, 2, ',', '.') ?></h2>
          <span><?= $total['qtd_bens'] ?? 0 ?> bens cadastrados</span>
        </div>
        <div class="card">
          <h4>Valor Contábil</h4>
          <h2>R$ <?= number_format($contabil['valor_contabil'] ?? 0, 2, ',', '.') ?></h2>
          <span><?= $contabil['qtd_ativos'] ?? 0 ?> bens ativos</span>
        </div>
        <div class="card">
          <h4>Depreciação Acumulada</h4>
          <h2>R$ <?= number_format($dep['dep_total'] ?? 0, 2, ',', '.') ?></h2>
          <span><?= $depreciados['qtd_depreciados'] ?? 0 ?> bens totalmente depreciados</span>
        </div>
        <div class="card">
          <h4>Bens Baixados</h4>
          <h2><?= $baixados['qtd_baixados'] ?? 0 ?></h2>
          <span>Removidos do patrimônio</span>
        </div>
      </div>

      <div class="card">
        <h3>Relatório de novos bens (Últimos 6 meses)</h3>
        <table>
          <thead>
            <tr>
              <th>Nome</th>
              <th>Categoria</th>
              <th>Data de Aquisição</th>
              <th>Valor de Aquisição</th>
              <th>Vida Útil</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($res_recentes && $res_recentes->num_rows > 0): ?>
              <?php while ($bem = $res_recentes->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($bem['descricao']) ?></td>
                  <td><?= htmlspecialchars($bem['categoria']) ?></td>
                  <td><?= htmlspecialchars(date('d/m/Y', strtotime($bem['data_aquisicao']))) ?></td>
                  <td>R$ <?= number_format($bem['valor_inicial'], 2, ',', '.') ?></td>
                  <td><?= htmlspecialchars($bem['vida_util']) ?> anos</td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="5">Nenhum bem cadastrado nos últimos 6 meses.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- BENS -->
    <section id="bens">
      <div class="card">
        <table>
          <thead>
            <tr>
              <th>Nome</th>
              <th>Categoria</th>
              <th>Data Aquisição</th>
              <th>Valor Aquisição</th>
              <th>Valor Contábil</th>
              <th>Depreciação</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($b = $res_bens->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($b['nome']) ?></td>
                <td><?= htmlspecialchars($b['categoria']) ?></td>
                <td><?= date('d/m/Y', strtotime($b['data_aquisicao'])) ?></td>
                <td>R$ <?= number_format($b['valor_inicial'], 2, ',', '.') ?></td>
                <td>R$ <?= number_format($b['valor_atual'], 2, ',', '.') ?></td>
                <td>R$ <?= number_format($b['valor_depreciado'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars($b['status']) ?></td>
                <td>
                  <a class="btn-editar" data-id="<?= $b['id_bem'] ?>" data-descricao="<?= htmlspecialchars($b['nome']) ?>"
                    data-categoria="<?= $b['categoria'] ?>" data-valor="<?= $b['valor_inicial'] ?>"
                    data-data="<?= $b['data_aquisicao'] ?>" data-vida="<?= $b['vida_util'] ?>">
                    <img src="public/icon_editar.png" alt="Editar">
                  </a>

                  <?php if ($b['status'] === 'ativo'): ?>
                    <a href="?baixar=<?= $b['id_bem'] ?>" onclick="return confirm('Dar baixa neste bem?')">
                      <img src="public/icon_deletar.png" alt="Dar baixa">
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- RELATÓRIOS -->
    <section id="relatorios">
      <nav class="sub-menu">
        <ul>
          <li class="active" data-subtarget="relatorio-depreciacao">Depreciação Anual</li>
          <li data-subtarget="relatorio-categoria">Por Categoria</li>
          <li data-subtarget="relatorio-contabil">Valor Contábil</li>
          <li data-subtarget="relatorio-depreciados">Depreciados</li>
          <li data-subtarget="relatorio-novos">Novos Bens</li>
          <li data-subtarget="relatorio-baixas">Baixas</li>
          <li data-subtarget="relatorio-vida">Vida Útil</li>
        </ul>
      </nav>
      <div class="subsection active" id="relatorio-depreciacao">
        <div class="card">
          <div>Relatório de Depreciação Anual</div>
          <div class="card" style="margin-top:10px; padding:10px;">
            <span><strong>Total de Depreciação Anual</strong></span><br>
            <h2>R$ <?= number_format($total_dep_anual, 2, ',', '.') ?></h2>
          </div>
          <table>
            <thead>
              <tr>
                <th>Bem</th>
                <th>Categoria</th>
                <th>Taxa Anual (%)</th>
                <th>Depreciação Anual</th>
                <th>Anos Decorridos</th>
                <th>Anos Restantes</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($result_dep_anual)): ?>
                <?php foreach ($result_dep_anual as $result): ?>
                  <tr>
                    <td><?= htmlspecialchars($result['nome']) ?></td>
                    <td><?= htmlspecialchars($result['categoria']) ?></td>
                    <td><?= number_format($result['taxa_anual'] ?? 0, 2, ',', '.') ?>%</td>
                    <td>R$ <?= number_format($result['depreciacao_anual'] ?? 0, 2, ',', '.') ?></td>
                    <td><?= number_format($result['anos_decorridos'] ?? 0, 1, '.', '.') ?></td>
                    <td><?= number_format($result['anos_restantes'] ?? 0, 1, '.', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6">Nenhum bem encontrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>

        </div>
      </div>

      <div class="subsection" id="relatorio-categoria">
        <div class="card">
          <div class="card-header">Relatório por Categoria</div>
          <table>
            <thead>
              <tr>
                <th>Categoria</th>
                <th>Quantidade</th>
                <th>Valor Total</th>
                <th>Depreciação Acumulada</th>
                <th>Valor Contábil</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($res_relatorio && $res_relatorio->num_rows > 0): ?>
                <?php while ($cat = $res_relatorio->fetch_assoc()): ?>
                  <?php
                  $depreciacao_acumulada = $cat['valor_total'] - $cat['valor_contabil'];
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($cat['categoria']) ?></td>
                    <td><?= $cat['quantidade'] ?></td>
                    <td>R$ <?= number_format($cat['valor_total'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($depreciacao_acumulada, 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($cat['valor_contabil'], 2, ',', '.') ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5">Nenhuma categoria com bens ativos.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>


      <div class="subsection" id="relatorio-contabil">
        <div class="card">
          <div>Relatório de Valor Contábil</div>
          <div class="card" style="margin-top:10px; padding:10px;">
            <span><strong>Valor Contábil Total</strong></span><br>
            <h2>R$ <?= number_format($cont_total['valor_contabil_total'] ?? 0, 2, ',', '.') ?></h2>
          </div>
          <table>
            <thead>
              <tr>
                <th>Bem</th>
                <th>Categoria</th>
                <th>Data de Aquisição</th>
                <th>Valor de Aquisição</th>
                <th>Depreciação Acumulada</th>
                <th>Valor Contábil</th>
                <th>% Depreciado</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($res_bens && $res_bens->num_rows > 0): ?>
                <?php $res_bens = $conn->query($sql_bens);
                while ($b = $res_bens->fetch_assoc()):
                  $percentual = ($b['valor_inicial'] > 0) ? (($b['valor_depreciado'] / $b['valor_inicial']) * 100) : 0; ?>
                  <tr>
                    <td><?= htmlspecialchars($b['nome']) ?></td>
                    <td><?= htmlspecialchars($b['categoria']) ?></td>
                    <td><?= date('d/m/Y', strtotime($b['data_aquisicao'])) ?></td>
                    <td>R$ <?= number_format($b['valor_inicial'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($b['valor_depreciado'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($b['valor_atual'], 2, ',', '.') ?></td>
                    <td><?= number_format($percentual, 2, ',', '.') ?>%</td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7">Nenhum bem cadastrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="subsection" id="relatorio-depreciados">
        <div class="card">
          <h4>Relatório de Bens Totalmente Depreciados</h4>
          <div class="card" style="margin-top:10px; padding:10px;">
            <span><strong>Total de Bens Depreciados</strong></span><br>
            <h2><?= $totalmente_dep['qtd_depreciados'] ?? 0 ?></h2>
          </div>

          <table>
            <thead>
              <tr>
                <th>Bem</th>
                <th>Categoria</th>
                <th>Data de Aquisição</th>
                <th>Valor de Aquisição</th>
                <th>Depreciação Total</th>
                <th>Anos em Uso</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($result_dep_anual)): ?>
                <?php foreach ($result_dep_anual as $result): ?>
                  <?php if (($result['anos_restantes'] ?? 0) <= 0): // apenas totalmente depreciados ?>
                    <tr>
                      <td><?= htmlspecialchars($result['nome']) ?></td>
                      <td><?= htmlspecialchars($result['categoria']) ?></td>
                      <td><?= date('d/m/Y', strtotime($result['data_aquisicao'])) ?></td>
                      <td>R$ <?= number_format($result['valor_inicial'] ?? 0, 2, ',', '.') ?></td>
                      <td>R$
                        <?= number_format($result['depreciacao_anual'] * ($result['anos_decorridos'] ?? 0), 2, ',', '.') ?>
                      </td>
                      <td><?= number_format(($result['anos_decorridos'] ?? 0), 0, ',', '.') ?> anos</td>
                    </tr>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6">Nenhum bem totalmente depreciado encontrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>


      <div class="subsection" id="relatorio-novos">
        <div class="card">
          <h4>Relatório de Novos Bens (Últimos 12 meses)</h4>

          <!-- Card resumo -->
          <div class="card" style="margin-top:10px; padding:10px;">
            <span><strong>Valor Total de Novos Bens</strong></span><br>
            <h2>
              R$ <?= number_format($valorT_novos_bens['valor_total_novos'] ?? 0, 2, ',', '.') ?>
            </h2>
            <span><?= ($qtd_novos['qtd_novos'] ?? 0) ?> bens adquiridos</span>
          </div>

          <!-- Tabela -->
          <table>
            <thead>
              <tr>
                <th>Bem</th>
                <th>Categoria</th>
                <th>Data de Aquisição</th>
                <th>Valor de Aquisição</th>
                <th>Vida Útil (anos)</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($n_bens)): ?>
                <?php foreach ($n_bens as $b): ?>
                  <tr>
                    <td><?= htmlspecialchars($b['nome']) ?></td>
                    <td><?= htmlspecialchars($b['categoria']) ?></td>
                    <td><?= date('d/m/Y', strtotime($b['data_aquisicao'])) ?></td>
                    <td>R$ <?= number_format($b['valor_inicial'], 2, ',', '.') ?></td>
                    <td><?= number_format($b['vida_util'], '1', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5">Nenhum bem encontrado nos últimos 12 meses.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="subsection" id="relatorio-baixas">
        <div class="card">
          <h4>Relatório de Bens Baixados</h4>

          <!-- Card resumo -->
          <div class="card" style="margin-top:10px; padding:10px;">
            <span><strong>Valor Total de Bens Baixados</strong></span><br>
            <h2>R$ <?= number_format($info_baixas['valor_total_baixados'] ?? 0, 2, ',', '.') ?></h2>
            <span><?= ($info_baixas['qtd_baixados'] ?? 0) ?> bens baixados</span>
          </div>

          <!-- Tabela -->
          <table>
            <thead>
              <tr>
                <th>Bem</th>
                <th>Categoria</th>
                <th>Data de Aquisição</th>
                <th>Data de Baixa</th>
                <th>Valor de Aquisição</th>
                <th>Valor Contábil</th>

              </tr>
            </thead>
            <tbody>
              <?php if (!empty($bens_baixados)): ?>
                <?php foreach ($bens_baixados as $b): ?>
                  <tr>
                    <td><?= htmlspecialchars($b['nome']) ?></td>
                    <td><?= htmlspecialchars($b['categoria']) ?></td>
                    <td><?= date('d/m/Y', strtotime($b['data_aquisicao'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($b['data_baixa'])) ?></td>
                    <td>R$ <?= number_format($b['valor_inicial'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($b['valor_atual'], 2, ',', '.') ?></td>

                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6">Nenhum bem baixado encontrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>


      <div class="subsection" id="relatorio-vida_util">
        
      </div>
    </section>
  </main>

  <!-- MODAL NOVO BEM -->
  <div id="modal-novo-bem" class="modal">
    <div class="modal-content">
      <h3>Novo Bem Patrimonial</h3>
      <form id="form-novo-bem" method="POST" action="?">
        <label>Descrição:</label>
        <input type="text" name="descricao" required>

        <label>Categoria:</label>
        <select name="id_categoria" required>
          <option value="">Selecione</option>
          <?php
          $res_cat = $conn->query("SELECT id_categoria, nome FROM categorias ORDER BY nome");
          while ($c = $res_cat->fetch_assoc()):
            ?>
            <option value="<?= $c['id_categoria'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
          <?php endwhile; ?>
        </select>

        <label>Valor de Aquisição (R$):</label>
        <input type="number" step="0.01" name="valor_inicial" required>

        <label>Data de Aquisição:</label>
        <input type="date" name="data_aquisicao" required>

        <label>Vida Útil (anos):</label>
        <input type="number" name="vida_util" required>

        <div class="modal-actions">
          <button type="submit">Salvar</button>
          <button type="button" id="fechar-modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal editar bem -->
  <div id="modal-editar-bem" class="modal">
    <div class="modal-content">
      <h3>Editar Bem Patrimonial</h3>
      <form id="form-editar-bem" method="POST">
        <input type="hidden" name="id_bem">

        <label>Descrição:</label>
        <input type="text" name="descricao" required>

        <label>Categoria:</label>
        <select name="id_categoria" required>
          <?php
          $res_cat = $conn->query("SELECT id_categoria, nome FROM categorias");
          while ($c = $res_cat->fetch_assoc()): ?>
            <option value="<?= $c['id_categoria'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
          <?php endwhile; ?>
        </select>

        <label>Valor de Aquisição:</label>
        <input type="number" step="0.01" name="valor_inicial" required>

        <label>Data de Aquisição:</label>
        <input type="date" name="data_aquisicao" required>

        <label>Vida Útil (anos):</label>
        <input type="number" name="vida_util" required>

        <div class="modal-actions">
          <button type="submit">Salvar</button>
          <button type="button" id="fechar-editar">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL BAIXA DE BEM -->
  <div id="modal-baixa-bem" class="modal">
    <div class="modal-content">
      <h3>Baixar Bem Patrimonial</h3>
      <p>Informe a data e o motivo da baixa do bem: <strong id="bem-nome-baixa"></strong></p>
      <form id="form-baixa-bem" method="POST" action="?">
        <input type="hidden" name="id_bem_baixa">

        <label>Data da Baixa:</label>
        <input type="date" name="data_baixa" required>

        <label>Motivo da Baixa:</label>
        <textarea name="motivo_baixa" rows="3" required></textarea>

        <div class="modal-actions">
          <button type="submit" name="confirmar_baixa">Confirmar</button>
          <button type="button" id="fechar-baixa">Cancelar</button>
        </div>
      </form>
    </div>
  </div>



  <script>
    const menuItens = document.querySelectorAll('nav ul li[data-target]');
    const secoes = document.querySelectorAll('main section');
    menuItens.forEach(item => {
      item.addEventListener('click', () => {
        menuItens.forEach(i => i.classList.remove('active'));
        item.classList.add('active');
        const alvo = item.getAttribute('data-target');
        secoes.forEach(sec => sec.classList.remove('active'));
        document.getElementById(alvo).classList.add('active');
      });
    });
    const subItens = document.querySelectorAll('.sub-menu li');
    const subSections = document.querySelectorAll('.subsection');
    subItens.forEach(sub => {
      sub.addEventListener('click', () => {
        subItens.forEach(i => i.classList.remove('active'));
        sub.classList.add('active');
        const subTarget = sub.getAttribute('data-subtarget');
        subSections.forEach(s => s.classList.remove('active'));
        document.getElementById(subTarget).classList.add('active');
      });
    });
    const modal = document.getElementById('modal-novo-bem');
    const btnNovoBem = document.getElementById('abrir-modal');
    const btnFechar = document.getElementById('fechar-modal');
    btnNovoBem.addEventListener('click', () => modal.style.display = 'flex');
    btnFechar.addEventListener('click', () => modal.style.display = 'none');
    modal.addEventListener('click', e => {
      if (e.target === modal) modal.style.display = 'none';
    });

    // 🔹 Modal de Edição
    const modalEditar = document.getElementById('modal-editar-bem');
    const btnFecharEditar = document.getElementById('fechar-editar');
    const formEditar = document.getElementById('form-editar-bem');

    document.querySelectorAll('.btn-editar').forEach(btn => {
      btn.addEventListener('click', () => {
        formEditar.id_bem.value = btn.dataset.id;
        formEditar.descricao.value = btn.dataset.descricao;
        formEditar.valor_inicial.value = btn.dataset.valor;
        formEditar.data_aquisicao.value = btn.dataset.data;
        formEditar.vida_util.value = btn.dataset.vida;
        modalEditar.style.display = 'flex';
      });
    });

    btnFecharEditar.addEventListener('click', () => modalEditar.style.display = 'none');
    modalEditar.addEventListener('click', e => {
      if (e.target === modalEditar) modalEditar.style.display = 'none';
    });

    // 🔹 Modal de Baixa
    const modalBaixa = document.getElementById('modal-baixa-bem');
    const formBaixa = document.getElementById('form-baixa-bem');
    const btnFecharBaixa = document.getElementById('fechar-baixa');
    const bemNomeBaixa = document.getElementById('bem-nome-baixa');

    // Botões de baixa (ícone do lixo)
    document.querySelectorAll('a[href*="?baixar="]').forEach(link => {
      link.addEventListener('click', e => {
        e.preventDefault(); // impede a navegação direta
        const id = link.href.split('=')[1];
        const nome = link.closest('tr').querySelector('td:first-child').textContent;

        formBaixa.id_bem_baixa.value = id;
        bemNomeBaixa.textContent = nome;
        modalBaixa.style.display = 'flex';
      });
    });

    btnFecharBaixa.addEventListener('click', () => modalBaixa.style.display = 'none');
    modalBaixa.addEventListener('click', e => {
      if (e.target === modalBaixa) modalBaixa.style.display = 'none';
    });

  </script>

</body>

</html>