# S-LECTURA: Smart Lecturer Availability System
**Politeknik Negeri Lampung (POLINELA)**

S-LECTURA adalah sistem informasi terpusat berbasis web yang menampilkan jadwal mengajar perkuliahan, slot konsultasi, slot bimbingan tugas akhir, dan status ketersediaan dosen secara mandiri (*self-managed*). Sistem ini dirancang untuk menyelesaikan permasalahan mahasiswa yang sering datang ke ruang dosen tanpa kepastian kehadiran.

---

## 🛡️ Prinsip Privasi Utama (Pembeda Proyek)
**TIDAK ADA pelacakan atau penampilan lokasi fisik dosen secara real-time** (tanpa GPS, tanpa RFID, dan tanpa nama ruangan fisik real-time). Sistem ini sepenuhnya berfokus pada transparansi **jadwal mengajar resmi dan status ketersediaan mandiri** yang diperbarui langsung oleh dosen. Hal ini menjamin kenyamanan dan privasi dosen dalam menjalankan tri dharma perguruan tinggi.

> **Konfigurasi Privasi Ruang Kuliah (`TAMPILKAN_RUANG`)**:
> Kolom ruang pada jadwal adalah ruang perkuliahan pada jadwal resmi kampus, **BUKAN** lokasi keberadaan fisik dosen saat ini. Diatur melalui konstanta `TAMPILKAN_RUANG` di `config/database.php` (default: `true`). Jika diubah menjadi `false`, hanya badge Daring/Luring yang ditampilkan. Ruang kuliah **tidak pernah** ditampilkan di kartu profil dosen maupun di badge status ketersediaan.

---

## 🔒 Privasi Data Demo

Data akun di SQL seed menggunakan nama generik, alamat `example.test`, dan NPM sintetis `DEMO0001`–`DEMO0034`. Data tersebut hanya untuk pengujian dan bukan identitas sivitas akademika. Jangan memasukkan atau mengunggah data pribadi maupun kredensial lingkungan produksi ke repositori.

---

## 💻 Tech Stack
- **Bahasa Pemrograman**: PHP Native (Versi 8.0+ / 8.2) dengan PDO Prepared Statements
- **Basis Data**: MySQL / MariaDB (Storage Engine: InnoDB, Charset: `utf8mb4`, Foreign Key Constraints)
- **Antarmuka (Frontend)**: HTML5, CSS3, JavaScript murni, Bootstrap 5.3 (Lokal + CDN fallback), Bootstrap Icons; login memakai carousel foto kampus dan logo POLINELA, dengan tipografi responsif yang konsisten.
- **Lingkungan**: XAMPP (Apache + MySQL) pada Windows &mdash; *Plug & Play* tanpa perlu npm build atau Node.js

---

## 🚀 Panduan Instalasi di XAMPP

### Langkah 1: Penempatan Folder Proyek
Salin folder proyek ini ke direktori `htdocs` XAMPP:
```text
C:\xampp\htdocs\pm-main\
```

### Langkah 2: Impor Basis Data Lengkap
Buka terminal / CMD / XAMPP Shell dan jalankan import berkas SQL utama:
```cmd
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\pm-main\database\s_lectura.sql
```
Salin `.env.example` menjadi `.env` bila perlu mengubah konfigurasi bawaan:
```cmd
copy C:\xampp\htdocs\pm-main\.env.example C:\xampp\htdocs\pm-main\.env
```
Skrip SQL membuat database `s_lectura`. Sesuaikan kredensial MySQL dan `BASE_URL` di `.env` bila instalasi berbeda.

*Untuk database lama yang belum memakai skema kelas dan mahasiswa, migrasikan skema secara berurutan setelah mencadangkan database:*
1. `database/migrations/002_skema_jadwal_kelas.sql` (Skema jadwal dengan satu dosen pemilik, kelas, dan mata kuliah)
2. `database/migrations/003_seed_data_kelas_ma5.sql` (Data kelas Manajemen Informatika A 5, 6 dosen, 13 jadwal, 34 mahasiswa)
3. `database/migrations/004_permintaan_pertemuan.sql` (Permintaan pertemuan, status persetujuan, dan data periode laporan)
4. `database/migrations/005_admin_jurusan_notifikasi.sql` (Peran admin jurusan, cakupan jurusan, dan notifikasi)
5. Untuk database lama yang masih memiliki tabel `jadwal_dosen`, jalankan migrasi 008 setelah 005 dan sebelum 006:
   - Migrasi mempertahankan setiap jadwal lama, menetapkan pemilik ke ID dosen terkecil pada relasi lama, dan menambahkan Termin 1/2.
   - Database baru dengan migrasi 002 versi terbaru atau `s_lectura.sql` tidak perlu menjalankan 008.
