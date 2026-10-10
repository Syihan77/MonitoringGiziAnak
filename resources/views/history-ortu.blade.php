<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Riwayat Anak - NutriTumbuh</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/globals.css', 'resources/css/history-ortu.css'])
</head>
<body>
  
  <!-- NAVIGASI ATAS -->
  <header class="navbar-ortu">
    <div class="nav-container-ortu">
      <div class="brand-ortu">
        <img src="/img/only-logo.png" alt="Logo NutriTumbuh" class="brand-logo-ortu"> 
        <span class="brand-text-ortu">NutriTumbuh</span>
      </div>
      
      <nav class="nav-links-ortu">
        <a href="/db-ortu" class="nav-item-ortu">
          <x-icon name="grid" /> Dashboard
        </a>
        <a href="/input-ortu" class="nav-item-ortu">
          <span class="nav-icon-plus"><x-icon name="plus" /></span> Input
        </a>
        <a href="#" class="nav-item-ortu active" data-page="history">
          <x-icon name="history" /> History
        </a>
      </nav>

      <div class="profile-ortu">
        <div class="avatar-initial avatar-sm" id="avatar-ortu">?</div>
        <button type="button" class="btn-logout" data-logout title="Keluar" aria-label="Keluar dari akun"><x-icon name="logout" /></button>
      </div>
    </div>
  </header>

  <!-- KONTEN UTAMA -->
  <main class="history-content">
    
    <!-- HEADER -->
    <div class="history-header">
      <div class="breadcrumb">
        <span>Child Records</span>
        <span class="breadcrumb-separator"></span>
        <span class="breadcrumb-current" id="breadcrumb-name">Budi Santoso</span>
      </div>
      <h1 class="page-title">Riwayat Anak</h1>
    </div>

    <!-- CHILD PROFILE CARD -->
    <div class="child-profile-card">
      <div class="profile-left">
        <div class="avatar-initial avatar-md" id="avatar-anak"></div>
        <div class="child-info-detailed">
          <h2 class="child-name-large" id="profile-name">Memuat Data...</h2>
          <div class="child-details">
            <div class="detail-item">
              <span><x-icon name="cake" /></span>
              <span>Usia: <strong id="profile-age">-</strong></span>
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
              <span>Orang Tua/Wali: <strong id="profile-ibu">-</strong></span>
            </div>
          </div>
        </div>
      </div>
      <div class="profile-right">
        <div class="status-gizi-badge">
          <span class="status-label">STATUS GIZI</span>
          <div class="status-value normal">
            <span class="status-dot"></span>
            <span>Normal</span>
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
          <span class="filter-select-arrow">?</span>
        </div>
        
        <button class="btn-print-riwayat" id="btn-cetak-riwayat">
          <span class="print-icon"><x-icon name="print" /></span> Cetak Riwayat
        </button>
      </div>
    </div>

    <!-- ============================================ -->
    <!-- LAYOUT 1: TIMELINE (DEFAULT - TAMPIL AWAL) -->
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

        <!-- RIGHT: AI + CATATAN + STATUS -->
        <div class="timeline-right">
          
          <!-- AI ANALISA -->
          <div class="info-card ai-card">
            <div class="card-header">
              <span class="card-icon"><x-icon name="sparkle" /></span>
              <h3>AI Analisa Pertumbuhan</h3>
            </div>
            <p class="card-description" id="ai-analysis-text">
              Tren kenaikan berat badan <span id="ai-child-name">Budi</span> menunjukkan grafik positif dalam 3 bulan terakhir. Asupan protein harian sudah memenuhi target standar KMS.
            </p>
            <div class="graph-placeholder">
              <span class="graph-icon"><x-icon name="bars" /></span>
              <span>[ Visualisasi Grafik KMS ]</span>
            </div>
          </div>

          <!-- CATATAN HARIAN -->
          <div class="section-card">
            <h2 class="section-title">
              <span class="title-icon"><x-icon name="memo" /></span>
              Catatan Harian
            </h2>
            <div class="form-group">
              <label class="form-label">Detail Catatan</label>
              <textarea class="form-textarea" id="daily-note" placeholder="Tuliskan detail catatan di sini..."></textarea>
              <span class="form-hint">*Opsional</span>
            </div>
            <button class="btn-save-note" id="btn-save-note">
              <span><x-icon name="save" /></span> Simpan Catatan
            </button>
          </div>

          <!-- STATUS PEMENUHAN GIZI -->
          <div class="section-card">
            <h2 class="section-title">
              <span class="title-icon"></span>
              Status Pemenuhan Gizi
            </h2>
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
          <span>?</span> Kembali ke Tampilan Timeline
        </button>
      </div>
    </div>

  </main>

  <!-- JavaScript -->
    @vite(['resources/js/api.js'])
    @vite(['resources/js/history-ortu.js'])
</body>
</html>
