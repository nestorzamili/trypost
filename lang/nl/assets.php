<?php

return [
    'title' => 'Assets',

    'tabs' => [
        'my_uploads' => 'Mijn uploads',
        'stock_photos' => 'Stockfoto\'s',
        'gifs' => 'GIFs',
        'references' => 'Merkreferenties',
    ],

    'upload' => [
        'drag_drop' => 'Sleep je bestanden hierheen, of klik om te selecteren',
        'formats' => 'JPEG, PNG, GIF, WebP, MP4, PDF',
        'uploading' => 'Uploaden...',
        'failed' => ':file kon niet worden geüpload. Probeer het opnieuw.',
        'file_too_large' => 'Bestandsgrootte overschrijdt het toegestane maximum (:max MB).',
        'cancelled' => 'Upload geannuleerd.',
    ],

    'empty' => [
        'title' => 'Nog geen assets',
        'description' => 'Upload afbeeldingen en video\'s om je mediabibliotheek op te bouwen.',
    ],

    'save_to_assets' => 'Opslaan in assets',
    'saved' => 'Opgeslagen in je assets!',
    'create_post' => 'Post aanmaken',
    'add_to_post' => 'Toevoegen aan post',
    'search_placeholder' => 'Media zoeken...',

    'delete' => [
        'title' => 'Asset verwijderen',
        'description' => 'Weet je zeker dat je deze asset wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.',
        'confirm' => 'Verwijderen',
        'cancel' => 'Annuleren',
    ],

    'unsplash' => [
        'search_placeholder' => 'Gratis foto\'s zoeken...',
        'no_results' => 'Geen foto\'s gevonden',
        'no_results_description' => 'Probeer een andere zoekterm.',
        'trending' => 'Populair op Unsplash',
        'start_searching' => 'Zoek naar gratis stockfoto\'s van Unsplash',
    ],

    'giphy' => [
        'trending' => 'Populair op Giphy',
        'search_placeholder' => 'GIFs zoeken...',
        'no_results' => 'Geen GIFs gevonden',
        'no_results_description' => 'Probeer een andere zoekterm.',
        'powered_by' => 'Mogelijk gemaakt door GIPHY',
    ],
    'references' => [
        'description' => 'Foto\'s die AI-beeldgeneratie sturen: gezichten die behouden moeten blijven, logo\'s, producten en visuele stijlen. Maximaal :max foto\'s.',
        'kind_label' => 'Fototype',
        'label_label' => 'Label (optioneel)',
        'label_placeholder' => 'bijv. Sara, frontale portretfoto',
        'kinds' => [
            'face_closeup' => 'Close-up van gezicht',
            'full_body' => 'Hele lichaam',
            'logo' => 'Logo',
            'product' => 'Product',
            'style' => 'Stijl',
            'other' => 'Overig',
        ],
        'upload' => [
            'drag_drop' => 'Sleep referentiefoto\'s hierheen of klik om te selecteren',
            'formats' => 'JPEG, PNG, GIF, WebP',
        ],
        'empty' => [
            'title' => 'Nog geen merkreferenties',
            'description' => 'Voeg 3–5 duidelijke foto\'s toe: een frontaal portret met goede belichting, je logo en de producten of stijlen die de AI opnieuw moet gebruiken.',
        ],
        'add_from_assets' => 'Toevoegen vanuit Assets',
        'from_assets_title' => 'Kies foto\'s uit je assets',
        'promote' => 'Opslaan als referentie',
        'promoted' => 'Opgeslagen als merkreferentie.',
        'promote_limit' => 'Er kunnen nog slechts :remaining foto\'s worden toegevoegd (max. :max).',
        'updated' => 'Referentiefoto bijgewerkt.',
        'deleted_title' => 'Referentiefoto verwijderen',
        'deleted_description' => 'Weet je zeker dat je deze referentiefoto wilt verwijderen? AI-generatie zal deze niet meer gebruiken. Deze actie kan niet ongedaan worden gemaakt.',
        'limit_reached' => 'Je hebt al :max merkreferentiefoto\'s. Verwijder er een voordat je een nieuwe toevoegt.',
        'search_placeholder' => 'Referenties zoeken...',
        'all_kinds' => 'Alle typen',
        'edit' => 'Label en type bewerken',
        'save' => 'Opslaan',
        'manage' => 'Beheren in Assets',
    ],
];
