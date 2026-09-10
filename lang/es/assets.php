<?php

declare(strict_types=1);

return [
    'title' => 'Medios',

    'tabs' => [
        'my_uploads' => 'Mis subidas',
        'stock_photos' => 'Fotos gratuitas',
        'gifs' => 'GIFs',
        'references' => 'Referencias de marca',
    ],

    'upload' => [
        'drag_drop' => 'Arrastra y suelta tus archivos aquí o haz clic para seleccionar',
        'formats' => 'JPEG, PNG, GIF, WebP, MP4, PDF',
        'uploading' => 'Subiendo...',
        'failed' => 'No se pudo subir :file. Inténtalo de nuevo.',
        'file_too_large' => 'El tamaño del archivo supera el máximo permitido (:max MB).',
        'cancelled' => 'Subida cancelada.',
    ],

    'empty' => [
        'title' => 'Todavía no hay medios',
        'description' => 'Sube imágenes y videos para construir tu biblioteca de medios.',
    ],

    'save_to_assets' => 'Guardar en la biblioteca',
    'saved' => '¡Guardado en tu biblioteca!',
    'create_post' => 'Crear post',
    'add_to_post' => 'Agregar al post',
    'search_placeholder' => 'Buscar media...',

    'delete' => [
        'title' => 'Eliminar medio',
        'description' => '¿Estás seguro de que deseas eliminar este medio? Esta acción no se puede deshacer.',
        'confirm' => 'Eliminar',
        'cancel' => 'Cancelar',
    ],

    'unsplash' => [
        'search_placeholder' => 'Buscar fotos gratuitas...',
        'no_results' => 'No se encontraron fotos',
        'no_results_description' => 'Prueba con otro término de búsqueda.',
        'trending' => 'Tendencias en Unsplash',
        'start_searching' => 'Busca fotos gratuitas de Unsplash',
    ],

    'giphy' => [
        'trending' => 'Tendencias en Giphy',
        'search_placeholder' => 'Buscar GIFs...',
        'no_results' => 'No se encontraron GIFs',
        'no_results_description' => 'Prueba con otro término de búsqueda.',
        'powered_by' => 'Powered by GIPHY',
    ],
    'references' => [
        'description' => 'Fotos que guían la generación de imágenes con IA: rostros a conservar, logotipos, productos y estilos visuales. Hasta :max fotos.',
        'kind_label' => 'Tipo de foto',
        'label_label' => 'Etiqueta (opcional)',
        'label_placeholder' => 'p. ej. Sara, retrato de frente',
        'kinds' => [
            'face_closeup' => 'Primer plano del rostro',
            'full_body' => 'Cuerpo completo',
            'logo' => 'Logotipo',
            'product' => 'Producto',
            'style' => 'Estilo',
            'other' => 'Otro',
        ],
        'upload' => [
            'drag_drop' => 'Arrastra y suelta fotos de referencia aquí o haz clic para seleccionar',
            'formats' => 'JPEG, PNG, GIF, WebP',
        ],
        'empty' => [
            'title' => 'Aún no hay referencias de marca',
            'description' => 'Añade de 3 a 5 fotos nítidas: un retrato de frente con buena iluminación, tu logotipo y los productos o estilos que quieras que la IA reutilice.',
        ],
        'add_from_assets' => 'Añadir desde Archivos',
        'from_assets_title' => 'Elige fotos de tus archivos',
        'promote' => 'Guardar como referencia',
        'promoted' => 'Guardado como referencia de marca.',
        'promote_limit' => 'Solo se pueden añadir :remaining fotos más (máximo :max).',
        'updated' => 'Foto de referencia actualizada.',
        'deleted_title' => 'Eliminar foto de referencia',
        'deleted_description' => '¿Seguro que quieres eliminar esta foto de referencia? La generación con IA dejará de usarla. Esta acción no se puede deshacer.',
        'limit_reached' => 'Ya tienes :max fotos de referencia de marca. Elimina una antes de añadir otra.',
        'search_placeholder' => 'Buscar referencias...',
        'all_kinds' => 'Todos los tipos',
        'edit' => 'Editar etiqueta y tipo',
        'save' => 'Guardar',
        'manage' => 'Gestionar en Archivos',
    ],
];
