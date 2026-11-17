<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Informasi Data Diri Orang Tua - NutriTumbuh</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/globals.css', 'resources/css/data-ortu.css'])
</head>
<body>
  <div class="ortu-page">
    <div class="ortu-card">
      
      <div class="ortu-header">
        <img class="ortu-logo" src="/img/NutrisiTumbuh-Logo.png" alt="Logo NutriTumbuh">
        <h1 class="ortu-title">Informasi Data Diri Orang Tua</h1>
        <p class="ortu-subtitle">Mohon masukkan data diri sesuai yang diperintahkan</p>
        
        <div class="stepper-container">
          <div class="stepper-line"></div>
          <div class="stepper-steps">
            <div class="step">
              <div class="circle active">1</div>
              <div class="step-text active-text">Data Diri<br>Orang Tua</div>
            </div>
            <div class="step">
              <div class="circle">2</div>
              <div class="step-text">Data Diri<br>Anak</div>
            </div>
            <div class="step">
              <div class="circle">3</div>
              <div class="step-text">Data Pertumbuhan<br>Anak</div>
            </div>
            <div class="step">
              <div class="circle">4</div>
              <div class="step-text">Tinjauan</div>
            </div>
          </div>
        </div>
      </div>

      <form class="ortu-form" id="form-data-ortu">
        
        <div class="input-group">
          <label class="input-label">Nama Lengkap <span class="required">*</span></label>
          <div class="input-box">
            <img class="input-icon" src="/img/orang.png" alt="User">
            <input type="text" id="namaLengkap" name="namaLengkap" placeholder="Tekan untuk mengisi nama lengkap" required>
          </div>
          <span class="helper-text">Sesuai dengan yang ada di dokumen resmi anda</span>
        </div>

        <div class="input-group">
          <label class="input-label">Date of Birth <span class="required">*</span></label>
          <div class="input-box">
            <img class="input-icon" src="/img/tgl.png" alt="Calendar">
            <input type="date" id="tanggalLahir" name="tanggalLahir" required>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Alamat Tempat Tinggal <span class="required">*</span></label>
          <div class="input-box textarea-box">
            <img class="input-icon" src="/img/icon-location.png" alt="Location">
            <textarea id="alamat" name="alamat" placeholder="Masukkan alamat, Rt/Rw, Kecamatan, Kelurahan, Kota" required></textarea>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Wilayah Posyandu <span class="required">*</span></label>
          <div class="input-box">
            <img class="input-icon" src="/img/icon-location.png" alt="Location">
            <input type="text" id="wilayahPosyandu" name="wilayahPosyandu" placeholder="cth: Posyandu Melati" required>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">No. Telepon</label>
          <div class="input-box">
            <img class="input-icon" src="/img/orang.png" alt="Phone">
            <input type="tel" id="noTelp" name="noTelp" placeholder="cth: 081234567890">
          </div>
        </div>

        <div class="form-action">
          <button type="submit" class="btn-submit">
            Lanjutkan <img src="/img/icon-arrow-right.png" alt="Arrow">
          </button>
        </div>

      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Batasi tanggal maksimal hari ini
      const tgl = document.getElementById('tanggalLahir');
      if (tgl) tgl.max = new Date().toISOString().slice(0, 10);

      // RESTORE DATA DARI SESSION JIKA SUDAH ADA
      const savedData = JSON.parse(sessionStorage.getItem('ortuDataStep1') || 'null');
      if (savedData) {
        if (savedData.namaLengkap) document.getElementById('namaLengkap').value = savedData.namaLengkap;
        if (savedData.tanggalLahir) document.getElementById('tanggalLahir').value = savedData.tanggalLahir;
        if (savedData.alamat) document.getElementById('alamat').value = savedData.alamat;
        if (savedData.wilayahPosyandu) document.getElementById('wilayahPosyandu').value = savedData.wilayahPosyandu;
        if (savedData.noTelp) document.getElementById('noTelp').value = savedData.noTelp;
      }
    });

    document.getElementById('form-data-ortu').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = {
        namaLengkap: document.getElementById('namaLengkap').value,
        tanggalLahir: document.getElementById('tanggalLahir').value,
        alamat: document.getElementById('alamat').value,
        wilayahPosyandu: document.getElementById('wilayahPosyandu').value,
        noTelp: document.getElementById('noTelp').value
      };

      sessionStorage.setItem('ortuDataStep1', JSON.stringify(formData));
      window.location.href = '/data-dirianak';
    });
  </script>
</body>
</html>
