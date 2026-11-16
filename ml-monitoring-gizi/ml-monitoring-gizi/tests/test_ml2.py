"""ML2: kompatibilitas hasil dengan artefak asli, penanganan nilai tidak diketahui, filter, dan validasi."""
import hashlib
import json
import math

import joblib
import pandas as pd
import pytest

from tests.conftest import EXAMPLES, ML2_DIR, ROOT, muat_json

NUT = ["energi", "karbohidrat", "protein", "lemak", "zat_besi", "vitamin_c", "kalsium", "zinc", "vitamin_a", "serat"]


def test_contoh_input_menghasilkan_contoh_output_persis(ml2):
    assert ml2.rekomendasi(muat_json("ml2_input.json")) == muat_json("ml2_output.json")


def test_label_cluster_basis_dan_prediksi_ulang_pipeline_identik():
    cfg = json.loads((ML2_DIR / "config_model.json").read_text(encoding="utf-8"))
    d = pd.read_csv(ROOT / "docs" / "ml2" / "dataset_dengan_cluster.csv")
    pipe = joblib.load(ML2_DIR / "kmeans_pipeline.joblib")
    pred = pipe.predict(d[cfg["fitur_clustering_kolom"]])
    assert len(d) == 93 and (pred == d["cluster"].to_numpy()).all()
    basis = pd.read_csv(ML2_DIR / "basis_resep.csv")
    assert (basis["cluster"].astype(int).to_numpy() == pred).all()


def test_nilai_tidak_diketahui_adalah_nan_bukan_nol(ml2):
    cfg = ml2.config
    assert ml2.basis["kalsium"].isna().sum() == cfg["metadata_missing"]["kalsium"] == 15
    assert ml2.basis["zinc"].isna().sum() == cfg["metadata_missing"]["zinc"] == 21
    assert ml2.basis["vitamin_c"].isna().sum() == cfg["metadata_missing"]["vitamin_c"] == 54
    assert not (ml2.basis[["kalsium", "zinc"]] == 0).any().any()


def test_config_menandai_cluster_usang_dan_versi_baru(ml2):
    cfg = ml2.config
    assert cfg["status_cluster"] == "USANG_DATA_LAMA"
    assert "tidak dihitung ulang" in cfg["alasan_status_cluster"]
    assert cfg["versi_model"] != "1.0.0"
    assert cfg["sumber_data"]["nama_berkas"].endswith("_tkpi.csv")


def test_bahan_json_identik_dengan_basis_lama(ml2):
    lama = json.loads((ROOT / "tests" / "fixtures_bahan_json_sha256_lama.json").read_text(encoding="utf-8"))
    sekarang = {
        str(r.id_resep): hashlib.sha256(str(r.bahan_json).encode("utf-8")).hexdigest()
        for r in ml2.basis.itertuples()
    }
    assert sekarang == lama
    berubah = ml2.basis.loc[
        ml2.basis["bahan_dan_bumbu"].str.contains(r"Kelapa parut: 50 g|Soun .*: 60 g|Agar-agar: 1 sdt", regex=True)
    ]["id_resep"]
    assert set(berubah) >= {"R042", "R046", "R057"}


def test_14_resep_tanpa_gizi_tidak_mendapat_kredit_di_ranking(ml2):
    kosong = {"R001", "R007", "R019", "R022", "R026", "R028", "R041", "R048",
              "R050", "R053", "R056", "R077", "R083", "R092"}
    semua = set(ml2.basis.loc[ml2.basis[NUT].isna().all(axis=1), "id_resep"])
    assert semua == kosong

    for zat in ["energi", "karbohidrat", "protein", "lemak", "zat_besi", "kalsium", "zinc", "vitamin_a", "serat", "vitamin_c"]:
        top_n = 5 if zat == "vitamin_c" else 50
        out = ml2.rekomendasi({"prioritas_gizi": {zat: 1}, "top_n": top_n})
        assert not (kosong & {h["id_resep"] for h in out["hasil"]}), zat


@pytest.mark.parametrize("zat", ["kalsium", "zinc", "zat_besi"])
def test_top5_prioritas_mikronutrien_sesuai_nilai_basis(ml2, zat):
    out = ml2.rekomendasi({"prioritas_gizi": {zat: 1}, "top_n": 5})
    nilai = [h["nutrisi_prioritas"][zat]["nilai"] for h in out["hasil"]]
    acuan = sorted(ml2.basis[zat].dropna().to_numpy(float), reverse=True)[:5]
    assert nilai == pytest.approx(acuan)


def test_resep_kalsium_tidak_diketahui_ditandai_dan_tidak_diklaim_memenuhi(ml2):
    out = ml2.rekomendasi({"prioritas_gizi": {"kalsium": 1}, "top_n": 50})
    for h in out["hasil"]:
        d = h["nutrisi_prioritas"]["kalsium"]
        if not d["tersedia"]:
            assert d["nilai"] is None and d["persentil"] is None and "kalsium" in h["nutrisi_tidak_tersedia"]
    # resep dengan data kalsium harus berperingkat di atas yang tidak diketahui (tanpa kredit)
    tersedia = [h["nutrisi_prioritas"]["kalsium"]["tersedia"] for h in out["hasil"]]
    assert tersedia == sorted(tersedia, reverse=True)


