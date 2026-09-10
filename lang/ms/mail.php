<?php

declare(strict_types=1);

return [

    'layout' => [
        'tagline' => 'Alat penjadualan media sosial sumber terbuka',
        'manage_notifications' => 'Urus pemberitahuan',
        'signoff' => 'Salam mesra,',
        'team' => 'Pasukan TryPost',
    ],

    'account_disconnected' => [
        'subject' => 'Akaun :platform anda dalam :workspace perlu disambungkan semula',
        'title' => 'Akaun :platform anda perlu disambungkan semula',
        'preview' => 'Sila sambungkan semula akaun :platform anda dalam :workspace untuk terus menjadualkan hantaran.',
        'heading' => 'Akaun Terputus Sambungan',
        'intro' => 'Akaun <strong>:platform</strong> anda <strong>:account</strong> telah diputuskan sambungan daripada ruang kerja <strong>:workspace</strong>.',
        'reasons_title' => 'Ini mungkin berlaku kerana:',
        'reason_expired' => 'Token akses anda telah tamat tempoh',
        'reason_revoked' => 'Anda telah membatalkan akses kepada TryPost',
        'reason_error' => 'Ralat pengesahan telah berlaku',
        'reconnect_cta' => 'Sila sambungkan semula akaun anda untuk terus menjadualkan dan menerbitkan hantaran.',
        'button' => 'Sambung Semula Akaun',
    ],

    'email_verification' => [
        'subject' => 'Sahkan alamat e-mel anda',
        'preview' => 'Sila sahkan alamat e-mel anda.',
        'greeting' => 'Hai :name,',
        'body' => 'Sila sahkan alamat e-mel anda dengan mengklik butang di bawah:',
        'button' => 'Sahkan Alamat E-mel',
        'ignore' => 'Jika anda tidak membuat akaun, anda boleh mengabaikan e-mel ini dengan selamat.',
    ],

    'mentioned_in_comment' => [
        'subject' => ':name telah menyebut anda di TryPost',
        'title' => ':name telah menyebut anda',
        'intro' => ':name telah menyebut anda dalam ulasan hantaran.',
        'button' => 'Lihat ulasan',
    ],

    'password_reset' => [
        'subject' => 'Tetapkan semula kata laluan anda',
        'preview' => 'Tetapkan semula kata laluan anda.',
        'greeting' => 'Hai :name,',
        'body' => 'Kami menerima permintaan untuk menetapkan semula kata laluan anda. Klik butang di bawah untuk membuat kata laluan baharu:',
        'button' => 'Tetapkan Semula Kata Laluan',
        'expiry' => 'Pautan ini akan tamat tempoh dalam masa 60 minit. Jika anda tidak meminta penetapan semula kata laluan, anda boleh mengabaikan e-mel ini dengan selamat.',
    ],

    'post_at_risk' => [
        'subject' => '{1} :count hantaran berisiko dalam :workspace|[0,*] :count hantaran berisiko dalam :workspace',
        'title' => 'Hantaran Mungkin Gagal Diterbitkan',
        'heading' => 'Hantaran Mungkin Gagal Diterbitkan',
        'intro' => 'Akaun sosial berikut dalam ruang kerja :workspace anda perlu disambungkan semula sebelum hantaran berjadual ini boleh diterbitkan:',
        'posts_label' => '{1} :count hantaran dijadualkan: :times UTC|[0,*] :count hantaran dijadualkan: :times UTC',
        'reconnect_cta' => 'Sila sambungkan semula akaun ini sekarang untuk mengelakkan hantaran berjadual anda terlepas.',
        'button' => 'Sambung Semula Akaun',
    ],

    'post_publish_failed' => [
        'subject' => 'Hantaran anda gagal diterbitkan dalam :workspace',
        'title' => 'Hantaran anda gagal diterbitkan',
        'preview' => 'Satu atau lebih platform gagal menerbitkan hantaran anda.',
        'heading' => 'Hantaran anda gagal diterbitkan',
        'body' => 'Hantaran berjadual anda dalam ruang kerja :workspace gagal diterbitkan pada satu atau lebih platform.',
        'platforms_title' => 'Platform yang gagal:',
        'button' => 'Lihat Hantaran',
    ],

    'post_published' => [
        'subject' => 'Hantaran anda telah diterbitkan dalam :workspace',
        'title' => 'Hantaran anda telah diterbitkan',
        'preview' => 'Hantaran anda telah berjaya diterbitkan.',
        'heading' => 'Hantaran anda telah diterbitkan',
        'body' => 'Hantaran anda dalam ruang kerja :workspace telah berjaya diterbitkan.',
        'platforms_title' => 'Diterbitkan di:',
        'view_post' => 'Lihat hantaran',
        'button' => 'Lihat Hantaran',
    ],

    'webhook_paused' => [
        'subject' => 'Webhook dijeda: :endpoint',
        'title' => 'Webhook dijeda selepas kegagalan berulang',
        'preview' => 'Kami telah menjeda webhook selepas 5 kegagalan penghantaran berturut-turut.',
        'heading' => 'Webhook dijeda selepas kegagalan berulang',
        'body' => 'Kami telah menjeda webhook di :endpoint selepas 5 kegagalan penghantaran berturut-turut. Semak titik akhir dan dayakan semula dari halaman butiran webhook.',
        'button' => 'Lihat webhook',
    ],

    'workspace_connections_disconnected' => [
        'subject' => '{1} :count akaun perlu disambungkan semula dalam :workspace|[0,*] :count akaun perlu disambungkan semula dalam :workspace',
        'title' => 'Akaun memerlukan sambungan semula',
        'heading' => 'Akaun memerlukan sambungan semula',
        'intro' => 'Akaun sosial berikut dalam ruang kerja <strong>:workspace</strong> anda telah diputuskan sambungan dan perlu disambungkan semula:',
        'reasons_title' => 'Ini mungkin berlaku kerana:',
        'reason_expired' => 'Token akses telah tamat tempoh',
        'reason_revoked' => 'Anda telah membatalkan akses kepada TryPost pada platform tersebut',
        'reason_changed' => 'Platform tersebut telah menukar keperluan pengesahan mereka',
        'reconnect_cta' => 'Sila sambungkan semula akaun ini untuk terus menjadualkan dan menerbitkan hantaran.',
        'button' => 'Sambung semula akaun',
    ],

    'workspace_invite' => [
        'subject' => 'Anda telah dijemput untuk menyertai :account',
        'title' => 'Anda telah dijemput untuk menyertai :account',
        'preview' => 'Anda telah dijemput untuk menyertai :account',
        'heading' => 'Anda telah dijemput!',
        'intro' => 'Anda telah dijemput untuk bekerjasama dalam ruang kerja <strong>:account</strong>.',
        'role' => 'Anda telah dijemput sebagai <strong>:role</strong>.',
        'button' => 'Terima Jemputan',
        'expiry' => 'Jemputan ini akan tamat tempoh dalam masa 7 hari.',
    ],

];