6. `database/migrations/006_dummy_jadwal_mi_semester_1_3.sql` (Contoh jadwal Manajemen Informatika semester 1 dan 3)
7. `database/migrations/007_dosen_trpl.sql` (Empat akun dosen contoh D4 Rekayasa Perangkat Lunak)
8. `database/migrations/009_master_jurusan.sql` (Migrasi satu kali untuk memisahkan master jurusan; jalankan terakhir setelah backup)
9. `database/migrations/010_akun_mahasiswa_nonaktif.sql` (Status akun aktif/nonaktif tanpa menghapus riwayat)
10. `database/migrations/011_foto_profil_mahasiswa.sql` (Foto profil mahasiswa)

Jika database lokal sudah memakai fitur janji temu (migrasi 004 sudah diterapkan), jalankan migrasi 005:
```cmd
C:\xampp\mysql\bin\mysql.exe -u root s_lectura < C:\xampp\htdocs\pm-main\database\migrations\005_admin_jurusan_notifikasi.sql
```
Untuk database lama yang belum mempunyai fitur janji temu, jalankan migrasi 004 lalu 005 secara berurutan. Jangan impor ulang `database/s_lectura.sql` pada database yang sudah berisi data karena skrip utama membuat ulang tabel. Hash sandi pada data seed sudah bisa langsung diverifikasi dan tidak perlu skrip pembaruan tambahan.
Pada database lama yang masih memiliki tabel pivot `jadwal_dosen`, jalankan migrasi 008 satu kali sebelum migrasi 006. Database yang memakai skema baru dari migrasi 002 terbaru atau `s_lectura.sql` tidak perlu menjalankan 008.
```cmd
C:\xampp\mysql\bin\mysql.exe -u root s_lectura < C:\xampp\htdocs\pm-main\database\migrations\008_jadwal_satu_dosen_dan_termin.sql
```
Untuk menambahkan contoh jadwal semester 1 dan 3 ke database yang sudah ada, jalankan migrasi 006:
```cmd
C:\xampp\mysql\bin\mysql.exe -u root s_lectura < C:\xampp\htdocs\pm-main\database\migrations\006_dummy_jadwal_mi_semester_1_3.sql
```
Untuk menambahkan empat akun dosen contoh ke prodi D4 Rekayasa Perangkat Lunak pada database yang sudah ada, jalankan migrasi 007:
```cmd
C:\xampp\mysql\bin\mysql.exe -u root s_lectura < C:\xampp\htdocs\pm-main\database\migrations\007_dosen_trpl.sql
```
Setelah semua migrasi skema dan data lama (termasuk 005, 006, 007, dan bila diperlukan 008) selesai, cadangkan database lalu jalankan migrasi 009 satu kali. Migrasi ini memisahkan master jurusan, mengaitkan prodi dan admin jurusan langsung ke jurusan, serta menghapus kolom teks lama. Nilai jurusan lama yang kosong dipindahkan ke jurusan bernama `Belum ditentukan`; periksa dan ubah penetapan tersebut melalui Master Jurusan/Program Studi jika ditemukan. Jangan menjalankan migrasi 009 pada database yang sudah memakai skema master jurusan atau menjalankannya ulang.
```cmd
if not exist C:\xampp\backup mkdir C:\xampp\backup
C:\xampp\mysql\bin\mysqldump.exe -u root s_lectura > C:\xampp\backup\s_lectura-before-009.sql
C:\xampp\mysql\bin\mysql.exe -u root s_lectura < C:\xampp\htdocs\pm-main\database\migrations\009_master_jurusan.sql
```
Setelah migrasi berhasil, aplikasi menyediakan menu **Master Jurusan** untuk tambah/edit/hapus jurusan. Jurusan yang masih dipakai prodi atau admin jurusan tidak dapat dihapus. Instalasi baru cukup mengimpor `database/s_lectura.sql`, yang sudah menggunakan skema master jurusan.
Setelah migrasi 009, jalankan migrasi 010 satu kali untuk mengaktifkan fitur penonaktifan akun pada database lama:
```cmd
C:\xampp\mysql\bin\mysql.exe -u root s_lectura < C:\xampp\htdocs\pm-main\database\migrations\010_akun_mahasiswa_nonaktif.sql
```
Akun nonaktif tidak dapat login, tetapi profil dan riwayat pertemuannya tetap tersimpan; admin jurusan dapat mengaktifkannya kembali. Status ini berlaku untuk akun mahasiswa dan dosen.
Jalankan migrasi 011 satu kali untuk menambahkan foto profil mahasiswa pada database lama:
```cmd
C:\xampp\mysql\bin\mysql.exe -u root s_lectura < C:\xampp\htdocs\pm-main\database\migrations\011_foto_profil_mahasiswa.sql
```
Mahasiswa dapat melihat nama, email, NPM, dan program studi serta mengubah foto profil melalui menu **Profil Saya**. Identitas akun hanya dapat diubah admin jurusan. Foto menerima format JPG, PNG, atau WEBP dengan ukuran maksimal 2MB.
Login memberi jeda 15 menit setelah 5 kegagalan untuk kombinasi identitas akun dan alamat sumber, serta setelah 30 kegagalan dari satu alamat sumber dalam 15 menit. Data pembatasan disimpan dalam berkas sementara di direktori temporary server, bukan di database, jadi tidak perlu migrasi. Pembatasan memakai `REMOTE_ADDR` langsung dan tidak mempercayai header proxy yang dikirim pengguna. Di belakang reverse proxy, semua pengguna dapat terlihat berasal dari satu alamat.
Setiap jadwal kini dimiliki satu dosen dan dibedakan menurut Termin 1/2. Dosen mengelola jadwal miliknya sendiri dan dapat mengunggah Excel. Admin jurusan dapat mengimpor satu file `.xlsx` berisi beberapa dosen, tetapi hanya dosen dan prodi dari jurusannya yang diterima. Unduh template dari menu **Import Jadwal Dosen**; impor hanya menyimpan data jika semua baris valid. Pastikan ekstensi PHP `zip` dan `SimpleXML` aktif.

