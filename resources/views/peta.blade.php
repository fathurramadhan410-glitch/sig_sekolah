<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Peta Sekolah</title>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <!-- Leaflet Marker Cluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
    <style>
        html, body { height: 100%; margin: 0; font-family: system-ui, sans-serif; }
        header { height: 56px; box-sizing: border-box; padding: 0 16px; display: flex; align-items: center; gap: 12px; background: #1e3a8a; color: #fff; flex-wrap: wrap; }
        header h1 { font-size: 18px; margin: 0; }
        header select, header input { padding: 4px 8px; border-radius: 4px; border: none; font-size: 13px; }
        header a { color: #bfdbfe; margin-left: auto; text-decoration: none; }
        #map { height: calc(100% - 56px); }
    </style>
</head>
<body>
    <header>
        <h1>Peta Sekolah</h1>
        
        <!-- Filter Jenjang -->
        <select id="filter-jenjang">
            <option value="">Semua Jenjang</option>
            <option value="SD">SD</option>
            <option value="SMP">SMP</option>
            <option value="SMA">SMA</option>
            <option value="SMK">SMK</option>
        </select>

        <!-- Filter Status -->
        <select id="filter-status">
            <option value="">Semua Status</option>
            <option value="Negeri">Negeri</option>
            <option value="Swasta">Swasta</option>
        </select>

        <!-- Pencarian Nama Sekolah -->
        <input type="text" id="cari-sekolah" placeholder="Cari nama sekolah... (Tekan Enter)">

        <span id="jumlah" style="font-size: 14px;"></span>
        <a href="{{ route('admin.sekolah.index') }}">Kelola Data</a>
    </header>

    <div id="map"></div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet Marker Cluster JS -->
    <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>

    <script>
        // 1. Definisikan Peta Dasar (Base Maps) & Kontrol Layer
        const osmLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        });

        const topographicLayer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
            maxZoom: 17,
            attribution: '&copy; OpenTopoMap contributors'
        });

        const map = L.map('map', {
            center: [-3.3194, 114.5908],
            zoom: 12,
            layers: [osmLayer] // Layer default
        });

        const baseMaps = {
            "OpenStreetMap": osmLayer,
            "Topografi": topographicLayer
        };
        L.control.layers(baseMaps).addTo(map);

        // 2. Inisialisasi Marker Cluster Group
        const markersCluster = L.markerClusterGroup();
        map.addLayer(markersCluster);

        // Objek untuk menyimpan referensi marker pencarian
        let markerLayers = {};

        // Mencegah XSS
        function esc(teks) {
            const d = document.createElement('div');
            d.textContent = teks ?? '';
            return d.innerHTML;
        }

        // 3. Konfigurasi GeoJSON dengan Warna Marker Berbeda Tiap Jenjang
        const lapisan = L.geoJSON(null, {
            pointToLayer: function (feature, latlng) {
                const jenjang = feature.properties.jenjang;
                let warna = 'gray';
                
                if (jenjang === 'SD') warna = '#2563eb';      // Biru
                else if (jenjang === 'SMP') warna = '#16a34a'; // Hijau
                else if (jenjang === 'SMA') warna = '#d97706'; // Oranye
                else if (jenjang === 'SMK') warna = '#dc2626'; // Merah

                return L.circleMarker(latlng, {
                    radius: 9,
                    fillColor: warna,
                    color: '#ffffff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.9
                });
            },
            onEachFeature: (fitur, layer) => {
                const p = fitur.properties;
                const [lng, lat] = fitur.geometry.coordinates;

                layer.bindPopup(`
                    <strong>${esc(p.nama)}</strong><br>
                    NPSN: ${esc(p.npsn)}<br>
                    ${esc(p.jenjang)} ${esc(p.status)}<br>
                    ${esc(p.alamat)}, ${esc(p.kecamatan)}<br>
                    Telp: ${esc(p.telepon) || '-'}<br>
                    <small>Koordinat: ${lat}, ${lng}</small>
                `);

                // Simpan untuk pencarian
                markerLayers[p.nama.toLowerCase()] = { layer: layer, latlng: [lat, lng] };

                // Tambahkan ke cluster group
                markersCluster.addLayer(layer);
            }
        });

        // 4. Fungsi Muat Data Berdasarkan Filter
        let currentJenjang = '';
        let currentStatus = '';

        async function muatData() {
            const url = `{{ route('peta.data') }}?jenjang=${currentJenjang}&status=${currentStatus}`;
            const respon = await fetch(url);
            const data = await respon.json();

            markersCluster.clearLayers();
            lapisan.clearLayers();
            markerLayers = {};

            lapisan.addData(data);

            document.getElementById('jumlah').textContent = data.features.length + ' sekolah';

            if (data.features.length > 0) {
                map.fitBounds(markersCluster.getBounds().pad(0.1));
            }
        }

        // Event listener filter
        document.getElementById('filter-jenjang').addEventListener('change', (e) => {
            currentJenjang = e.target.value;
            muatData();
        });

        document.getElementById('filter-status').addEventListener('change', (e) => {
            currentStatus = e.target.value;
            muatData();
        });

        // 5. Fitur Pencarian Nama Sekolah (Tekan Enter)
        document.getElementById('cari-sekolah').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                const keyword = this.value.toLowerCase().trim();
                if (!keyword) return;

                const matchedKey = Object.keys(markerLayers).find(nama => nama.includes(keyword));

                if (matchedKey) {
                    const target = markerLayers[matchedKey];
                    map.setView(target.latlng, 16);
                    target.layer.openPopup();
                } else {
                    alert('Sekolah tidak ditemukan.');
                }
            }
        });

        // Eksekusi awal saat halaman dimuat
        muatData();
    </script>
</body>
</html>