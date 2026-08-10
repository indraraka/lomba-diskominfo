# Lomba Diskominfo

Aplikasi operasional 10 lomba Agustusan 2026 berdasarkan dokumen **Peraturan Tiap Mata Lomba**. Sistem mencakup roster anggota, checklist keselamatan, heat fleksibel, timer server-side, rubrik juri, metrik waktu/sisa, eliminasi, Cerdas Cermat dua babak, ledger skor, penalti, audit, bracket Voli Air, tie-break, dan hasil Juara 1–3.

## Menjalankan secara lokal

Prasyarat: PHP 8.3+, Composer, Node.js 22+, dan SQLite/MySQL.

```bash
composer install
npm install
php artisan migrate --seed
```

Jalankan aplikasi, frontend, dan WebSocket pada tiga terminal:

```bash
php artisan serve
npm run dev
php artisan reverb:start
```

Buka `http://127.0.0.1:8000`. Akun pengembangan bawaan adalah `admin@lomba.test` dengan password `Lomba17!`; ganti melalui profil atau variabel `ADMIN_EMAIL` dan `ADMIN_PASSWORD` sebelum menjalankan seeder.

## Alur acara

1. Pilih salah satu dari 10 profil resmi, lalu klik **Siapkan sesi baru**.
2. Daftarkan peserta/regu, afiliasi, dan anggota sesuai batas profil. Konfirmasikan checklist perlengkapan dan keselamatan.
3. Atur kapasitas heat atau opsi Juara 3, lalu buat babak. Susunan dan snapshot aturan terkunci setelah babak dibuat.
4. Jalankan timer dan catat hasil, penalti, gangguan alat, atau keputusan juri. Sistem menghitung peringkat lintas heat dan menyimpan audit.
5. Untuk Cerdas Cermat, catat 10 soal wajib secara serentak, lanjutkan 10 soal rebutan melalui buzzer, lalu gunakan tie-break bila perlu.
6. Buka **Layar publik** di proyektor. Reverb digunakan untuk real-time dan polling otomatis menjadi fallback ketika WebSocket terputus.

Profil resmi tidak dapat mengubah aturan inti. Gunakan **Kloning sebagai kustom** bila panitia perlu membuat variasi aturan. Karena skema operasional 2026 mengganti skema prototipe, instalasi awal menggunakan `php artisan migrate:fresh --seed`.

## Deployment production

Production dibangun sebagai image berbasis commit SHA oleh GitHub Actions dan otomatis dipasang ke VM setelah CI pada branch `main` lulus. Runtime menggunakan MySQL dan Redis terpisah, HTTPS Caddy, backup harian, health check, dan rollback image.

Konfigurasi lengkap, bootstrap data satu kali, rahasia GitHub, DNS, serta prosedur pemulihan tersedia di [DEPLOYMENT.md](DEPLOYMENT.md). Jangan menjalankan seeder roster pada database operasional; `ProductionBootstrapSeeder` sengaja menolak database yang tidak kosong.

## Pemeriksaan

```bash
npm run build
php artisan test
```