### Langkah 3: Buka Aplikasi di Browser
Akses URL aplikasi:
👉 **`http://localhost/pm-main/`** *(atau `http://localhost:8000/` jika menggunakan `php -S localhost:8000`)*.

---

## Kredensial Akun Pengujian (Demo) & Aturan Sandi

Seed baru menyediakan akun demo berikut. Semua akun demo wajib mengganti sandi pada login pertama. Kredensial ini hanya untuk pengujian lokal; jangan gunakan di lingkungan produksi.

| Peran | Login | Sandi awal |
|---|---|---|
| Administrator | `admin@example.test` | `123456` |
| Dosen | `dosen1@example.test`-`dosen6@example.test` | `123456` |
| Mahasiswa | `DEMO0001`-`DEMO0034` atau `mahasiswa01@example.test`-`mahasiswa34@example.test` | `123456` |

### Administrator, admin jurusan, dan notifikasi
Administrator utama hanya mengelola akun **Admin Jurusan** (termasuk cakupan jurusan dan status akun), master jurusan/program studi, serta melihat laporan agregat seluruh kampus. Administrator utama tidak mengelola akun mahasiswa/dosen atau jadwal dosen. Admin jurusan memilih program studi pada halaman **Pilih Prodi & Pengguna** dan dapat menambahkan akun dengan dua cara: mengisi formulir **Tambah Manual** untuk satu akun, atau mengimpor banyak akun sekaligus dari Excel. Untuk impor, pilih jenis data **Mahasiswa** atau **Dosen**, lalu unduh template Excel khusus untuk jenis tersebut (maksimal 500 baris). Template mahasiswa berisi `nama`, `email`, `npm`; template dosen berisi `nama`, `email`, `nidn_nip`. Kedua cara divalidasi sebelum perubahan disimpan. Password awal untuk akun baru maupun reset semua peran adalah `123456`, wajib diganti saat login pertama/berikutnya. Formatkan NPM/NIDN sebagai teks di Excel untuk menjaga angka nol di depan. Admin jurusan dapat mengedit nama/email mahasiswa dan data akun dosen (nama, email, NIDN/NIP), serta mereset password kedua peran ke `123456`. Bidang keahlian diisi dan diperbarui sendiri oleh dosen melalui profilnya. Akun mahasiswa maupun dosen dapat dinonaktifkan atau diaktifkan kembali. Penonaktifan mencegah login tanpa menghapus profil; riwayat pertemuan tetap tersimpan, dan jadwal dosen juga tetap tersimpan. Laporan admin utama bersifat agregat lintas jurusan; admin jurusan hanya dapat melihat laporan sesuai cakupan jurusannya. Jadwal dan permintaan pertemuan dosen tetap dikelola oleh dosen.

