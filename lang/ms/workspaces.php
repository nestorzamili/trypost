<?php

declare(strict_types=1);

return [
    'title' => 'Ruang Kerja',
    'select_title' => 'Ruang kerja anda',
    'select_description' => 'Pilih ruang kerja untuk diteruskan',
    'current' => 'Semasa',
    'connections' => ':count sambungan',
    'posts' => ':count siaran',
    'create' => [
        'page_title' => 'Cipta ruang kerja anda',
        'title' => 'Sediakan ruang kerja anda',
        'description' => 'Beritahu kami sedikit tentang anda atau projek anda. Kami akan menggunakannya untuk menyesuaikan siaran janaan AI mengikut gaya suara anda.',
        'website' => 'Laman web',
        'website_placeholder' => 'https://jenamaanda.com',
        'autofill' => 'Isi automatik daripada laman web',
        'autofill_missing_url' => 'Masukkan URL terlebih dahulu.',
        'autofill_success' => 'Maklumat jenama dimuatkan.',
        'autofill_error' => 'Tidak dapat mengisi secara automatik. Anda boleh mengisi medan secara manual.',
        'autofill_errors' => [
            'unreachable' => 'Kami tidak dapat menghubungi laman web tersebut (:reason).',
            'http_status' => 'Laman web mengembalikan status yang tidak dijangka (:status).',
            'invalid_scheme' => 'Hanya URL http dan https yang disokong.',
            'missing_host' => 'URL tidak mempunyai hos.',
            'unresolvable_host' => 'Kami tidak dapat menyelesaikan hos (:host).',
            'private_network' => 'URL yang menghala ke rangkaian peribadi tidak dibenarkan.',
        ],
        'logo_captured' => 'Logo diambil daripada laman web anda.',
        'name' => 'Nama ruang kerja',
        'name_placeholder' => 'cth. Acme Inc',
        'brand_description' => 'Penerangan jenama',
        'brand_description_placeholder' => 'Apakah yang jenama anda lakukan?',
        'content_language' => 'Bahasa kandungan',
        'content_language_description' => 'Kapsyen janaan AI akan ditulis dalam bahasa ini.',
        'brand_color' => 'Warna jenama',
        'background_color' => 'Warna latar belakang',
        'text_color' => 'Warna teks',
        'submit' => 'Cipta ruang kerja',
        'success' => 'Ruang kerja dicipta. Sambungkan akaun sosial untuk mula menyiarkan.',
    ],
    'cannot_delete_last' => 'Anda tidak boleh memadamkan satu-satunya ruang kerja anda. Batalkan langganan anda dalam tetapan pengebilan untuk menutup akaun.',
    'flash' => [
        'deleted' => 'Ruang kerja berjaya dipadamkan.',
    ],
];
