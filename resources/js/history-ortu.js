/**
 * History Orang Tua - Riwayat Anak (DUAL LAYOUT)
 * File: history-ortu.js
 * 
 * LAYOUT 1 (Default): Timeline + AI + Catatan + Status Gizi
 * LAYOUT 2 (Setelah pilih bulan): Grid 4 Kolom
 */

document.addEventListener('DOMContentLoaded', async () => {

  // ============================================
  // 1. AMBIL DATA: utama dari API, fallback sessionStorage (alur lama)
  // ============================================
  const legacyOrtu = safeParse(sessionStorage.getItem('ortuDataStep1'));
  const legacyAnak = safeParse(sessionStorage.getItem('anakDataStep2'));
  const legacyGrowth = safeParse(sessionStorage.getItem('pertumbuhanDataStep3'));

  let namaAnak = (legacyAnak && legacyAnak.namaAnak) || 'Anak NutriTumbuh';
  let tglLahirAnak = legacyAnak && legacyAnak.tanggalLahirAnak;
  let berat = legacyGrowth && legacyGrowth.beratBadan;
  let tinggi = legacyGrowth && legacyGrowth.tinggiBadan;
  let namaOrtu = legacyOrtu && legacyOrtu.namaLengkap;
  let kategoriTerakhir = null;
  let pesanKategori = null;

  if (window.NutriApi && window.NutriApi.getToken()) {
    try {
      const list = await window.NutriApi.anakList();
      const items = Array.isArray(list) ? list : (list && list.data) || [];
      if (items.length > 0) {
        const detail = await window.NutriApi.anakDetail(items[0].anak_id);
        namaAnak = detail.nama;
        tglLahirAnak = detail.tanggal_lahir;

        const riwayat = await window.NutriApi.ukurRiwayat(items[0].anak_id);
        const arr = Array.isArray(riwayat) ? riwayat : [];
        if (arr.length > 0) {
          const terakhir = arr.reduce((a, b) =>
            new Date(a.tanggal_ukur) > new Date(b.tanggal_ukur) ? a : b
          );
          berat = terakhir.berat_kg;
          tinggi = terakhir.tinggi_cm;
          kategoriTerakhir = terakhir.kategori_pemantauan || null;
          pesanKategori = terakhir.pesan || null;
        }
      }
      const me = await window.NutriApi.me();
      if (me && me.nama) namaOrtu = me.nama;
    } catch (e) {
      console.warn('Gagal memuat data API, memakai data lokal:', e && e.message);
    }
  }

  if (!tglLahirAnak && !legacyAnak && !(window.NutriApi && window.NutriApi.getToken())) {
    alert('Silakan lakukan pendaftaran data anak terlebih dahulu.');
    window.location.href = '/data-ortu';
    return;
  }

  function safeParse(s) {
    try {
      return s ? JSON.parse(s) : null;
    } catch {
      return null;
    }
  }

  function inisial(nama) {
    if (!nama) return '?';
    return nama.trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
  }

  const avOrtu = document.getElementById('avatar-ortu');
  const avAnak = document.getElementById('avatar-anak');
  if (avOrtu && namaOrtu) avOrtu.textContent = inisial(namaOrtu);
  if (avAnak && namaAnak) avAnak.textContent = inisial(namaAnak);

  // ============================================
  // 2. FUNGSI HITUNG UMUR
  // ============================================
  function hitungUmur(tanggalLahir) {
    if (!tanggalLahir) return "-";
    const lahir = new Date(tanggalLahir);
    const hariIni = new Date();
    let tahun = hariIni.getFullYear() - lahir.getFullYear();
    let bulan = hariIni.getMonth() - lahir.getMonth();
    
    if (bulan < 0 || (bulan === 0 && hariIni.getDate() < lahir.getDate())) {
      tahun--;
      bulan += 12;
    }
    
    return tahun > 0 ? `${tahun} Tahun ${bulan} Bulan` : `${bulan} Bulan`;
  }

  // ============================================
  // 3. ISI DATA PROFIL KE HTML
  // ============================================
  const namaDepan = namaAnak.split(' ')[0];

  document.getElementById('profile-name').textContent = namaAnak;
  document.getElementById('breadcrumb-name').textContent = namaAnak;
  document.getElementById('profile-age').textContent = hitungUmur(tglLahirAnak);
  document.getElementById('ai-child-name').textContent = namaDepan;

  if (berat !== undefined && berat !== null) {
    document.getElementById('profile-bb').textContent = berat || '-';
    document.getElementById('profile-tb').textContent = tinggi || '-';
  }

  if (namaOrtu) {
    document.getElementById('profile-ibu').textContent = namaOrtu || '-';
  }

  const labelKategori = {
    NORMAL_LOW_RISK: 'Normal',
    AT_RISK_MONITORING: 'Perlu Pemantauan',
    HIGH_RISK_REFERRAL: 'Perlu Rujukan',
  };
  const statusTextBox = document.getElementById('profile-status-text');
  const statusBox = document.getElementById('profile-status');
  if (statusTextBox) statusTextBox.textContent = labelKategori[kategoriTerakhir] || 'Belum diukur';
  if (statusBox) {
    statusBox.className = 'status-value '
      + (kategoriTerakhir === 'HIGH_RISK_REFERRAL'
        ? 'buruk'
        : (kategoriTerakhir === 'AT_RISK_MONITORING' ? 'kurang' : 'normal'));
    if (pesanKategori) statusBox.title = pesanKategori;
  }

  // ============================================
  // 4. TIMELINE KONSUMSI REAL (7 hari terakhir, via API)
  // Akun baru tanpa konsumsi -> empty state, BUKAN data contoh.
  // ============================================
  function tanggalISO(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  function labelTanggal(d, pertama) {
    const s = d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    return pertama ? `Hari Ini, ${s}` : s;
  }

  function renderKonsumsiTimeline(hari) {
    const container = document.getElementById('timeline-container');
    container.innerHTML = '';

    if (hari.length === 0) {
      container.innerHTML = '<p style="text-align:center;color:#6b7280;padding:32px 16px;">'
        + 'Belum ada log makanan yang tercatat.<br>Catat konsumsi harian anak untuk melihat riwayat di sini.</p>';
      return;
    }

    hari.forEach((day) => {
      const item = document.createElement('div');
      item.className = `timeline-item ${day.current ? 'current' : ''}`;

      const daftar = day.items.map((it) =>
        `<div class="meal-card"><div class="meal-content">${it.nama} &middot; ${it.porsi}`
        + `${it.invalid ? ' <em>(dikecualikan)</em>' : ''}</div></div>`
      ).join('');

      item.innerHTML = `
        <div class="timeline-dot"></div>
        <div class="timeline-date">${day.tanggal}</div>
        ${day.sakit ? '<div style="margin:8px 0;padding:10px 14px;border-radius:10px;background:#fef3c7;color:#92400e;font-size:13px;">Anak tercatat sedang sakit — data konsumsi tidak digunakan dalam analisis.</div>' : ''}
        <div class="meals-grid">${daftar}</div>
      `;
      container.appendChild(item);
    });
  }

  async function muatTimelineKonsumsi(anakId) {
    const hasil = [];
    const hariIni = new Date();

    const daftarTanggal = [];
    for (let i = 0; i < 7; i++) {
      const d = new Date(hariIni);
      d.setDate(hariIni.getDate() - i);
      daftarTanggal.push(d);
    }

    const respons = await Promise.all(
      daftarTanggal.map((d) =>
        window.NutriApi.konsumsiList(anakId, tanggalISO(d))
          .then((items) => ({ d, items: Array.isArray(items) ? items : [] }))
          .catch(() => ({ d, items: [] }))
      )
    );

    respons.forEach(({ d, items }, idx) => {
      if (items.length === 0) return;
      hasil.push({
        tanggal: labelTanggal(d, idx === 0),
        current: idx === 0,
        sakit: items.some((it) => it.status_kondisi === 'SAKIT'),
        items: items.map((it) => ({
          nama: it.nama_bahan || 'Bahan',
          porsi: `${it.porsi_gram} g`,
          invalid: it.status_validitas === 'INVALID',
        })),
      });
    });

    renderKonsumsiTimeline(hasil);
  }

  // Anak aktif untuk filter bulan (diisi saat initial load).
  let anakAktifId = null;

  // ============================================
  // 6. GRID BULANAN DARI DATA REAL (gantikan mock)
  // ============================================
  function pastikanOpsiBulan() {
    const filterBulan = document.getElementById('filter-bulan');
    if (!filterBulan) return;
    const ada = new Set(Array.from(filterBulan.options).map((o) => o.value));
    const now = new Date();
    for (let i = 0; i < 12; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      const val = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
      if (!ada.has(val)) {
        const opt = document.createElement('option');
        opt.value = val;
        opt.textContent = d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
        filterBulan.appendChild(opt);
      }
    }
  }

  function akhirBulan(tahun, bulan1) {
    return new Date(tahun, bulan1, 0).getDate();
  }

  async function muatGridBulanan(bulanTerpilih, bulanLabel) {
    const gridLayout = document.getElementById('grid-layout');
    const timelineLayout = document.getElementById('timeline-layout');
    timelineLayout.style.display = 'none';
    gridLayout.style.display = 'block';

    if (!anakAktifId || !(window.NutriApi && window.NutriApi.getToken())) {
      renderDailyLog([], bulanLabel);
      return;
    }

    const [thn, bln] = bulanTerpilih.split('-').map(Number);
    const dari = `${thn}-${String(bln).padStart(2, '0')}-01`;
    const sampai = `${thn}-${String(bln).padStart(2, '0')}-${akhirBulan(thn, bln)}`;

    try {
      const items = await window.NutriApi.konsumsiRentang(anakAktifId, dari, sampai);
      const perTanggal = {};
      (Array.isArray(items) ? items : []).forEach((it) => {
        (perTanggal[it.tanggal_konsumsi] = perTanggal[it.tanggal_konsumsi] || []).push(it);
      });

      const data = Object.keys(perTanggal).sort().map((tgl) => {
        const [y, m, d] = tgl.split('-').map(Number);
        return {
          id: tgl,
          tanggal: new Date(y, m - 1, d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }),
          sakit: perTanggal[tgl].some((it) => it.status_kondisi === 'SAKIT'),
          items: perTanggal[tgl].map((it) => ({
            nama: it.nama_bahan || 'Bahan',
            porsi: `${it.porsi_gram} g`,
            invalid: it.status_validitas === 'INVALID',
          })),
        };
      });

      renderDailyLog(data, bulanLabel);
    } catch (e) {
      console.warn('Gagal memuat grid bulanan:', e && e.message);
      renderDailyLog([], bulanLabel);
    }
  }

  // ============================================
  // 7. RENDER GRID (LAYOUT 2)
  // ============================================
  function renderDailyLog(data, bulanLabel) {
    const gridContainer = document.getElementById('daily-log-grid');
    gridContainer.innerHTML = '';
    document.getElementById('grid-bulan-label').textContent = bulanLabel;

    if (data.length === 0) {
      gridContainer.innerHTML = '<p style="text-align: center; color: #6b7280; grid-column: 1/-1; padding: 40px;">Tidak ada data untuk bulan ini.</p>';
      return;
    }

    data.forEach((day) => {
      const card = document.createElement('div');
      card.className = 'daily-card';
      card.setAttribute('data-id', day.id);

      const rows = (day.items || []).map((it) => `
        <div class="meal-item">
          <div class="meal-radio checked" data-meal="konsumsi" data-id="${day.id}"></div>
          <div class="meal-info">
            <div class="meal-type">Konsumsi</div>
            <div class="meal-food">${it.nama} &middot; ${it.porsi}${it.invalid ? ' (dikecualikan)' : ''}</div>
          </div>
        </div>`).join('');

      card.innerHTML = `
        <div class="daily-header">
          <div>
            <div class="daily-date">${day.tanggal}</div>
            <div class="daily-meals-info">${(day.items || []).length} item tercatat</div>
          </div>
          <button class="btn-lengkap" data-id="${day.id}">${day.sakit ? 'Sakit' : 'Lengkap'}</button>
        </div>
        ${day.sakit ? '<div style="margin:0 0 10px;padding:8px 12px;border-radius:8px;background:#fef3c7;color:#92400e;font-size:12px;">Data hari sakit dikecualikan dari analisis.</div>' : ''}
        ${rows}
      `;
      gridContainer.appendChild(card);
    });

    attachGridEventListeners();
  }

  // ============================================
  // 8. EVENT LISTENERS GRID
  // ============================================
  function attachGridEventListeners() {
    document.querySelectorAll('.meal-radio').forEach(radio => {
      radio.addEventListener('click', (e) => {
        e.target.classList.toggle('checked');
      });
    });
  }

  // ============================================
  // 9. TOGGLE ANTARA TIMELINE DAN GRID
  // ============================================
  const filterBulan = document.getElementById('filter-bulan');
  const timelineLayout = document.getElementById('timeline-layout');
  const gridLayout = document.getElementById('grid-layout');

  if (filterBulan) {
    filterBulan.addEventListener('change', (e) => {
      const bulanTerpilih = e.target.value;

      if (bulanTerpilih) {
        const bulanLabel = e.target.options[e.target.selectedIndex].text;
        muatGridBulanan(bulanTerpilih, bulanLabel);
      } else {
        // KEMBALI KE TIMELINE
        timelineLayout.style.display = 'block';
        gridLayout.style.display = 'none';
      }
    });
  }

  // Tombol Kembali ke Timeline
  const btnKembaliTimeline = document.getElementById('btn-kembali-timeline');
  if (btnKembaliTimeline) {
    btnKembaliTimeline.addEventListener('click', () => {
      timelineLayout.style.display = 'block';
      gridLayout.style.display = 'none';
      filterBulan.value = ''; // Reset dropdown
    });
  }

  // ============================================
  // 10. SEARCH MAKANAN (Untuk kedua layout)
  // ============================================
  const searchInput = document.getElementById('search-makanan');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const keyword = e.target.value.toLowerCase();
      
      // Search di Timeline
      document.querySelectorAll('.meal-card').forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(keyword) || keyword === '') {
          card.style.opacity = '1';
        } else {
          card.style.opacity = '0.3';
        }
      });

      // Search di Grid
      document.querySelectorAll('.daily-card').forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(keyword) || keyword === '') {
          card.style.display = 'block';
          card.style.opacity = '1';
        } else {
          card.style.opacity = '0.3';
        }
      });
    });
  }

  // ============================================
  // 11. SIMPAN CATATAN
  // ============================================
  const btnSaveNote = document.getElementById('btn-save-note');
  if (btnSaveNote) {
    btnSaveNote.addEventListener('click', () => {
      const textarea = document.getElementById('daily-note');
      const noteText = textarea.value.trim();
      
      if (!noteText) {
        alert('Catatan tidak boleh kosong!');
        return;
      }

      const originalText = btnSaveNote.innerHTML;
        btnSaveNote.innerHTML = 'Menyimpan...';
      btnSaveNote.disabled = true;

      setTimeout(() => {
        alert(`Catatan untuk ${namaAnak} berhasil disimpan!`);
        textarea.value = '';
        btnSaveNote.innerHTML = originalText;
        btnSaveNote.disabled = false;
      }, 1000);
    });
  }

  // ============================================
  // 12. TOMBOL CETAK
  // ============================================
  const btnCetakRiwayat = document.getElementById('btn-cetak-riwayat');

  const handlePrint = () => {
    window.print();
  };

  if (btnCetakRiwayat) btnCetakRiwayat.addEventListener('click', handlePrint);

  // ============================================
  // 3b. STATUS PEMENUHAN GIZI BULANAN (real, 3 bulan terakhir)
  // ============================================
  const NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

  function labelBulan(ym) {
    const [y, m] = ym.split('-').map(Number);
    return `${NAMA_BULAN[m - 1]} ${y}`;
  }

  async function muatStatusBulanan(anakId) {
    const wadah = document.querySelector('.nutrition-status-list');
    if (!wadah || !anakId) return;
    wadah.classList.add('carousel-x');

    const now = new Date();
    const daftar = [];
    for (let i = 0; i < 12; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      daftar.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    }

    const hasil = await Promise.all(daftar.map((b) =>
      window.NutriApi.get(`/anak/${anakId}/gizi-bulanan?bulan=${b}`).catch(() => null)
    ));

    const meta = {
      'TERPENUHI': { dot: 'green', badge: 'fulfilled' },
      'KURANG TERPENUHI': { dot: 'yellow', badge: 'partial' },
      'TIDAK TERPENUHI': { dot: 'red', badge: 'unfulfilled' },
    };

    wadah.innerHTML = hasil.map((h, idx) => {
      if (!h) return '';
      if (h.status === 'belum_tercatat') {
        return `<div class="nutrition-status-item"><div class="status-dot gray"></div>`
          + `<div class="status-info"><div class="month-name">${labelBulan(daftar[idx])}</div>`
          + `<div class="month-desc">Belum ada catatan konsumsi.</div></div>`
          + `<div class="status-badge belum">BELUM TERCATAT</div></div>`;
      }
      const m = meta[h.status] || { dot: 'gray', badge: 'belum' };
      const saran = h.zat_terendah ? ` Perlu peningkatan konsumsi ${h.zat_terendah}.` : '';
      return `<div class="nutrition-status-item"><div class="status-dot ${m.dot}"></div>`
        + `<div class="status-info"><div class="month-name">${labelBulan(daftar[idx])}</div>`
        + `<div class="month-desc">${h.rata_persen_akg}% rata-rata pemenuhan (${h.hari_tercatat} hari tercatat).${saran}</div></div>`
        + `<div class="status-badge ${m.badge}">${h.status}</div></div>`;
    }).join('');

    pasangNavigasiKarosel(wadah);
  }

  // Dots + panah ala postingan IG: 1 slide penuh per tampilan.
  function pasangNavigasiKarosel(wadah) {
    wadah.parentElement.querySelectorAll('.gizi-nav').forEach((el) => el.remove());

    const jumlah = wadah.querySelectorAll('.nutrition-status-item').length;
    if (jumlah === 0) return;

    const nav = document.createElement('div');
    nav.className = 'gizi-nav';

    const btnPrev = document.createElement('button');
    btnPrev.type = 'button';
    btnPrev.className = 'gizi-prev';
    btnPrev.textContent = '← Sebelumnya';

    const dots = document.createElement('div');
    dots.className = 'gizi-dots';

    const btnNext = document.createElement('button');
    btnNext.type = 'button';
    btnNext.className = 'gizi-next';
    btnNext.textContent = 'Berikutnya →';

    const tombol = [];
    for (let i = 0; i < jumlah; i++) {
      const d = document.createElement('button');
      d.type = 'button';
      d.className = `gizi-dot${i === 0 ? ' aktif' : ''}`;
      d.setAttribute('aria-label', `Slide ${i + 1}`);
      d.addEventListener('click', () => keSlide(i));
      dots.appendChild(d);
      tombol.push(d);
    }

    const keSlide = (i) => {
      const kartu = wadah.querySelectorAll('.nutrition-status-item')[i];
      if (kartu) wadah.scrollTo({ left: kartu.offsetLeft - wadah.offsetLeft, behavior: 'smooth' });
    };

    const segarkan = () => {
      const idx = Math.round(wadah.scrollLeft / Math.max(wadah.clientWidth, 1));
      tombol.forEach((d, i) => d.classList.toggle('aktif', i === idx));
    };

    btnPrev.addEventListener('click', () => {
      const idx = tombol.findIndex((d) => d.classList.contains('aktif'));
      keSlide((idx - 1 + jumlah) % jumlah);
    });
    btnNext.addEventListener('click', () => {
      const idx = tombol.findIndex((d) => d.classList.contains('aktif'));
      keSlide((idx + 1) % jumlah);
    });

    let scrollTimer = null;
    wadah.addEventListener('scroll', () => {
      clearTimeout(scrollTimer);
      scrollTimer = setTimeout(segarkan, 120);
    }, { passive: true });

    nav.appendChild(btnPrev);
    nav.appendChild(dots);
    nav.appendChild(btnNext);
    wadah.insertAdjacentElement('afterend', nav);
    segarkan();
  }

  // ============================================
  // 13. INITIAL RENDER - TIMELINE KONSUMSI REAL
  // ============================================
  (async () => {
    try {
      pastikanOpsiBulan();

      if (window.NutriApi && window.NutriApi.getToken()) {
        const list = await window.NutriApi.anakList();
        const items = Array.isArray(list) ? list : (list && list.data) || [];
        if (items.length > 0) {
          anakAktifId = items[0].anak_id;
          await muatTimelineKonsumsi(items[0].anak_id);
          await muatStatusBulanan(items[0].anak_id);
          return;
        }
      }
    } catch (e) {
      console.warn('Gagal memuat timeline konsumsi:', e && e.message);
    }
    renderKonsumsiTimeline([]);
  })();
});