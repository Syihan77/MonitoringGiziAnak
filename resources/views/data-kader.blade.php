<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Informasi Data Diri Kader - NutriTumbuh</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/globals.css', 'resources/css/data-kader.css'])
</head>
<body>
  <div class="kader-page">
    <div class="kader-card">
      
      <div class="kader-header">
        <img class="logo" src="/img/NutrisiTumbuh-Logo.png" alt="Logo NutriTumbuh">
        <h1 class="title">Informasi Data Diri Kader Posyandu</h1>
        <p class="subtitle">Mohon masukkan data diri sesuai yang diperintahkan</p>
        
        <div class="stepper-container">
          <div class="stepper-line"></div>
          <div class="stepper-steps">
            <div class="step">
              <div class="circle active">1</div>
              <div class="step-text active-text">Data Diri<br>Kader</div>
            </div>
            <div class="step">
              <div class="circle">2</div>
              <div class="step-text">Tinjauan</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Hapus action & method, kita handle pakai JavaScript -->
      <form class="kader-form" id="form-data-kader">
        
        <div class="input-group">
          <label class="input-label">Nama Lengkap <span class="required">*</span></label>
          <div class="input-box">
            <img class="input-icon" src="/img/orang.png" alt="User">
            <!-- TAMBAHKAN id dan name -->
            <input type="text" id="nama" name="nama" placeholder="Tekan untuk mengisi nama lengkap" required>
          </div>
          <span class="helper-text">Sesuai dengan yang ada di dokumen resmi anda</span>
        </div>

        <div class="input-group">
          <label class="input-label">Nomor Induk Pegawai <span class="required">*</span></label>
          <div class="input-box">
            <img class="input-icon" src="/img/orang.png" alt="ID">
            <input type="text" id="nip" name="nip" placeholder="Tekan untuk memasukkan nomor id" required>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Date of Birth <span class="required">*</span></label>
          <div class="input-box">
            <img class="input-icon" src="/img/tgl.png" alt="Calendar">
            <input type="date" id="tglLahir" name="tglLahir" required>
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
          <label class="input-label">Wilayah Posyandu Binaan <span class="required">*</span></label>
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
    // Tangkap event submit form
    document.getElementById('form-data-kader').addEventListener('submit', function(e) {
      e.preventDefault(); // Mencegah reload halaman
      
      // 1. Ambil data dari input berdasarkan ID
      const formData = {
        nama: document.getElementById('nama').value,
        nip: document.getElementById('nip').value,
        tglLahir: document.getElementById('tglLahir').value,
        alamat: document.getElementById('alamat').value,
        wilayahPosyandu: document.getElementById('wilayahPosyandu').value,
        noTelp: document.getElementById('noTelp').value
      };

      // 2. Simpan ke sessionStorage sebagai string JSON
      sessionStorage.setItem('kaderData', JSON.stringify(formData));

      // 3. Redirect ke halaman review
      window.location.href = '/review-kader';
    });
  </script>
</body>
</html>
