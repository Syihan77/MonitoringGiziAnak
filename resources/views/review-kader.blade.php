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
      // 1. Ambil data dari sessionStorage
      const storedData = sessionStorage.getItem('kaderData');
      
      if (!storedData) {
        alert('Data tidak ditemukan. Silakan isi formulir terlebih dahulu.');
        window.location.href = '/data-kader';
        return;
      }

      const data = JSON.parse(storedData);

      // 2. Tampilkan data ke HTML Review
      document.getElementById('review-nama').textContent = data.nama;
      document.getElementById('review-nip').textContent = data.nip;
      
      // Format Tanggal Lahir dan Hitung Umur Otomatis
      const dateObj = new Date(data.tglLahir);
      const options = { day: 'numeric', month: 'long', year: 'numeric' };
      const formattedDate = dateObj.toLocaleDateString('id-ID', options);
      
      const today = new Date();
      let age = today.getFullYear() - dateObj.getFullYear();
      const monthDiff = today.getMonth() - dateObj.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dateObj.getDate())) {
        age--;
      }
      
      document.getElementById('review-tgl').textContent = `${formattedDate} (${age} Tahun)`;
      document.getElementById('review-alamat').innerHTML = data.alamat.replace(/\n/g, '<br>');

      // 3. Handle Tombol "Simpan & Lanjutkan" -> register + login via API.
      const btnSubmit = document.getElementById('btn-final-submit');

      btnSubmit.addEventListener('click', async () => {
        const regAkun = JSON.parse(sessionStorage.getItem('regAkun') || 'null');
        if (!regAkun || !regAkun.email || !regAkun.password) {
          alert('Data akun tidak ditemukan. Silakan ulangi dari halaman daftar.');
          window.location.href = '/daftar';
          return;
        }

        const originalText = btnSubmit.innerHTML;
        btnSubmit.innerHTML = 'Menyimpan data...';
        btnSubmit.disabled = true;
        btnSubmit.style.opacity = '0.7';

        try {
          try {
            await window.NutriApi.register({
              nama: data.nama,
              email: regAkun.email,
              password: regAkun.password,
              role: 'kader',
              wilayah_posyandu: data.wilayahPosyandu,
              no_telp: data.noTelp || undefined,
              consent_approved: true,
            });
          } catch (err) {
            if (!(err && err.error_code === 'VALIDATION_ERROR' && err.errors && err.errors.email)) throw err;
          }

          await window.NutriApi.login(regAkun.email, regAkun.password);

          sessionStorage.removeItem('regAkun');
          sessionStorage.removeItem('kaderData');
          sessionStorage.setItem('currentUser', JSON.stringify({ nama: data.nama, role: 'kader' }));

          window.location.href = '/db-kader';
        } catch (err) {
          btnSubmit.innerHTML = originalText;
          btnSubmit.disabled = false;
          btnSubmit.style.opacity = '1';
          alert((err && err.message) || 'Penyimpanan gagal. Coba lagi.');
        }
      });
    });
  </script>
</body>
</html>
