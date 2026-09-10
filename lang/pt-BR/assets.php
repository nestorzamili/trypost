<?php

declare(strict_types=1);

return [
    'title' => 'Mídias',

    'tabs' => [
        'my_uploads' => 'Meus uploads',
        'stock_photos' => 'Fotos gratuitas',
        'gifs' => 'GIFs',
        'references' => 'Referências da marca',
    ],

    'upload' => [
        'drag_drop' => 'Arraste e solte seus arquivos aqui ou clique para selecionar',
        'formats' => 'JPEG, PNG, GIF, WebP, MP4, PDF',
        'uploading' => 'Enviando...',
        'failed' => 'Não foi possível enviar :file. Tente novamente.',
        'file_too_large' => 'O tamanho do arquivo excede o máximo permitido (:max MB).',
        'cancelled' => 'Envio cancelado.',
    ],

    'empty' => [
        'title' => 'Nenhuma mídia ainda',
        'description' => 'Envie imagens e vídeos para criar sua biblioteca de mídia.',
    ],

    'save_to_assets' => 'Salvar na biblioteca',
    'saved' => 'Salvo na sua biblioteca!',
    'create_post' => 'Criar post',
    'add_to_post' => 'Adicionar ao post',
    'search_placeholder' => 'Buscar mídia...',

    'delete' => [
        'title' => 'Excluir mídia',
        'description' => 'Tem certeza que deseja excluir esta mídia? Esta ação não pode ser desfeita.',
        'confirm' => 'Excluir',
        'cancel' => 'Cancelar',
    ],

    'unsplash' => [
        'search_placeholder' => 'Buscar fotos gratuitas...',
        'no_results' => 'Nenhuma foto encontrada',
        'no_results_description' => 'Tente outro termo de busca.',
        'trending' => 'Em alta no Unsplash',
        'start_searching' => 'Busque fotos gratuitas do Unsplash',
    ],

    'giphy' => [
        'trending' => 'Em alta no Giphy',
        'search_placeholder' => 'Buscar GIFs...',
        'no_results' => 'Nenhum GIF encontrado',
        'no_results_description' => 'Tente outro termo de busca.',
        'powered_by' => 'Powered by GIPHY',
    ],
    'references' => [
        'description' => 'Fotos que orientam a geração de imagens por IA: rostos a preservar, logotipos, produtos e estilos visuais. Até :max fotos.',
        'kind_label' => 'Tipo de foto',
        'label_label' => 'Rótulo (opcional)',
        'label_placeholder' => 'ex.: Sara, retrato de frente',
        'kinds' => [
            'face_closeup' => 'Close-up do rosto',
            'full_body' => 'Corpo inteiro',
            'logo' => 'Logotipo',
            'product' => 'Produto',
            'style' => 'Estilo',
            'other' => 'Outro',
        ],
        'upload' => [
            'drag_drop' => 'Arraste e solte as fotos de referência aqui ou clique para selecionar',
            'formats' => 'JPEG, PNG, GIF, WebP',
        ],
        'empty' => [
            'title' => 'Ainda não há referências da marca',
            'description' => 'Adicione de 3 a 5 fotos nítidas: um retrato de frente com boa iluminação, seu logotipo e os produtos ou estilos que você quer que a IA reutilize.',
        ],
        'add_from_assets' => 'Adicionar dos Arquivos',
        'from_assets_title' => 'Escolha fotos dos seus arquivos',
        'promote' => 'Salvar como referência',
        'promoted' => 'Salva como referência da marca.',
        'promote_limit' => 'Só é possível adicionar mais :remaining fotos (máx. :max).',
        'updated' => 'Foto de referência atualizada.',
        'deleted_title' => 'Excluir foto de referência',
        'deleted_description' => 'Tem certeza de que deseja excluir esta foto de referência? A geração por IA não vai mais usá-la. Esta ação não pode ser desfeita.',
        'limit_reached' => 'Você já tem :max fotos de referência da marca. Exclua uma antes de adicionar outra.',
        'search_placeholder' => 'Pesquisar referências...',
        'all_kinds' => 'Todos os tipos',
        'edit' => 'Editar rótulo e tipo',
        'save' => 'Salvar',
        'manage' => 'Gerenciar nos Arquivos',
    ],
];