Pendaftaran mandiri mahasiswa tidak tersedia. Akun mahasiswa dan dosen dibuat oleh admin jurusan, baik dengan formulir manual untuk satu akun maupun impor data massal per program studi. S-LECTURA mendukung konsultasi akademik dan bimbingan umum; layanan Tugas Akhir dikelola di sistem terpisah.

Notifikasi janji temu muncul di dalam aplikasi untuk mahasiswa dan dosen terkait. Pesan notifikasi tidak menyertakan alasan konsultasi, sehingga kategori konsultasi pribadi tidak membocorkan isi atau tujuannya.

Jadwal kelas mahasiswa tidak ditampilkan di S-LECTURA karena tersedia di eKuliah. Dataset demo menyertakan contoh jadwal untuk Manajemen Informatika semester 1 dan 3, selain data kelas semester 5. Data tersebut adalah contoh pengujian dan bukan jadwal akademik resmi.

Direktori dosen mahasiswa otomatis mengikuti jurusan pada profilnya. Dropdown **Program Studi** hanya menampilkan prodi dalam jurusan tersebut, dan filter prodi/status berlaku saat tombol **Filter** ditekan.

### Pengajuan pertemuan tanpa slot
Mahasiswa dapat memilih slot konsultasi/bimbingan yang disediakan dosen atau mengusulkan tanggal dan rentang jam sendiri. Slot tidak wajib, sehingga dosen yang belum membuka slot tetap dapat menerima permintaan. Semua permintaan menunggu keputusan dosen; dosen juga dapat menawarkan waktu lain tanpa harus membuat slot terlebih dahulu dan mahasiswa mengonfirmasi usulan tersebut. Bentrok dengan jadwal praktik atau pertemuan lain yang sudah disetujui diblokir. Bentrok jadwal teori ditampilkan sebagai peringatan, bukan penolakan otomatis, karena jadwal teori dapat jarang dijalankan.

## 📁 Struktur Direktori Proyek