def test_kebijakan_keluarkan_membuang_resep_tidak_diketahui(ml2):
    out = ml2.rekomendasi({"prioritas_gizi": {"kalsium": 1}, "top_n": 50, "kebijakan_nilai_hilang": "keluarkan"})
    assert out["meta"]["dikeluarkan"]["nutrisi_prioritas_tidak_tersedia"] == 15
    assert all(h["nutrisi_prioritas"]["kalsium"]["tersedia"] for h in out["hasil"])


def test_skor_finite_dan_dalam_0_1_untuk_banyak_prioritas(ml2):
    import random
    rnd = random.Random(0)
    for _ in range(40):
        pr = {k: rnd.random() + 0.01 for k in rnd.sample(NUT, rnd.randint(1, 4))}
        out = ml2.rekomendasi({"prioritas_gizi": pr, "top_n": 50})
        for h in out["hasil"]:
            assert math.isfinite(h["score"]) and 0 <= h["score"] <= 1
        json.dumps(out, allow_nan=False)


def test_ranking_menurun_dan_bobot_ternormalisasi(ml2):
    out = ml2.rekomendasi({"prioritas_gizi": {"protein": 3, "energi": 1}, "top_n": 50})
    assert out["meta"]["bobot_ternormalisasi"] == {"protein": 0.75, "energi": 0.25}
    skor = [h["score"] for h in out["hasil"]]
    assert skor == sorted(skor, reverse=True)
    assert [h["rank"] for h in out["hasil"]] == list(range(1, len(skor) + 1))


def test_mode_semua_wajib_tidak_ada_bahan_kurang(ml2):
    out = ml2.rekomendasi({"prioritas_gizi": {"protein": 1}, "top_n": 50, "mode_bahan": "semua_wajib",
                           "bahan_tersedia": ["nasi", "ayam", "tahu", "wortel", "telur", "tempe", "ikan", "ubi rambat",
                                              "kentang", "buncis", "tomat", "santan", "pisang", "bayam", "susu"]})
    assert out["hasil"], "harus ada hasil untuk uji ini"
    assert all(h["kecocokan_bahan"]["wajib_kurang"] == [] for h in out["hasil"])


def test_mode_sebagian_menghormati_min_kecocokan(ml2):
    inp = muat_json("ml2_input.json") | {"top_n": 50}
    out = ml2.rekomendasi(inp)
    assert out["hasil"]
    assert all(h["kecocokan_bahan"]["fraksi_cocok"] >= inp["min_kecocokan"] for h in out["hasil"])


def test_filter_waktu_dan_bahan_dihindari(ml2):
    out = ml2.rekomendasi({"prioritas_gizi": {"protein": 1}, "top_n": 50, "maks_waktu_menit": 30,
                           "bahan_dihindari": ["telur"]})
    assert out["hasil"] and all(h["waktu_menit"] <= 30 for h in out["hasil"])
    bas = ml2.basis.set_index("id_resep")
    for h in out["hasil"]:
        nama_bahan = " ".join(b["nama"].lower() for b in json.loads(bas.loc[h["id_resep"], "bahan_json"]))
        assert "telur" not in nama_bahan


@pytest.mark.parametrize("payload", [
    {},                                                                   # tanpa prioritas
    {"prioritas_gizi": {"protein": -1}},                                  # bobot negatif
    {"prioritas_gizi": {"protein": float("nan")}},
    {"prioritas_gizi": {"protein": 0}},                                   # total nol
    {"prioritas_gizi": {"vitamin_z": 1}},                                 # zat gizi tidak dikenal
    {"prioritas_gizi": {"protein": 1}, "top_n": 0},
    {"prioritas_gizi": {"protein": 1}, "top_n": 51},
    {"prioritas_gizi": {"protein": 1}, "top_m": 3},                       # kunci salah ketik
    {"prioritas_gizi": {"protein": 1}, "mode_bahan": "semua_wajib"},      # filter aktif tanpa bahan
    {"prioritas_gizi": {"protein": 1}, "mode_bahan": "ngawur", "bahan_tersedia": ["ayam"]},
    {"prioritas_gizi": {"protein": 1}, "metode": "ngawur"},
    {"prioritas_gizi": {"protein": 1}, "min_kecocokan": 1.5},
    {"prioritas_gizi": {"protein": 1}, "kebijakan_nilai_hilang": "isi_nol"},
])
def test_masukan_tidak_valid_ditolak_valueerror(ml2, payload):
    with pytest.raises(ValueError):
        ml2.rekomendasi(payload)


def test_tidak_ada_training_saat_rekomendasi(ml2, monkeypatch):
    from sklearn.cluster import KMeans
    monkeypatch.setattr(KMeans, "fit", lambda *a, **k: (_ for _ in ()).throw(AssertionError("fit dipanggil")))
    ml2.rekomendasi(muat_json("ml2_input.json"))
    ml2.rekomendasi({"prioritas_gizi": {"protein": 1}, "metode": "cluster_diverse"})


def test_metode_cluster_diverse_tetap_tersedia_tetapi_bukan_default(ml2):
    assert ml2.config["metode_default"] == "baseline"
    out = ml2.rekomendasi({"prioritas_gizi": {"protein": 1}, "metode": "cluster_diverse", "top_n": 5})
    assert out["meta"]["metode"] == "cluster_diverse" and len(out["hasil"]) == 5


def test_peringatan_standar_selalu_ada(ml2):
    out = ml2.rekomendasi(muat_json("ml2_input.json"))
    teks = " ".join(out["meta"]["peringatan"])
    assert "bukan catatan konsumsi anak" in teks and "belum divalidasi" in teks
