<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Orang Tua - NutriTumbuh</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/globals.css', 'resources/css/db-ortu.css'])
</head>
<body>
  
  <header class="navbar-ortu">
    <div class="nav-container-ortu">
      <div class="brand-ortu">
        <img src="/img/only-logo.png" alt="Logo NutriTumbuh" class="brand-logo-ortu"> 
        <span class="brand-text-ortu">NutriTumbuh</span>
      </div>
      
      <nav class="nav-links-ortu">
        <a href="#" class="nav-item-ortu active" data-page="dashboard">
          <x-icon name="grid" /> Dashboard
        </a>
        <a href="/input-ortu" class="nav-item-ortu">
            <span class="nav-icon-plus"><x-icon name="plus" /></span> Input
        </a>
        <a href="/history-ortu" class="nav-item-ortu" data-page="history">
          <x-icon name="history" /> History
        </a>
      </nav>

      <div class="profile-ortu">
        <div class="avatar-initial avatar-sm" id="avatar-ortu">?</div>
        <button type="button" class="btn-logout" data-logout title="Keluar" aria-label="Keluar dari akun"><x-icon name="logout" /></button>
      </div>
    </div>
  </header>

  <main class="dashboard-ortu-content">
    <section class="greeting-section">
      <div class="greeting-text">
        <!-- ID greeting-parent ditambahkan di sini -->
        <h1 class="greeting-title">Halo, <span id="greeting-parent">Bunda</span>!</h1>
        <p class="greeting-subtitle">Pantau terus tumbuh kembang si kecil dengan mudah. Hari ini adalah hari yang baik untuk mencatat nutrisi.</p>
      </div>
      <div class="child-profile-card-header">
        <div class="avatar-initial avatar-sm" id="avatar-anak">?</div>
        <div class="child-info-header">
          <!-- ID child-name dan child-age -->
          <h3 class="child-name-header" id="child-name">Memuat Data...</h3>
          <p class="child-age-header"><x-icon name="user" /> <span id="child-age">-</span></p>
        </div>
        <div class="status-badge-header">
          <span class="status-dot"></span> Status Normal
        </div>
      </div>
    </section>

    <div class="main-grid">
      <div class="left-column">
        <section class="section-title-with-icon">
          <span class="section-icon"><x-icon name="fork" /></span>
          <h2>Asupan Harian</h2>
        </section>

        <div class="nutrition-card">
          <h3 class="nutrition-title">Catat Nutrisi</h3>
          <!-- ID child-name-inline -->
          <p class="nutrition-subtitle">Pencatatan rutin membantu AI menganalisis kebutuhan gizi <span id="child-name-inline">Si Kecil</span>.</p>
          
          <div class="search-nutrition">
            <span class="search-icon"><x-icon name="search" /></span>
            <input type="text" id="food-input" placeholder="Ketik Makanan Hari Ini" inputmode="search" enterkeyhint="done" autocomplete="off">
            <input type="number" id="food-porsi" value="100" min="1" max="2000" title="Porsi (gram)" aria-label="Porsi dalam gram">
            <span class="porsi-unit">g</span>
          </div>
          <div id="food-suggest"></div>

          <div class="food-tags" id="food-tags">
            <span class="food-tag">Ayam <button class="tag-remove" data-food="Ayam"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:12px;height:12px;flex:none;"><path d="M18 6 6 18M6 6l12 12"/></svg></button></span>
            <span class="food-tag">Nasi <button class="tag-remove" data-food="Nasi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:12px;height:12px;flex:none;"><path d="M18 6 6 18M6 6l12 12"/></svg></button></span>
          </div>

          <button class="btn-calculate" id="btn-calculate">
            <span class="btn-plus">+</span> Hitung Makanan Hari Ini
          </button>
          <p id="catat-error" style="display:none;color:#fecaca;font-size:13px;margin:10px 0 0;" role="alert"></p>
          <div id="catat-hasil" style="display:none;" class="hasil-gizi"></div>
        </div>

        <section class="section-title-with-icon">
          <span class="section-icon"><x-icon name="chart" /></span>
          <h2>Perkembangan Fisik</h2>
        </section>

        <div class="physical-grid">
          <div class="physical-card tb-card">
            <div class="physical-header">
              <span class="physical-label">Tinggi Badan (TB)</span>
              <div class="physical-icon tb-icon"><img src="/img/icon-ruler.png" alt="Tinggi Badan"></div>
            </div>
            <div class="physical-value">
              <!-- ID tb-value -->
              <span class="value-number" id="tb-value">-</span>
              <span class="value-unit">cm</span>
            </div>
            <div class="physical-change positive"><span></span> Tumbuh dengan baik</div>
          </div>

          <div class="physical-card bb-card">
            <div class="physical-header">
              <span class="physical-label">Berat Badan (BB)</span>
              <div class="physical-icon bb-icon"><img src="/img/bb.png" alt="Berat Badan"></div>
            </div>
            <div class="physical-value">
              <!-- ID bb-value -->
              <span class="value-number" id="bb-value">-</span>
              <span class="value-unit">kg</span>
            </div>
            <div class="physical-change positive"><span></span> Berat ideal</div>
          </div>
        </div>
      </div>

      <div class="right-column">
        <div class="analysis-card">
          <div class="analysis-header">
            <span class="analysis-icon"><x-icon name="sparkle" /></span>
            <h3>Analisis Cerdas</h3>
          </div>
          <!-- ID child-name-analysis -->
          <p class="analysis-text" id="analysis-text">
            Berdasarkan data awal, pertumbuhan <span id="child-name-analysis">Si Kecil</span> terlihat baik. Pertahankan asupan gizi seimbang.
          </p>
          <div class="analysis-list">
            <div class="analysis-item success">
              <div class="analysis-dot success-dot"><x-icon name="meat" /></div>
              <span>Protein Terpenuhi</span>
            </div>
            <div class="analysis-item warning">
              <div class="analysis-dot warning-dot"><x-icon name="leafy" /></div>
              <span>Pantau Sayur Hijau</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

    @vite(['resources/js/api.js'])
    @vite(['resources/js/db-ortu.js'])
</body>
</html>
