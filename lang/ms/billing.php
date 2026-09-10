<?php

declare(strict_types=1);

return [
    'title' => 'Pengebilan',
    'past_due_notice' => [
        'title' => 'Pembayaran telah tertunggak',
        'description' => 'Kemas kini kaedah pembayaran anda untuk mengekalkan langganan anda aktif.',
        'cta' => 'Kemas kini pembayaran',
    ],
    'annual_banner' => [
        'title' => 'Dapatkan 2 bulan percuma',
        'description' => 'Beralih kepada pengebilan tahunan dan bayar lebih rendah setiap bulan — pelan yang sama, tiada perubahan lain.',
        'cta' => 'Naik taraf ke tahunan',
    ],
    'subscribe' => [
        'billed_monthly' => 'Dibilkan setiap bulan',
        'billed_yearly' => 'Dibilkan setiap tahun',
        'prices' => [
            'workspace' => [
                'monthly' => '$12',
                'yearly_per_month' => '$10',
                'yearly' => '$120',
            ],
        ],
    ],
    'plan' => [
        'title' => 'Pelan',
        'description' => 'Urus pelan langganan anda.',
        'label' => 'Pelan',
        'workspaces' => '{1}:count ruang kerja|[2,*]:count ruang kerja',
        'per_workspace' => 'setiap ruang kerja',
        'price' => 'Harga',
        'month' => 'bulan',
        'trial' => 'Percubaan',
        'active' => 'Aktif',
        'past_due' => 'Tertunggak',
        'cancelling' => 'Membatalkan',
        'trial_ends' => 'Percubaan tamat',
    ],
    'subscription' => [
        'title' => 'Langganan',
        'description' => 'Urus kaedah pembayaran, butiran pengebilan, dan langganan anda.',
        'payment_method' => 'Kaedah pembayaran',
        'no_payment_method' => 'Tiada kaedah pembayaran dalam rekod lagi.',
        'expires_on' => 'Tamat pada :month/:year',
        'manage_label' => 'Langganan',
        'manage_stripe' => 'Urus di Stripe',
    ],
    'invoices' => [
        'title' => 'Invois',
        'description' => 'Muat turun invois terdahulu anda.',
        'empty' => 'Tiada invois ditemui',
        'paid' => 'Dibayar',
    ],
    'flash' => [
        'plan_changed' => 'Anda kini berada pada pelan :plan.',
        'switched_to_yearly' => 'Anda kini menggunakan pengebilan tahunan.',
        'cannot_manage' => 'Hanya pemilik akaun boleh mengurus pengebilan.',
        'credits_exhausted' => 'Kredit AI kehabisan — peruntukan :limit bulanan anda telah digunakan. Naik taraf pelan anda atau tunggu bulan depan.',
        'subscription_required' => 'Langganan aktif diperlukan untuk menggunakan ciri AI.',
    ],
    'processing' => [
        'page_title' => 'Memproses...',
        'title' => 'Memproses langganan anda',
        'description' => 'Sila tunggu sementara kami menyediakan akaun anda. Ini hanya mengambil masa sebentar.',
        'success_title' => 'Semuanya siap!',
        'success_description' => 'Langganan anda aktif. Mengalihkan anda ke ruang kerja anda...',
        'cancelled_title' => 'Daftar keluar dibatalkan',
        'cancelled_description' => 'Daftar keluar anda telah dibatalkan. Tiada caj dikenakan.',
        'retry' => 'Cuba lagi',
    ],
];
