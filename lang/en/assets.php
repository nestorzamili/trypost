<?php

return [
    'title' => 'Assets',

    'tabs' => [
        'my_uploads' => 'My Uploads',
        'stock_photos' => 'Stock Photos',
        'gifs' => 'GIFs',
        'references' => 'Brand References',
    ],

    'upload' => [
        'drag_drop' => 'Drag & drop your files here, or click to select',
        'formats' => 'JPEG, PNG, GIF, WebP, MP4, PDF',
        'uploading' => 'Uploading...',
        'failed' => 'Could not upload :file. Please try again.',
        'file_too_large' => 'File size exceeds the maximum allowed (:max MB).',
        'cancelled' => 'Upload cancelled.',
    ],

    'empty' => [
        'title' => 'No assets yet',
        'description' => 'Upload images and videos to build your media library.',
    ],

    'save_to_assets' => 'Save to Assets',
    'saved' => 'Saved to your assets!',
    'create_post' => 'Create post',
    'add_to_post' => 'Add to post',
    'search_placeholder' => 'Search media...',

    'delete' => [
        'title' => 'Delete asset',
        'description' => 'Are you sure you want to delete this asset? This action cannot be undone.',
        'confirm' => 'Delete',
        'cancel' => 'Cancel',
    ],

    'unsplash' => [
        'search_placeholder' => 'Search free photos...',
        'no_results' => 'No photos found',
        'no_results_description' => 'Try a different search term.',
        'trending' => 'Trending on Unsplash',
        'start_searching' => 'Search for free stock photos from Unsplash',
    ],

    'giphy' => [
        'trending' => 'Trending on Giphy',
        'search_placeholder' => 'Search GIFs...',
        'no_results' => 'No GIFs found',
        'no_results_description' => 'Try a different search term.',
        'powered_by' => 'Powered by GIPHY',
    ],

    'references' => [
        'description' => 'Photos that guide AI image generation: faces to preserve, logos, products, and visual styles. Up to :max photos.',
        'kind_label' => 'Photo type',
        'label_label' => 'Label (optional)',
        'label_placeholder' => 'e.g. Sara, front-facing portrait',
        'kinds' => [
            'face_closeup' => 'Face close-up',
            'full_body' => 'Full body',
            'logo' => 'Logo',
            'product' => 'Product',
            'style' => 'Style',
            'other' => 'Other',
        ],
        'upload' => [
            'drag_drop' => 'Drag & drop reference photos here, or click to select',
            'formats' => 'JPEG, PNG, GIF, WebP',
        ],
        'empty' => [
            'title' => 'No brand references yet',
            'description' => 'Add 3–5 clear photos: a front-facing portrait with good lighting, your logo, and products or styles you want the AI to reuse.',
        ],
        'add_from_assets' => 'Add from Assets',
        'from_assets_title' => 'Choose photos from your assets',
        'promote' => 'Save as reference',
        'promoted' => 'Saved as brand reference.',
        'promote_limit' => 'Only :remaining more photos can be added (max :max).',
        'updated' => 'Reference photo updated.',
        'deleted_title' => 'Delete reference photo',
        'deleted_description' => 'Are you sure you want to delete this reference photo? AI generation will no longer use it. This action cannot be undone.',
        'limit_reached' => 'You already have :max brand reference photos. Delete one before adding another.',
        'search_placeholder' => 'Search references...',
        'all_kinds' => 'All types',
        'edit' => 'Edit label and type',
        'save' => 'Save',
        'manage' => 'Manage in Assets',
    ],
];
