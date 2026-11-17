"""Contoh memakai artefak dari Python biasa (di luar Colab). Jalankan: python contoh_penggunaan.py"""
import json
import sys
from pathlib import Path

FOLDER = Path(__file__).resolve().parent
sys.path.insert(0, str(FOLDER))
from rekomendasi_gizi import PemberiRekomendasi            # noqa: E402

model = PemberiRekomendasi.muat(FOLDER)                    # muat SEKALI (jangan per permintaan)
payload = json.loads((FOLDER / "contoh_input.json").read_text(encoding="utf-8"))
hasil = model.rekomendasi(payload)

print("Model versi", hasil["meta"]["versi_model"], "| metode", hasil["meta"]["metode"],
      "| kandidat layak", hasil["meta"]["jumlah_kandidat_layak"])
for h in hasil["hasil"]:
    print(f"{h['rank']}. {h['id_resep']} {h['nama_menu']}  skor={h['score']}  cluster={h['cluster']}")
    for a in h["alasan"]:
        print("     -", a)
print("\nCluster untuk resep baru (contoh nilai ilustrasi):",
      model.prediksi_cluster({"energi": 150, "karbohidrat": 20, "protein": 6, "lemak": 4, "zat_besi": 1.0,
                              "vitamin_c": 3, "vitamin_a": 120, "serat": 1.5}))
