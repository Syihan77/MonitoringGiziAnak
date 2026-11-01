<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Orang Tua - NutriTumbuh</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/globals.css', 'resources/css/db-ortu.css'])
</head>
<body>
    
    <header class="navbar-ortu">
        <div class="nav-container-ortu">
            <div class="brand-ortu">
                <img src="/img/only-logo.png" alt="Logo NutriTumbuh" class="brand-logo-ortu"> 
                <span class="brand-text-ortu">NutriTumbuh</span>
            </div>
            
            <nav class="nav-links-ortu">
                <a href="#" class="nav-item-ortu active" data-page="dashboard">
                    <x-icon name="grid" /> Dashboard
                </a>
                <a href="/input-ortu" class="nav-item-ortu">
                    <span class="nav-icon-plus"><x-icon name="plus" /></span> Input
                </a>
                <a href="/history-ortu" class="nav-item-ortu" data-page="history">
                    <x-icon name="history" /> History
                </a>
            </nav>

            <div class="profile-ortu">
                <div class="avatar-initial avatar-sm" id="avatar-ortu">?</div>
                <button type="button" class="btn-logout" data-logout title="Keluar" aria-label="Keluar dari akun"><x-icon name="logout" /></button>
            </div>
        </div>
    </header>

    <main class="dashboard-ortu-content">
        <section class="greeting-section">
            <div class="greeting-text">
                <h1 class="greeting-title">Halo, <span id="greeting-parent">Bunda</span>!</h1>
                <p class="greeting-subtitle">Pantau terus tumbuh kembang si kecil dengan mudah. Hari ini adalah hari yang baik untuk mencatat nutrisi.</p>
            </div>
            <div class="child-profile-card-header">
                <div class="avatar-initial avatar-sm" id="avatar-anak">?</div>
                <div class="child-info-header">
                    <h3 class="child-name-header" id="child-name">Memuat Data...</h3>
                    <p class="child-age-header"><x-icon name="user" /> <span id="child-age">-</span></p>
                </div>
                <div class="status-badge-header" id="status-badge-header">
                    <span class="status-dot"></span> <span id="status-badge-text">Belum diukur</span>
                </div>
                <button type="button" class="btn-kelola-alergi" id="btn-kelola-alergi" style="margin-left:10px;border:1px solid #d1d5db;background:#fff;border-radius:999px;padding:7px 12px;cursor:pointer;font-size:12px;">Kelola Alergi</button>
            </div>
        </section>

        <div class="main-grid">
            <div class="left-column">
                <section class="section-title-with-icon">
                    <span class="section-icon"><x-icon name="fork" /></span>
                    <h2>Asupan Harian</h2>
                </section>

                <!-- KOTAK CATAT NUTRISI (REVISI 2 TAB) -->
                <div class="nutrition-card">
                    <h3 class="nutrition-title">Catat Nutrisi</h3>
                    <p class="nutrition-subtitle">Pencatatan rutin membantu AI menganalisis kebutuhan gizi <span id="child-name-inline">Si Kecil</span>.</p>
                    
                    <!-- Tab Navigasi -->
                    <div class="nutrition-tabs">
                        <button type="button" class="tab-btn active" data-tab="tab-resep">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg> 
                            Input dari Resep
                        </button>
                        <button type="button" class="tab-btn" data-tab="tab-manual">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                            </svg>
                            Input Bahan Manual
                        </button>
                    </div>

                    <!-- TAB 1: INPUT RESEP -->
                    <div id="tab-resep" class="tab-content active">
                        <div class="form-group-resep">
                            <label for="resep-select">Pilih Resep Makanan:</label>
                            <select id="resep-select" class="resep-dropdown">
                                <option value="">-- Memuat daftar resep... --</option>
                            </select>
                        </div>
                        
                        <div id="resep-bahan-container" style="display: none;">
                            <div class="resep-porsi-wrapper">
                                <label for="resep-porsi">Porsi yang dimakan:</label>
                                <input type="number" id="resep-porsi" value="1" min="0.1" step="0.1" class="input-porsi-resep">
                            </div>
                            <p class="resep-instruksi">Centang bahan yang benar-benar dimakan anak:</p>
                            <div class="bahan-resep-list" id="bahan-resep-list">
                                <!-- Checkbox bahan akan di-render dari JS -->
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: INPUT MANUAL -->
                    <div id="tab-manual" class="tab-content">
                        <div class="search-nutrition">
                            <span class="search-icon"><x-icon name="search" /></span>
                            <input type="text" id="food-input" placeholder="Ketik Makanan Hari Ini" inputmode="search" enterkeyhint="done" autocomplete="off">
                            <input type="number" id="food-porsi" value="100" min="1" max="2000" title="Porsi (gram)" aria-label="Porsi dalam gram">
                            <span class="porsi-unit">g</span>
                        </div>
                        <div id="food-suggest"></div>

                        <div class="food-tags" id="food-tags">
                            <!-- Tag manual akan di-render dari JS -->
                        </div>
                    </div>

                    <!-- TOMBOL SUBMIT -->
                    <button class="btn-calculate" id="btn-calculate">
                        <span class="btn-plus">+</span> Hitung Makanan Hari Ini
                    </button>
                    
                    <p id="catat-error" style="display:none;color:#fecaca;font-size:13px;margin:10px 0 0;" role="alert"></p>
                    <div id="catat-hasil" style="display:none;" class="hasil-gizi"></div>
                </div>

                <section class="section-title-with-icon">
                    <span class="section-icon"><x-icon name="chart" /></span>
                    <h2>Perkembangan Fisik</h2>
                </section>

                <div class="physical-grid">
                    <div class="physical-card tb-card">
                        <div class="physical-header">
                            <span class="physical-label">Tinggi Badan (TB)</span>
                            <div class="physical-icon tb-icon"><img src="/img/icon-ruler.png" alt="Tinggi Badan"></div>
                        </div>
                        <div class="physical-value">
                            <span class="value-number" id="tb-value">-</span>
                            <span class="value-unit">cm</span>
                        </div>
                        <div class="physical-change positive"><span></span> Tumbuh dengan baik</div>
                    </div>

                    <div class="physical-card bb-card">
                        <div class="physical-header">
                            <span class="physical-label">Berat Badan (BB)</span>
                            <div class="physical-icon bb-icon"><img src="/img/bb.png" alt="Berat Badan"></div>
                        </div>
                        <div class="physical-value">
                            <span class="value-number" id="bb-value">-</span>
                            <span class="value-unit">kg</span>
                        </div>
                        <div class="physical-change positive"><span></span> Berat ideal</div>
                    </div>
                </div>
            </div>

            <div class="right-column">
                <div class="analysis-card">
                    <div class="analysis-header">
                        <span class="analysis-icon"><x-icon name="sparkle" /></span>
                        <h3>Analisis Cerdas</h3>
                    </div>
                    <p class="analysis-text" id="analysis-text">
                        Berdasarkan data awal, pertumbuhan <span id="child-name-analysis">Si Kecil</span> terlihat baik. Pertahankan asupan gizi seimbang.
                    </p>
                    <div class="analysis-list">
                        <div class="analysis-item success">
                            <div class="analysis-dot success-dot"><x-icon name="meat" /></div>
                            <span>Protein Terpenuhi</span>
                        </div>
                        <div class="analysis-item warning">
                            <div class="analysis-dot warning-dot"><x-icon name="leafy" /></div>
                            <span>Pantau Sayur Hijau</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- ========================================== -->
    <!-- MODAL KELOLA ALERGI / PANTANGAN -->
    <!-- ========================================== -->
    <div id="modal-alergi-anak" class="modal-overlay" style="display: none;">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Alergi / Pantangan Anak</h3>
            </div>
            <div class="modal-body">
                <p class="modal-question">Pilih bahan atau kelompok makanan yang harus dihindari.</p>
                <div id="alergi-modal-options" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;">
                    <label class="radio-label"><input type="checkbox" name="alergi-modal" value="telur"><span class="radio-text">Telur</span></label>
                    <label class="radio-label"><input type="checkbox" name="alergi-modal" value="ikan"><span class="radio-text">Ikan</span></label>
                    <label class="radio-label"><input type="checkbox" name="alergi-modal" value="udang/ebi"><span class="radio-text">Udang / Ebi</span></label>
                    <label class="radio-label"><input type="checkbox" name="alergi-modal" value="susu sapi/olahannya"><span class="radio-text">Susu Sapi</span></label>
                    <label class="radio-label"><input type="checkbox" name="alergi-modal" value="kedelai"><span class="radio-text">Kedelai</span></label>
                    <label class="radio-label"><input type="checkbox" name="alergi-modal" value="gluten"><span class="radio-text">Gluten</span></label>
                    <label class="radio-label"><input type="checkbox" name="alergi-modal" value="wijen"><span class="radio-text">Wijen</span></label>
                    <label class="radio-label"><input type="checkbox" name="alergi-modal" value="kacang-kacangan"><span class="radio-text">Kacang-kacangan</span></label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" id="btn-alergi-batal">Batal</button>
                <button type="button" class="btn-modal-cancel" id="btn-alergi-simpan" style="background:#006a38;color:#fff;">Simpan</button>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL POP-UP KONDISI ANAK (SKPL-F-15) -->
    <!-- ========================================== -->
    <div id="modal-kondisi-anak" class="modal-overlay" style="display: none;">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Pemeriksaan Kondisi Anak</h3>
            </div>
            <div class="modal-body">
                <p class="modal-question">Bagaimana kondisi <strong id="modal-child-name">Si Kecil</strong> hari ini?</p>
                <div class="kondisi-options">
                    <button type="button" class="btn-kondisi btn-sehat" id="btn-kondisi-sehat">
                        <span class="kondisi-icon">😊</span>
                        <span class="kondisi-title">Anak Tidak Sedang Sakit</span>
                        <span class="kondisi-desc">Asupan gizi akan dicatat dan dianalisis secara normal.</span>
                    </button>
                    <button type="button" class="btn-kondisi btn-sakit" id="btn-kondisi-sakit">
                        <span class="kondisi-icon">🤒</span>
                        <span class="kondisi-title">Anak Sedang Sakit</span>
                        <span class="kondisi-desc">Data konsumsi tetap disimpan namun dikecualikan dari analisis gizi.</span>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" id="btn-kondisi-batal">Batal</button>
            </div>
        </div>
    </div>

    @vite(['resources/js/api.js'])
    @vite(['resources/js/db-ortu.js'])
</body>
</html>