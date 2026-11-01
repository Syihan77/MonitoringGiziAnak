<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Riwayat Anak - NutriTumbuh</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/globals.css', 'resources/css/riwayat-kader.css'])
</head>
<body>
  
  <!-- NAVIGASI ATAS -->
  <header class="navbar-kader">
    <div class="nav-container-kader">
      <div class="brand-kader">
        <img src="/img/only-logo.png" alt="Logo NutriTumbuh" class="brand-logo-kader"> 
        <span class="brand-text-kader">NutriTumbuh</span>
      </div>
      
      <nav class="nav-links-kader">
        <a href="/db-kader" class="nav-item-kader">
          <x-icon name="grid" /> Dashboard
        </a>
        <a href="/perhatian-kader" class="nav-item-kader active">
          <x-icon name="history" /> Riwayat
        </a>
      </nav>

      <div class="profile-kader">
        <div class="avatar-initial avatar-sm" id="avatar-kader">?</div>
        <button type="button" class="btn-logout" data-logout title="Keluar" aria-label="Keluar dari akun"><x-icon name="logout" /></button>
      </div>
    </div>
  </header>

  <!-- KONTEN UTAMA -->
  <main class="riwayat-content">
    
    <!-- HEADER -->
    <div class="riwayat-header">
      <div class="breadcrumb">
        <a href="/perhatian-kader">Child Records</a>
        <span class="breadcrumb-separator">›</span>
        <span class="breadcrumb-current" id="breadcrumb-name">Memuat...</span>
      </div>
      <h1 class="page-title">Riwayat Anak</h1>
    </div>

    <!-- CHILD PROFILE CARD -->
    <div class="child-profile-card">
      <div class="profile-left">
        <div class="child-avatar-large" id="child-avatar">-</div>
        <div class="child-info-detailed">
          <h2 class="child-name-large" id="profile-name">Memuat Data...</h2>
          <div class="child-details">
            <div class="detail-item">
              <span><x-icon name="cake" /></span>
              <span>Usia: <strong id="profile-usia">-</strong> Bulan</span>
            </div>
            <div class="detail-item">
              <span><x-icon name="scale" /></span>
              <span>BB Terakhir: <strong id="profile-bb">-</strong> kg</span>
            </div>
            <div class="detail-item">
              <span><x-icon name="ruler" /></span>
              <span>TB Terakhir: <strong id="profile-tb">-</strong> cm</span>
            </div>
            <div class="detail-item">
              <span><x-icon name="user" /></span>
              <span>Ibu: <strong id="profile-ibu">-</strong></span>
            </div>
            <div class="detail-item">
              <span><x-icon name="pin" /></span>
              <span>Wilayah: <strong id="profile-wilayah">-</strong></span>
            </div>
          </div>
        </div>
      </div>
      <div class="profile-right">
        <div class="status-gizi-badge">
          <span class="status-label">KATEGORI PEMANTAUAN</span>
          <div class="status-value" id="profile-status">
            <span class="status-dot"></span>
            <span id="profile-status-text">-</span>
          </div>
        </div>
      </div>
    </div>

    <!-- SEARCH & FILTER -->
    <div class="search-filter-bar">
      <div class="search-box-large">
        <span class="search-icon"><x-icon name="search" /></span>
        <input type="text" id="search-makanan" placeholder="Cari berdasarkan nama makanan (misal: &quot;Bayam&quot;, &quot;Telur&quot;)...">
      </div>
      <div class="filter-controls">
        <div class="filter-select-wrapper">
          <span class="filter-select-icon"><x-icon name="calendar" /></span>
          <select class="filter-select" id="filter-bulan">
            <option value="">Pilih Bulan...</option>
          </select>
          <span class="filter-select-arrow">▼</span>
        </div>
        
        <button class="btn-print-riwayat" id="btn-cetak-riwayat">
          <span class="print-icon"><x-icon name="print" /></span> Cetak Riwayat
        </button>
      </div>
    </div>

    <!-- ============================================ -->
    <!-- LAYOUT 1: TIMELINE + AI + STATUS (DEFAULT) -->
    <!-- ============================================ -->
    <div class="timeline-layout" id="timeline-layout">
      <div class="timeline-main-grid">
        
        <!-- LEFT: LOG MAKANAN TIMELINE -->
        <div class="timeline-left">
          <div class="section-card">
            <h2 class="section-title">
              <span class="title-icon"><x-icon name="fork" /></span>
              Log Makanan Harian
            </h2>
            
            <div class="timeline" id="timeline-container">
              <!-- Timeline diisi oleh JavaScript -->
            </div>
          </div>
        </div>

        <!-- RIGHT: AI + STATUS -->
        <div class="timeline-right">
          
          <!-- AI ANALISA -->
          <div class="info-card ai-card">
            <div class="card-header">
              <span class="card-icon"><x-icon name="sparkle" /></span>
              <h3>Analisis Cerdas</h3>
            </div>
            <p class="card-description" id="ai-analysis-text">
              Berdasarkan input minggu ini, asupan protein <span id="ai-child-name">anak</span> sudah optimal. Pertimbangkan untuk menambah porsi sayuran hijau untuk zat besi.
            </p>
            
            <div class="analysis-list">
              <div class="analysis-item success">
                <div class="analysis-dot success-dot"></div>
                <span>Protein Terpenuhi</span>
              </div>
              <div class="analysis-item warning">
                <div class="analysis-dot warning-dot"></div>
                <span>Kurang Sayur Hijau</span>
              </div>
            </div>
          </div>

          <!-- STATUS PEMENUHAN GIZI -->
          <div class="section-card">
            <h2 class="section-title-small">Status Pemenuhan Gizi</h2>
            <p class="section-subtitle">Ringkasan bulan terpenuhi dan belum terpenuhi.</p>
            
            <div class="nutrition-status-list">
              <div class="nutrition-status-item">
                <div class="status-dot green"></div>
                <div class="status-info">
                  <div class="month-name">September</div>
                  <div class="month-desc">92% - pemenuhan gizi sangat baik.</div>
                </div>
                <div class="status-badge fulfilled">TERPENUHI</div>
              </div>
              
              <div class="nutrition-status-item">
                <div class="status-dot yellow"></div>
                <div class="status-info">
                  <div class="month-name">Agustus</div>
                  <div class="month-desc">68% - perlu peningkatan konsumsi protein dan sayur.</div>
                </div>
                <div class="status-badge partial">KURANG TERPENUHI</div>
              </div>
              
              <div class="nutrition-status-item">
                <div class="status-dot red"></div>
                <div class="status-info">
                  <div class="month-name">Juli</div>
                  <div class="month-desc">46% - perlu perhatian dan pemenuhan gizi dengan baik.</div>
                </div>
                <div class="status-badge unfulfilled">TIDAK TERPENUHI</div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ============================================ -->
    <!-- LAYOUT 2: GRID 4 KOLOM (SEMBUNYI AWAL) -->
    <!-- ============================================ -->
    <div class="grid-layout" id="grid-layout" style="display: none;">
      <div class="log-section">
        <h2 class="section-title-with-icon">
          <span class="section-icon"><x-icon name="fork" /></span>
          Log Makanan Harian - <span id="grid-bulan-label">-</span>
        </h2>
        
        <div class="daily-log-grid" id="daily-log-grid">
          <!-- Grid diisi oleh JavaScript -->
        </div>
      </div>

      <!-- Tombol Kembali ke Timeline -->
      <div class="back-button-wrapper">
        <button class="btn-back" id="btn-kembali-timeline">
          <span>←</span> Kembali ke Tampilan Timeline
        </button>
      </div>
    </div>

    <!-- Tombol Kembali ke Daftar Anak -->
    <div class="back-button-wrapper" style="margin-top: 24px;">
      <a href="/perhatian-kader" class="btn-back">
        <span>←</span> Kembali ke Daftar Anak
      </a>
    </div>

  </main>

  <!-- JavaScript -->
    @vite(['resources/js/api.js'])
    @vite(['resources/js/riwayat-kader.js'])
</body>
</html>
