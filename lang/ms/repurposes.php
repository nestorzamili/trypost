<?php

declare(strict_types=1);

return [
    'title' => 'Guna Semula',
    'description' => 'Ulang siar apa yang anda siarkan di luar TryPost ke rangkaian anda yang lain secara automatik.',
    'new' => 'Guna semula baharu',

    'flow' => [
        'no_source' => 'Tiada akaun sumber',
        'no_destinations' => 'Belum ada destinasi',
    ],

    'publish_mode' => [
        'title' => 'Penerbitan',
        'description' => 'Apa yang berlaku apabila hantaran baharu muncul.',
    ],

    'publish_modes' => [
        'publish' => 'Terbitkan secara automatik',
        'publish_hint' => 'Setiap hantaran baharu dijadualkan sebaik sahaja ia ditemui.',
        'draft' => 'Cipta sebagai draf',
        'draft_hint' => 'Setiap hantaran baharu menjadi draf di sini untuk anda semak dan terbitkan.',
    ],

    'formats' => [
        'reel' => 'Reels',
        'video' => 'Video',
        'story' => 'Cerita',
    ],

    'source' => [
        'title' => 'Sumber',
        'description' => 'TryPost memerhatikan akaun ini untuk hantaran baharu dalam format di bawah.',
        'account_label' => 'Akaun',
        'watch_label' => 'Perhatikan untuk',
        'needs_reconnect' => 'Perlu disambungkan semula',
    ],

    'summary' => [
        'sentence' => 'Setiap :format baharu yang anda siarkan di :source akan diterbitkan semula ke :destinations.',
        'no_destinations' => 'Setiap :format baharu yang anda siarkan di :source sedang menunggu destinasi.',
        'no_source' => 'Guna semula ini tiada akaun sumber. Pilih satu untuk memulakannya semula.',
    ],

    'empty' => [
        'title' => 'Belum ada guna semula yang disediakan',
        'description' => 'TryPost memerhatikan akaun yang anda pilih dan menerbitkan semula setiap hantaran baharu ke rangkaian yang anda pilih.',
    ],

    'table' => [
        'flow' => 'Aliran',
        'status' => 'Status',
        'published' => 'Diterbitkan semula',
        'last_polled' => 'Terakhir diperiksa',
    ],

    'status' => [
        'draft' => 'Draf',
        'active' => 'Aktif',
        'paused' => 'Dijeda',
        'disabled' => 'Dinyahdayakan',
    ],

    'create' => [
        'title' => 'Guna semula baharu',
        'description' => 'Pilih akaun yang perlu diperhatikan oleh TryPost. Anda pilih destinasi di skrin seterusnya.',
        'source_label' => 'Akaun sumber',
        'source_placeholder' => 'Pilih akaun',
        'source_search' => 'Cari akaun',
        'source_empty' => 'Tiada akaun ditemui.',
        'no_accounts' => 'Sambungkan akaun Instagram atau Facebook terlebih dahulu. Hanya akaun ini boleh menjadi sumber kerana rangkaian ini membenarkan kami memuat turun video.',
        'submit' => 'Cipta',
        'connect' => 'Sambung akaun',
    ],

    'show' => [
        'title' => 'Guna Semula',
        'saving' => 'Menyimpan...',
        'saved' => 'Disimpan',
    ],

    'tabs' => [
        'configuration' => 'Konfigurasi',
        'activity' => 'Aktiviti',
        'settings' => 'Tetapan',
    ],

    'destinations' => [
        'paused_note' => 'Dimatikan dan dilangkau sehingga anda menghidupkannya semula: :accounts',
        'title' => 'Destinasi',
        'description' => 'Pilih akaun yang menerimanya. Setiap satu diterbitkan dalam format yang anda pilih.',
        'hint' => 'Kapsyen disesuaikan mengikut rangkaian hanya apabila ia melebihi had rangkaian tersebut.',
        'none_available' => 'Tiada akaun lain yang disambungkan dalam ruang kerja ini lagi.',
        'publish_as' => 'Terbitkan sebagai',
    ],

    'status_card' => [
        'title' => 'Status',
        'activate' => 'Aktifkan',
        'pause' => 'Jeda',
        'resume' => 'Sambung semula',
        'disable' => 'Nyahdayakan',
        'watermark' => 'Diperhatikan sejak',
        'last_polled' => 'Terakhir diperiksa',
        'draft_hint' => 'Pilih sekurang-kurangnya satu destinasi, kemudian aktifkan. Hanya hantaran yang diterbitkan selepas anda mengaktifkan akan diduplikasi.',
        'active_hint' => 'TryPost menyemak akaun ini secara kerap dan menduplikasi setiap hantaran baharu.',
        'paused_hint' => 'Pemeriksaan ditangguhkan. Menyambung semula akan meneruskan dari tempat ia berhenti, jadi tiada apa yang disiarkan sementara itu terlepas.',
        'disabled_hint' => 'Dimatikan. Mengaktifkan semula bermula segar: apa-apa yang anda siarkan semasa ia dimatikan akan kekal dimatikan.',
    ],

    'items' => [
        'source' => 'Asal',
        'published_at' => 'Disiarkan',
        'status' => 'Status',
        'detail' => 'Butiran',
        'posts' => 'Diduplikasi ke',
        'view_original' => 'Lihat asal',
        'original_from' => 'asal dari :date',
        'empty' => [
            'title' => 'Belum ada apa-apa',
            'description' => 'Hantaran yang diterbitkan akaun ini di luar TryPost akan dipaparkan di sini.',
        ],
        'open_post' => 'Buka hantaran',
        'statuses' => [
            'pending' => 'Dalam baris gilir',
            'processing' => 'Memproses',
            'published' => 'Diterbitkan semula',
            'drafted' => 'Didrafkan',
            'skipped' => 'Dilangkau',
            'failed' => 'Gagal',
        ],
        'reasons' => [
            'published_via_trypost' => 'Telah diterbitkan melalui TryPost',
            'media_url_missing' => 'Rangkaian tidak berkongsi fail yang boleh dimuat turun, biasanya kerana audio berhak cipta',
            'download_failed' => 'Video tidak dapat dimuat turun',
            'post_creation_failed' => 'Tidak dapat mencipta hantaran',
            'no_usable_destinations' => 'Tiada destinasi yang tersedia untuk diterbitkan',
        ],
    ],

    'menu' => [
        'label' => 'Tindakan lanjut',
    ],

    'danger' => [
        'title' => 'Padam guna semula ini',
        'description' => 'Pemeriksaan dihentikan serta-merta. Hantaran yang telah dicipta kekal dalam kalendar anda.',
        'delete' => 'Padam guna semula',
    ],

    'health' => [
        'stopped_itself' => 'Berhenti sendiri — buka untuk melihat sebabnya',
        'source_missing' => 'Penduaan ditangguhkan: guna semula ini tiada akaun sumber. Pilih satu, kemudian sambung semula.',
        'source_unusable' => 'Penduaan ditangguhkan: akaun yang diperhatikan oleh guna semula ini perlu disambungkan semula.',
        'no_destinations' => 'Penduaan ditangguhkan: tiada destinasi tersedia. Tambah satu, kemudian sambung semula.',
        'ready' => 'Masalah telah diselesaikan. Sambung semula guna semula ini untuk mula menduplikasi lagi.',
    ],

    'errors' => [
        'source_already_used' => 'Akaun ini telah digunakan oleh guna semula lain. Edit yang itu sebaliknya.',
        'source_missing' => 'Pilih akaun untuk diperhatikan sebelum memulakan guna semula ini.',
        'source_unusable' => 'Sambung semula akaun yang diperhatikan oleh guna semula ini sebelum memulakannya.',
        'destinations_required' => 'Pilih sekurang-kurangnya satu destinasi sebelum mengaktifkan.',
        'destination_needs_video' => 'Format tersebut tidak boleh memuatkan video.',
        'only_paused_resumes' => 'Hanya guna semula yang dijeda boleh disambung semula.',
        'only_active_pauses' => 'Hanya guna semula yang aktif boleh dijeda.',
        'only_running_disables' => 'Hanya guna semula yang sedang berjalan boleh dimatikan.',
        'only_idle_activates' => 'Hanya guna semula draf atau yang dimatikan boleh diaktifkan.',
        'destination_unavailable' => 'Akaun destinasi tersebut tidak lagi tersedia.',
        'destination_is_source' => 'Destinasi tersebut adalah akaun yang diperhatikan oleh guna semula ini.',
        'source_unavailable' => 'Akaun sumber tersebut tidak lagi tersedia.',
        'action_failed' => 'Sesuatu tidak kena. Semak borang dan cuba lagi.',
    ],
];
