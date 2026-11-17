"""
rekomendasi_gizi.py — modul rekomendasi resep gizi anak (ML2).

Modul mandiri (tanpa ketergantungan pada Google Colab) yang dipakai oleh notebook
dan oleh layanan Python/FastAPI. Isi:
  - deteksi kolom nutrisi & parsing bahan,
  - content-based ranking berbasis persentil nutrisi (nilai kosong TIDAK diisi nol),
  - rule-based filtering bahan,
  - opsi re-ranking keragaman berbasis cluster K-Means (eksperimen),
  - kelas PemberiRekomendasi.muat(folder) untuk memuat artefak yang tersimpan.

Peringkat adalah alat bantu memilih menu, BUKAN diagnosis atau jaminan kecukupan gizi.
"""
from __future__ import annotations

import json
import math
import re
from pathlib import Path

import numpy as np
import pandas as pd

VERSI_MODUL = "1.0.0"

# --------------------------------------------------------------------------- #
# 1. Kolom nutrisi
# --------------------------------------------------------------------------- #
# alias -> pola regex pada nama kolom (huruf kecil). Dicocokkan ke header ASLI dataset.
POLA_NUTRISI = {
    "energi": r"energi",
    "karbohidrat": r"karbo",
    "protein": r"protein",
    "lemak": r"lemak",
    "zat_besi": r"besi|(^|_)fe(_|$)",
    "vitamin_c": r"vitamin_?c",
    "kalsium": r"kalsium|calcium",
    "zinc": r"zinc|(^|_)seng(_|$)",
    "vitamin_a": r"vitamin_?a",
    "serat": r"serat|fiber",
}
LABEL_NUTRISI = {
    "energi": "Energi", "karbohidrat": "Karbohidrat", "protein": "Protein", "lemak": "Lemak",
    "zat_besi": "Zat besi", "vitamin_c": "Vitamin C", "kalsium": "Kalsium", "zinc": "Zinc",
    "vitamin_a": "Vitamin A", "serat": "Serat",
}
SINONIM_NUTRISI = {
    "besi": "zat_besi", "fe": "zat_besi", "iron": "zat_besi", "seng": "zinc", "zn": "zinc",
    "vit_c": "vitamin_c", "vit_a": "vitamin_a", "calcium": "kalsium", "ca": "kalsium",
    "karbo": "karbohidrat", "kalori": "energi", "kkal": "energi", "fiber": "serat",
}


def deteksi_kolom_nutrisi(df: pd.DataFrame) -> dict:
    """Petakan alias nutrisi -> nama kolom asli berdasarkan header (bukan asumsi nama tetap)."""
    peta = {}
    for alias, pola in POLA_NUTRISI.items():
        cocok = [c for c in df.columns if re.search(pola, str(c).lower()) and "per_porsi" in str(c).lower()]
        if not cocok:  # longgar: abaikan syarat 'per_porsi'
            cocok = [c for c in df.columns if re.search(pola, str(c).lower())
                     and pd.api.types.is_numeric_dtype(df[c])]
        if len(cocok) == 1:
            peta[alias] = cocok[0]
        elif len(cocok) > 1:
            raise ValueError(f"Kolom ambigu untuk '{alias}': {cocok}. Tentukan manual.")
    return peta


def satuan_dari_kolom(nama_kolom: str) -> str:
    m = re.search(r"_(kcal|g|mg|mcg|ug|re)_per_porsi", str(nama_kolom), flags=re.I)
    return m.group(1) if m else ""


def parse_angka(nilai):
    """Ubah teks angka (mendukung koma desimal, mis. '8,9333') menjadi float; gagal -> NaN."""
    if nilai is None:
        return np.nan
    if isinstance(nilai, (int, float, np.integer, np.floating)):
        return float(nilai)
    s = str(nilai).strip().replace(" ", "")
    if s == "" or s.lower() in {"nan", "none", "null", "-", "n/a", "na"}:
        return np.nan
    if "," in s and "." in s:  # pemisah terakhir dianggap desimal
        if s.rfind(",") > s.rfind("."):
            s = s.replace(".", "").replace(",", ".")
        else:
            s = s.replace(",", "")
    elif "," in s:
        s = s.replace(",", ".")
    try:
        return float(s)
    except ValueError:
        return np.nan


