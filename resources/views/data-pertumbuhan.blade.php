<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Informasi Data Pertumbuhan Anak - NutriTumbuh</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/globals.css', 'resources/css/data-pertumbuhan.css'])
</head>
<body>
  <div class="pertumbuhan-page">
    <div class="pertumbuhan-card">
      
      <div class="pertumbuhan-header">
        <img class="pertumbuhan-logo" src="/img/NutrisiTumbuh-Logo.png" alt="Logo NutriTumbuh">
        <h1 class="pertumbuhan-title">Informasi Data Pertumbuhan Anak</h1>
        <p class="pertumbuhan-subtitle">Mohon masukkan data pertumbuhan anak sesuai kondisi saat ini</p>
        
        <div class="stepper-container">
          <div class="stepper-line"></div>
          <div class="stepper-steps">
            <div class="step">
              <div class="circle">1</div>
              <div class="step-text">Data Diri<br>Orang Tua</div>
            </div>
            <div class="step">
              <div class="circle">2</div>
              <div class="step-text">Data Diri<br>Anak</div>
            </div>
            <div class="step">
              <div class="circle active">3</div>
              <div class="step-text active-text">Data Pertumbuhan<br>Anak</div>
            </div>
            <div class="step">
              <div class="circle">4</div>
              <div class="step-text">Tinjauan</div>
            </div>
          </div>
        </div>
      </div>

      <form class="pertumbuhan-form" id="form-data-pertumbuhan">
        
        <!-- Info Box Analisis Cerdas -->
        <div class="info-box">
          <div class="info-icon">
            <img src="/img/icon-sparkle.png" alt="Sparkle">
          </div>
          <div class="info-content">
            <h3 class="info-title">Analisis Cerdas NutriTumbuh</h3>
            <p class="info-text">
              Data awal ini sangat penting. AI kami akan menggunakannya sebagai titik nol (baseline) untuk mendeteksi tren pertumbuhan, memprediksi potensi stunting lebih awal, dan memberikan rekomendasi gizi yang presisi setiap bulannya.
            </p>
          </div>
        </div>

        <!-- Grid Input Tinggi & Berat Badan -->
        <div class="measurement-grid">
          
          <!-- Tinggi Badan -->
          <div class="measurement-card">
            <div class="measurement-icon">
              <img src="/img/icon-ruler.png" alt="Ruler">
            </div>
            <h3 class="measurement-label">Tinggi Badan</h3>
            <div class="measurement-input-wrapper">
              <input type="number" id="tinggiBadan" name="tinggiBadan" 
                     placeholder="0.0" step="0.1" min="0" max="200" required>
              <span class="measurement-unit">cm</span>
            </div>
          </div>

          <!-- Berat Badan -->
          <div class="measurement-card">
            <div class="measurement-icon">
              <img src="/img/icon-scale.png" alt="Scale">
            </div>
            <h3 class="measurement-label">Berat Badan</h3>
            <div class="measurement-input-wrapper">
              <input type="number" id="beratBadan" name="beratBadan" 
                     placeholder="0.0" step="0.1" min="0" max="100" required>
              <span class="measurement-unit">kg</span>
            </div>
          </div>

        </div>

        <!-- Tombol Aksi -->
        <div class="form-action-two-buttons">
          <button type="button" class="btn-secondary" id="btn-kembali">
            <img src="/img/icon-arrow-left.png" alt="Arrow"> Kembali
          </button>
          <button type="submit" class="btn-submit">
            Lanjutkan <img src="/img/icon-arrow-right.png" alt="Arrow">
          </button>
        </div>

      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const ortuData = sessionStorage.getItem('ortuDataStep1');
      const anakData = sessionStorage.getItem('anakDataStep2');
      
      if (!ortuData || !anakData) {
        alert('Silakan lengkapi data sebelumnya terlebih dahulu');
        window.location.href = '/data-ortu';
        return;
      }

      // RESTORE DATA PERTUMBUHAN JIKA SUDAH PERNAH DIISI
      const savedPertumbuhan = JSON.parse(sessionStorage.getItem('pertumbuhanDataStep3') || 'null');
      if (savedPertumbuhan) {
        if (savedPertumbuhan.tinggiBadan) document.getElementById('tinggiBadan').value = savedPertumbuhan.tinggiBadan;
        if (savedPertumbuhan.beratBadan) document.getElementById('beratBadan').value = savedPertumbuhan.beratBadan;
      }
    });

    document.getElementById('form-data-pertumbuhan').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const tinggiBadan = parseFloat(document.getElementById('tinggiBadan').value);
      const beratBadan = parseFloat(document.getElementById('beratBadan').value);
      
      if (tinggiBadan <= 0 || beratBadan <= 0) {
        alert('Mohon masukkan nilai tinggi dan berat badan yang valid');
        return;
      }
      
      const formData = {
        tinggiBadan: tinggiBadan,
        beratBadan: beratBadan
      };

      sessionStorage.setItem('pertumbuhanDataStep3', JSON.stringify(formData));
      window.location.href = '/review-ortu';
    });

    document.getElementById('btn-kembali').addEventListener('click', function() {
      window.location.href = '/data-dirianak';
    });
  </script>
</body>
</html>
