import Chart from 'chart.js/auto';

const charts = new WeakMap();

const KATEGORI_WARNA = {
  NORMAL_LOW_RISK: '#3c9a5f',
  AT_RISK_MONITORING: '#f59e0b',
  HIGH_RISK_REFERRAL: '#e53935',
};

function destroyChart(canvas) {
  const chart = charts.get(canvas);
  if (chart) {
    chart.destroy();
    charts.delete(canvas);
  }
}

function formatAngka(nilai) {
  return Number(nilai).toLocaleString('id-ID', {
    maximumFractionDigits: 1,
  });
}

export function renderPrevalensi(canvas, data) {
  if (!canvas || !Array.isArray(data) || data.length === 0) return;

  destroyChart(canvas);

  const labels = data.map((item) => item.label);
  const nilai = data.map((item) => item.persen_perlu_perhatian);
  const points = data.map((item) => {
    if (item.persen_perlu_perhatian === null) return 0;
    return item.denominator_kecil ? 5 : 4;
  });

  const chart = new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: '% perlu perhatian',
        data: nilai,
        borderColor: '#006a38',
        backgroundColor: 'rgba(0, 106, 56, 0.12)',
        pointBackgroundColor: data.map((item) => (
          item.denominator_kecil ? '#e53935' : '#006a38'
        )),
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: points,
        pointHoverRadius: 6,
        spanGaps: false,
        tension: 0.25,
        fill: false,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: {
            callback: (value) => `${value}%`,
          },
        },
      },
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          callbacks: {
            label: (context) => {
              const item = data[context.dataIndex];
              if (item.persen_perlu_perhatian === null) {
                return 'Belum ada data pengukuran';
              }

              const baris = [
                `${formatAngka(item.persen_perlu_perhatian)}% perlu perhatian`,
                `n=${item.total_diukur}`,
                `Normal ${item.normal} · At risk ${item.at_risk} · High risk ${item.high_risk}`,
              ];

              if (item.tidak_terklasifikasi > 0) {
                baris.push(`Tidak terklasifikasi ${item.tidak_terklasifikasi}`);
              }
              if (item.denominator_kecil) {
                baris.push('Denominator kecil — tafsirkan hati-hati');
              }

              return baris;
            },
          },
        },
      },
    },
  });

  charts.set(canvas, chart);
}

export function renderPertumbuhan(canvas, payload, indikator = 'bb_u') {
  if (!canvas || !payload) return;

  destroyChart(canvas);

  const acuan = payload.acuan && Array.isArray(payload.acuan[indikator])
    ? payload.acuan[indikator]
    : [];
  const titik = Array.isArray(payload.titik) ? payload.titik : [];
  const berat = indikator === 'bb_u';
  const labelSumbu = berat ? 'Berat badan (kg)' : 'Tinggi badan (cm)';

  const garis = [
    { kunci: 'sd_minus3', label: '-3 SD', warna: '#ef9a9a' },
    { kunci: 'sd_minus2', label: '-2 SD', warna: '#f59e0b' },
    { kunci: 'median', label: 'Median', warna: '#94a3b8' },
    { kunci: 'sd_plus2', label: '+2 SD', warna: '#f59e0b' },
    { kunci: 'sd_plus3', label: '+3 SD', warna: '#ef9a9a' },
  ];

  const datasets = garis.map((garisAcuan) => ({
    label: garisAcuan.label,
    data: acuan.map((row) => ({
      x: row.usia_bulan,
      y: row[garisAcuan.kunci],
    })),
    borderColor: garisAcuan.warna,
    backgroundColor: 'transparent',
    borderWidth: garisAcuan.kunci === 'median' ? 2 : 1,
    pointRadius: 0,
    pointHoverRadius: 0,
    tension: 0.2,
  }));

  datasets.push({
    label: 'Anak',
    data: titik.map((item) => ({
      x: item.usia_bulan_saat_ukur ?? item.umur_bulan,
      y: berat ? item.berat_kg : item.tinggi_cm,
      _titik: item,
    })),
    borderColor: '#006a38',
    backgroundColor: '#006a38',
    borderWidth: 2.5,
    pointRadius: 5,
    pointHoverRadius: 7,
    pointBackgroundColor: titik.map((item) => (
      KATEGORI_WARNA[item.kategori_pemantauan] || '#64748b'
    )),
    pointBorderColor: '#ffffff',
    pointBorderWidth: 2,
    tension: 0.2,
  });

  const chart = new Chart(canvas, {
    type: 'line',
    data: { datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'nearest',
        intersect: false,
      },
      scales: {
        x: {
          type: 'linear',
          min: 0,
          max: 60,
          title: {
            display: true,
            text: 'Usia (bulan)',
          },
          ticks: {
            stepSize: 6,
          },
        },
        y: {
          beginAtZero: false,
          title: {
            display: true,
            text: labelSumbu,
          },
        },
      },
      plugins: {
        legend: {
          labels: {
            boxWidth: 10,
            filter: (item) => item.text !== 'Anak',
          },
        },
        tooltip: {
          filter: (context) => context.dataset.label === 'Anak',
          callbacks: {
            label: (context) => {
              const item = context.raw._titik;
              if (!item) return '';

              const ukuran = berat
                ? `${formatAngka(item.berat_kg)} kg`
                : `${formatAngka(item.tinggi_cm)} cm`;

              return [
                `${item.tanggal_ukur} · ${formatAngka(item.usia_bulan_saat_ukur ?? item.umur_bulan)} bln`,
                `${ukuran} · ${item.kategori_pemantauan || 'Tidak terklasifikasi'}`,
              ];
            },
          },
        },
      },
    },
  });

  charts.set(canvas, chart);
}