def parse_waktu_menit(teks):
    """'30 menit' -> 30 ; '1 jam 15 menit' -> 75 ; tidak terbaca -> NaN."""
    if teks is None or (isinstance(teks, float) and math.isnan(teks)):
        return np.nan
    t = str(teks).lower()
    jam = re.search(r"(\d+(?:[.,]\d+)?)\s*jam", t)
    mnt = re.search(r"(\d+(?:[.,]\d+)?)\s*menit", t)
    if not jam and not mnt:
        return np.nan
    total = 0.0
    if jam:
        total += parse_angka(jam.group(1)) * 60
    if mnt:
        total += parse_angka(mnt.group(1))
    return total


def parse_porsi(teks):
    m = re.search(r"\d+", str(teks))
    return int(m.group()) if m else np.nan


# --------------------------------------------------------------------------- #
# 2. Parsing & pencocokan bahan
# --------------------------------------------------------------------------- #
SINONIM_BAHAN = {
    "ubi jalar": "ubi rambat", "serai": "sereh", "tapioca": "tapioka", "kool": "kol",
    "bawng": "bawang", "kedele": "kedelai", "kacang kedelai": "kedelai", "salam": "daun salam",
    "tepung maizena": "maizena", "labu waluh": "labu kuning", "daun kari": "daun kari",
}
# kata pengolahan / satuan / pengisi yang dibuang agar nama bahan inti tersisa
KATA_ABAIKAN = {
    "potong", "dipotong", "dadu", "kecil", "iris", "diiris", "cincang", "dicincang", "parut", "halus",
    "haluskan", "dihaluskan", "geprek", "memarkan", "dimemarkan", "rebus", "direbus", "kukus", "mentah",
    "kocok", "serut", "tipis", "kotak", "yang", "sudah", "direndam", "digunting", "dan", "di", "untuk",
    "sedang", "besar", "ptg", "sdm", "sdt", "gr", "g", "ml", "buah", "siung", "lembar", "ruas", "cm",
    "tanpa", "tulang", "masak", "matang", "segar", "bubuk_", "potongan", "membungkus", "menumis",
}
# Bumbu dapur / bahan pelengkap yang diasumsikan tersedia & tidak dijadikan bahan wajib.
# Setiap entri = himpunan kata yang harus seluruhnya ada pada nama bahan.
BUMBU_DAPUR = [
    {"garam"}, {"merica"}, {"lada"}, {"gula"}, {"minyak"}, {"margarin"}, {"mentega"}, {"air"},
    {"bawang", "putih"}, {"bawang", "merah"}, {"bawang", "bombay"}, {"bawang", "daun"},
    {"daun", "salam"}, {"sereh"}, {"jahe"}, {"lengkuas"}, {"kunyit"}, {"ketumbar"}, {"kemiri"},
    {"daun", "jeruk"}, {"kayu", "manis"}, {"jinten"}, {"pala"}, {"cengkeh"}, {"kecap"},
    {"saus", "tiram"}, {"kaldu"}, {"vanila"}, {"vanilla"}, {"essence"}, {"ekstrak"}, {"terasi"},
    {"asam", "jawa"}, {"asam", "sunti"}, {"daun", "pandan"}, {"jeruk", "nipis"}, {"seledri"},
    {"bumbu"}, {"penyedap"}, {"bunga", "lawang"}, {"daun", "kari"}, {"ragi"}, {"maizena"},
    {"tapioka"}, {"daun", "kemangi"},
]
PENANDA_OPSIONAL_QTY = ("secukupnya", "sejumput", "opsional", "sesuai selera", "taburan", "hiasan")
PENANDA_OPSIONAL_NAMA = ("opsional", "untuk membungkus", "untuk menumis", "untuk menggoreng",
                         "pelengkap", "hiasan", "taburan")


def _norm_teks(s: str) -> str:
    s = str(s).lower().replace("\u00bd", " ").replace("\u00bc", " ").replace("\u00be", " ")
    s = re.sub(r"[^a-z0-9/\s\-]", " ", s)
    return re.sub(r"\s+", " ", s).strip()


