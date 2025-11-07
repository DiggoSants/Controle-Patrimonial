<?php
require_once '../Controle-Patrimonial/config/conexao.php';

/* =============================
   📊 CONSULTAS PRINCIPAIS DO DASHBOARD
   ============================= */

// 1️⃣ Valor total e quantidade de bens
$sql_total = "SELECT SUM(valor_inicial) AS total_bens, COUNT(*) AS qtd_bens FROM bens";
$res_total = $conn->query($sql_total);
$total = $res_total->fetch_assoc();

// 2️⃣ Valor contábil e quantidade de bens ativos
$sql_contabil = "
  SELECT 
    SUM(valor_atual) AS valor_contabil,
    COUNT(*) AS qtd_ativos
  FROM bens 
  WHERE status = 'ativo'
";
$res_contabil = $conn->query($sql_contabil);
$contabil = $res_contabil->fetch_assoc();

// 3️⃣ Depreciação acumulada e bens totalmente depreciados
$sql_dep = "
  SELECT 
    SUM(valor_depreciado) AS dep_total
  FROM depreciacoes
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

// 4️⃣ Bens baixados
$sql_baixados = "SELECT COUNT(*) AS qtd_baixados FROM bens WHERE status = 'baixado'";
$res_baixados = $conn->query($sql_baixados);
$baixados = $res_baixados->fetch_assoc();

// 5️⃣ Relatório por categoria
$sql_relatorio = "
  SELECT 
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

// 6️⃣ Relatório de novos bens (últimos 6 meses)
$sql_recentes = "
  SELECT 
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
$sql_bens = "SELECT";


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

    th,td {
      border-bottom: 1px solid #ddd;
      padding: 10px;
      text-align: left;
    }
  </style>
</head>

<body>
  <header>
    <div class="card-header">
      <h1>Controle Patrimonial</h1>
      <p>Registro de bens e controle de depreciação</p>
      <button>Exportar</button>
      <button>Novo bem</button>
    </div>
  </header>

  <main>
    <nav>
      <ul>
        <li data-target="dashboard" class="active">Dashboard</li>
        <li data-target="bens">Bens Patrimoniais</li>
        <li data-target="relatorios">Relatórios</li>
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

      <!-- RELATÓRIO POR CATEGORIA -->
      <div class="card">
        <h3>Relatório por Categoria</h3>
        <table>
          <thead>
            <tr>
              <th>Categoria</th>
              <th>Quantidade</th>
              <th>Valor Total</th>
              <th>Valor Contábil</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($res_relatorio && $res_relatorio->num_rows > 0): ?>
              <?php while ($cat = $res_relatorio->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($cat['categoria']) ?></td>
                  <td><?= $cat['quantidade'] ?></td>
                  <td>R$ <?= number_format($cat['valor_total'], 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($cat['valor_contabil'], 2, ',', '.') ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="4">Nenhuma categoria com bens ativos.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- NOVOS BENS -->
      <div class="card">
        <h3>Relatório de novos bens (Últimos 6 meses)</h3>
        <table>
          <thead>
            <tr>
              <th>Descrição</th>
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

    <!-- OUTRAS SEÇÕES -->
    <section id="bens">
      <h2>Bens Patrimoniais</h2>
      <p>CRUD futuramente.</p>
    </section>

    <section id="relatorios">
      <h2>Relatórios</h2>
      <p>Aqui entram os relatórios de depreciação, baixas e totais.</p>
    </section>
  </main>

  <script>
    const menuItens = document.querySelectorAll('nav li');
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
  </script>
</body>

</html>