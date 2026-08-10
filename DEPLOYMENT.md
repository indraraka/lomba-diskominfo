# Deployment production

Production berjalan pada VM GCP `wa-gateway` dan dilayani oleh Caddy yang sama dengan `wapi.web.id`. Aplikasi lomba menggunakan Compose project, MySQL, Redis, volume, dan user deployment tersendiri.

## Domain dan arsitektur

- Domain: `https://lomba.indramayukab.go.id`
- DNS A: `34.101.76.184`
- Reverse proxy: container `wa-gateway-caddy-1`
- Upstream Caddy: `lomba-app:80` pada network eksternal `wa-gateway_default`
- Image: `ghcr.io/indraraka/lomba-diskominfo:<commit-sha>`
- Runtime internal: nginx, PHP-FPM, Laravel Reverb, dan queue worker melalui Supervisor

MySQL, Redis, Reverb, dan HTTP aplikasi tidak membuka port host. Hanya Caddy yang menerima trafik publik pada port 80/443.

## GitHub Actions

Repository variable `DEPLOY_ENABLED=true` mengaktifkan deployment. GitHub Environment `production` menyimpan:

- `VM_HOST`
- `VM_USER`
- `VM_SSH_KEY`
- `VM_KNOWN_HOSTS`

Pull request dan push selalu menjalankan secret scan, Pint, PHPUnit, serta build Vite. Push ke `main` yang hijau menerbitkan image SHA ke GHCR dan menjalankan deployment otomatis.

## Bootstrap dan data

`ProductionBootstrapSeeder` hanya menerima database kosong. First deploy menjalankan bootstrap satu kali dan membuat marker `.bootstrapped`. Deploy selanjutnya hanya menjalankan migrasi.

Kredensial awal berada di VM pada:

```text
/home/lomba-deploy/lomba-diskominfo/shared/bootstrap-credentials.txt
```

File tersebut, `.env`, backup, volume database, dan PIN tidak pernah dikirim ke GitHub.

## Backup dan rollback

Backup dibuat sebelum migrasi dan setiap hari pukul 02.00 WIB. File gzip disimpan 14 hari di `shared/backups`.

Rollback manual dilakukan dari GitHub Actions melalui **Run workflow** dengan `image_tag=previous`, atau isi tag SHA lama yang masih tersedia di GHCR. Migrasi production wajib backward-compatible karena rollback image tidak melakukan rollback skema otomatis.