def tokens_bahan(teks: str) -> frozenset:
    """Normalisasi nama bahan menjadi himpunan kata inti (tanpa angka, kata olahan, satuan)."""
    s = _norm_teks(teks)
    for lama, baru in SINONIM_BAHAN.items():
        s = re.sub(rf"\b{re.escape(lama)}\b", baru, s)
    toks = [t for t in re.split(r"[\s\-]+", s) if t and not t.isdigit() and t not in KATA_ABAIKAN]
    return frozenset(toks)


def parse_item_bahan(item: str):
    """Satu item 'Nama (kualifier): jumlah' -> dict atau None bila tidak terbaca."""
    item = (item or "").strip()
    if not item:
        return None
    bagian = item.split(":", 1)
    nama_raw, jumlah = bagian[0], (bagian[1].strip().lower() if len(bagian) > 1 else "")
    kualifier = " ".join(re.findall(r"\(([^)]*)\)", nama_raw)).lower()
    nama = re.sub(r"\([^)]*\)", " ", nama_raw)
    nama = re.sub(r"\(.*$", " ", nama)          # tanda kurung tidak seimbang
    nama = nama.split(",")[0].strip()
    alternatif = [tokens_bahan(a) for a in nama.split("/")]
    alternatif = [a for a in alternatif if a]
    if not alternatif:
        return None
    gabung_nama = _norm_teks(nama_raw)
    if all(any(b <= a for b in BUMBU_DAPUR) for a in alternatif):
        kategori = "bumbu_dapur"
    elif ("bumbu" in kualifier or any(p in kualifier for p in PENANDA_OPSIONAL_NAMA)
          or any(p in jumlah for p in PENANDA_OPSIONAL_QTY)
          or any(p in gabung_nama for p in PENANDA_OPSIONAL_NAMA)):
        kategori = "opsional"
    else:
        kategori = "wajib"
    return {"nama": re.sub(r"\s+", " ", nama).strip(), "alt": [sorted(a) for a in alternatif],
            "kategori": kategori}


def parse_bahan(teks) -> list:
    """Parse kolom bahan_dan_bumbu (item dipisah ';'). Item yang tidak terbaca dilewati."""
    if teks is None or (isinstance(teks, float) and math.isnan(teks)):
        return []
    hasil = [parse_item_bahan(x) for x in str(teks).split(";")]
    return [h for h in hasil if h]


def _cocok(user_tokens: frozenset, alt_tokens) -> bool:
    a = frozenset(alt_tokens)
    return bool(user_tokens) and bool(a) and (user_tokens <= a or a <= user_tokens)


def bahan_cocok(item_bahan: dict, daftar_user_tokens: list) -> bool:
    return any(_cocok(u, a) for u in daftar_user_tokens for a in item_bahan["alt"])


# --------------------------------------------------------------------------- #
# 3. Basis resep
# --------------------------------------------------------------------------- #
def bangun_basis_resep(df: pd.DataFrame, kolom_nutrisi: dict, alias_nol_tidak_diketahui=(),
                       kolom_id="id_resep", kolom_nama="nama_menu", kolom_bahan="bahan_dan_bumbu",
                       kolom_waktu="waktu_memasak", kolom_porsi="porsi", kolom_cara="cara_membuat",
                       cluster=None, sel_tidak_diketahui=()) -> pd.DataFrame:
    """Bangun tabel basis untuk ranking. Nilai 0 pada nutrisi di `alias_nol_tidak_diketahui`
    dipetakan ke NaN (tidak diketahui), sesuai bukti pada audit data. `sel_tidak_diketahui`
    berisi pasangan (id_resep, alias) yang dicurigai salah input dan dianggap tidak diketahui."""
    for k in (kolom_id, kolom_nama, kolom_bahan):
        if k not in df.columns:
            raise KeyError(f"Kolom wajib '{k}' tidak ada. Kolom tersedia: {list(df.columns)}")
    b = pd.DataFrame({"id_resep": df[kolom_id].astype(str).values, "nama_menu": df[kolom_nama].values})
    b["porsi"] = df[kolom_porsi].map(parse_porsi).values if kolom_porsi in df.columns else np.nan
    b["waktu_menit"] = df[kolom_waktu].map(parse_waktu_menit).values if kolom_waktu in df.columns else np.nan
    b["bahan_dan_bumbu"] = df[kolom_bahan].values
    b["cara_membuat"] = df[kolom_cara].values if kolom_cara in df.columns else ""
    for alias, kol in kolom_nutrisi.items():
        v = df[kol].map(parse_angka).astype(float)
        if alias in alias_nol_tidak_diketahui:
            v = v.mask(v == 0)
        b[alias] = v.values
    for id_, alias in sel_tidak_diketahui:
        b.loc[b["id_resep"] == str(id_), alias] = np.nan
    b["bahan_json"] = [json.dumps(parse_bahan(t), ensure_ascii=False) for t in df[kolom_bahan]]
    b["cluster"] = np.asarray(cluster) if cluster is not None else np.nan
    return b.reset_index(drop=True)


