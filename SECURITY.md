# Security Policy

Laporkan kerentanan secara privat kepada pengelola repository melalui GitHub Security Advisory. Jangan membuka issue publik yang memuat kredensial, data sesi, atau langkah eksploitasi aktif.

Repository ini sengaja memuat roster peserta acara 2026 berdasarkan keputusan pemilik data. Repository tidak boleh memuat `.env`, database runtime, password admin, PIN sesi production, private key, backup, log, atau token layanan.

Deployment production menggunakan image berdasarkan commit SHA, SSH key khusus GitHub Actions, host key yang dipasang tetap, dan rahasia yang hanya disimpan pada VM serta GitHub Environment `production`.
