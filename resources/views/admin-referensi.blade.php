<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel Data Referensi - NutriTumbuh</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  @vite(['resources/css/globals.css', 'resources/css/admin-referensi.css'])
</head>
<body>
  <header class="admin-topbar">
    <div class="admin-brand">
      <span class="admin-brand-mark"><x-icon name="sprout" /></span>
      <div>
        <strong>NutriTumbuh</strong>
        <span>Panel Admin</span>
      </div>
    </div>
    <div class="admin-account">
      <span id="admin-name">Admin</span>
      <button type="button" class="btn-logout" data-logout title="Keluar" aria-label="Keluar dari akun"><x-icon name="logout" /></button>
    </div>
  </header>

  <main class="admin-main">
    <section class="admin-heading">
      <div>
        <p class="admin-eyebrow">Data Referensi Gizi</p>
        <h1>Kelola Bahan Makanan &amp; Resep</h1>
        <p class="admin-lede">Perbarui kandungan gizi per porsi dan verifikasi resep agar layak menjadi kandidat rekomendasi menu.</p>
      </div>
      <button type="button" class="btn-primary" id="btn-create">
        <x-icon name="plus" /> <span id="btn-create-label">Tambah Bahan</span>
      </button>
    </section>

    <div class="admin-tabs" role="tablist" aria-label="Jenis data referensi">
      <button type="button" class="admin-tab active" role="tab" aria-selected="true" data-tab="bahan" id="tab-bahan">Bahan Makanan</button>
      <button type="button" class="admin-tab" role="tab" aria-selected="false" data-tab="resep" id="tab-resep">Resep</button>
    </div>

    <div class="admin-alert" id="admin-alert" role="status" hidden></div>

    <section class="admin-panel" id="panel-bahan" role="tabpanel" aria-labelledby="tab-bahan">
      <div class="admin-toolbar">
        <label class="search-field">
          <x-icon name="search" />
          <input type="search" id="bahan-search" placeholder="Cari nama bahan..." autocomplete="off">
        </label>
        <button type="button" class="btn-ghost" id="bahan-refresh">Muat ulang</button>
      </div>
      <div class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Nama Bahan</th>
              <th>Kelompok</th>
              <th>Energi</th>
              <th>Protein</th>
              <th>Lemak</th>
              <th>Karbo</th>
              <th>Fe / Zinc</th>
              <th>Sumber</th>
              <th class="col-action">Aksi</th>
            </tr>
          </thead>
          <tbody id="bahan-table-body"></tbody>
        </table>
      </div>
      <div class="pagination" id="bahan-pagination" hidden>
        <button type="button" class="btn-ghost" data-page-prev="bahan">Sebelumnya</button>
        <span id="bahan-page-info"></span>
        <button type="button" class="btn-ghost" data-page-next="bahan">Berikutnya</button>
      </div>
    </section>

    <section class="admin-panel" id="panel-resep" role="tabpanel" aria-labelledby="tab-resep" hidden>
      <div class="admin-toolbar admin-toolbar-wrap">
        <label class="search-field">
          <x-icon name="search" />
          <input type="search" id="resep-search" placeholder="Cari nama resep..." autocomplete="off">
        </label>
        <select id="resep-status-filter" aria-label="Filter status verifikasi">
          <option value="">Semua status</option>
          <option value="DATA_LENGKAP">Data Lengkap</option>
          <option value="PERLU_VERIFIKASI">Perlu Verifikasi</option>
          <option value="TERVERIFIKASI">Terverifikasi</option>
        </select>
        <select id="resep-kategori-filter" aria-label="Filter kategori gizi">
          <option value="">Semua kategori</option>
          <option value="makanan_utama">Makanan Utama</option>
          <option value="camilan_dessert">Camilan / Dessert</option>
          <option value="minuman">Minuman</option>
          <option value="puree_buah_sayur">Puree Buah / Sayur</option>
          <option value="umum">Umum</option>
          <option value="tinggi protein">Tinggi Protein</option>
          <option value="tinggi zat besi">Tinggi Zat Besi</option>
          <option value="tinggi karbohidrat">Tinggi Karbohidrat</option>
          <option value="tinggi vitamin">Tinggi Vitamin</option>
        </select>
        <button type="button" class="btn-ghost" id="resep-refresh">Muat ulang</button>
      </div>
      <div class="table-scroll">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Nama Resep</th>
              <th>Kategori</th>
              <th>Porsi</th>
              <th>Usia</th>
              <th>Tekstur</th>
              <th>Bahan</th>
              <th>Status</th>
              <th class="col-action">Aksi</th>
            </tr>
          </thead>
          <tbody id="resep-table-body"></tbody>
        </table>
      </div>
      <div class="pagination" id="resep-pagination" hidden>
        <button type="button" class="btn-ghost" data-page-prev="resep">Sebelumnya</button>
        <span id="resep-page-info"></span>
        <button type="button" class="btn-ghost" data-page-next="resep">Berikutnya</button>
      </div>
    </section>
  </main>

  <div class="modal-backdrop" id="bahan-modal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="bahan-modal-title">
      <div class="modal-head">
        <h2 id="bahan-modal-title">Tambah Bahan Makanan</h2>
        <button type="button" class="modal-close" data-close-modal="bahan-modal" aria-label="Tutup"><x-icon name="close" /></button>
      </div>
      <form id="bahan-form">
        <input type="hidden" id="bahan-id">
        <div class="form-grid">
          <label class="field field-wide">
            <span>Nama bahan <em>*</em></span>
            <input type="text" id="bahan-nama" maxlength="100" required>
          </label>
          <label class="field field-wide">
            <span>Kelompok gizi <em>*</em></span>
            <input type="text" id="bahan-kelompok" maxlength="50" placeholder="Contoh: Protein Hewani" required>
          </label>
          <label class="field">
            <span>Energi (kkal/100 g)</span>
            <input type="number" id="bahan-energi" min="0" max="1000" step="0.01">
          </label>
          <label class="field">
            <span>Karbohidrat (g/100 g)</span>
            <input type="number" id="bahan-karbo" min="0" max="100" step="0.01">
          </label>
          <label class="field">
            <span>Protein (g/100 g)</span>
            <input type="number" id="bahan-protein" min="0" max="100" step="0.01">
          </label>
          <label class="field">
            <span>Lemak (g/100 g)</span>
            <input type="number" id="bahan-lemak" min="0" max="100" step="0.01">
          </label>
          <label class="field">
            <span>Zat besi (mg/100 g)</span>
            <input type="number" id="bahan-besi" min="0" max="1000" step="0.01">
          </label>
          <label class="field">
            <span>Zinc (mg/100 g)</span>
            <input type="number" id="bahan-zinc" min="0" max="1000" step="0.01">
          </label>
          <label class="field field-wide">
            <span>Sumber rujukan</span>
            <input type="text" id="bahan-sumber" maxlength="100" placeholder="Contoh: TKPI 2019">
          </label>
        </div>
        <div class="form-error" id="bahan-form-error" role="alert" hidden></div>
        <div class="modal-actions">
          <button type="button" class="btn-ghost" data-close-modal="bahan-modal">Batal</button>
          <button type="submit" class="btn-primary"><x-icon name="save" /> Simpan Bahan</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" id="resep-modal" hidden>
    <div class="modal-card modal-card-wide" role="dialog" aria-modal="true" aria-labelledby="resep-modal-title">
      <div class="modal-head">
        <h2 id="resep-modal-title">Tambah Resep</h2>
        <button type="button" class="modal-close" data-close-modal="resep-modal" aria-label="Tutup"><x-icon name="close" /></button>
      </div>
      <form id="resep-form">
        <input type="hidden" id="resep-id">
        <div class="form-section">
          <h3>Informasi Resep</h3>
          <div class="form-grid">
            <label class="field field-wide">
              <span>Nama resep <em>*</em></span>
              <input type="text" id="resep-nama" maxlength="100" required>
            </label>
            <label class="field field-wide">
              <span>Cara memasak <em>*</em></span>
              <textarea id="resep-langkah" rows="3" required></textarea>
            </label>
            <label class="field">
              <span>Kategori gizi</span>
              <input type="text" id="resep-kategori" maxlength="50" list="kategori-resep-options" placeholder="umum">
              <datalist id="kategori-resep-options">
                <option value="makanan_utama"></option>
                <option value="camilan_dessert"></option>
                <option value="minuman"></option>
                <option value="puree_buah_sayur"></option>
                <option value="umum"></option>
                <option value="tinggi protein"></option>
                <option value="tinggi zat besi"></option>
                <option value="tinggi karbohidrat"></option>
                <option value="tinggi vitamin"></option>
              </datalist>
            </label>
            <label class="field">
              <span>Jumlah porsi hasil <em>*</em></span>
              <input type="number" id="resep-porsi" min="1" max="100" step="1" value="1" required>
            </label>
            <label class="field">
              <span>Tingkat kesulitan</span>
              <select id="resep-kesulitan">
                <option value="">Pilih</option>
                <option value="Mudah">Mudah</option>
                <option value="Sedang">Sedang</option>
                <option value="Sulit">Sulit</option>
              </select>
            </label>
            <label class="field">
              <span>Tekstur sajian</span>
              <input type="text" id="resep-tekstur" maxlength="50" placeholder="Contoh: Lumat">
            </label>
            <label class="field">
              <span>Usia minimum (bulan)</span>
              <input type="number" id="resep-usia-min" min="0" max="60" step="1">
            </label>
            <label class="field">
              <span>Usia maksimum (bulan)</span>
              <input type="number" id="resep-usia-maks" min="0" max="60" step="1">
            </label>
            <label class="field">
              <span>Usia minimum setelah modifikasi</span>
              <input type="number" id="resep-usia-modifikasi" min="0" max="60" step="1">
            </label>
            <label class="field">
              <span>Jenis menu</span>
              <input type="text" id="resep-jenis-menu" maxlength="50" list="jenis-menu-options" placeholder="Contoh: makanan_utama">
              <datalist id="jenis-menu-options">
                <option value="makanan_utama"></option>
                <option value="camilan_dessert"></option>
                <option value="minuman"></option>
                <option value="puree_buah_sayur"></option>
              </datalist>
            </label>
            <label class="field">
              <span>Keyakinan label</span>
              <select id="resep-keyakinan">
                <option value="">Pilih</option>
                <option value="tinggi">Tinggi</option>
                <option value="sedang">Sedang</option>
                <option value="rendah">Rendah</option>
              </select>
            </label>
            <label class="field field-wide">
              <span>Modifikasi untuk menurunkan usia</span>
              <input type="text" id="resep-modifikasi" maxlength="200" placeholder="Contoh: tekstur dilumatkan dan tanpa garam">
            </label>
            <label class="field field-wide">
              <span>Alergen (pisahkan dengan koma)</span>
              <input type="text" id="resep-alergen" maxlength="500" placeholder="Contoh: telur, kedelai">
            </label>
            <label class="field field-wide">
              <span>Catatan resep</span>
              <textarea id="resep-catatan" rows="2"></textarea>
            </label>
            <label class="field field-wide">
              <span>Sumber resep</span>
              <input type="text" id="resep-sumber" maxlength="200" placeholder="Contoh: Buku Resep Dashat">
            </label>
            <label class="field">
              <span>Status verifikasi</span>
              <select id="resep-status">
                <option value="PERLU_VERIFIKASI">Perlu Verifikasi</option>
                <option value="DATA_LENGKAP">Data Lengkap</option>
                <option value="TERVERIFIKASI" disabled>Terverifikasi (khusus ahli gizi)</option>
              </select>
            </label>
          </div>
          <p class="form-hint">Resep data lengkap wajib memiliki porsi hasil, usia minimum, alergen (boleh kosong), seluruh nilai gizi per porsi, dan minimal satu bahan.</p>
        </div>

        <div class="form-section">
          <h3>Gizi per Porsi</h3>
          <div class="form-grid form-grid-compact">
            <label class="field"><span>Energi (kkal)</span><input type="number" id="gizi-energi" min="0" max="5000" step="0.01"></label>
            <label class="field"><span>Protein (g)</span><input type="number" id="gizi-protein" min="0" max="500" step="0.01"></label>
            <label class="field"><span>Lemak (g)</span><input type="number" id="gizi-lemak" min="0" max="500" step="0.01"></label>
            <label class="field"><span>Karbohidrat (g)</span><input type="number" id="gizi-karbo" min="0" max="500" step="0.01"></label>
            <label class="field"><span>Serat (g)</span><input type="number" id="gizi-serat" min="0" max="200" step="0.01"></label>
            <label class="field"><span>Kalsium (mg)</span><input type="number" id="gizi-kalsium" min="0" max="5000" step="0.01"></label>
            <label class="field"><span>Zat besi (mg)</span><input type="number" id="gizi-besi" min="0" max="500" step="0.01"></label>
            <label class="field"><span>Zinc (mg)</span><input type="number" id="gizi-zinc" min="0" max="500" step="0.01"></label>
            <label class="field"><span>Vitamin A (mcg)</span><input type="number" id="gizi-vita" min="0" max="5000" step="0.01"></label>
            <label class="field"><span>Vitamin C (mg)</span><input type="number" id="gizi-vitc" min="0" max="5000" step="0.01"></label>
          </div>
        </div>

        <div class="form-section">
          <div class="section-head">
            <h3>Bahan Resep</h3>
            <button type="button" class="btn-ghost btn-small" id="btn-add-bahan-row"><x-icon name="plus" /> Tambah Bahan</button>
          </div>
          <div class="bahan-rows" id="bahan-rows"></div>
          <p class="form-hint">Isi takaran jumlah dan satuan. Ekuivalen gram boleh kosong hanya untuk bahan pelengkap; bahan utama wajib memiliki ekuivalen gram.</p>
        </div>

        <div class="form-error" id="resep-form-error" role="alert" hidden></div>
        <div class="modal-actions">
          <button type="button" class="btn-ghost" data-close-modal="resep-modal">Batal</button>
          <button type="submit" class="btn-primary"><x-icon name="save" /> Simpan Resep</button>
        </div>
      </form>
    </div>
  </div>

  @vite(['resources/js/api.js'])
  @vite(['resources/js/admin-referensi.js'])
</body>
</html>