# --------------------------------------------------------------------------- #
# 4. Prioritas gizi
# --------------------------------------------------------------------------- #
def normalisasi_alias(nama: str) -> str:
    k = re.sub(r"[\s\-]+", "_", str(nama).strip().lower())
    return SINONIM_NUTRISI.get(k, k)


def validasi_prioritas(prioritas: dict, nutrisi_tersedia) -> dict:
    """Validasi bobot: dict tak kosong, nutrisi dikenal, bobot finite & tidak negatif, lalu normalisasi."""
    if not isinstance(prioritas, dict) or not prioritas:
        raise ValueError("`prioritas_gizi` harus dict tidak kosong, mis. {'protein': 0.5, 'kalsium': 0.5}.")
    bersih = {}
    for k, v in prioritas.items():
        a = normalisasi_alias(k)
        if a not in nutrisi_tersedia:
            raise ValueError(f"Zat gizi '{k}' tidak dikenali. Pilihan: {sorted(nutrisi_tersedia)}")
        try:
            v = float(v)
        except (TypeError, ValueError):
            raise ValueError(f"Bobot '{k}' bukan angka: {v!r}")
        if not np.isfinite(v) or v < 0:
            raise ValueError(f"Bobot '{k}' harus >= 0 dan terhingga (diterima {v}).")
        bersih[a] = bersih.get(a, 0.0) + v
    total = sum(bersih.values())
    if total <= 0:
        raise ValueError("Total bobot prioritas harus > 0.")
    return {k: v / total for k, v in bersih.items() if v > 0}


def hitung_prioritas_dari_asupan(asupan: dict, acuan: dict, nutrisi_tersedia) -> dict:
    """Bobot ∝ kekurangan relatif = max(0, 1 - asupan/acuan). `acuan` WAJIB diberikan pemanggil
    (modul tidak menyertakan angka kebutuhan klinis). Bila tidak ada kekurangan -> ValueError."""
    kekurangan = {}
    for k, ref in acuan.items():
        a = normalisasi_alias(k)
        if a not in nutrisi_tersedia:
            raise ValueError(f"Zat gizi '{k}' tidak dikenali pada `acuan_harian`.")
        ref = float(ref)
        if not np.isfinite(ref) or ref <= 0:
            raise ValueError(f"Acuan '{k}' harus > 0.")
        diambil = float({normalisasi_alias(x): y for x, y in asupan.items()}.get(a, 0.0))
        if not np.isfinite(diambil) or diambil < 0:
            raise ValueError(f"Asupan '{k}' harus >= 0.")
        kekurangan[a] = max(0.0, 1.0 - diambil / ref)
    total = sum(kekurangan.values())
    if total <= 0:
        raise ValueError("Tidak ada kekurangan asupan terhadap acuan; berikan `prioritas_gizi` manual.")
    return {k: v / total for k, v in kekurangan.items() if v > 0}


# --------------------------------------------------------------------------- #
# 5. Pemberi rekomendasi
# --------------------------------------------------------------------------- #
KUNCI_PAYLOAD = {"prioritas_gizi", "asupan_harian", "acuan_harian", "bahan_tersedia", "mode_bahan",
                 "min_kecocokan", "top_n", "maks_waktu_menit", "bahan_dihindari", "metode",
                 "lambda_keragaman", "kebijakan_nilai_hilang"}
