<?php require_once '../Controle-Patrimonial/config/conexao.php';
// if (isset($_SESSION['usuario'])) {
//   // Usuário está logado, pode acessar o dashboard
// } else {
//   // Usuário não está logado, redireciona para a página de login
//   header('Location: login.php');
//   exit();
// }

// Valor total e quantidade de bens
$sql_total = "SELECT SUM(valor_inicial) AS total_bens, COUNT(*) AS qtd_bens FROM bens";
$res_total = $conn->query($sql_total);
$total = $res_total->fetch_assoc();

// Valor contábil (somatório de valor_atual)
$sql_contabil = "SELECT SUM(valor_atual) AS valor_contabil FROM bens WHERE status='ativo'";
$res_contabil = $conn->query($sql_contabil);
$contabil = $res_contabil->fetch_assoc();

// Depreciação acumulada
$sql_dep = "SELECT SUM(valor_depreciado) AS dep_total FROM depreciacoes";
$res_dep = $conn->query($sql_dep);
$dep = $res_dep->fetch_assoc();

// Bens baixados
$sql_baixados = "SELECT COUNT(*) AS qtd_baixados FROM bens WHERE status='baixado'";
$res_baixados = $conn->query($sql_baixados);
$baixados = $res_baixados->fetch_assoc();
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

    header {
      background: #2c3e50;
      color: white;
      padding: 15px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
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
    }

    main {
      padding: 30px;
    }

    section {
      display: none;
      /* todas ocultas inicialmente */
    }

    section.active {
      display: block;
      /* mostra apenas a ativa */
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

    .card h3 {
      margin: 0;
      color: #2c3e50;
    }

    .card p {
      margin-top: 5px;
      color: #555;
    }
  </style>
</head>

<body>

  <header>
    <h1>Controle Patrimonial</h1>
    <button>Exportar</button>
    <button>Adicionar novo bem</button>

  </header>

  <main>
    <nav>
      <ul>
        <li data-target="dashboard" class="active">Dashboard</li>
        <li data-target="bens">Bens Patrimoniais</li>
        <li data-target="relatorios">Relatórios</li>
      </ul>
    </nav>
    <!-- Seção Dashboard -->
    <section id="dashboard" class="active">
  <h2>Dashboard</h2>
  <div class="cards">
    <div class="card">
      <h3>Valor Total dos Bens</h3>
      <h4>R$ <?= number_format($total['total_bens'] ?? 0, 2, ',', '.') ?></h4>
      <span><?= $total['qtd_bens'] ?? 0 ?> bens cadastrados</span>
    </div>

    <div class="card">
      <h3>Valor Contábil</h3>
      <h4>R$ <?= number_format($contabil['valor_contabil'] ?? 0, 2, ',', '.') ?></h4>
      <span>Bens ativos</span>
    </div>

    <div class="card">
      <h3>Depreciação Acumulada</h3>
      <h4>R$ <?= number_format($dep['dep_total'] ?? 0, 2, ',', '.') ?></h4>
      <span>Total depreciado</span>
    </div>

    <div class="card">
      <h3>Bens Baixados</h3>
      <h4><?= $baixados['qtd_baixados'] ?? 0 ?></h4>
      <span>Removidos do patrimônio</span>
    </div>
  </div>
</section>


    <!-- Seção Bens -->
    <section id="bens">
      <h2>Bens Patrimoniais</h2>
      <p>Lista de bens cadastrados. CRUD futuramente.</p>
    </section>

    <!-- Seção Relatórios -->
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
        // Atualiza menu ativo
        menuItens.forEach(i => i.classList.remove('active'));
        item.classList.add('active');

        // Mostra seção correspondente
        const alvo = item.getAttribute('data-target');
        secoes.forEach(sec => sec.classList.remove('active'));
        document.getElementById(alvo).classList.add('active');
      });
    });
  </script>

</body>

</html>