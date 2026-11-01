/**
 * Dashboard Orang Tua - Dynamic Controller (SKPL v2.0 - 2-Tab & Modal Kondisi Anak)
 * File: db-ortu.js
 */
document.addEventListener('DOMContentLoaded', async () => {
    // ==========================================
    // 1. AMBIL DATA USER DAN ANAK
    // ==========================================
    const legacyOrtu = safeParse(sessionStorage.getItem('ortuDataStep1'));
    const legacyAnak = safeParse(sessionStorage.getItem('anakDataStep2'));
    const legacyGrowth = safeParse(sessionStorage.getItem('pertumbuhanDataStep3'));
    
    let namaOrtu = legacyOrtu && legacyOrtu.namaLengkap;
    let namaAnak = legacyAnak && legacyAnak.namaAnak;
    let tglLahirAnak = legacyAnak && legacyAnak.tanggalLahirAnak;
    let tinggi = legacyGrowth && legacyGrowth.tinggiBadan;
    let berat = legacyGrowth && legacyGrowth.beratBadan;
    let kategoriTerakhir = null;
    let pesanKategori = null;
    let profilAnak = null;
    let profilAnakId = null;

    if (window.NutriApi && window.NutriApi.getToken()) {
        try {
            const me = await window.NutriApi.me();
            if (me && me.nama) namaOrtu = me.nama;
            
            const list = await window.NutriApi.anakList();
            const items = Array.isArray(list) ? list : (list && list.data) || [];
            
            if (items.length > 0) {
                const pertama = items[0];
                const detail = await window.NutriApi.anakDetail(pertama.anak_id);
                profilAnak = detail;
                profilAnakId = pertama.anak_id;
                namaAnak = detail.nama;
                tglLahirAnak = detail.tanggal_lahir;
                
                const riwayat = await window.NutriApi.ukurRiwayat(pertama.anak_id);
                const arr = Array.isArray(riwayat) ? riwayat : [];
                if (arr.length > 0) {
                    const terakhir = arr.reduce((a, b) =>
                        new Date(a.tanggal_ukur) > new Date(b.tanggal_ukur) ? a : b
                    );
                    tinggi = terakhir.tinggi_cm;
                    berat = terakhir.berat_kg;
                    kategoriTerakhir = terakhir.kategori_pemantauan || null;
                    pesanKategori = terakhir.pesan || null;
                }
            }
        } catch (e) {
            console.warn('Gagal memuat data API, memakai data lokal:', e && e.message);
        }
    }

    function safeParse(s) {
        try { return s ? JSON.parse(s) : null; } catch { return null; }
    }

    function inisial(nama) {
        if (!nama) return '?';
        return nama.trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
    }

    function isiAvatar() {
        const avOrtu = document.getElementById('avatar-ortu');
        const avAnak = document.getElementById('avatar-anak');
        if (avOrtu && namaOrtu) avOrtu.textContent = inisial(namaOrtu);
        if (avAnak && namaAnak) avAnak.textContent = inisial(namaAnak);
    }
    isiAvatar();

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
        if (tahun > 0) return `${tahun} Tahun ${bulan} Bulan`;
        return `${bulan} Bulan`;
    }

    // Isi data ke DOM
    if (namaOrtu) document.getElementById('greeting-parent').textContent = namaOrtu;
    if (namaAnak) {
        const namaDepan = namaAnak.split(' ')[0];
        document.getElementById('child-name').textContent = namaAnak;
        document.getElementById('child-name-inline').textContent = namaDepan;
        document.getElementById('child-name-analysis').textContent = namaDepan;
        document.getElementById('child-age').textContent = hitungUmur(tglLahirAnak);
        
        const modalChild = document.getElementById('modal-child-name');
        if (modalChild) modalChild.textContent = namaDepan;
    }
    if (tinggi !== undefined && tinggi !== null) {
        document.getElementById('tb-value').textContent = tinggi || '-';
        document.getElementById('bb-value').textContent = berat || '-';
    }

    const labelKategori = {
        NORMAL_LOW_RISK: 'Normal',
        AT_RISK_MONITORING: 'Perlu Pemantauan',
        HIGH_RISK_REFERRAL: 'Perlu Rujukan',
    };
    const badgeText = document.getElementById('status-badge-text');
    const badge = document.getElementById('status-badge-header');
    if (badgeText) badgeText.textContent = labelKategori[kategoriTerakhir] || 'Belum diukur';
    if (badge && pesanKategori) badge.title = pesanKategori;
    if (badge) {
        const dot = badge.querySelector('.status-dot');
        if (dot) {
            dot.style.background = kategoriTerakhir === 'HIGH_RISK_REFERRAL'
                ? '#dc2626'
                : (kategoriTerakhir === 'AT_RISK_MONITORING' ? '#d97706' : '#16a34a');
        }
    }

    // ==========================================
    // 2. TAB SYSTEM LOGIC (SKPL-F-06)
    // ==========================================
    let activeTab = 'tab-resep';
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            activeTab = btn.dataset.tab;
            document.getElementById(activeTab).classList.add('active');
        });
    });

    // ==========================================
    // 3. TAB 1: INPUT BERDASARKAN RESEP
    // ==========================================
    const resepSelect = document.getElementById('resep-select');
    const resepBahanContainer = document.getElementById('resep-bahan-container');
    const bahanResepList = document.getElementById('bahan-resep-list');
    const resepPorsiInput = document.getElementById('resep-porsi');
    let loadedRecipes = {};

    async function loadResepDropdown() {
        if (!resepSelect) return;
        try {
            if (window.NutriApi && window.NutriApi.getToken()) {
                const res = await window.NutriApi.get('/resep?status_verifikasi_data=DATA_LENGKAP&limit=100');
                const items = Array.isArray(res) ? res : (res && res.data) || [];
                resepSelect.innerHTML = '<option value="">-- Pilih Resep Makanan --</option>';
                items.forEach(r => {
                    loadedRecipes[r.resep_id || r.id] = r;
                    const opt = document.createElement('option');
                    opt.value = r.resep_id || r.id;
                    opt.textContent = r.nama_resep;
                    resepSelect.appendChild(opt);
                });
            }
        } catch (e) {
            console.warn('Gagal memuat resep dari API:', e);
            resepSelect.innerHTML = '<option value="">Gagal memuat daftar resep</option>';
        }
    }
    loadResepDropdown();

    if (resepSelect) {
        resepSelect.addEventListener('change', async () => {
            const resepId = resepSelect.value;
            if (!resepId) {
                resepBahanContainer.style.display = 'none';
                bahanResepList.innerHTML = '';
                return;
            }

            let detail = loadedRecipes[resepId];
            try {
                if (window.NutriApi) {
                    const fetched = await window.NutriApi.get(`/resep/${resepId}`);
                    if (fetched) detail = fetched;
                }
            } catch (err) {
                console.warn('Gagal memuat detail resep:', err);
            }

            const bahanArr = (detail && (detail.bahans || detail.bahan || detail.RESEP_BAHAN)) || [];
            resepBahanContainer.dataset.porsiHasil = (detail && (detail.jumlah_porsi_hasil || detail.porsi_saji)) || 1;
            bahanResepList.innerHTML = '';

            if (bahanArr.length === 0) {
                bahanResepList.innerHTML = '<p style="font-size:12px;opacity:0.8;">Tidak ada rincian bahan terdaftar.</p>';
            } else {
                bahanArr.forEach((b) => {
                    const bahanId = b.bahan_id || b.id;
                    const namaBahan = b.nama_bahan || (b.bahan_makanan && b.bahan_makanan.nama_bahan) || 'Bahan Makanan';
                    const gramEkuivalen = b.takaran_gram_ekuivalen ?? b.takaran_gram ?? null;
                    const bisaDihitung = gramEkuivalen !== null && Number(gramEkuivalen) > 0;
                    const takaranStr = b.takaran_jumlah
                        ? `${b.takaran_jumlah} ${b.takaran_satuan || 'g'}`
                        : (bisaDihitung ? `${gramEkuivalen}g` : 'Takaran belum tersedia');

                    const label = document.createElement('label');
                    label.className = 'bahan-resep-item';
                    label.innerHTML = `
                        <input type="checkbox" class="cb-bahan-resep" value="${bahanId}" data-nama="${namaBahan}" data-gram="${bisaDihitung ? gramEkuivalen : ''}" ${bisaDihitung ? 'checked' : 'disabled'}>
                        <div class="bahan-teks">
                            <span>${namaBahan}</span>
                            <span class="bahan-takaran">${takaranStr}</span>
                        </div>
                    `;
                    bahanResepList.appendChild(label);
                });
            }
            resepBahanContainer.style.display = 'block';
        });
    }

    // ==========================================
    // 4. TAB 2: INPUT BAHAN MANUAL
    // ==========================================
    const foodInput = document.getElementById('food-input');
    const foodTags = document.getElementById('food-tags');
    const foodPorsi = document.getElementById('food-porsi');
    const saranBox = document.getElementById('food-suggest');
    
    let catatItems = [];

    function porsiAktif() {
        const v = foodPorsi ? parseFloat(foodPorsi.value) : 100;
        return Number.isFinite(v) && v > 0 ? Math.min(v, 2000) : 100;
    }

    function gambarUlangTag() {
        if (!foodTags) return;
        foodTags.innerHTML = '';
        catatItems.forEach((item) => {
            const tag = document.createElement('span');
            tag.className = 'food-tag';
            tag.innerHTML = `${item.nama} `
                + `<button type="button" class="tag-porsi" data-nama="${item.nama}">${item.porsi}g</button> `
                + `<button class="tag-remove" data-nama="${item.nama}">`
                + `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;"><path d="M18 6 6 18M6 6l12 12"/></svg></button>`;
            
            tag.querySelector('.tag-remove').addEventListener('click', () => {
                catatItems = catatItems.filter((i) => i.nama.toLowerCase() !== item.nama.toLowerCase());
                gambarUlangTag();
            });
            foodTags.appendChild(tag);
        });
    }

    function tutupSaran() {
        if (saranBox) { saranBox.style.display = 'none'; saranBox.innerHTML = ''; }
    }

    let catatTimer = null;
    if (foodInput) {
        foodInput.addEventListener('input', () => {
            clearTimeout(catatTimer);
            const kata = foodInput.value.trim();
            if (kata.length < 2 || !(window.NutriApi && window.NutriApi.getToken())) {
                tutupSaran();
                return;
            }
            catatTimer = setTimeout(async () => {
                try {
                    const hasil = await window.NutriApi.bahanSearch(kata, 8);
                    const items = Array.isArray(hasil) ? hasil : (hasil && hasil.data) || [];
                    if (!saranBox || items.length === 0) {
                        tutupSaran();
                        return;
                    }
                    saranBox.innerHTML = '';
                    items.forEach((b) => {
                        const el = document.createElement('button');
                        el.type = 'button';
                        el.className = 'saran-item';
                        el.innerHTML = `<strong>${b.nama_bahan}</strong> <span>${b.kelompok_gizi || ''}</span>`;
                        el.addEventListener('mousedown', (e) => {
                            e.preventDefault();
                            if (!catatItems.some((i) => i.nama.toLowerCase() === b.nama_bahan.toLowerCase())) {
                                catatItems.push({ id: b.id, nama: b.nama_bahan, porsi: porsiAktif() });
                                gambarUlangTag();
                            }
                            foodInput.value = '';
                            tutupSaran();
                            foodInput.focus();
                        });
                        saranBox.appendChild(el);
                    });
                    saranBox.style.display = 'block';
                } catch {
                    tutupSaran();
                }
            }, 300);
        });

        foodInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') tutupSaran();
            if (e.key === 'Enter') {
                e.preventDefault();
                if (foodInput.value.trim()) {
                    const val = foodInput.value.trim();
                    if (!catatItems.some((i) => i.nama.toLowerCase() === val.toLowerCase())) {
                        catatItems.push({ id: null, nama: val, porsi: porsiAktif() });
                        gambarUlangTag();
                    }
                    foodInput.value = '';
                    tutupSaran();
                }
            }
        });
        foodInput.addEventListener('blur', () => setTimeout(tutupSaran, 150));
    }

    // ==========================================
    // 5. MODAL POP-UP LOGIC (SKPL-F-15)
    // ==========================================
    const modalKondisi = document.getElementById('modal-kondisi-anak');
    const btnSehat = document.getElementById('btn-kondisi-sehat');
    const btnSakit = document.getElementById('btn-kondisi-sakit');
    const btnBatal = document.getElementById('btn-kondisi-batal');

    function bukaModalKondisi() {
        if (modalKondisi) modalKondisi.style.display = 'flex';
    }

    function tutupModalKondisi() {
        if (modalKondisi) modalKondisi.style.display = 'none';
    }

    if (btnBatal) btnBatal.addEventListener('click', tutupModalKondisi);

    // ==========================================
    // 5b. MODAL KELOLA ALERGI / PANTANGAN
    // ==========================================
    const modalAlergi = document.getElementById('modal-alergi-anak');
    const btnKelolaAlergi = document.getElementById('btn-kelola-alergi');
    const btnAlergiBatal = document.getElementById('btn-alergi-batal');
    const btnAlergiSimpan = document.getElementById('btn-alergi-simpan');

    function isiModalAlergi() {
        const aktif = new Set(
            ((profilAnak && profilAnak.pantangan) || [])
                .map((item) => item.alergen)
                .filter(Boolean)
        );

        document.querySelectorAll('input[name="alergi-modal"]').forEach((input) => {
            input.checked = aktif.has(input.value);
        });
    }

    function tutupModalAlergi() {
        if (modalAlergi) modalAlergi.style.display = 'none';
    }

    if (btnKelolaAlergi) {
        btnKelolaAlergi.addEventListener('click', () => {
            if (!profilAnakId) return alert('Data anak belum tersedia.');
            isiModalAlergi();
            if (modalAlergi) modalAlergi.style.display = 'flex';
        });
    }

    if (btnAlergiBatal) btnAlergiBatal.addEventListener('click', tutupModalAlergi);

    if (btnAlergiSimpan) {
        btnAlergiSimpan.addEventListener('click', async () => {
            if (!profilAnakId) return;

            const opsi = Array.from(document.querySelectorAll('input[name="alergi-modal"]'));
            const nilaiDikenal = new Set(opsi.map((input) => input.value));
            const pantanganLain = ((profilAnak && profilAnak.pantangan) || [])
                .filter((item) => item.bahan_id || (item.alergen && !nilaiDikenal.has(item.alergen)))
                .map((item) => item.bahan_id
                    ? { bahan_id: item.bahan_id, jenis: item.jenis || 'PANTANGAN' }
                    : { alergen: item.alergen, jenis: item.jenis || 'ALERGI' });
            const pantangan = [
                ...pantanganLain,
                ...opsi
                    .filter((input) => input.checked)
                    .map((input) => ({ alergen: input.value, jenis: 'ALERGI' })),
            ];

            const teksAsli = btnAlergiSimpan.textContent;
            btnAlergiSimpan.textContent = 'Menyimpan...';
            btnAlergiSimpan.disabled = true;

            try {
                await window.NutriApi.anakUpdate(profilAnakId, { pantangan });
                if (profilAnak) profilAnak.pantangan = pantangan;
                tutupModalAlergi();
            } catch (err) {
                alert((err && err.message) || 'Gagal menyimpan alergi.');
            } finally {
                btnAlergiSimpan.textContent = teksAsli;
                btnAlergiSimpan.disabled = false;
            }
        });
    }

    // ==========================================
    // 6. SUBMIT KONSUMSI & AKG PROCESS
    // ==========================================
    const btnCalculate = document.getElementById('btn-calculate');
    const catatError = document.getElementById('catat-error');
    const catatHasil = document.getElementById('catat-hasil');

    function tampilGalatCatat(pesan) {
        if (!catatError) return;
        catatError.textContent = pesan;
        catatError.style.display = 'block';
    }

    async function anakAktifId() {
        const tersimpan = sessionStorage.getItem('lastAnakId');
        if (tersimpan) return tersimpan;
        const list = await window.NutriApi.anakList();
        const items = Array.isArray(list) ? list : (list && list.data) || [];
        if (items.length === 0) return null;
        sessionStorage.setItem('lastAnakId', items[0].anak_id);
        return items[0].anak_id;
    }

    // Klik tombol Hitung Makanan Hari Ini -> Buka Pop-Up Kondisi Anak
    if (btnCalculate) {
        btnCalculate.addEventListener('click', () => {
            if (catatError) catatError.style.display = 'none';
            if (catatHasil) catatHasil.style.display = 'none';

            if (activeTab === 'tab-resep') {
                const resepId = resepSelect ? resepSelect.value : null;
                if (!resepId) return alert('Silakan pilih resep terlebih dahulu!');
                const checkedBahan = document.querySelectorAll('.cb-bahan-resep:checked');
                if (checkedBahan.length === 0) return alert('Pilih minimal satu bahan resep yang dikonsumsi anak!');
            } else {
                if (catatItems.length === 0) return alert('Silakan tambahkan makanan manual terlebih dahulu!');
            }

            bukaModalKondisi();
        });
    }

    // Pilihan: Anak Tidak Sakit
    if (btnSehat) {
        btnSehat.addEventListener('click', () => {
            tutupModalKondisi();
            simpanKonsumsi('TIDAK_SAKIT');
        });
    }

    // Pilihan: Anak Sakit
    if (btnSakit) {
        btnSakit.addEventListener('click', () => {
            tutupModalKondisi();
            simpanKonsumsi('SAKIT');
        });
    }

    async function simpanKonsumsi(statusKondisi) {
        if (!(window.NutriApi && window.NutriApi.getToken())) {
            alert('Silakan login terlebih dahulu.');
            window.location.href = '/login';
            return;
        }

        const anakId = await anakAktifId();
        if (!anakId) {
            tampilGalatCatat('Belum ada data anak. Lengkapi pendaftaran anak terlebih dahulu.');
            return;
        }

        const hariIni = new Date().toISOString().slice(0, 10);

        const asliText = btnCalculate.innerHTML;
        btnCalculate.innerHTML = 'Menyimpan...';
        btnCalculate.disabled = true;

        try {
            // 1. Simpan Kondisi Harian (SKPL-F-15)
            await window.NutriApi.post(`/anak/${anakId}/kondisi-harian`, {
                tanggal: hariIni,
                status_kondisi: statusKondisi
            });

            // 2. Susun Payload Konsumsi Harian
            let payload = {
                tanggal_konsumsi: hariIni,
                metode_input: activeTab === 'tab-resep' ? 'RESEP' : 'MANUAL'
            };

            if (activeTab === 'tab-resep') {
                const resepId = resepSelect.value;
                const checkedBahan = Array.from(document.querySelectorAll('.cb-bahan-resep:checked'));
                const porsiDikonsumsi = parseFloat(resepPorsiInput ? resepPorsiInput.value : 1) || 1;
                const porsiHasil = parseFloat(resepBahanContainer.dataset.porsiHasil) || 1;

                payload.resep_id = resepId;
                payload.jumlah_porsi_dikonsumsi = porsiDikonsumsi;
                payload.items = checkedBahan
                    .filter((cb) => Number(cb.dataset.gram) > 0)
                    .map((cb) => ({
                        bahan_id: cb.value,
                        jumlah: (parseFloat(cb.dataset.gram) / porsiHasil) * porsiDikonsumsi,
                        satuan: 'g'
                    }));
            } else {
                const items = [];
                for (const item of catatItems) {
                    let id = item.id;
                    if (!id) {
                        const searchRes = await window.NutriApi.bahanSearch(item.nama, 1);
                        const resArr = Array.isArray(searchRes) ? searchRes : (searchRes && searchRes.data) || [];
                        if (resArr.length > 0) id = resArr[0].id;
                    }
                    if (id) {
                        items.push({ bahan_id: id, jumlah: item.porsi, satuan: 'g' });
                    }
                }
                payload.items = items;
            }

            if (payload.items.length === 0) {
                tampilGalatCatat('Tidak ada bahan yang cocok di katalog. Perbaiki nama makanan lalu coba lagi.');
                return;
            }

            // 3. Simpan Konsumsi ke API (SKPL-F-16)
            await window.NutriApi.post(`/anak/${anakId}/konsumsi`, payload);

            // 4. Jika Sakit -> Tampilkan Banner Khusus INVALID (SKPL-F-16)
            if (statusKondisi === 'SAKIT') {
                catatHasil.innerHTML = `
                    <div class="banner-sakit">
                        <span>⚠️</span>
                        <div>Anak tercatat sedang sakit pada tanggal ini — data konsumsi tidak digunakan dalam analisis.</div>
                    </div>
                    <a href="/history-ortu" class="hasil-link" style="display:inline-block;margin-top:12px;">Lihat History →</a>
                `;
                catatHasil.style.display = 'block';
            } else {
                // Jika Sehat -> Tampilkan Analisis Gizi AKG
                const analisis = await window.NutriApi.get(`/anak/${anakId}/analisis-gizi?tanggal=${hariIni}`);
                tampilkanAnalisis(analisis);
            }

            if (activeTab === 'tab-manual') {
                catatItems = [];
                gambarUlangTag();
            }
        } catch (err) {
            tampilGalatCatat((err && err.message) || 'Gagal menyimpan konsumsi. Coba lagi.');
        } finally {
            btnCalculate.innerHTML = asliText;
            btnCalculate.disabled = false;
        }
    }

    function tampilkanAnalisis(data) {
        if (!catatHasil) return;
        const rows = (data.ringkasan || []).map((r) =>
            `<div class="hasil-row"><span>${r.zat_gizi}</span>`
            + `<strong>${r.persentase_akg}% (${r.status})</strong></div>`
        ).join('');

        let isi = '';
        if (data.analisis_status !== 'dianalisis' && rows === '') {
            const pesan = data.penanda
                || (data.status_analisis === 'TIDAK_DAPAT_DIANALISIS_TIDAK_ADA_DATA_VALID'
                    ? 'Data konsumsi hari ini tidak valid sehingga belum dapat dianalisis.'
                    : 'Belum ada data konsumsi yang dapat dianalisis pada tanggal ini.');
            isi = `<p class="hasil-kosong">${pesan}</p>`;
        } else {
            isi = rows;
        }

        catatHasil.innerHTML = `<h4>Hasil analisis hari ini (${data.kelompok_umur || ''})</h4>`
            + isi
            + (data.catatan ? `<p class="hasil-catatan">${data.catatan}</p>` : '')
            + `<a href="/history-ortu" class="hasil-link" style="display:inline-block;margin-top:8px;">Lihat History →</a>`;
        catatHasil.style.display = 'block';
        catatHasil.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
});