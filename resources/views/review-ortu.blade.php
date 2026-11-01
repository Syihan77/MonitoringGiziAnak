<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tinjauan Data Orang Tua - NutriTumbuh</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/globals.css', 'resources/css/review-ortu.css'])
    @vite(['resources/js/api.js'])
</head>
<body>
  <div class="review-ortu-page">
    <div class="review-ortu-card">
      
      <!-- HEADER -->
      <div class="review-ortu-header">
        <img class="review-ortu-logo" src="/img/NutrisiTumbuh-Logo.png" alt="Logo NutriTumbuh">
        <h1 class="review-ortu-title">Tinjauan Data Pendaftaran</h1>
        <p class="review-ortu-subtitle">Tinjau dan Konfirmasi Data Anda</p>
        
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
              <div class="circle">3</div>
              <div class="step-text">Data Pertumbuhan<br>Anak</div>
            </div>
            <div class="step">
              <div class="circle active">4</div>
              <div class="step-text active-text">Tinjauan</div>
            </div>
          </div>
        </div>
      </div>

      <!-- BODY -->
      <div class="review-ortu-body">
        
        <!-- Section 1: Data Diri Orang Tua/Wali -->
        <div class="review-section">
          <div class="section-header">
            <div class="section-title">
              <div class="icon-circle">
                <x-icon name="user" />
              </div>
              <h2>Data Diri Pengguna / Wali</h2>
            </div>
            <a href="/data-ortu" class="btn-edit">
              <img src="/img/icon-edit.png" alt="Edit"> Ubah Data Diri
            </a>
          </div>

          <div class="data-list">
            <div class="data-row">
              <span class="data-label">Nama Lengkap</span>
              <span class="data-value" id="review-nama-ortu">-</span>
            </div>
            <div class="data-row highlight">
              <span class="data-label">Tanggal Lahir</span>
              <span class="data-value" id="review-tgl-ortu">-</span>
            </div>
            <div class="data-row highlight">
              <span class="data-label">Alamat Tinggal</span>
              <span class="data-value" id="review-alamat-ortu">-</span>
            </div>
          </div>
        </div>

        <!-- Section 2: Data Anak & Pengukuran Fisik -->
        <div class="review-section">
          <div class="section-header">
            <div class="section-title">
              <div class="icon-circle">
                <img src="/img/orang.png" alt="Child">
              </div>
              <h2>Data Anak & Pengukuran Fisik</h2>
            </div>
            <a href="/data-dirianak" class="btn-edit">
              <img src="/img/icon-edit.png" alt="Edit"> Ubah Data Anak
            </a>
          </div>

          <!-- Card Profil Anak -->
          <div class="child-profile-card">
            <div class="avatar-initial avatar-md" id="review-avatar-anak">?</div>
            <div class="child-info">
              <h3 class="child-name" id="review-nama-anak">-</h3>
            <div class="child-meta">
                <span class="child-gender" id="review-gender-anak">-</span>
                <span class="child-birth" id="review-tgl-anak">-</span>
                <span class="child-age" id="review-usia-anak">-</span>
            </div>
            <p class="helper-text" style="margin:8px 0 0;">Alergi / pantangan: <strong id="review-alergi-anak">Tidak ada</strong></p>
          </div>
          </div>

          <!-- Detail Pengukuran -->
          <div class="measurement-detail">
            <p class="measurement-label">DETAIL PENGUKURAN AWAL</p>
            <div class="measurement-grid">
              <div class="measurement-item">
                <div class="measurement-header">
                  <span class="measurement-name">Tinggi Badan</span>
                  <img src="/img/icon-ruler.png" alt="Ruler" class="measurement-icon-small">
                </div>
                <div class="measurement-value">
                  <span class="value-number" id="review-tinggi">-</span>
                  <span class="value-unit">cm</span>
                </div>
              </div>
              <div class="measurement-item">
                <div class="measurement-header">
                  <span class="measurement-name">Berat Badan</span>
                  <img src="/img/icon-scale.png" alt="Scale" class="measurement-icon-small">
                </div>
                <div class="measurement-value">
                  <span class="value-number" id="review-berat">-</span>
                  <span class="value-unit">kg</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="form-action-two-buttons">
          <button type="button" class="btn-secondary" id="btn-kembali">
            <img src="/img/icon-arrow-left.png" alt="Kembali"> Kembali
          </button>
          <button type="button" class="btn-submit" id="btn-simpan">
            Simpan & Lanjutkan <img src="/img/icon-arrow-right.png" alt="Lanjut">
          </button>
        </div>

      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // 1. Ambil data dari sessionStorage
      const dataOrtu = JSON.parse(sessionStorage.getItem('ortuDataStep1'));
      const dataAnak = JSON.parse(sessionStorage.getItem('anakDataStep2'));
      const dataPertumbuhan = JSON.parse(sessionStorage.getItem('pertumbuhanDataStep3'));

      if (!dataOrtu || !dataAnak || !dataPertumbuhan) {
        alert('Data tidak lengkap. Silakan isi formulir dari awal.');
        window.location.href = '/data-ortu';
        return;
      }

      // 2. Tampilkan Data Orang Tua
      document.getElementById('review-nama-ortu').textContent = dataOrtu.namaLengkap;
      
      const dateOrtu = new Date(dataOrtu.tanggalLahir);
      const options = { day: 'numeric', month: 'long', year: 'numeric' };
      const formattedDateOrtu = dateOrtu.toLocaleDateString('id-ID', options);
      
      const today = new Date();
      let ageOrtu = today.getFullYear() - dateOrtu.getFullYear();
      const monthDiffOrtu = today.getMonth() - dateOrtu.getMonth();
      if (monthDiffOrtu < 0 || (monthDiffOrtu === 0 && today.getDate() < dateOrtu.getDate())) {
        ageOrtu--;
      }
      document.getElementById('review-tgl-ortu').textContent = `${formattedDateOrtu} (${ageOrtu} Tahun)`;
      document.getElementById('review-alamat-ortu').textContent = dataOrtu.alamat;

      // 3. Tampilkan Data Anak
      document.getElementById('review-nama-anak').textContent = dataAnak.namaAnak;
      const avAnak = document.getElementById('review-avatar-anak');
      if (avAnak && dataAnak.namaAnak) {
        avAnak.textContent = dataAnak.namaAnak.trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
      }
      document.getElementById('review-gender-anak').textContent = dataAnak.jenisKelamin;
      
      const dateAnak = new Date(dataAnak.tanggalLahirAnak);
      const formattedDateAnak = dateAnak.toLocaleDateString('id-ID', options);
      document.getElementById('review-tgl-anak').textContent = formattedDateAnak;

      // Hitung usia anak dalam tahun dan bulan
      let years = today.getFullYear() - dateAnak.getFullYear();
      let months = today.getMonth() - dateAnak.getMonth();
      if (months < 0 || (months === 0 && today.getDate() < dateAnak.getDate())) {
        years--;
        months += 12;
      }
      document.getElementById('review-usia-anak').textContent = `Usia ${years} Th ${months} Bln`;

      const alergiAnak = Array.isArray(dataAnak.pantangan) ? dataAnak.pantangan : [];
      document.getElementById('review-alergi-anak').textContent = alergiAnak.length
        ? alergiAnak.map((item) => item.alergen).filter(Boolean).join(', ')
        : 'Tidak ada';

      // 4. Tampilkan Data Pertumbuhan
      document.getElementById('review-tinggi').textContent = dataPertumbuhan.tinggiBadan;
      document.getElementById('review-berat').textContent = dataPertumbuhan.beratBadan;

      // 5. Handle Tombol Kembali
      document.getElementById('btn-kembali').addEventListener('click', () => {
        window.location.href = '/data-pertumbuhan';
      });

      // 6. Handle Tombol Simpan & Lanjutkan -> register + simpan anak + ukur via API.
      const btnSimpan = document.getElementById('btn-simpan');
      btnSimpan.addEventListener('click', async () => {
        const originalText = btnSimpan.innerHTML;
        const resetBtn = () => {
          btnSimpan.innerHTML = originalText;
          btnSimpan.disabled = false;
          btnSimpan.style.opacity = '1';
        };

        const regAkun = JSON.parse(sessionStorage.getItem('regAkun') || 'null');
        if (!regAkun || !regAkun.email || !regAkun.password) {
          alert('Data akun tidak ditemukan. Silakan ulangi dari halaman daftar.');
          window.location.href = '/daftar';
          return;
        }

        btnSimpan.innerHTML = 'Menyimpan data...';
        btnSimpan.disabled = true;
        btnSimpan.style.opacity = '0.7';

        try {
          // 1. Registrasi (abaikan bila email sudah terdaftar -> lanjut login).
          try {
            await window.NutriApi.register({
              nama: dataOrtu.namaLengkap,
              email: regAkun.email,
              password: regAkun.password,
              role: 'orang_tua',
              wilayah_posyandu: dataOrtu.wilayahPosyandu,
              no_telp: dataOrtu.noTelp || undefined,
              consent_approved: true,
            });
          } catch (err) {
            if (!(err && err.error_code === 'VALIDATION_ERROR' && err.errors && err.errors.email)) throw err;
          }

          // 2. Login utk token.
          await window.NutriApi.login(regAkun.email, regAkun.password);

          // 3. Simpan anak.
          const jk = (dataAnak.jenisKelamin || '').toLowerCase().startsWith('laki') ? 'L' : 'P';
          const anak = await window.NutriApi.anakCreate({
            nama: dataAnak.namaAnak,
            tanggal_lahir: dataAnak.tanggalLahirAnak,
            jenis_kelamin: jk,
            alamat: dataAnak.alamatAnak || undefined,
            pantangan: Array.isArray(dataAnak.pantangan) ? dataAnak.pantangan : [],
          });

          // 4. Simpan pengukuran awal (tanggal ukur = hari ini).
          const today = new Date().toISOString().slice(0, 10);
          const ukur = await window.NutriApi.ukurCreate(anak.anak_id, {
            berat_badan_kg: Number(dataPertumbuhan.beratBadan),
            tinggi_badan_cm: Number(dataPertumbuhan.tinggiBadan),
            tanggal_ukur: today,
          });

          // 5. Bersihkan data wizard (termasuk password transit).
          ['regAkun', 'ortuDataStep1', 'anakDataStep2', 'pertumbuhanDataStep3', 'registrationComplete']
            .forEach((k) => sessionStorage.removeItem(k));
          sessionStorage.setItem('lastAnakId', anak.anak_id);
          sessionStorage.setItem('lastStatusGizi', ukur.status_gizi_antropometri);

          window.location.href = '/db-ortu';
        } catch (err) {
          resetBtn();
          alert(pesanGalat(err));
        }
      });

      function pesanGalat(err) {
        if (!err || !err.error_code) return 'Penyimpanan gagal. Periksa koneksi lalu coba lagi.';
        if (err.error_code === 'CONSENT_REQUIRED') return 'Pendaftaran memerlukan persetujuan pengolahan data.';
        if (err.error_code === 'VALIDATION_ERROR' && err.errors) {
          const det = Object.entries(err.errors).map(([k, v]) => `${k}: ${[].concat(v).join(', ')}`).join('\n');
          return `Data belum valid:\n${det}`;
        }
        return err.message || 'Penyimpanan gagal. Coba lagi.';
      }
    });
  </script>
</body>
</html>
