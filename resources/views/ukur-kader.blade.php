<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Catat Pengukuran - Kader NutriTumbuh</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/globals.css', 'resources/css/data-pertumbuhan.css', 'resources/css/ukur-kader.css'])
</head>
<body>
  <div class="pertumbuhan-page">
    <div class="pertumbuhan-card">

      <div class="pertumbuhan-header">
        <img class="pertumbuhan-logo" src="/img/NutrisiTumbuh-Logo.png" alt="Logo NutriTumbuh">
        <h1 class="pertumbuhan-title">Catat Pengukuran Anak Binaan</h1>
        <p class="pertumbuhan-subtitle">Hasil tersimpan dan langsung terlihat orang tua</p>
      </div>

      <form class="pertumbuhan-form" id="form-ukur-kader">

        <div class="input-group">
          <label class="input-label" for="anak-select">Anak Binaan <span class="required">*</span></label>
          <div class="input-box">
            <select id="anak-select" name="anak_id" required>
              <option value="">Memuat daftar anak...</option>
            </select>
          </div>
        </div>

        <div class="measurement-grid">

          <div class="measurement-card">
            <div class="measurement-icon">
              <img src="/img/icon-ruler.png" alt="Ruler">
            </div>
            <h3 class="measurement-label">Tinggi Badan</h3>
            <div class="measurement-input-wrapper">
              <input type="number" id="tinggiBadan" name="tinggiBadan"
                     placeholder="0.0" step="0.1" min="30" max="150" required>
              <span class="measurement-unit">cm</span>
            </div>
          </div>

          <div class="measurement-card">
            <div class="measurement-icon">
              <img src="/img/icon-scale.png" alt="Scale">
            </div>
            <h3 class="measurement-label">Berat Badan</h3>
            <div class="measurement-input-wrapper">
              <input type="number" id="beratBadan" name="beratBadan"
                     placeholder="0.0" step="0.1" min="0.5" max="40" required>
              <span class="measurement-unit">kg</span>
            </div>
          </div>

        </div>

        <div class="input-group">
          <label class="input-label" for="posisiUkur">Posisi Ukur <span class="required">*</span></label>
          <div class="input-box">
            <select id="posisiUkur" name="posisiUkur" required>
              <option value="">Pilih posisi ukur...</option>
              <option value="terlentang">Terlentang (tidur)</option>
              <option value="berdiri">Berdiri</option>
            </select>
          </div>
          <span class="helper-text">Wajib diisi kader agar koreksi tinggi tepat</span>
        </div>

        <div class="input-group">
          <label class="input-label" for="tanggalUkur">Tanggal Ukur <span class="required">*</span></label>
          <div class="input-box">
            <img class="input-icon" src="/img/tgl.png" alt="Calendar">
            <input type="date" id="tanggalUkur" name="tanggalUkur" required>
          </div>
        </div>

        <p id="ukur-error" style="display:none;color:#b91c1c;font-size:14px;" role="alert"></p>

        <div id="ukur-hasil" style="display:none;" class="hasil-box"></div>

        <div class="form-action-two-buttons">
          <button type="button" class="btn-secondary" id="btn-kembali">Kembali</button>
          <button type="submit" class="btn-submit" id="btn-simpan">Simpan Pengukuran</button>
        </div>

      </form>
    </div>
  </div>

    @vite(['resources/js/api.js'])
    @vite(['resources/js/ukur-kader.js'])
</body>
</html>
