<?php

declare(strict_types=1);

return [
    'title' => 'Varlıklar',

    'tabs' => [
        'my_uploads' => 'Yüklemelerim',
        'stock_photos' => 'Stok Fotoğraflar',
        'gifs' => 'GIF\'ler',
        'references' => 'Marka referansları',
    ],

    'upload' => [
        'drag_drop' => 'Dosyalarınızı buraya sürükleyip bırakın veya seçmek için tıklayın',
        'formats' => 'JPEG, PNG, GIF, WebP, MP4, PDF',
        'uploading' => 'Yükleniyor...',
        'failed' => ':file yüklenemedi. Lütfen tekrar deneyin.',
        'file_too_large' => 'Dosya boyutu izin verilen maksimumu aşıyor (:max MB).',
        'cancelled' => 'Yükleme iptal edildi.',
    ],

    'empty' => [
        'title' => 'Henüz varlık yok',
        'description' => 'Medya kitaplığınızı oluşturmak için görsel ve video yükleyin.',
    ],

    'save_to_assets' => 'Varlıklara Kaydet',
    'saved' => 'Varlıklarınıza kaydedildi!',
    'create_post' => 'Gönderi oluştur',
    'add_to_post' => 'Gönderiye ekle',
    'search_placeholder' => 'Medya ara...',

    'delete' => [
        'title' => 'Varlığı sil',
        'description' => 'Bu varlığı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.',
        'confirm' => 'Sil',
        'cancel' => 'İptal',
    ],

    'unsplash' => [
        'search_placeholder' => 'Ücretsiz fotoğraf ara...',
        'no_results' => 'Fotoğraf bulunamadı',
        'no_results_description' => 'Farklı bir arama terimi deneyin.',
        'trending' => 'Unsplash\'te Popüler',
        'start_searching' => 'Unsplash\'ten ücretsiz stok fotoğraflar arayın',
    ],

    'giphy' => [
        'trending' => 'Giphy\'de Popüler',
        'search_placeholder' => 'GIF ara...',
        'no_results' => 'GIF bulunamadı',
        'no_results_description' => 'Farklı bir arama terimi deneyin.',
        'powered_by' => 'GIPHY tarafından desteklenmektedir',
    ],
    'references' => [
        'description' => 'AI görsel oluşturmayı yönlendiren fotoğraflar: korunacak yüzler, logolar, ürünler ve görsel stiller. En fazla :max fotoğraf.',
        'kind_label' => 'Fotoğraf türü',
        'label_label' => 'Etiket (isteğe bağlı)',
        'label_placeholder' => 'örn. Sara, önden portre',
        'kinds' => [
            'face_closeup' => 'Yüz yakın planı',
            'full_body' => 'Tüm vücut',
            'logo' => 'Logo',
            'product' => 'Ürün',
            'style' => 'Stil',
            'other' => 'Diğer',
        ],
        'upload' => [
            'drag_drop' => 'Referans fotoğraflarını buraya sürükleyip bırakın veya seçmek için tıklayın',
            'formats' => 'JPEG, PNG, GIF, WebP',
        ],
        'empty' => [
            'title' => 'Henüz marka referansı yok',
            'description' => '3–5 net fotoğraf ekleyin: iyi ışıklandırılmış önden bir portre, logonuz ve AI\'nin yeniden kullanmasını istediğiniz ürün veya stiller.',
        ],
        'add_from_assets' => 'Varlıklardan ekle',
        'from_assets_title' => 'Varlıklarınızdan fotoğraf seçin',
        'promote' => 'Referans olarak kaydet',
        'promoted' => 'Marka referansı olarak kaydedildi.',
        'promote_limit' => 'Yalnızca :remaining fotoğraf daha eklenebilir (maks. :max).',
        'updated' => 'Referans fotoğrafı güncellendi.',
        'deleted_title' => 'Referans fotoğrafını sil',
        'deleted_description' => 'Bu referans fotoğrafını silmek istediğinizden emin misiniz? AI oluşturma artık bunu kullanmayacak. Bu işlem geri alınamaz.',
        'limit_reached' => 'Zaten :max marka referansı fotoğrafınız var. Yeni bir tane eklemeden önce birini silin.',
        'search_placeholder' => 'Referanslarda ara...',
        'all_kinds' => 'Tüm türler',
        'edit' => 'Etiket ve türü düzenle',
        'save' => 'Kaydet',
        'manage' => 'Varlıklarda yönet',
    ],
];
