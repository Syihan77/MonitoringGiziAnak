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
        <!-- ? PERBAIKAN 1: href diarahkan ke history-ortu.html -->
        <a href="/history-ortu" class="nav-item-ortu" data-page="history">
          <x-icon name="history" /> History
        </a>
      </nav>

      <div class="profile-ortu">
        <div class="avatar-initial avatar-sm"><x-icon name="user" /></div>
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
      <!-- ? PERBAIKAN 2: Ditambahkan ID agar nama anak bisa muncul dinamis -->
      <h1 class="ai-title" id="ai-page-title">Rencana Makan Mingguan</h1>
      <p class="ai-subtitle">Disusun khusus berdasarkan kebutuhan kalori, riwayat pertumbuhan, dan ketersediaan bahan di kulkas Anda.</p>
    </section>

    <!-- SECTION INPUT -->
    <div class="input-sections">
      
      <!-- Section 1: Budget -->
      <div class="input-card">
        <div class="input-card-header">
          <div class="input-icon-wrapper budget-icon">
            <span><x-icon name="money" /></span>
          </div>
          <div>
            <h3 class="input-title">Berapa uang belanja Hari ini?</h3>
            <p class="input-subtitle">Pilih nominal siap tekan atau masukkan sendiri agar rekomendasi lebih pas.</p>
          </div>
        </div>

        <div class="budget-options">
          <button class="budget-btn" data-budget="10000">Rp 10.000</button>
          <button class="budget-btn" data-budget="15000">Rp 15.000</button>
          <button class="budget-btn" data-budget="20000">Rp 20.000</button>
          <button class="budget-btn" data-budget="25000">Rp 25.000</button>
        </div>

        <div class="custom-budget">
          <label class="custom-label">Atau masukkan nominal sendiri</label>
          <div class="input-with-icon">
            <span class="input-prefix">Rp</span>
            <input type="number" id="custom-budget-input" placeholder="15000" min="0">
          </div>
        </div>
      </div>

      <!-- Section 2: Bahan di Rumah -->
      <div class="input-card">
        <div class="input-card-header">
          <div class="input-icon-wrapper ingredients-icon">
            <span><x-icon name="broccoli" /></span> <!-- Ditambahkan emoji agar tidak kosong -->
          </div>
          <div>
            <h3 class="input-title">Bahan apa yang ada di rumah?</h3>
            <p class="input-subtitle">Pilih bahan umum yang tersedia agar resepnya lebih mudah dibuat.</p>
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
      
      <div class="menu-cards-grid">
        
        <!-- Menu Card 1 -->
        <div class="menu-card">
          <div class="menu-card-header">
            <h3 class="menu-title">Opsi 1</h3>
            <span class="menu-calories">Total: 320 kkal</span>
          </div>
          
          <div class="meal-section">
            <div class="meal-type">
              <span class="meal-icon"><x-icon name="sunrise" /></span> Sarapan
            </div>
            <div class="meal-content">
              <img src="/img/omelet.jpg" alt="Omelet" class="meal-image" onerror="this.src='https://placehold.co/80x80/006a38/ffffff?text=Omelet'">
              <div class="meal-info">
                <h4 class="meal-name">Omelet Bayam & Roti Gandum</h4>
              </div>
            </div>
            <ul class="meal-ingredients">
              <li>Telur ayam 2 butir (120g)</li>
              <li>Bayam 1 ikat (100g)</li>
              <li>Roti gandum 2 lembar (60g)</li>
            </ul>
            <div class="meal-nutrition">
              <span>Prot: 12g</span>
              <span>Lemak: 8g</span>
              <span>Karbo: 30g</span>
            </div>
          </div>

          <a href="#" class="btn-see-more" data-resep="1">Lihat Resep </a>
        </div>

        <!-- Menu Card 2 -->
        <div class="menu-card">
          <div class="menu-card-header">
            <h3 class="menu-title">Opsi 2</h3>
            <span class="menu-calories">Total: 350 kkal</span>
          </div>
          
          <div class="meal-section">
            <div class="meal-type">
              <span class="meal-icon"><x-icon name="sun" /></span> Siang
            </div>
            <div class="meal-content">
              <img src="/img/bubur.jpg" alt="Bubur" class="meal-image" onerror="this.src='https://placehold.co/80x80/006a38/ffffff?text=Bubur'">
              <div class="meal-info">
                <h4 class="meal-name">Bubur Kacang Hijau Susu</h4>
              </div>
            </div>
            <ul class="meal-ingredients">
              <li>Kacang hijau 100g</li>
              <li>Beras 60g</li>
              <li>Susu cair 150ml</li>
            </ul>
            <div class="meal-nutrition">
              <span>Prot: 14g</span>
              <span>Lemak: 6g</span>
              <span>Karbo: 55g</span>
            </div>
          </div>

          <a href="#" class="btn-see-more">Lihat Resep </a>
        </div>

        <!-- Menu Card 3 -->
        <div class="menu-card">
          <div class="menu-card-header">
            <h3 class="menu-title">Opsi 3</h3>
            <span class="menu-calories">Total: 310 kkal</span>
          </div>
          
          <div class="meal-section">
            <div class="meal-type">
              <span class="meal-icon"><x-icon name="moon" /></span> Malam
            </div>
            <div class="meal-content">
              <img src="/img/pancake.jpg" alt="Pancake" class="meal-image" onerror="this.src='https://placehold.co/80x80/006a38/ffffff?text=Pancake'">
              <div class="meal-info">
                <h4 class="meal-name">Pancake Pisang Oatmeal</h4>
              </div>
            </div>
            <ul class="meal-ingredients">
              <li>Pisang 1 buah (80g)</li>
              <li>Oatmeal 40g</li>
              <li>Telur 1 butir (50g)</li>
            </ul>
            <div class="meal-nutrition">
              <span>Prot: 10g</span>
              <span>Lemak: 8g</span>
              <span>Karbo: 45g</span>
            </div>
          </div>

          <a href="#" class="btn-see-more">Lihat Resep </a>
        </div>

      </div>
    </div>

  </main>

  <!-- JavaScript -->
    @vite(['resources/js/api.js'])
    @vite(['resources/js/input-ortu.js'])
  
  <!-- Script Tambahan untuk Personalisasi Judul -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // Ambil nama anak dari sessionStorage agar halaman terasa personal
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
