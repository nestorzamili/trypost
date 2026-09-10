<?php

return [
    'title' => '에셋',

    'tabs' => [
        'my_uploads' => '내 업로드',
        'stock_photos' => '스톡 사진',
        'gifs' => 'GIF',
        'references' => '브랜드 레퍼런스',
    ],

    'upload' => [
        'drag_drop' => '여기에 파일을 끌어다 놓거나 클릭하여 선택하세요',
        'formats' => 'JPEG, PNG, GIF, WebP, MP4, PDF',
        'uploading' => '업로드 중...',
        'failed' => ':file을(를) 업로드할 수 없습니다. 다시 시도해 주세요.',
        'file_too_large' => '파일 크기가 허용된 최대값(:max MB)을 초과했습니다.',
        'cancelled' => '업로드가 취소되었습니다.',
    ],

    'empty' => [
        'title' => '아직 에셋이 없습니다',
        'description' => '이미지와 동영상을 업로드하여 미디어 라이브러리를 만드세요.',
    ],

    'save_to_assets' => '에셋에 저장',
    'saved' => '에셋에 저장되었습니다!',
    'create_post' => '게시물 만들기',
    'add_to_post' => '게시물에 추가',
    'search_placeholder' => '미디어 검색...',

    'delete' => [
        'title' => '에셋 삭제',
        'description' => '이 에셋을 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.',
        'confirm' => '삭제',
        'cancel' => '취소',
    ],

    'unsplash' => [
        'search_placeholder' => '무료 사진 검색...',
        'no_results' => '사진을 찾을 수 없습니다',
        'no_results_description' => '다른 검색어로 시도해 보세요.',
        'trending' => 'Unsplash 인기 사진',
        'start_searching' => 'Unsplash의 무료 스톡 사진을 검색하세요',
    ],

    'giphy' => [
        'trending' => 'Giphy 인기 GIF',
        'search_placeholder' => 'GIF 검색...',
        'no_results' => 'GIF를 찾을 수 없습니다',
        'no_results_description' => '다른 검색어로 시도해 보세요.',
        'powered_by' => 'Powered by GIPHY',
    ],
    'references' => [
        'description' => 'AI 이미지 생성을 안내하는 사진: 유지할 얼굴, 로고, 제품, 시각적 스타일. 최대 :max장.',
        'kind_label' => '사진 유형',
        'label_label' => '라벨(선택 사항)',
        'label_placeholder' => '예: 사라, 정면 인물 사진',
        'kinds' => [
            'face_closeup' => '얼굴 클로즈업',
            'full_body' => '전신',
            'logo' => '로고',
            'product' => '제품',
            'style' => '스타일',
            'other' => '기타',
        ],
        'upload' => [
            'drag_drop' => '레퍼런스 사진을 여기에 끌어다 놓거나 클릭하여 선택',
            'formats' => 'JPEG, PNG, GIF, WebP',
        ],
        'empty' => [
            'title' => '아직 브랜드 레퍼런스가 없습니다',
            'description' => '선명한 사진 3~5장을 추가하세요: 조명이 좋은 정면 인물 사진, 로고, AI가 재사용하기를 원하는 제품이나 스타일.',
        ],
        'add_from_assets' => '자산에서 추가',
        'from_assets_title' => '자산에서 사진 선택',
        'promote' => '레퍼런스로 저장',
        'promoted' => '브랜드 레퍼런스로 저장되었습니다.',
        'promote_limit' => ':remaining장만 더 추가할 수 있습니다(최대 :max장).',
        'updated' => '레퍼런스 사진이 업데이트되었습니다.',
        'deleted_title' => '레퍼런스 사진 삭제',
        'deleted_description' => '이 레퍼런스 사진을 삭제하시겠습니까? AI 생성에 더 이상 사용되지 않습니다. 이 작업은 취소할 수 없습니다.',
        'limit_reached' => '이미 브랜드 레퍼런스 사진이 :max장 있습니다. 추가하기 전에 하나를 삭제하세요.',
        'search_placeholder' => '레퍼런스 검색...',
        'all_kinds' => '모든 유형',
        'edit' => '라벨 및 유형 편집',
        'save' => '저장',
        'manage' => '자산에서 관리',
    ],
];
