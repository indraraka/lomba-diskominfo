<?php

namespace App\Support;

class OfficialCompetitionProfiles
{
    public const VERSION = 'Peraturan Tiap Mata Lomba Agustusan 2026 - 2026-08-10';

    public static function all(): array
    {
        $profiles = [
            self::profile('Lomba Cerdas Cermat', 'quiz_two_phase', 'team', 'Beregu', '±20 menit', 'Parkiran samping bawah', 'Bu Nok', 3, 3, [
                'workflow' => ['mandatory_questions' => 10, 'rebuttal_questions' => 10, 'answer_seconds' => 15, 'correct_points' => 10, 'mandatory_wrong_points' => 0, 'rebuttal_wrong_points' => -5, 'max_throw_attempts' => 1, 'tiebreak_questions' => 3],
                'equipment' => ['Paket 20 soal', 'Kartu jawaban atau papan tulis kecil', 'Bel atau alat penanda', 'Papan skor dan alat tulis'],
                'setup' => ['Setiap tim terdiri atas tepat 3 peserta.', 'Kartu jawaban dan alat penanda diperiksa sebelum babak dimulai.'],
                'play' => ['Babak wajib berisi 10 soal yang dijawab seluruh tim secara serentak melalui kartu jawaban.', 'Babak rebutan berisi 10 soal; tim yang menekan bel paling dahulu memperoleh hak menjawab.', 'Waktu menjawab maksimal 15 detik setelah soal selesai dibacakan.', 'Pada babak rebutan, soal yang gagal dijawab dapat dilempar satu kali kepada tim lain.'],
                'scoring' => ['Babak wajib: jawaban benar +10 poin dan salah 0 poin.', 'Babak rebutan: jawaban benar +10 poin dan salah -5 poin.', 'Skor total tertinggi menjadi pemenang.'],
                'contingencies' => ['Jika skor posisi juara sama, diberikan maksimal 3 soal cepat-tepat.', 'Tim pertama yang menjawab benar pada soal penentu memperoleh peringkat lebih tinggi.'],
                'violations' => ['Dilarang menggunakan telepon genggam, mesin pencari, catatan, atau bantuan penonton.', 'Bantuan dari luar pertama mengurangi 20 poin.', 'Pelanggaran bantuan luar kedua berakibat diskualifikasi.'],
                'safety' => [],
            ], type: 'quiz'),
            self::profile('Lomba Tumpeng Antar Tim', 'judged_rubric', 'team', 'Beregu', '17 Agustus 2026 setelah upacara', 'Area aula', 'Dewan Juri', 1, 4, [
                'workflow' => ['timer_kind' => 'countdown', 'duration_seconds' => 2700, 'rubric' => ['creativity' => 30, 'presentation' => 25, 'taste' => 25, 'hygiene' => 20], 'tie_order' => ['creativity', 'hygiene', 'taste']],
                'equipment' => ['Satu tumpeng bertema kemerdekaan per tim', 'Meja dan alas meja', 'Titik cuci tangan'],
                'setup' => ['Setiap tim mengirim satu tumpeng bertema kemerdekaan.', 'Maksimal 4 orang mewakili tim saat penataan akhir.', 'Bahan boleh dipersiapkan lebih dahulu dan wajib layak konsumsi.'],
                'play' => ['Penataan akhir dilakukan di lokasi selama 45 menit setelah upacara.', 'Tumpeng wajib disusun oleh tim dan bukan pesanan jadi.', 'Tim menjaga meja, alas, serta titik kerja tetap rapi dan higienis.'],
                'scoring' => ['Kreativitas dan kesesuaian tema kemerdekaan: 30 poin.', 'Kerapian dan keindahan penyajian: 25 poin.', 'Cita rasa: 25 poin.', 'Kebersihan, higienitas, dan keamanan pangan: 20 poin.'],
                'contingencies' => ['Jika nilai sama, peringkat ditentukan berturut-turut dari nilai kreativitas, kebersihan, lalu cita rasa.'],
                'violations' => ['Dilarang memakai hiasan yang tidak aman untuk pangan.', 'Styrofoam tidak boleh bersentuhan langsung dengan makanan.', 'Api terbuka, lilin, dan kompor dilarang di area lomba.'],
                'safety' => ['Semua bahan harus layak konsumsi.', 'Hiasan yang digunakan harus aman untuk pangan.', 'Area penataan wajib dijaga bersih dan higienis.'],
            ]),
            self::profile('Mancing Emosi Makan Kerupuk', 'metric_race', 'team', 'Beregu', '±20 menit', 'Parkiran samping', 'Wahyu', 2, 2, [
                'workflow' => ['timer_kind' => 'countdown', 'duration_seconds' => 180, 'primary_metric' => 'time_ms', 'timeout_metric' => 'remaining_total', 'ranking' => 'asc', 'first_violation_penalty_ms' => 10000, 'second_violation' => 'disqualification', 'remaining_unit_required' => true, 'remaining_unit' => 'potongan kerupuk', 'remaining_unit_options' => ['potongan kerupuk', 'gram', 'persen']],
                'equipment' => ['Dua pancing bambu ringan', 'Dua kerupuk sama ukuran', 'Dua penutup mata', 'Dua kursi'],
                'setup' => ['Dua peserta duduk saling berhadapan pada jarak yang ditetapkan panitia.', 'Keduanya memakai penutup mata dan memegang pancing yang telah dipasangi kerupuk.', 'Tangan boleh berada di depan, tetapi hanya untuk memegang gagang pancing.'],
                'play' => ['Kedua peserta bermain bersamaan, bukan bergantian.', 'Setiap peserta mengarahkan kerupuk pada pancingnya ke mulut pasangan sekaligus memakan kerupuk dari pancing pasangan.', 'Kerupuk dan tali tidak boleh disentuh secara langsung.', 'Batas waktu permainan adalah 3 menit.'],
                'scoring' => ['Pasangan yang paling cepat menghabiskan kedua kerupuk menjadi pemenang.', 'Kerupuk dinyatakan habis jika tidak ada bagian yang masih dapat dimakan pada tali atau pengait.', 'Jika waktu habis, total sisa kedua kerupuk paling sedikit menjadi pemenang.'],
                'violations' => ['Dilarang membuka, menggeser, atau mengintip dari penutup mata.', 'Dilarang menyentuh kerupuk, tali, atau mulut pasangan; menyuapi diri sendiri; berdiri; atau menerima arahan penonton.', 'Pelanggaran pertama mendapat penalti 10 detik; pelanggaran kedua mendiskualifikasi pasangan.'],
                'safety' => ['Pancing harus ringan dan tidak memiliki ujung tajam.', 'Kerupuk diarahkan perlahan dan dimakan sedikit demi sedikit.', 'Dilarang menyentak pancing, mendorong kerupuk ke mulut, atau memaksa pasangan makan.', 'Wasit menghentikan peserta yang batuk terus-menerus atau tampak tersedak.'],
            ]),
            self::profile('Lomba Joget Kardus', 'metric_race', 'individual', 'Individu', '±20 menit', 'Parkiran samping', 'Dilla', 1, 1, [
                'workflow' => ['timer_kind' => 'countdown', 'duration_seconds' => 90, 'primary_metric' => 'time_ms', 'timeout_metric' => 'balls_remaining', 'timeout_metric_min' => 0, 'timeout_metric_max' => 10, 'ranking' => 'asc', 'tie_replay_seconds' => 30, 'max_per_affiliation' => 1],
                'equipment' => ['Kardus pinggang berlubang', '10 bola plastik sama ukuran', 'Pengikat kardus', 'Musik opsional'],
                'setup' => ['Kardus dipasang kuat pada pinggang di bagian belakang atau punggung bawah.', 'Seluruh 10 bola masih berada di dalam kardus saat posisi awal.', 'Peserta berdiri di area masing-masing dan baru bergerak setelah aba-aba wasit.'],
                'play' => ['Peserta berjoget serta menggoyangkan pinggang dan tubuh agar bola keluar melalui lubang kardus.', 'Tangan boleh digerakkan untuk menari atau menjaga keseimbangan.', 'Peserta wajib tetap berada di area lomba selama batas waktu 90 detik.'],
                'scoring' => ['Peserta yang paling dahulu mengeluarkan seluruh bola melalui lubang kardus menjadi pemenang.', 'Jika waktu habis, jumlah bola tersisa paling sedikit menjadi pemenang.'],
                'contingencies' => ['Jika hasil sama, peserta terkait mengikuti babak ulang selama 30 detik.', 'Jika kardus terlepas atau rusak, peserta berhenti dan mengangkat tangan; panitia memasang kembali atau menggantinya.', 'Bola yang tumpah akibat kardus terlepas atau rusak tidak dihitung.'],
                'violations' => ['Dilarang menyentuh, memegang, menepuk, atau menggoyangkan kardus dengan tangan.', 'Dilarang mengambil bola dengan tangan, memperbesar lubang, atau meminta bantuan orang lain.', 'Bola hanya sah jika keluar melalui lubang akibat gerakan tubuh.'],
                'safety' => ['Dilarang berlari, melompat tinggi, saling mendekati, atau melakukan gerakan berisiko jatuh.', 'Bola yang keluar harus segera disingkirkan agar tidak terinjak.', 'Jarak antarpeserta cukup dan lantai tidak licin.'],
            ]),
            self::profile('Masukkan Paku dengan Bantuan Spion', 'metric_race', 'individual', 'Individu', '±20 menit', 'Parkiran samping', 'Oki', 1, 1, [
                'workflow' => ['timer_kind' => 'countdown', 'duration_seconds' => 120, 'primary_metric' => 'time_ms', 'ranking' => 'asc', 'repeat_if_no_finisher' => true, 'max_per_affiliation' => 1],
                'equipment' => ['Botol plastik', 'Paku besar tumpul bertali', 'Cermin kecil atau spion', 'Kursi/alas seragam'],
                'setup' => ['Peserta duduk membelakangi botol yang tegak pada titik panitia.', 'Satu tangan memegang cermin atau spion di depan tubuh; tangan lain memegang ujung tali.', 'Ukuran paku, tali, mulut botol, jarak, serta jenis tempat duduk dibuat sama.'],
                'play' => ['Peserta mengatur cermin atau spion untuk melihat pantulan botol.', 'Dengan tetap menghadap depan, peserta menggerakkan tali untuk menurunkan paku ke mulut botol.', 'Cermin atau spion boleh diatur selama perlombaan.', 'Batas waktu setiap babak adalah 2 menit.'],
                'scoring' => ['Peserta yang paling dahulu memasukkan badan paku ke dalam botol menjadi pemenang setelah disahkan wasit.'],
                'contingencies' => ['Jika belum ada yang berhasil saat waktu habis, peserta terkait mengikuti babak ulang.', 'Jika botol bergeser atau jatuh bukan karena peserta, permainan dihentikan sementara untuk diperbaiki.', 'Jika botol bergeser karena disentuh peserta, percobaan dibatalkan dan botol dikembalikan ke posisi awal.'],
                'violations' => ['Dilarang menoleh atau memutar tubuh untuk melihat botol langsung.', 'Dilarang menyentuh paku, botol, lantai sekitar botol, memindahkan botol, atau menerima petunjuk arah.', 'Paku hanya boleh diarahkan melalui tali sambil melihat pantulan.'],
                'safety' => ['Paku wajib cukup besar, tumpul, bersih, dan tidak berkarat.', 'Jarak antarpeserta cukup lebar serta kursi atau alas duduk stabil.', 'Semua peserta dalam satu babak mendapat pengaturan yang setara.'],
            ]),
            self::profile('Lomba Suap-suapan', 'metric_race', 'team', 'Beregu', '±20 menit', 'Parkiran samping', 'Suci', 2, 2, [
                'workflow' => ['timer_kind' => 'countdown', 'duration_seconds' => 180, 'primary_metric' => 'time_ms', 'timeout_metric' => 'remaining_total', 'ranking' => 'asc', 'tie_replay' => true, 'remaining_unit_required' => true, 'remaining_unit' => 'potongan pisang', 'remaining_unit_options' => ['potongan pisang', 'gram', 'persen']],
                'equipment' => ['Papan manila berlubang', 'Penutup mata', 'Pisang dengan porsi sama', 'Piring, sarung tangan/alat makan, tisu, air minum'],
                'setup' => ['Peran pemberi dan penerima ditentukan sebelum babak dan tidak boleh ditukar.', 'Penerima berada di depan papan dengan kepala melalui lubang tengah.', 'Pemberi berada di belakang papan, memakai penutup mata, dan memasukkan tangan melalui dua lubang.', 'Papan dibuat stabil dan makanan berada dalam jangkauan pemberi.'],
                'play' => ['Pemberi mengambil pisang, membaginya menjadi suapan kecil, lalu menyuapi penerima tanpa membuka penutup mata.', 'Penerima boleh memberi arahan suara dan menggerakkan kepala secara wajar.', 'Penerima tidak boleh memegang makanan atau tangan pemberi.', 'Batas waktu setiap babak adalah 3 menit.'],
                'scoring' => ['Tim yang paling dahulu menghabiskan porsi dan menelan suapan terakhir dengan aman menjadi pemenang.', 'Jika waktu habis, sisa makanan paling sedikit menjadi pemenang.'],
                'contingencies' => ['Jika hasil sama, dilakukan babak ulang menggunakan porsi yang lebih kecil.', 'Makanan yang jatuh ke lantai tidak boleh dimakan dan dihitung sebagai sisa.'],
                'violations' => ['Pemberi dilarang membuka atau menggeser penutup mata, mengintip, atau memindahkan papan.', 'Penerima dilarang mengambil makanan atau mengarahkan tangan pemberi dengan tangan.'],
                'safety' => ['Pisang dibagi menjadi suapan kecil dan tidak boleh dimasukkan secara paksa.', 'Penerima harus selesai menelan sebelum suapan berikut dan boleh memberi isyarat berhenti kapan saja.', 'Panitia menghentikan peserta yang batuk terus-menerus atau tampak tersedak.', 'Tangan wajib bersih dan makanan yang jatuh segera dibuang.'],
            ]),
            self::profile('Lomba Susun Kata', 'multi_round_score', 'team', 'Beregu', '3 sesi × 3 menit', 'Parkiran samping', 'Danang', 6, 6, [
                'workflow' => ['rounds' => 3, 'timer_kind' => 'countdown', 'duration_seconds' => 180, 'metric' => 'correct_answers', 'ranking' => 'desc', 'tiebreak' => 'sudden_death'],
                'equipment' => ['Enam kartu huruf besar', 'Daftar petunjuk dan jawaban', 'Garis susunan, peluit, lembar hasil'],
                'setup' => ['Enam peserta berdiri di belakang garis awal dan masing-masing memegang satu kartu huruf.', 'Huruf harus terlihat jelas oleh juri dan tidak boleh ditukar setelah sesi dimulai.', 'Satu kombinasi huruf harus dapat membentuk seluruh jawaban dalam sesi.'],
                'play' => ['Panitia membacakan petunjuk, lalu peserta berdiskusi dan berpindah posisi membentuk jawaban.', 'Hanya huruf yang digunakan berdiri berjajar di garis jawaban; huruf lain berada di area samping.', 'Susunan dibaca dari kiri ke kanan menurut pandangan juri.', 'Tim meneriakkan “selesai” dan seluruh peserta berhenti; jawaban salah boleh diperbaiki jika waktu masih ada.'],
                'scoring' => ['Tim dengan total jawaban benar paling banyak menjadi pemenang.', 'Lomba berlangsung tepat 3 sesi, masing-masing 3 menit.'],
                'contingencies' => ['Jika jumlah jawaban benar sama, diberikan satu petunjuk tambahan.', 'Tim yang paling dahulu membentuk jawaban tambahan dengan benar menjadi pemenang.'],
                'violations' => ['Dilarang melepas, membalik, menutup, atau menukar kartu huruf.', 'Dilarang memakai huruf yang sama dua kali, gawai, catatan, atau jawaban dari penonton.', 'Susunan tidak boleh diubah setelah meneriakkan “selesai” sebelum izin juri.'],
                'safety' => ['Peserta hanya boleh berjalan cepat; dilarang berlari, mendorong, menarik pakaian, atau menghalangi tim lain.', 'Area wajib kering, tidak licin, dan memiliki jarak antartim yang cukup.'],
            ]),
            self::profile('Lomba Pindah Warna', 'elimination', 'individual', 'Individu', '3 sesi', 'Parkiran samping', 'Bu Nok', 1, 1, [
                'workflow' => ['commands' => ['step_on_color', 'avoid_color'], 'max_per_affiliation' => 2, 'all_fail' => 'repeat_round'],
                'equipment' => ['Tiga indikator warna sama ukuran', 'Penutup mata', 'Garis awal', 'Petugas pendamping'],
                'setup' => ['Peserta memakai penutup mata, berdiri dengan kaki rapat, dan menghadap lurus.', 'Tiga indikator sama ukuran diletakkan di sisi peserta sekitar satu lompatan pendek.', 'Arah lompatan sama untuk satu putaran; posisi indikator tidak boleh diubah setelah aba-aba.'],
                'play' => ['Wasit memberi perintah “injak warna” atau “jangan injak warna”.', 'Peserta melakukan tepat satu lompatan pendek ke samping lalu berhenti saat mendarat.', 'Perintah “injak warna” berhasil jika sedikitnya satu kaki menyentuh indikator.', 'Perintah “jangan injak warna” berhasil jika kedua kaki tidak menyentuh seluruh indikator.'],
                'scoring' => ['Peserta berhasil melanjutkan ke putaran berikutnya; peserta gagal tereliminasi.', 'Perintah dan posisi indikator diubah sampai tersisa satu pemenang.'],
                'contingencies' => ['Jika seluruh peserta tersisa gagal dalam putaran yang sama, putaran diulang dengan susunan indikator baru.'],
                'violations' => ['Dilarang membuka penutup mata, meraba lantai, melompat lebih dari sekali, atau melangkah lagi setelah mendarat.', 'Dilarang melompat ke depan/belakang, menerima petunjuk arah, atau sengaja memindahkan indikator.'],
                'safety' => ['Lompatan dilakukan pendek, rendah, dan ke samping.', 'Kertas warna harus direkatkan; piring wajib ringan, tidak mudah pecah, dan tidak licin.', 'Pendamping berjaga tanpa mengarahkan lompatan.', 'Area rata, kering, serta bebas kabel dan benda keras.'],
            ]),
            self::profile('Lomba Estafet Air', 'metric_race', 'team', 'Beregu', '±20 menit', 'Parkiran samping', 'Wahyu', 5, 5, [
                'workflow' => ['timer_kind' => 'stopwatch', 'primary_metric' => 'time_ms', 'tie_metric' => 'volume_ml', 'ranking' => 'asc', 'tie_replay' => true],
                'equipment' => ['Satu ember dengan jumlah air awal yang sama pada setiap lintasan.', 'Satu spons dengan ukuran dan daya serap seragam.', 'Satu botol penampung dengan ukuran dan tanda batas penuh yang sama.', 'Tanda posisi untuk 5 peserta serta lap atau pel pada setiap lintasan.'],
                'setup' => ['Lima peserta berbaris menghadap ke depan dengan jarak yang ditentukan panitia.', 'Peserta pertama berada di depan dekat ember; peserta kelima di belakang dekat botol.', 'Setiap peserta wajib tetap berada pada tanda posisinya selama permainan.'],
                'play' => ['Peserta pertama mencelupkan spons hingga basah lalu melemparkannya ke belakang kepada peserta kedua.', 'Peserta kedua, ketiga, dan keempat menangkap lalu melempar spons ke peserta berikutnya.', 'Peserta kelima memeras spons di atas mulut botol hingga air masuk.', 'Spons dikembalikan kepada peserta pertama dan rangkaian diulang sampai botol mencapai batas penuh.'],
                'scoring' => ['Regu yang botolnya lebih dahulu mencapai tanda batas penuh menjadi pemenang.', 'Permainan dihentikan setelah panitia memeriksa air benar-benar mencapai tanda.'],
                'contingencies' => ['Spons jatuh: peserta yang gagal menangkap boleh mengambilnya, kembali ke tanda posisi, lalu melanjutkan.', 'Waktu tetap berjalan dan air yang tumpah tidak diganti.', 'Jika waktu penuh sulit dibedakan, juri memeriksa volume air dan dapat mengadakan pertandingan ulang singkat.'],
                'violations' => ['Dilarang berpindah dari tanda untuk memperpendek jarak atau menyerahkan spons langsung tanpa dilempar.', 'Dilarang melewati urutan, memindahkan atau menuang ember, dan memeras spons sebelum peserta kelima.', 'Dilarang menambah air dengan tangan atau wadah lain serta mengganggu regu lain.', 'Air yang tidak masuk ke dalam botol tidak dihitung.'],
                'safety' => ['Lemparan harus pendek, pelan, terarah, dan tidak diarahkan ke wajah.', 'Jarak peserta disesuaikan agar spons dapat ditangkap dengan aman.', 'Botol diletakkan pada permukaan kokoh agar tidak mudah jatuh.', 'Genangan segera dibersihkan; area wajib rata dan tidak licin.'],
            ]),
            self::profile('Lomba Voli Air', 'knockout_score', 'team', 'Beregu', 'Sistem gugur', 'Parkiran voli', 'Dilla', 6, 6, [
                'workflow' => ['max_entries' => 6, 'points_to_win' => 7, 'win_by' => 1, 'draw' => 'random', 'third_place_default' => false, 'third_place_fallback' => 'best_semifinal_score'],
                'equipment' => ['Tiga sarung per tim', 'Balon air seragam', 'Net/pembatas', 'Peluit, papan skor, alat pembersih'],
                'setup' => ['Setiap tim berisi 6 peserta yang dibagi menjadi 3 pasangan.', 'Setiap pasangan memegang satu sarung pada sisi atau sudut yang ditetapkan.', 'Maksimal 6 regu masuk undian sistem gugur.'],
                'play' => ['Servis dan seluruh pengembalian balon dilakukan menggunakan sarung.', 'Pasangan yang menangkap balon wajib langsung melemparkannya melewati net.', 'Balon tidak boleh dioper atau dipindahkan kepada pasangan lain dalam satu tim.'],
                'scoring' => ['Tim mendapat 1 poin jika balon pecah di pihak lawan atau gagal ditangkap lawan.', 'Lawan mendapat 1 poin jika balon pecah di pihak pelempar, gagal melewati net, atau terjadi pelanggaran.', 'Tim pertama mencapai tepat 7 poin menjadi pemenang; tidak ada ketentuan selisih 2 poin.'],
                'contingencies' => ['Jika jumlah regu ganjil, bye ditentukan melalui undian.', 'Perebutan Juara 3 dilaksanakan jika waktu memungkinkan.', 'Jika perebutan Juara 3 tidak dimainkan, peringkat 3 ditentukan dari skor semifinal terbaik.'],
                'violations' => ['Dilarang menyentuh balon dengan tangan, menahan balon di sarung, atau melempar tanpa sarung.', 'Dilarang mengoper kepada pasangan satu tim, melewati garis tengah, mengganggu lawan, atau menarik sarung lawan.'],
                'safety' => ['Lemparan tidak boleh diarahkan keras ke wajah atau tubuh lawan.', 'Jarak antarpasangan harus cukup longgar agar tidak bertabrakan.', 'Area basah segera dikeringkan dan peserta wajib memakai alas kaki tidak licin.'],
            ], generalFormat: 'knockout'),
        ];

        $videos = [
            'Lomba Cerdas Cermat' => 'https://www.youtube.com/watch?v=ipsZyGKzqqI',
            'Lomba Tumpeng Antar Tim' => 'https://www.youtube.com/watch?v=LSag0UOUUpM',
            'Mancing Emosi Makan Kerupuk' => 'https://www.tiktok.com/@mitayamita/video/7539379557967613204',
            'Lomba Joget Kardus' => 'https://vt.tiktok.com/ZS4HNHkXt/',
            'Masukkan Paku dengan Bantuan Spion' => 'https://vt.tiktok.com/ZS4HFQYEs/',
            'Lomba Suap-suapan' => 'https://vt.tiktok.com/ZS4HYNDwS/',
            'Lomba Susun Kata' => 'https://vt.tiktok.com/ZS4HYcLDd/',
            'Lomba Pindah Warna' => 'https://vt.tiktok.com/ZS4H23NR6/',
            'Lomba Estafet Air' => 'https://www.tiktok.com/@spancagress/video/7650094394233687314',
            'Lomba Voli Air' => 'https://www.tiktok.com/@pemudapendowo/video/7532045504771886342',
        ];

        return array_map(
            fn (array $profile) => [...$profile, 'video_url' => $videos[$profile['name']]],
            $profiles,
        );
    }

    private static function profile(string $name, string $workflow, string $kind, string $category, string $schedule, string $venue, string $pic, int $membersMin, int $membersMax, array $rules, string $type = 'general', string $generalFormat = 'standard'): array
    {
        $rules['entry'] = ['kind' => $kind, 'members_min' => $membersMin, 'members_max' => $membersMax];
        $rules['category'] = $category;
        $rules['schedule'] = $schedule;
        $rules['venue'] = $venue;
        $rules['pic'] = $pic;
        $rules['source'] = self::VERSION;

        return compact('name', 'workflow', 'kind', 'schedule', 'venue', 'pic', 'rules', 'type', 'generalFormat');
    }
}
