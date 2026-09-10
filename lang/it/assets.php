<?php

return [
    'title' => 'Risorse',

    'tabs' => [
        'my_uploads' => 'I miei caricamenti',
        'stock_photos' => 'Foto stock',
        'gifs' => 'GIF',
        'references' => 'Riferimenti brand',
    ],

    'upload' => [
        'drag_drop' => 'Trascina qui i tuoi file oppure clicca per selezionarli',
        'formats' => 'JPEG, PNG, GIF, WebP, MP4, PDF',
        'uploading' => 'Caricamento in corso...',
        'failed' => 'Impossibile caricare :file. Riprova.',
        'file_too_large' => 'La dimensione del file supera il massimo consentito (:max MB).',
        'cancelled' => 'Caricamento annullato.',
    ],

    'empty' => [
        'title' => 'Ancora nessuna risorsa',
        'description' => 'Carica immagini e video per creare la tua libreria multimediale.',
    ],

    'save_to_assets' => 'Salva nelle risorse',
    'saved' => 'Salvato nelle tue risorse!',
    'create_post' => 'Crea post',
    'add_to_post' => 'Aggiungi al post',
    'search_placeholder' => 'Cerca media...',

    'delete' => [
        'title' => 'Elimina risorsa',
        'description' => 'Vuoi davvero eliminare questa risorsa? Questa azione non può essere annullata.',
        'confirm' => 'Elimina',
        'cancel' => 'Annulla',
    ],

    'unsplash' => [
        'search_placeholder' => 'Cerca foto gratuite...',
        'no_results' => 'Nessuna foto trovata',
        'no_results_description' => 'Prova un altro termine di ricerca.',
        'trending' => 'Di tendenza su Unsplash',
        'start_searching' => 'Cerca foto stock gratuite da Unsplash',
    ],

    'giphy' => [
        'trending' => 'Di tendenza su Giphy',
        'search_placeholder' => 'Cerca GIF...',
        'no_results' => 'Nessuna GIF trovata',
        'no_results_description' => 'Prova un altro termine di ricerca.',
        'powered_by' => 'Powered by GIPHY',
    ],
    'references' => [
        'description' => 'Foto che guidano la generazione di immagini AI: volti da preservare, loghi, prodotti e stili visivi. Fino a :max foto.',
        'kind_label' => 'Tipo di foto',
        'label_label' => 'Etichetta (facoltativa)',
        'label_placeholder' => 'es. Sara, ritratto frontale',
        'kinds' => [
            'face_closeup' => 'Primo piano del volto',
            'full_body' => 'Figura intera',
            'logo' => 'Logo',
            'product' => 'Prodotto',
            'style' => 'Stile',
            'other' => 'Altro',
        ],
        'upload' => [
            'drag_drop' => 'Trascina qui le foto di riferimento oppure fai clic per selezionare',
            'formats' => 'JPEG, PNG, GIF, WebP',
        ],
        'empty' => [
            'title' => 'Nessun riferimento brand',
            'description' => 'Aggiungi 3–5 foto nitide: un ritratto frontale ben illuminato, il tuo logo e i prodotti o stili che vuoi che l\'AI riutilizzi.',
        ],
        'add_from_assets' => 'Aggiungi da Risorse',
        'from_assets_title' => 'Scegli le foto dalle tue risorse',
        'promote' => 'Salva come riferimento',
        'promoted' => 'Salvato come riferimento brand.',
        'promote_limit' => 'È possibile aggiungere solo altre :remaining foto (massimo :max).',
        'updated' => 'Foto di riferimento aggiornata.',
        'deleted_title' => 'Elimina foto di riferimento',
        'deleted_description' => 'Eliminare definitivamente questa foto di riferimento? La generazione AI non la utilizzerà più. Questa azione non può essere annullata.',
        'limit_reached' => 'Hai già :max foto di riferimento brand. Eliminane una prima di aggiungerne un\'altra.',
        'search_placeholder' => 'Cerca riferimenti...',
        'all_kinds' => 'Tutti i tipi',
        'edit' => 'Modifica etichetta e tipo',
        'save' => 'Salva',
        'manage' => 'Gestisci in Risorse',
    ],
];
