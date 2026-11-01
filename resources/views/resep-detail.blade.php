<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detail Resep - NutriTumbuh</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/globals.css', 'resources/css/resep-detail.css'])
</head>
<body>
  
  <!-- TOP BAR -->
  <div class="top-bar">
    <a href="/input-ortu" class="btn-back-top">
      <span class="back-icon">←</span> Kembali ke Menu Utama
    </a>
    <div class="ai-badge-top">
      <span class="ai-icon-top"><x-icon name="sparkle" /></span> REKOMENDASI AI
    </div>
  </div>

  <!-- KONTEN UTAMA -->
  <main class="resep-content">
    
    <!-- HEADER RESEP CARD -->
    <div class="resep-header-card">
      <div class="resep-image-wrapper">
        <img src="/img/omelet.jpg" alt="Resep" class="resep-hero-image" id="resep-image" onerror="this.src='https://placehold.co/600x400/006a38/ffffff?text=Resep'">
      </div>
      <div class="resep-info-wrapper">
        <h1 class="resep-title" id="resep-title">Omelet Bayam & Roti Gandum</h1>
        <p class="resep-description" id="resep-description">
          Menu ini disusun secara khusus berdasarkan perhitungan kalori, riwayat tumbuh kembang balita Anda, serta ketersediaan bahan bergizi yang praktis di kulkas.
        </p>
        
        <div class="info-grid">
          <div class="info-box">
            <div class="info-box-icon calorie-icon"><x-icon name="fire" /></div>
            <div class="info-box-content">
              <span class="info-box-label">Kalori</span>
              <span class="info-box-value" id="resep-kalori">±320 kkal</span>
            </div>
          </div>
          <div class="info-box">
            <div class="info-box-icon time-icon"><x-icon name="timer" /></div>
            <div class="info-box-content">
              <span class="info-box-label">Waktu Masak</span>
              <span class="info-box-value" id="resep-waktu">15 Menit</span>
            </div>
          </div>
          <div class="info-box">
            <div class="info-box-icon difficulty-icon"><x-icon name="chart" /></div>
            <div class="info-box-content">
              <span class="info-box-label">Kesulitan</span>
              <span class="info-box-value" id="resep-kesulitan">Mudah</span>
            </div>
          </div>
          <div class="info-box">
            <div class="info-box-icon category-icon"><x-icon name="smile" /></div>
            <div class="info-box-content">
              <span class="info-box-label">Kategori</span>
              <span class="info-box-value" id="resep-kategori">Cocok Balita</span>
            </div>
          </div>
        </div>
        <div id="resep-peringatan" style="display:none;margin-top:12px;padding:12px 14px;border-radius:12px;background:#fef3c7;color:#92400e;font-size:13px;"></div>
      </div>
    </div>

    <!-- GRID KONTEN: BAHAN + GIZI -->
    <div class="bahan-gizi-grid">
      
      <!-- BAHAN YANG DIBUTUHKAN -->
      <div class="bahan-card">
        <div class="bahan-header">
          <h2 class="bahan-title">Bahan yang Dibutuhkan</h2>
          <span class="bahan-badge" id="bahan-total">Total: 4 bahan</span>
        </div>
        <ul class="bahan-list" id="bahan-list">
          <!-- Diisi oleh JavaScript -->
        </ul>
      </div>

      <!-- INFORMASI GIZI PER PORSI -->
      <div class="gizi-card">
        <h2 class="gizi-title">Informasi Gizi per Porsi</h2>
        
        <div class="gizi-list" id="gizi-list">
          <div class="gizi-item">
            <div class="gizi-item-header">
              <span class="gizi-name">Energi</span>
              <span class="gizi-value calorie" id="gizi-energi">320 kkal</span>
            </div>
            <div class="gizi-bar-bg">
              <div class="gizi-bar-fill calorie" style="width: 80%"></div>
            </div>
          </div>
          
          <div class="gizi-item">
            <div class="gizi-item-header">
              <span class="gizi-name">Protein</span>
              <span class="gizi-value protein" id="gizi-protein">15 g</span>
            </div>
            <div class="gizi-bar-bg">
              <div class="gizi-bar-fill protein" style="width: 45%"></div>
            </div>
          </div>
          
          <div class="gizi-item">
            <div class="gizi-item-header">
              <span class="gizi-name">Karbohidrat</span>
              <span class="gizi-value carb" id="gizi-karbo">38 g</span>
            </div>
            <div class="gizi-bar-bg">
              <div class="gizi-bar-fill carb" style="width: 60%"></div>
            </div>
          </div>
          
          <div class="gizi-item">
            <div class="gizi-item-header">
              <span class="gizi-name">Lemak</span>
              <span class="gizi-value fat" id="gizi-lemak">12 g</span>
            </div>
            <div class="gizi-bar-bg">
              <div class="gizi-bar-fill fat" style="width: 35%"></div>
            </div>
          </div>
        </div>

        <div class="gizi-info-box">
          <span class="info-icon-circle"><x-icon name="info" /></span>
          <span>Rendah gula tambahan dan sangat aman sebagai asupan tumbuh kembang harian.</span>
        </div>
      </div>
    </div>

    <!-- CARA MEMASAK -->
    <div class="memasak-card">
      <h2 class="memasak-title">Cara Memasak</h2>
      
      <div class="langkah-timeline" id="langkah-timeline">
        <!-- Diisi oleh JavaScript -->
      </div>
    </div>

  </main>

  <!-- Bottom navbar mobile -->
  <nav class="nav-links-ortu" aria-label="Navigasi utama">
    <a href="/db-ortu" class="nav-item-ortu">
      <x-icon name="grid" /> Dashboard
    </a>
    <a href="/input-ortu" class="nav-item-ortu">
      <span class="nav-icon-plus"><x-icon name="plus" /></span> Input
    </a>
    <a href="/history-ortu" class="nav-item-ortu">
      <x-icon name="history" /> History
    </a>
  </nav>

  <!-- JavaScript -->
    @vite(['resources/js/api.js'])
    @vite(['resources/js/resep-detail.js'])
</body>
</html>
