<?php

return [
    'title' => 'Médias',

    'tabs' => [
        'my_uploads' => 'Mes imports',
        'stock_photos' => 'Banque d\'images',
        'gifs' => 'GIF',
        'references' => 'Références de marque',
    ],

    'upload' => [
        'drag_drop' => 'Glissez-déposez vos fichiers ici, ou cliquez pour sélectionner',
        'formats' => 'JPEG, PNG, GIF, WebP, MP4, PDF',
        'uploading' => 'Import en cours...',
        'failed' => 'Impossible d\'importer :file. Veuillez réessayer.',
        'file_too_large' => 'La taille du fichier dépasse le maximum autorisé (:max Mo).',
        'cancelled' => 'Import annulé.',
    ],

    'empty' => [
        'title' => 'Aucun média pour le moment',
        'description' => 'Importez des images et des vidéos pour construire votre bibliothèque de médias.',
    ],

    'save_to_assets' => 'Enregistrer dans les médias',
    'saved' => 'Enregistré dans vos médias !',
    'create_post' => 'Créer une publication',
    'add_to_post' => 'Ajouter à la publication',
    'search_placeholder' => 'Rechercher un média...',

    'delete' => [
        'title' => 'Supprimer le média',
        'description' => 'Voulez-vous vraiment supprimer ce média ? Cette action est irréversible.',
        'confirm' => 'Supprimer',
        'cancel' => 'Annuler',
    ],

    'unsplash' => [
        'search_placeholder' => 'Rechercher des photos gratuites...',
        'no_results' => 'Aucune photo trouvée',
        'no_results_description' => 'Essayez un autre terme de recherche.',
        'trending' => 'Tendances sur Unsplash',
        'start_searching' => 'Recherchez des photos libres de droits sur Unsplash',
    ],

    'giphy' => [
        'trending' => 'Tendances sur Giphy',
        'search_placeholder' => 'Rechercher des GIF...',
        'no_results' => 'Aucun GIF trouvé',
        'no_results_description' => 'Essayez un autre terme de recherche.',
        'powered_by' => 'Propulsé par GIPHY',
    ],
    'references' => [
        'description' => 'Photos qui guident la génération d\'images par IA : visages à conserver, logos, produits et styles visuels. Jusqu\'à :max photos.',
        'kind_label' => 'Type de photo',
        'label_label' => 'Libellé (facultatif)',
        'label_placeholder' => 'ex. Sara, portrait de face',
        'kinds' => [
            'face_closeup' => 'Gros plan du visage',
            'full_body' => 'Corps entier',
            'logo' => 'Logo',
            'product' => 'Produit',
            'style' => 'Style',
            'other' => 'Autre',
        ],
        'upload' => [
            'drag_drop' => 'Glissez-déposez les photos de référence ici, ou cliquez pour sélectionner',
            'formats' => 'JPEG, PNG, GIF, WebP',
        ],
        'empty' => [
            'title' => 'Aucune référence de marque pour l\'instant',
            'description' => 'Ajoutez 3 à 5 photos nettes : un portrait de face bien éclairé, votre logo, ainsi que les produits ou styles que l\'IA doit réutiliser.',
        ],
        'add_from_assets' => 'Ajouter depuis les Ressources',
        'from_assets_title' => 'Choisissez des photos parmi vos ressources',
        'promote' => 'Enregistrer comme référence',
        'promoted' => 'Enregistré comme référence de marque.',
        'promote_limit' => 'Seules :remaining photos supplémentaires peuvent être ajoutées (maximum :max).',
        'updated' => 'Photo de référence mise à jour.',
        'deleted_title' => 'Supprimer la photo de référence',
        'deleted_description' => 'Voulez-vous vraiment supprimer cette photo de référence ? La génération par IA ne l\'utilisera plus. Cette action est irréversible.',
        'limit_reached' => 'Vous avez déjà :max photos de référence de marque. Supprimez-en une avant d\'en ajouter une autre.',
        'search_placeholder' => 'Rechercher des références...',
        'all_kinds' => 'Tous les types',
        'edit' => 'Modifier le libellé et le type',
        'save' => 'Enregistrer',
        'manage' => 'Gérer dans les Ressources',
    ],
];
