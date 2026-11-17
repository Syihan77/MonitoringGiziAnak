<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Input Makanan - NutriTumbuh</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- CSS Files -->
    @vite(['resources/css/globals.css', 'resources/css/input-ortu.css'])
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
        <a href="#" class="nav-item-ortu active" data-page="input">
          <span class="nav-icon-plus"><x-icon name="plus" /></span> Input
        </a>
        <a href="/history-ortu" class="nav-item-ortu" data-page="history">
          <x-icon name="history" /> History
        </a>
      </nav>

      <div class="profile-ortu profile-account">
        <button type="button" class="avatar-initial avatar-sm profile-avatar-btn" id="avatar-ortu" data-profile-trigger aria-label="Buka informasi profil akun"><x-icon name="user" /></button>
        <button type="button" class="btn-logout" data-logout title="Keluar" aria-label="Keluar dari akun"><x-icon name="logout" /></button>
      </div>
    </div>
  </header>

  <!-- KONTEN UTAMA -->
  <main class="input-ortu-content">
    
    <!-- HEADER AI GENERATED -->
    <section class="ai-header-section">
      <div class="ai-badge">
        <span class="ai-icon"><x-icon name="sparkle" /></span>
        <span>AI GENERATED</span>
      </div>
      <h1 class="ai-title" id="ai-page-title">Rencana Makan Mingguan</h1>
      <p class="ai-subtitle">Disusun khusus berdasarkan riwayat pertumbuhan, kebutuhan gizi harian, dan bahan yang ada di rumah Anda.</p>
    </section>

    <!-- SECTION INPUT -->
    <div class="input-sections">
      
      <!-- Section: Bahan di Rumah (Section Budget dihapus sesuai SKPL v2.0) -->
      <div class="input-card">
        <div class="input-card-header">
          <div class="input-icon-wrapper ingredients-icon">
            <span><x-icon name="broccoli" /></span>
          </div>
          <div>
            <h3 class="input-title">Bahan apa yang ada di rumah?</h3>
            <p class="input-subtitle">Pilih bahan umum yang tersedia agar sistem dapat mencari resep yang paling cocok.</p>
          </div>
        </div>

        <div class="search-ingredients">
          <span class="search-icon"><x-icon name="search" /></span>
          <input type="text" id="ingredient-search" placeholder="Contoh: ayam, telur, tempe, atau sayur..." inputmode="search" enterkeyhint="search" autocomplete="off">
        </div>

        <div class="ingredients-tags" id="ingredients-tags">
          <button class="ingredient-tag" data-ingredient="Ayam">Ayam</button>
          <button class="ingredient-tag" data-ingredient="Telur">Telur</button>
          <button class="ingredient-tag selected" data-ingredient="Tempe">
            <span class="check-icon"><x-icon name="check" /></span> Tempe
          </button>
          <button class="ingredient-tag" data-ingredient="Sayur">Sayur</button>
          <button class="ingredient-tag" data-ingredient="Bawang">Bawang</button>
          <button class="ingredient-tag" data-ingredient="Kentang">Kentang</button>
          <button class="ingredient-tag" data-ingredient="Tahu">Tahu</button>
          <button class="ingredient-tag" data-ingredient="Ikan">Ikan</button>
        </div>
      </div>

      <!-- Button Generate -->
      <button class="btn-generate" id="btn-generate">
        <span class="generate-icon"><x-icon name="sparkle" /></span>
        Buat Rekomendasi Makanan
      </button>

    </div>

    <!-- SECTION HASIL REKOMENDASI -->
    <div class="recommendations-section" id="recommendations-section" style="display: none;">
      <h2 class="recommendations-title">Menu Rekomendasi untuk Hari Ini</h2>
      
      <div class="menu-cards-grid" id="menu-cards-grid">
        <div class="menu-empty-state" hidden>
          <img src="/img/placeholder-menu.svg" alt="" class="menu-empty-image">
          <p>Belum ada rekomendasi. Pilih bahan lalu tekan “Buat Rekomendasi Makanan”.</p>
        </div>
      </div>
    </div>

  </main>

  <!-- JavaScript -->
    @vite(['resources/js/api.js'])
    @vite(['resources/js/profile-menu.js'])
    @vite(['resources/js/input-ortu.js'])
  
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const dataAnak = JSON.parse(sessionStorage.getItem('anakDataStep2'));
      if (dataAnak && dataAnak.namaAnak) {
        const titleElement = document.getElementById('ai-page-title');
        if (titleElement) {
          titleElement.textContent = `Rencana Makan untuk ${dataAnak.namaAnak}`;
        }
      }
    });
  </script>
</body>
</html>