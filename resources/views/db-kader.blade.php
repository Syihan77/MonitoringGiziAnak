<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Kader - NutriTumbuh</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- CSS Files -->
    @vite(['resources/css/globals.css', 'resources/css/db-kader.css'])
</head>
<body>
  
  <!-- NAVIGASI ATAS -->
  <header class="navbar">
    <div class="nav-container">
      <div class="brand">
        <img src="/img/only-logo.png" alt="Logo NutriTumbuh" class="brand-logo"> 
        <span class="brand-text">NutriTumbuh</span>
      </div>
      
      <nav class="nav-links">
        <a href="#" class="nav-item active" data-page="dashboard">
          <x-icon name="grid" /> Dashboard
        </a>
        <a href="#" class="nav-item" data-page="history">
          <x-icon name="history" /> Riwayat
        </a>
      </nav>

      <div class="profile">
        <div class="avatar-initial avatar-sm"><x-icon name="user" /></div>
        <button type="button" class="btn-logout" data-logout title="Keluar" aria-label="Keluar dari akun"><x-icon name="logout" /></button>
      </div>
    </div>
  </header>

  <!-- KONTEN UTAMA -->
  <main class="dashboard-content">
    
    <!-- HALAMAN DASHBOARD -->
    <div id="dashboard-page" class="page-content active">
      <!-- Header Dashboard & Pencarian -->
      <section class="dashboard-header">
        <div class="header-titles">
          <h1>Dashboard Kader</h1>
          <p>Ringkasan data kesehatan balita Posyandu Melati</p>
        </div>
        <div class="search-box">
          <img src="/img/icon-search.png" alt="Search">
          <input type="text" id="search-input" placeholder="Cari nama anak atau orang tua...">
        </div>
      </section>

      <!-- Grid Ringkasan -->
      <section class="summary-grid">
        <div class="summary-card active-card">
          <div class="card-top">
            <span class="card-title">Total Anak</span>
            <div class="icon-wrapper total"><img src="/img/icon-total.png" alt=""></div>
          </div>
          <div class="card-value" id="total-anak">0</div>
        </div>
        <div class="summary-card">
          <div class="card-top">
            <span class="card-title">Normal / Risiko Rendah</span>
            <div class="icon-wrapper baik"><img src="/img/icon-baik.png" alt=""></div>
          </div>
          <div class="card-value" id="gizi-baik">0</div>
        </div>
        <div class="summary-card">
          <div class="card-top">
            <span class="card-title">Perlu Pemantauan</span>
            <div class="icon-wrapper kurang"><img src="/img/icon-kurang.png" alt=""></div>
          </div>
          <div class="card-value" id="gizi-kurang">0</div>
        </div>
        <div class="summary-card">
          <div class="card-top">
            <span class="card-title">Perlu Rujukan</span>
            <div class="icon-wrapper buruk"><img src="/img/icon-buruk.png" alt=""></div>
          </div>
          <div class="card-value" id="gizi-buruk">0</div>
        </div>
      </section>

      <!-- Area Grafik -->
      <section class="charts-area">
        <div class="chart-card trend-chart-card">
          <div class="trend-header">
            <div>
              <h3>Tren Prevalensi Gizi Kurang & Buruk</h3>
              <p>Pemantauan efektivitas program intervensi pangan desa</p>
            </div>
            <div class="badge-green" id="trend-badge">
              <x-icon name="trend-down" /> <span id="trend-text">Memuat data...</span>
            </div>
          </div>
          <div class="trend-graph-placeholder">
            <div class="trend-graph-inner">
              <canvas id="trendChart"></canvas>
            </div>
          </div>
        </div>

        <div class="chart-card donut-chart-card">
          <h3>Distribusi Kategori Pemantauan</h3>
          <div class="donut-chart-container">
            <div class="donut-chart" id="donutChart">
              <div class="donut-inner">
                <span class="donut-total" id="donut-total">0</span>
                <span class="donut-label">Total</span>
              </div>
            </div>
            <div class="chart-legend" id="chart-legend"></div>
          </div>
        </div>
      </section>

      <!-- Tabel Daftar Balita -->
      <section class="table-section">
        <div class="table-header">
          <div class="table-title-group">
            <span class="red-dot"></span>
            <h2>Daftar Balita Perlu Perhatian</h2>
          </div>
          <a href="/perhatian-kader" class="link-action">Buka Seluruh Riwayat Anak <x-icon name="arrow-right" /></a>
        </div>
        
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Nama Balita</th>
                <th>Usia / JK</th>
                <th>Nama Orang Tua</th>
                <th>Wilayah Dusun</th>
                <th>Kategori Pemantauan</th>
                <th>Tindakan Cepat</th>
              </tr>
            </thead>
            <tbody id="table-body">
              <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">Memuat data...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- HALAMAN HISTORY/RIWAYAT -->
    <div id="history-page" class="page-content">
      <section class="dashboard-header">
        <div class="header-titles">
          <h1>Data Balita Terkini</h1>
          <p>Riwayat pencatatan gizi seluruh balita Posyandu Melati</p>
        </div>
        <div class="search-box">
          <img src="/img/icon-search.png" alt="Search">
          <input type="text" id="history-search-input" placeholder="Cari nama balita...">
        </div>
      </section>

      <section class="table-section history-table-section">
        <div class="table-responsive">
          <table class="data-table history-table">
            <thead>
              <tr>
                <th>NAMA</th>
                <th>USIA (BULAN)</th>
                <th>PENCATATAN TERAKHIR</th>
                <th>STATUS GIZI</th>
                <th>AKSI</th>
              </tr>
            </thead>
            <tbody id="history-table-body">
              <!-- Data akan diisi oleh JavaScript -->
            </tbody>
          </table>
        </div>
      </section>
    </div>

  </main>

  <!-- JavaScript -->
    @vite(['resources/js/api.js'])
    @vite(['resources/js/db-kader.js'])
</body>
</html>
