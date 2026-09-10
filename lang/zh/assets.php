<?php

return [
    'title' => '素材库',

    'tabs' => [
        'my_uploads' => '我的上传',
        'stock_photos' => '图库照片',
        'gifs' => 'GIF',
        'references' => '品牌参考',
    ],

    'upload' => [
        'drag_drop' => '将文件拖放到此处，或点击选择',
        'formats' => 'JPEG、PNG、GIF、WebP、MP4、PDF',
        'uploading' => '上传中…',
        'failed' => '无法上传 :file，请重试。',
        'file_too_large' => '文件大小超过允许的最大值(:max MB)。',
        'cancelled' => '上传已取消。',
    ],

    'empty' => [
        'title' => '暂无素材',
        'description' => '上传图片和视频，构建你的媒体库。',
    ],

    'save_to_assets' => '保存到素材库',
    'saved' => '已保存到你的素材库！',
    'create_post' => '创建帖子',
    'add_to_post' => '添加到帖子',
    'search_placeholder' => '搜索媒体…',

    'delete' => [
        'title' => '删除素材',
        'description' => '确定要删除此素材吗？此操作无法撤销。',
        'confirm' => '删除',
        'cancel' => '取消',
    ],

    'unsplash' => [
        'search_placeholder' => '搜索免费照片…',
        'no_results' => '未找到照片',
        'no_results_description' => '换一个搜索词试试。',
        'trending' => 'Unsplash 热门',
        'start_searching' => '在 Unsplash 上搜索免费图库照片',
    ],

    'giphy' => [
        'trending' => 'Giphy 热门',
        'search_placeholder' => '搜索 GIF…',
        'no_results' => '未找到 GIF',
        'no_results_description' => '换一个搜索词试试。',
        'powered_by' => '由 GIPHY 提供支持',
    ],
    'references' => [
        'description' => '用于引导 AI 生成图片的照片: 需要保留的面部、徽标、产品和视觉风格。最多 :max 张。',
        'kind_label' => '照片类型',
        'label_label' => '标签(可选)',
        'label_placeholder' => '例如: Sara,正面人像',
        'kinds' => [
            'face_closeup' => '面部特写',
            'full_body' => '全身',
            'logo' => '徽标',
            'product' => '产品',
            'style' => '风格',
            'other' => '其他',
        ],
        'upload' => [
            'drag_drop' => '将参考照片拖放到此处,或点击选择',
            'formats' => 'JPEG、PNG、GIF、WebP',
        ],
        'empty' => [
            'title' => '还没有品牌参考',
            'description' => '添加 3–5 张清晰照片: 光线良好的正面人像、您的徽标,以及希望 AI 复用的产品或风格。',
        ],
        'add_from_assets' => '从素材添加',
        'from_assets_title' => '从您的素材中选择照片',
        'promote' => '保存为参考',
        'promoted' => '已保存为品牌参考。',
        'promote_limit' => '只能再添加 :remaining 张照片(最多 :max 张)。',
        'updated' => '参考照片已更新。',
        'deleted_title' => '删除参考照片',
        'deleted_description' => '确定要删除这张参考照片吗? AI 生成将不再使用它。此操作无法撤消。',
        'limit_reached' => '您已有 :max 张品牌参考照片。请先删除一张再添加。',
        'search_placeholder' => '搜索参考...',
        'all_kinds' => '所有类型',
        'edit' => '编辑标签和类型',
        'save' => '保存',
        'manage' => '在素材中管理',
    ],
];