PERINGATAN_STANDAR = [
    "Skor adalah peringkat RELATIF terhadap resep dalam dataset (persentil), bukan penilaian kecukupan gizi.",
    "Hasil adalah alat bantu memilih menu; bukan diagnosis, bukan saran klinis, dan bukan catatan konsumsi anak.",
    "Kualitas rekomendasi belum divalidasi terhadap penilaian ahli gizi atau pengguna.",
]


def _aman(x):
    """Konversi ke tipe JSON-compatible (NaN/inf -> None)."""
    if isinstance(x, (np.integer,)):
        return int(x)
    if isinstance(x, (np.floating, float)):
        x = float(x)
        return x if math.isfinite(x) else None
    return x


class PemberiRekomendasi:
    def __init__(self, basis: pd.DataFrame, config: dict, pipeline=None):
        self.basis = basis.reset_index(drop=True).copy()
        self.config = config
        self.pipeline = pipeline
        self.kolom_nutrisi = config["kolom_nutrisi"]            # alias -> kolom asli
        self.satuan = config.get("satuan", {})
        self.nutrisi = [n for n in self.kolom_nutrisi if n in self.basis.columns]
        self.bahan = [json.loads(s) for s in self.basis["bahan_json"]]
        # referensi persentil: hanya nilai yang DIKETAHUI (NaN dibuang, bukan diisi 0)
        self._ref = {n: np.sort(self.basis[n].dropna().to_numpy(float)) for n in self.nutrisi}
        self.pct = pd.DataFrame({n: self._hitung_persentil(n) for n in self.nutrisi})
        # resep yang datanya tidak lengkap -> nilai 0 pada nutrisi lain patut diragukan (mungkin belum tercatat)
        self.data_tidak_lengkap = self.basis[self.nutrisi].isna().any(axis=1).to_numpy()

    # ---- memuat artefak ---------------------------------------------------- #
    @classmethod
    def muat(cls, folder, muat_model=True):
        folder = Path(folder)
        cfg_path = folder / "config_model.json"
        basis_path = folder / "basis_resep.csv"
        for p in (cfg_path, basis_path):
            if not p.exists():
                raise FileNotFoundError(f"Artefak tidak ditemukan: {p}")
        config = json.loads(cfg_path.read_text(encoding="utf-8"))
        basis = pd.read_csv(basis_path)
        pipeline = None
        if muat_model:
            import joblib
            mp = folder / "kmeans_pipeline.joblib"
            if not mp.exists():
                raise FileNotFoundError(f"Model tidak ditemukan: {mp}")
            pipeline = joblib.load(mp)
        return cls(basis, config, pipeline)

    # ---- util -------------------------------------------------------------- #
    def _hitung_persentil(self, n):
        ref = self._ref[n]
        v = self.basis[n].to_numpy(float)
        out = np.full(len(v), np.nan)
        ok = ~np.isnan(v)
        if len(ref) and ok.any():
            lo = np.searchsorted(ref, v[ok], side="left")
            hi = np.searchsorted(ref, v[ok], side="right")
            out[ok] = (lo + hi) / 2.0 / len(ref)
        return out

    @staticmethod
    def _tingkat(p):
        return "tinggi" if p >= 0.67 else ("sedang" if p >= 0.33 else "rendah")

    def prediksi_cluster(self, nutrisi: dict) -> int:
        """Cluster untuk resep BARU berdasarkan fitur clustering (semua wajib diisi)."""
        if self.pipeline is None:
            raise RuntimeError("Model K-Means belum dimuat (muat_model=False).")
        fitur = self.config["fitur_clustering_alias"]
        kurang = [f for f in fitur if f not in {normalisasi_alias(k) for k in nutrisi}]
        if kurang:
            raise ValueError(f"Nutrisi untuk clustering belum lengkap: {kurang}")
        nut = {normalisasi_alias(k): v for k, v in nutrisi.items()}
        x = pd.DataFrame([[float(nut[f]) for f in fitur]],
                         columns=[self.kolom_nutrisi[f] for f in fitur])
        if x.isna().any().any() or (x < 0).any().any():
            raise ValueError("Nilai nutrisi harus terisi dan >= 0.")
        return int(self.pipeline.predict(x)[0])

    # ---- inti: rekomendasi --------------------------------------------------- #
    def rekomendasi(self, payload: dict) -> dict:
        if not isinstance(payload, dict):
            raise ValueError("Payload harus dict/JSON object.")
        tak_dikenal = set(payload) - KUNCI_PAYLOAD
        if tak_dikenal:
            raise ValueError(f"Kunci payload tidak dikenali: {sorted(tak_dikenal)}. Valid: {sorted(KUNCI_PAYLOAD)}")
        peringatan = list(PERINGATAN_STANDAR)

        # prioritas
        if payload.get("prioritas_gizi"):
            w = validasi_prioritas(payload["prioritas_gizi"], self.nutrisi)
        elif payload.get("asupan_harian") is not None and payload.get("acuan_harian"):
            w = hitung_prioritas_dari_asupan(payload["asupan_harian"], payload["acuan_harian"], self.nutrisi)
            peringatan.append("Bobot diturunkan dari kekurangan asupan terhadap `acuan_harian` yang diberikan pemanggil.")
        else:
            raise ValueError("Berikan `prioritas_gizi` atau pasangan `asupan_harian` + `acuan_harian`.")

        # parameter
        top_n = int(payload.get("top_n", 5))
        if not 1 <= top_n <= 50:
            raise ValueError("`top_n` harus 1..50.")
        bahan_user = payload.get("bahan_tersedia") or []
        if isinstance(bahan_user, str):
            bahan_user = [bahan_user]
        bahan_user = [p.strip() for x in bahan_user for p in str(x).split("/") if p.strip()]  # 'asi/susu' -> 2 bahan
        mode = payload.get("mode_bahan") or ("sebagian" if bahan_user else "nonaktif")
        if mode not in {"nonaktif", "semua_wajib", "sebagian"}:
            raise ValueError("`mode_bahan` harus 'nonaktif', 'semua_wajib', atau 'sebagian'.")
        if mode != "nonaktif" and not bahan_user:
            raise ValueError("Filter bahan aktif tetapi `bahan_tersedia` kosong.")
        min_kec = float(payload.get("min_kecocokan", 0.5))
        if not 0 <= min_kec <= 1:
            raise ValueError("`min_kecocokan` harus 0..1.")
        metode = payload.get("metode", self.config.get("metode_default", "baseline"))
        if metode not in {"baseline", "cluster_diverse"}:
            raise ValueError("`metode` harus 'baseline' atau 'cluster_diverse'.")
        lam = float(payload.get("lambda_keragaman", self.config.get("lambda_keragaman", 0.05)))
        if not np.isfinite(lam) or lam < 0:
            raise ValueError("`lambda_keragaman` harus >= 0.")
        kebijakan = payload.get("kebijakan_nilai_hilang", "penalti")
        if kebijakan not in {"penalti", "keluarkan"}:
            raise ValueError("`kebijakan_nilai_hilang` harus 'penalti' atau 'keluarkan'.")
        maks_waktu = payload.get("maks_waktu_menit")
        if maks_waktu is not None:
            maks_waktu = float(maks_waktu)
            if not np.isfinite(maks_waktu) or maks_waktu <= 0:
                raise ValueError("`maks_waktu_menit` harus > 0.")
        hindari = [tokens_bahan(p) for x in (payload.get("bahan_dihindari") or []) for p in str(x).split("/")]
        hindari = [h for h in hindari if h]
        user_tokens = [t for t in (tokens_bahan(x) for x in bahan_user) if t]
        if bahan_user and not user_tokens:
            raise ValueError("`bahan_tersedia` tidak berisi nama bahan yang terbaca.")
        if metode == "cluster_diverse" and self.basis["cluster"].isna().all():
            raise ValueError("Basis resep tidak memiliki label cluster.")

        n = len(self.basis)
        alasan_keluar = {"waktu_masak": 0, "bahan_dihindari": 0, "bahan_tidak_cukup": 0,
                         "bahan_wajib_tidak_terdeteksi": 0, "nutrisi_prioritas_tidak_tersedia": 0}
        layak = np.ones(n, bool)
        info_bahan = [None] * n

        for i in range(n):
            items = self.bahan[i]
            wajib = [b for b in items if b["kategori"] == "wajib"]
            cocok_w = [b for b in wajib if bahan_cocok(b, user_tokens)] if user_tokens else []
            kurang = [b["nama"] for b in wajib if b not in cocok_w]
            frac = (len(cocok_w) / len(wajib)) if (wajib and user_tokens) else float("nan")
            info_bahan[i] = {"jumlah_wajib": len(wajib), "wajib_cocok": len(cocok_w),
                             "fraksi_cocok": frac, "wajib_kurang": kurang}
            if maks_waktu is not None and not (self.basis.loc[i, "waktu_menit"] <= maks_waktu):
                layak[i] = False; alasan_keluar["waktu_masak"] += 1; continue
            if hindari and any(bahan_cocok(b, hindari) for b in items):
                layak[i] = False; alasan_keluar["bahan_dihindari"] += 1; continue
            if mode != "nonaktif":
                if not wajib:
                    layak[i] = False; alasan_keluar["bahan_wajib_tidak_terdeteksi"] += 1; continue
                ok = (len(kurang) == 0) if mode == "semua_wajib" else (frac >= min_kec)
                if not ok:
                    layak[i] = False; alasan_keluar["bahan_tidak_cukup"] += 1; continue
            if kebijakan == "keluarkan" and self.pct.loc[i, list(w)].isna().any():
                layak[i] = False; alasan_keluar["nutrisi_prioritas_tidak_tersedia"] += 1

        # skor: jumlah bobot * persentil, HANYA atas nutrisi yang diketahui (NaN = tanpa kredit, bukan 0)
        P = self.pct[list(w)]
        bobot = np.array([w[k] for k in P.columns])
        skor = np.nansum(P.to_numpy(float) * bobot, axis=1)
        ketersediaan = (~P.isna()).to_numpy() @ bobot
        if not np.all(np.isfinite(skor)):
            raise RuntimeError("Skor tidak finite; periksa data.")

        kand = pd.DataFrame({"idx": np.arange(n), "score": skor, "ketersediaan": ketersediaan,
                             "fraksi": [(x["fraksi_cocok"] if np.isfinite(x["fraksi_cocok"]) else -1.0)
                                        for x in info_bahan],
                             "cluster": self.basis["cluster"].to_numpy(float), "id": self.basis["id_resep"]})
        kand = kand[layak].sort_values(["score", "fraksi", "id"], ascending=[False, False, True])
        urut = kand["idx"].tolist()

        if metode == "cluster_diverse":
            # Pengurang BERBATAS: lambda hanya dikenakan bila cluster resep sudah terwakili di daftar terpilih.
            terpilih, terwakili = [], set()
            sisa = kand.reset_index(drop=True)
            skor_seleksi = {}
            while len(terpilih) < top_n and len(sisa):
                sudah = sisa["cluster"].map(lambda c: c in terwakili).to_numpy(float)
                adj = sisa["score"].to_numpy() - lam * sudah
                j = int(np.argmax(adj))
                baris = sisa.iloc[j]
                terpilih.append(int(baris["idx"])); skor_seleksi[int(baris["idx"])] = float(adj[j])
                terwakili.add(baris["cluster"])
                sisa = sisa.drop(sisa.index[j]).reset_index(drop=True)
            urut = terpilih
        else:
            urut = urut[:top_n]
            skor_seleksi = {}

        hasil = []
        for r, i in enumerate(urut, 1):
            row = self.basis.loc[i]
            nut_prior, alasan, hilang = {}, [], []
            for k in w:
                nilai = row[k]
                satu = self.satuan.get(k, "")
                if pd.isna(nilai):
                    hilang.append(k)
                    nut_prior[k] = {"nilai": None, "satuan": satu, "persentil": None, "tersedia": False}
                    alasan.append(f"{LABEL_NUTRISI[k]}: nilai tidak tersedia di data — tidak mendapat kredit skor "
                                  f"dan TIDAK dianggap terpenuhi.")
                else:
                    p = float(self.pct.loc[i, k])
                    nut_prior[k] = {"nilai": _aman(nilai), "satuan": satu, "persentil": round(p, 3), "tersedia": True}
                    teks = (f"{LABEL_NUTRISI[k]} {nilai:.1f} {satu}/porsi — {self._tingkat(p)} "
                            f"dibanding resep lain (persentil {p*100:.0f}).")
                    if nilai == 0 and self.data_tidak_lengkap[i]:
                        nut_prior[k]["catatan"] = "nilai 0 diragukan (data gizi resep ini tidak lengkap)"
                        teks += " Catatan: nilai 0 diragukan karena data gizi resep ini tidak lengkap."
                    alasan.append(teks)
            ib = info_bahan[i]
            if user_tokens:
                if ib["jumlah_wajib"]:
                    alasan.append(f"Bahan wajib cocok {ib['wajib_cocok']}/{ib['jumlah_wajib']}"
                                  + (f"; belum ada: {', '.join(ib['wajib_kurang'][:5])}" if ib["wajib_kurang"] else "."))
                else:
                    alasan.append("Bahan wajib tidak terdeteksi pada resep ini.")
            if metode == "cluster_diverse" and not pd.isna(row["cluster"]):
                alasan.append(f"Dipilih dengan mempertimbangkan keragaman cluster nutrisi (cluster {int(row['cluster'])}).")
            hasil.append({
                "rank": r, "id_resep": row["id_resep"], "nama_menu": row["nama_menu"],
                "score": round(float(skor[i]), 4),
                "skor_seleksi": round(skor_seleksi[i], 4) if i in skor_seleksi else None,
                "cluster": None if pd.isna(row["cluster"]) else int(row["cluster"]),
                "porsi": _aman(row["porsi"]), "waktu_menit": _aman(row["waktu_menit"]),
                "ketersediaan_data_prioritas": round(float(ketersediaan[i]), 3),
                "nutrisi_prioritas": nut_prior, "nutrisi_tidak_tersedia": hilang,
                "kecocokan_bahan": {"diperiksa": bool(user_tokens), "jumlah_wajib": ib["jumlah_wajib"],
                                    "wajib_cocok": ib["wajib_cocok"] if user_tokens else None,
                                    "fraksi_cocok": _aman(ib["fraksi_cocok"]),
                                    "wajib_kurang": ib["wajib_kurang"] if user_tokens else []},
                "alasan": alasan,
            })
        return {"meta": {"versi_modul": VERSI_MODUL, "versi_model": self.config.get("versi_model"),
                         "metode": metode, "bobot_ternormalisasi": {k: round(v, 4) for k, v in w.items()},
                         "mode_bahan": mode, "kebijakan_nilai_hilang": kebijakan, "top_n": top_n,
                         "jumlah_kandidat_layak": int(layak.sum()), "jumlah_resep_total": n,
                         "dikeluarkan": alasan_keluar, "peringatan": peringatan},
                "hasil": hasil}


def hasil_ke_dataframe(out: dict) -> pd.DataFrame:
    """Ringkas output rekomendasi menjadi DataFrame yang rapi untuk ditampilkan."""
    baris = []
    for h in out["hasil"]:
        r = {"rank": h["rank"], "id_resep": h["id_resep"], "nama_menu": h["nama_menu"], "score": h["score"],
             "cluster": h["cluster"]}
        for k, d in h["nutrisi_prioritas"].items():
            r[f"{k}_{d['satuan']}" if d["satuan"] else k] = d["nilai"]
        kb = h["kecocokan_bahan"]
        r["kecocokan_bahan"] = f"{kb['wajib_cocok']}/{kb['jumlah_wajib']}" if kb.get("diperiksa") else "- (tidak diperiksa)"
        r["nutrisi_tidak_tersedia"] = ", ".join(h["nutrisi_tidak_tersedia"]) or "-"
        r["alasan"] = " | ".join(h["alasan"])
        baris.append(r)
    return pd.DataFrame(baris)
