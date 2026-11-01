<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Informasi Data Diri Anak - NutriTumbuh</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/globals.css', 'resources/css/data-dirianak.css'])
</head>
<body>
  <div class="anak-page">
    <div class="anak-card">
      
      <div class="anak-header">
        <img class="anak-logo" src="/img/NutrisiTumbuh-Logo.png" alt="Logo NutriTumbuh">
        <h1 class="anak-title">Informasi Data Diri Anak</h1>
        <p class="anak-subtitle">Mohon masukkan data diri sesuai yang diperintahkan</p>
        
        <div class="stepper-container">
          <div class="stepper-line"></div>
          <div class="stepper-steps">
            <div class="step">
              <div class="circle">1</div>
              <div class="step-text">Data Diri<br>Orang Tua</div>
            </div>
            <div class="step">
              <div class="circle active">2</div>
              <div class="step-text active-text">Data Diri<br>Anak</div>
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

      <form class="anak-form" id="form-data-anak">
        
        <div class="input-group">
          <label class="input-label">Nama Lengkap <span class="required">*</span></label>
          <div class="input-box">
            <img class="input-icon" src="/img/orang.png" alt="User">
            <input type="text" id="namaAnak" name="namaAnak" placeholder="Tekan untuk mengisi nama lengkap" required>
          </div>
          <span class="helper-text">Sesuai dengan yang ada di dokumen resmi anda</span>
        </div>

        <div class="input-group">
          <label class="input-label">Date of Birth <span class="required">*</span></label>
          <div class="input-box">
            <img class="input-icon" src="/img/tgl.png" alt="Calendar">
            <input type="date" id="tanggalLahirAnak" name="tanggalLahirAnak" required>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Jenis Kelamin <span class="required">*</span></label>
          <div class="radio-group">
            <label class="radio-label">
              <input type="radio" name="jenisKelamin" value="Laki-laki" required>
              <span class="radio-text">Laki-laki</span>
            </label>
            <label class="radio-label">
              <input type="radio" name="jenisKelamin" value="Perempuan" required>
              <span class="radio-text">Perempuan</span>
            </label>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Alamat Tempat Tinggal <span class="required">*</span></label>
          <div class="input-box textarea-box">
            <img class="input-icon" src="/img/icon-location.png" alt="Location">
            <textarea id="alamatAnak" name="alamatAnak" placeholder="Masukkan alamat, Rt/Rw, Kecamatan, Kelurahan, Kota" required></textarea>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Alergi / Pantangan Makanan</label>
          <div id="alergi-options" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;">
            <label class="radio-label"><input type="checkbox" name="alergi" value="telur"><span class="radio-text">Telur</span></label>
            <label class="radio-label"><input type="checkbox" name="alergi" value="ikan"><span class="radio-text">Ikan</span></label>
            <label class="radio-label"><input type="checkbox" name="alergi" value="udang/ebi"><span class="radio-text">Udang / Ebi</span></label>
            <label class="radio-label"><input type="checkbox" name="alergi" value="susu sapi/olahannya"><span class="radio-text">Susu Sapi</span></label>
            <label class="radio-label"><input type="checkbox" name="alergi" value="kedelai"><span class="radio-text">Kedelai</span></label>
            <label class="radio-label"><input type="checkbox" name="alergi" value="gluten"><span class="radio-text">Gluten</span></label>
            <label class="radio-label"><input type="checkbox" name="alergi" value="wijen"><span class="radio-text">Wijen</span></label>
            <label class="radio-label"><input type="checkbox" name="alergi" value="kacang-kacangan"><span class="radio-text">Kacang-kacangan</span></label>
          </div>
          <span class="helper-text">Pilih jika anak memiliki alergi atau pantangan. Dapat diubah dari dashboard.</span>
        </div>

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
    // Load data from previous step if exists (optional)
    document.addEventListener('DOMContentLoaded', function() {
      // Tanggal lahir tidak boleh melebihi hari ini (backend menolak via validasi).
      const tgl = document.getElementById('tanggalLahirAnak');
      if (tgl) tgl.max = new Date().toISOString().slice(0, 10);

      const ortuData = sessionStorage.getItem('ortuDataStep1');
      if (!ortuData) {
        // Jika tidak ada data step 1, kembali ke halaman awal
        alert('Silakan isi data orang tua terlebih dahulu');
        window.location.href = '/data-ortu';
      }
    });

    // Handle form submission
    document.getElementById('form-data-anak').addEventListener('submit', function(e) {
      e.preventDefault();
      
      // 1. Ambil data dari input
      const formData = {
        namaAnak: document.getElementById('namaAnak').value,
        tanggalLahirAnak: document.getElementById('tanggalLahirAnak').value,
        jenisKelamin: document.querySelector('input[name="jenisKelamin"]:checked').value,
        alamatAnak: document.getElementById('alamatAnak').value,
        pantangan: Array.from(document.querySelectorAll('input[name="alergi"]:checked'))
          .map((input) => ({ alergen: input.value, jenis: 'ALERGI' }))
      };

      // 2. Simpan ke sessionStorage
      sessionStorage.setItem('anakDataStep2', JSON.stringify(formData));

      // 3. Redirect ke halaman berikutnya (Step 3: Data Pertumbuhan Anak)
      window.location.href = '/data-pertumbuhan';
    });

    // Handle tombol Kembali
    document.getElementById('btn-kembali').addEventListener('click', function() {
      window.location.href = '/data-ortu';
    });
  </script>
</body>
</html>
