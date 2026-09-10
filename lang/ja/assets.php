<?php

return [
    'title' => 'アセット',

    'tabs' => [
        'my_uploads' => 'アップロード済み',
        'stock_photos' => 'ストックフォト',
        'gifs' => 'GIF',
        'references' => 'ブランドリファレンス',
    ],

    'upload' => [
        'drag_drop' => 'ここにファイルをドラッグ＆ドロップするか、クリックして選択してください',
        'formats' => 'JPEG、PNG、GIF、WebP、MP4、PDF',
        'uploading' => 'アップロード中...',
        'failed' => ':file をアップロードできませんでした。もう一度お試しください。',
        'file_too_large' => 'ファイルサイズが許容される最大値(:max MB)を超えています。',
        'cancelled' => 'アップロードをキャンセルしました。',
    ],

    'empty' => [
        'title' => 'アセットがまだありません',
        'description' => '画像や動画をアップロードして、メディアライブラリを作成しましょう。',
    ],

    'save_to_assets' => 'アセットに保存',
    'saved' => 'アセットに保存しました！',
    'create_post' => '投稿を作成',
    'add_to_post' => '投稿に追加',
    'search_placeholder' => 'メディアを検索...',

    'delete' => [
        'title' => 'アセットを削除',
        'description' => 'このアセットを削除してもよろしいですか？この操作は取り消せません。',
        'confirm' => '削除',
        'cancel' => 'キャンセル',
    ],

    'unsplash' => [
        'search_placeholder' => '無料の写真を検索...',
        'no_results' => '写真が見つかりません',
        'no_results_description' => '別のキーワードで検索してみてください。',
        'trending' => 'Unsplash の人気',
        'start_searching' => 'Unsplash の無料ストックフォトを検索',
    ],

    'giphy' => [
        'trending' => 'Giphy の人気',
        'search_placeholder' => 'GIF を検索...',
        'no_results' => 'GIF が見つかりません',
        'no_results_description' => '別のキーワードで検索してみてください。',
        'powered_by' => 'Powered by GIPHY',
    ],
    'references' => [
        'description' => 'AI画像生成を導く写真: 保持する顔、ロゴ、商品、ビジュアルスタイル。最大:max枚。',
        'kind_label' => '写真の種類',
        'label_label' => 'ラベル(任意)',
        'label_placeholder' => '例: サラ、正面のポートレート',
        'kinds' => [
            'face_closeup' => '顔のクローズアップ',
            'full_body' => '全身',
            'logo' => 'ロゴ',
            'product' => '商品',
            'style' => 'スタイル',
            'other' => 'その他',
        ],
        'upload' => [
            'drag_drop' => 'リファレンス写真をここにドラッグ&ドロップ、またはクリックして選択',
            'formats' => 'JPEG、PNG、GIF、WebP',
        ],
        'empty' => [
            'title' => 'ブランドリファレンスはまだありません',
            'description' => '3〜5枚の鮮明な写真を追加してください: 照明の良い正面のポートレート、ロゴ、AIに再利用させたい商品やスタイル。',
        ],
        'add_from_assets' => 'アセットから追加',
        'from_assets_title' => 'アセットから写真を選択',
        'promote' => 'リファレンスとして保存',
        'promoted' => 'ブランドリファレンスとして保存しました。',
        'promote_limit' => '追加できる写真はあと:remaining枚のみです(最大:max枚)。',
        'updated' => 'リファレンス写真を更新しました。',
        'deleted_title' => 'リファレンス写真を削除',
        'deleted_description' => 'このリファレンス写真を削除してもよろしいですか? AI生成では今後使用されません。この操作は取り消せません。',
        'limit_reached' => 'ブランドリファレンス写真がすでに:max枚あります。追加する前に1枚削除してください。',
        'search_placeholder' => 'リファレンスを検索...',
        'all_kinds' => 'すべての種類',
        'edit' => 'ラベルと種類を編集',
        'save' => '保存',
        'manage' => 'アセットで管理',
    ],
];
