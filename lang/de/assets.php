<?php

declare(strict_types=1);

return [
    'title' => 'Assets',

    'tabs' => [
        'my_uploads' => 'Meine Uploads',
        'stock_photos' => 'Stockfotos',
        'gifs' => 'GIFs',
        'references' => 'Markenreferenzen',
    ],

    'upload' => [
        'drag_drop' => 'Dateien hierher ziehen und ablegen oder zum Auswählen klicken',
        'formats' => 'JPEG, PNG, GIF, WebP, MP4, PDF',
        'uploading' => 'Wird hochgeladen...',
        'failed' => ':file konnte nicht hochgeladen werden. Bitte versuche es erneut.',
        'file_too_large' => 'Die Dateigröße überschreitet das zulässige Maximum (:max MB).',
        'cancelled' => 'Upload abgebrochen.',
    ],

    'empty' => [
        'title' => 'Noch keine Assets',
        'description' => 'Lade Bilder und Videos hoch, um deine Medienbibliothek aufzubauen.',
    ],

    'save_to_assets' => 'In Assets speichern',
    'saved' => 'In deinen Assets gespeichert!',
    'create_post' => 'Beitrag erstellen',
    'add_to_post' => 'Zum Beitrag hinzufügen',
    'search_placeholder' => 'Medien suchen...',

    'delete' => [
        'title' => 'Asset löschen',
        'description' => 'Möchtest du dieses Asset wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.',
        'confirm' => 'Löschen',
        'cancel' => 'Abbrechen',
    ],

    'unsplash' => [
        'search_placeholder' => 'Kostenlose Fotos suchen...',
        'no_results' => 'Keine Fotos gefunden',
        'no_results_description' => 'Versuche einen anderen Suchbegriff.',
        'trending' => 'Beliebt auf Unsplash',
        'start_searching' => 'Suche nach kostenlosen Stockfotos von Unsplash',
    ],

    'giphy' => [
        'trending' => 'Beliebt auf Giphy',
        'search_placeholder' => 'GIFs suchen...',
        'no_results' => 'Keine GIFs gefunden',
        'no_results_description' => 'Versuche einen anderen Suchbegriff.',
        'powered_by' => 'Bereitgestellt von GIPHY',
    ],
    'references' => [
        'description' => 'Fotos, die die KI-Bildgenerierung steuern: zu bewahrende Gesichter, Logos, Produkte und visuelle Stile. Bis zu :max Fotos.',
        'kind_label' => 'Fototyp',
        'label_label' => 'Beschriftung (optional)',
        'label_placeholder' => 'z. B. Sara, Frontalporträt',
        'kinds' => [
            'face_closeup' => 'Nahaufnahme des Gesichts',
            'full_body' => 'Ganzkörper',
            'logo' => 'Logo',
            'product' => 'Produkt',
            'style' => 'Stil',
            'other' => 'Sonstige',
        ],
        'upload' => [
            'drag_drop' => 'Referenzfotos hierher ziehen oder klicken zum Auswählen',
            'formats' => 'JPEG, PNG, GIF, WebP',
        ],
        'empty' => [
            'title' => 'Noch keine Markenreferenzen',
            'description' => 'Fügen Sie 3–5 klare Fotos hinzu: ein Frontalporträt mit guter Beleuchtung, Ihr Logo sowie Produkte oder Stile, die die KI wiederverwenden soll.',
        ],
        'add_from_assets' => 'Aus Assets hinzufügen',
        'from_assets_title' => 'Fotos aus Ihren Assets auswählen',
        'promote' => 'Als Referenz speichern',
        'promoted' => 'Als Markenreferenz gespeichert.',
        'promote_limit' => 'Es können nur noch :remaining weitere Fotos hinzugefügt werden (max. :max).',
        'updated' => 'Referenzfoto aktualisiert.',
        'deleted_title' => 'Referenzfoto löschen',
        'deleted_description' => 'Möchten Sie dieses Referenzfoto wirklich löschen? Die KI-Generierung wird es nicht mehr verwenden. Diese Aktion kann nicht rückgängig gemacht werden.',
        'limit_reached' => 'Sie haben bereits :max Markenreferenz-Fotos. Löschen Sie eines, bevor Sie ein neues hinzufügen.',
        'search_placeholder' => 'Referenzen suchen...',
        'all_kinds' => 'Alle Typen',
        'edit' => 'Beschriftung und Typ bearbeiten',
        'save' => 'Speichern',
        'manage' => 'In Assets verwalten',
    ],
];
