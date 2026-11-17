<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tinjauan Data Kader - NutriTumbuh</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/globals.css', 'resources/css/review-kader.css'])
    @vite(['resources/js/api.js'])
</head>
<body>
  <div class="kader-page">
    <div class="kader-card">
      
      <div class="kader-header">
        <img class="logo" src="/img/NutrisiTumbuh-Logo.png" alt="Logo NutriTumbuh">
        <h1 class="title">Informasi Data Diri Kader Posyandu</h1>
        <p class="subtitle">Tinjau dan Konfirmasi Data Anda</p>
        
        <div class="stepper-container">
          <div class="stepper-line"></div>
          <div class="stepper-steps">
            <div class="step">
              <div class="circle">1</div>
              <div class="step-text">Data Diri<br>Kader</div>
            </div>
            <div class="step">
              <div class="circle active">2</div>
              <div class="step-text active-text">Tinjauan</div>
            </div>
          </div>
        </div>
      </div>

      <div class="review-body">
        
        <div class="review-section-header">
          <div class="section-title">
            <div class="icon-circle">
              <img src="/img/icon-user-dark.png" alt="User">
            </div>
            <h2>Data Diri Kader</h2>
          </div>
          <a href="/data-kader" class="btn-edit">
            <img src="/img/icon-edit.png" alt="Edit"> Ubah Data Diri
          </a>
        </div>

        <!-- TAMBAHKAN ID PADA SETIAP DATA-VALUE -->
        <div class="data-list">
          <div class="data-row">
            <span class="data-label">Nama Lengkap</span>
            <span class="data-value" id="review-nama">-</span>
          </div>
          <div class="data-row">
            <span class="data-label">Nomor Induk Pegawai</span>
            <span class="data-value" id="review-nip">-</span>
          </div>
          <div class="data-row highlight">
            <span class="data-label">Tanggal Lahir</span>
            <span class="data-value" id="review-tgl">-</span>
          </div>
          <div class="data-row highlight">
            <span class="data-label">Alamat Tinggal</span>
            <span class="data-value" id="review-alamat">-</span>
          </div>
        </div>

        <div class="review-action-footer">
          <a href="/data-kader" class="btn-back">
            <img src="/img/icon-arrow-left.png" alt="Kembali"> Kembali
          </a>
          <!-- Tombol ini nanti akan mengirim data ke backend -->
          <button type="button" id="btn-final-submit" class="btn-submit">
            Simpan & Lanjutkan <img src="/img/icon-arrow-right.png" alt="Lanjut">
          </button>
        </div>
        
      </div>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const dataKader = JSON.parse(sessionStorage.getItem('kaderData') || sessionStorage.getItem('kaderDataStep1') || 'null');

    if (!dataKader) {
      alert('Data tidak ditemukan. Silakan isi formulir terlebih dahulu.');
      window.location.href = '/data-kader';
      return;
    }

    // 1. Tampilkan Nama Lengkap
    const elNama = document.getElementById('review-nama') || document.getElementById('review-nama-kader');
    if (elNama) {
      elNama.textContent = dataKader.nama || dataKader.namaLengkap || '-';
    }

    // 2. Tampilkan NIP
    const elNip = document.getElementById('review-nip') || document.getElementById('review-nip-kader');
    if (elNip) {
      elNip.textContent = dataKader.nip || dataKader.nomorIndukPegawai || '-';
    }

    // 3. Tampilkan Tanggal Lahir & Usia
    const elTgl = document.getElementById('review-tgl') || document.getElementById('review-tgl-kader');
    const rawDate = dataKader.tglLahir || dataKader.tanggalLahir;
    if (elTgl && rawDate) {
      const dateKader = new Date(rawDate);
      const options = { day: 'numeric', month: 'long', year: 'numeric' };
      const formattedDate = dateKader.toLocaleDateString('id-ID', options);
      
      const today = new Date();
      let age = today.getFullYear() - dateKader.getFullYear();
      const m = today.getMonth() - dateKader.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < dateKader.getDate())) {
        age--;
      }
      elTgl.textContent = `${formattedDate} (${age} Tahun)`;
    }

    // 4. Tampilkan Alamat
    const elAlamat = document.getElementById('review-alamat') || document.getElementById('review-alamat-kader');
    if (elAlamat) {
      elAlamat.textContent = dataKader.alamat || '-';
    }

    // 5. Tombol Simpan & Lanjutkan
    const btnSubmit = document.getElementById('btn-final-submit');
    if (btnSubmit) {
      btnSubmit.addEventListener('click', async () => {
        const regAkun = JSON.parse(sessionStorage.getItem('regAkun') || 'null');
        const email = regAkun?.email;
        const password = regAkun?.password;

        if (!email || !password) {
          alert('Data akun pendaftaran tidak ditemukan. Silakan daftar dari awal.');
          window.location.href = '/daftar';
          return;
        }

        const originalText = btnSubmit.innerHTML;
        btnSubmit.innerHTML = 'Menyimpan...';
        btnSubmit.disabled = true;

        try {
          if (window.NutriApi) {
            try {
              await window.NutriApi.register({
                nama: dataKader.nama || dataKader.namaLengkap,
                email: email,
                password: password,
                role: 'kader',
                wilayah_posyandu: dataKader.wilayahPosyandu || 'Posyandu Melati',
                no_telp: dataKader.noTelp || undefined,
                consent_approved: true
              });
            } catch (err) {
              if (!(err && err.error_code === 'VALIDATION_ERROR' && err.errors && err.errors.email)) {
                throw err;
              }
            }

            await window.NutriApi.login(email, password);
          }

          sessionStorage.removeItem('kaderData');
          sessionStorage.removeItem('kaderDataStep1');
          sessionStorage.removeItem('regAkun');

          window.location.href = '/db-kader';
        } catch (err) {
          btnSubmit.innerHTML = originalText;
          btnSubmit.disabled = false;
          alert((err && err.message) || 'Gagal menyimpan data.');
        }
      });
    }
  });
</script>
</body>
</html>
