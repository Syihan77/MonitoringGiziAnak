<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Seluruh Riwayat Anak - NutriTumbuh</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- CSS Files -->
    @vite(['resources/css/globals.css', 'resources/css/perhatian-kader.css'])
</head>
<body>
  
  <div class="riwayat-page">
    
    <!-- TOMBOL KEMBALI -->
    <div class="back-button-container">
      <a href="/db-kader" class="btn-kembali">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="19" y1="12" x2="5" y2="12"></line>
          <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Kembali
      </a>
      <button type="button" class="btn-logout" data-logout title="Keluar" aria-label="Keluar dari akun"><x-icon name="logout" /></button>
    </div>

    <!-- KONTEN UTAMA -->
    <main class="riwayat-content">
      
      <!-- HEADER TABEL -->
      <div class="riwayat-header">
        <div class="header-title-group">
          <span class="red-dot"></span>
          <h1>Daftar Balita Perlu Perhatian</h1>
        </div>
      </div>

      <!-- TABEL DATA BALITA -->
      <div class="table-container">
        <table class="riwayat-table">
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
          <tbody id="riwayat-table-body">
            <!-- Data akan diisi oleh JavaScript -->
          </tbody>
        </table>
      </div>

    </main>
  </div>

  <!-- Bottom navbar mobile (disembunyikan di desktop) -->
  <nav class="nav-bottom-kader" aria-label="Navigasi kader">
    <a href="/db-kader" class="nav-bottom-item">
      <x-icon name="grid" /> <span>Dashboard</span>
    </a>
    <a href="/perhatian-kader" class="nav-bottom-item active" aria-current="page">
      <x-icon name="history" /> <span>Perhatian</span>
    </a>
  </nav>

  <!-- JavaScript -->
    @vite(['resources/js/api.js'])
    @vite(['resources/js/perhatian-kader.js'])
</body>
</html>