```text
s-lectura/
├── config/
│   └── database.php              # PDO Singleton, BASE_URL, konstanta TAMPILKAN_RUANG
├── includes/
│   ├── auth.php                  # RBAC, login/logout, paksa ganti sandi (require_password_change)
│   ├── helpers.php               # Anti-XSS (e()), CSRF, format tanggal/WIB, check_time_collision() normalized
│   ├── meetings.php              # Validasi waktu, bentrok praktik/pertemuan, dan peringatan teori
│   ├── schedule_import.php       # Validasi cakupan dan simpan import jadwal secara transaksional
│   ├── schedule_xlsx.php         # Baca file XLSX dan buat template import
│   ├── notifications.php         # Notifikasi dalam aplikasi per pengguna
│   ├── header.php                # Navigasi Bootstrap 5 responsif, menu multi-peran
│   └── footer.php                # Footer privasi kampus, bootstrap.bundle.js
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css     # Bootstrap 5.3 lokal (offline ready)
│   │   ├── bootstrap-icons.min.css
│   │   └── style.css             # Tema hijau Polinela (#107C41)
│   └── js/
│       ├── bootstrap.bundle.min.js
│       └── app.js                # Auto-dismiss alert, konfirmasi hapus, validasi waktu
├── database/
│   ├── migrations/
│   │   ├── 002_skema_jadwal_kelas.sql     # Skema kelas, mata kuliah, dan pemilik jadwal tunggal
│   │   ├── 003_seed_data_kelas_ma5.sql    # Data kelas Manajemen Informatika A 5 & 13 jadwal
│   │   ├── 004_permintaan_pertemuan.sql   # Skema pengajuan janji temu dan statusnya
│   │   ├── 005_admin_jurusan_notifikasi.sql # Admin jurusan dan notifikasi
│   │   ├── 006_dummy_jadwal_mi_semester_1_3.sql # Contoh jadwal MI semester 1 dan 3
│   │   ├── 007_dosen_trpl.sql              # Empat akun dosen contoh D4 Rekayasa Perangkat Lunak
│   │   ├── 008_jadwal_satu_dosen_dan_termin.sql # Migrasi database jadwal lama
│   │   ├── 009_master_jurusan.sql           # Master jurusan dan relasi prodi/admin
│   │   ├── 010_akun_mahasiswa_nonaktif.sql  # Status aktif/nonaktif akun
│   │   └── 011_foto_profil_mahasiswa.sql    # Foto profil mahasiswa
│   ├── s_lectura.sql                      # Skema DDL lengkap + seed langsung siap pakai
│   └── gen_hashes.php                      # Membuat hash untuk sandi demo
├── docs/
│   └── pengujian.md              # Laporan pengujian fungsional & skenario uji baru
├── ganti-password.php            # Halaman ganti kata sandi wajib (login pertama) & sukarela
├── mahasiswa/
│   ├── index.php                 # Direktori dosen, filter status & prodi
│   ├── detail.php                # Detail dosen, jadwal/slot, dan pengajuan janji temu
│   ├── jadwal-kelas.php          # Rute lama; arahkan mahasiswa ke eKuliah
│   └── pertemuan.php             # Status janji temu dan konfirmasi usulan waktu
├── dosen/
│   ├── index.php                 # Dashboard dosen: status aktif, quick update, agenda hari ini
│   ├── jadwal.php                # Kelola jadwal milik dosen (Termin 1/2, anti-bentrok)
│   ├── jadwal-import.php         # Import Excel jadwal dosen sendiri
│   ├── slot.php                  # Kelola slot konsultasi & bimbingan TA
│   ├── pertemuan.php             # Kelola janji dan rekap pertemuan dosen
│   └── profil.php                # Edit profil, keahlian, upload foto, ganti sandi
├── admin/
│   ├── index.php                 # Dashboard metrik statistik kampus
│   ├── users.php                 # Manajemen akun dan penetapan admin jurusan
│   ├── mahasiswa.php             # Pemilihan prodi dan impor akun mahasiswa/dosen
│   ├── jadwal-import.php         # Import Excel multi-dosen terbatas per jurusan admin
│   ├── dosen.php                 # Master data dosen & penugasan prodi
│   ├── jurusan.php               # Kelola master jurusan
│   ├── prodi.php                 # Master program studi
│   └── pertemuan.php             # Laporan agregat per dosen/semester tanpa identitas mahasiswa
├── login.php                     # Masuk multi-peran (email / NPM)
├── notifikasi.php                # Notifikasi privat per akun
├── register.php                  # Pengalihan ke login (pendaftaran mandiri dinonaktifkan)
├── logout.php                    # Logout & destroy session
├── .env.example                  # Contoh konfigurasi lokal (jangan commit .env)
├── .gitignore                    # Abaikan konfigurasi dan upload privat
└── README.md                     # Dokumentasi panduan instalasi & penggunaan
```

---

## 🌟 Logika Validasi Bentrok Jadwal (*Collision Detection*)

Dua rentang waktu dianggap **bentrok** jika dan hanya jika:
$$\text{mulai\_baru} < \text{selesai\_lama} \quad \text{DAN} \quad \text{selesai\_baru} > \text{mulai\_lama}$$

Artinya:
- Sesi yang tepat bersinggungan di batas menit (misalnya selesai pukul `15:00` dan sesi baru mulai pukul `15:00`) **BUKAN merupakan bentrok**.
- Validasi memeriksa pemilik `id_dosen`, termin yang sama, dan slot konsultasi aktif. Setiap jadwal hanya memiliki satu dosen.

---

*Dikembangkan untuk Politeknik Negeri Lampung (POLINELA).*
