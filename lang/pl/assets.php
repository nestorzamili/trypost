<?php

return [
    'title' => 'Zasoby',

    'tabs' => [
        'my_uploads' => 'Moje przesłane pliki',
        'stock_photos' => 'Zdjęcia stockowe',
        'gifs' => 'GIF-y',
        'references' => 'Referencje marki',
    ],

    'upload' => [
        'drag_drop' => 'Przeciągnij i upuść tutaj pliki lub kliknij, aby wybrać',
        'formats' => 'JPEG, PNG, GIF, WebP, MP4, PDF',
        'uploading' => 'Przesyłanie...',
        'failed' => 'Nie udało się przesłać :file. Spróbuj ponownie.',
        'file_too_large' => 'Rozmiar pliku przekracza dozwolone maksimum (:max MB).',
        'cancelled' => 'Przesyłanie anulowane.',
    ],

    'empty' => [
        'title' => 'Brak zasobów',
        'description' => 'Prześlij zdjęcia i filmy, aby zbudować swoją bibliotekę multimediów.',
    ],

    'save_to_assets' => 'Zapisz w zasobach',
    'saved' => 'Zapisano w Twoich zasobach!',
    'create_post' => 'Utwórz post',
    'add_to_post' => 'Dodaj do posta',
    'search_placeholder' => 'Szukaj multimediów...',

    'delete' => [
        'title' => 'Usuń zasób',
        'description' => 'Czy na pewno chcesz usunąć ten zasób? Tej operacji nie można cofnąć.',
        'confirm' => 'Usuń',
        'cancel' => 'Anuluj',
    ],

    'unsplash' => [
        'search_placeholder' => 'Szukaj darmowych zdjęć...',
        'no_results' => 'Nie znaleziono zdjęć',
        'no_results_description' => 'Spróbuj innego wyszukiwanego hasła.',
        'trending' => 'Popularne na Unsplash',
        'start_searching' => 'Wyszukaj darmowe zdjęcia stockowe z Unsplash',
    ],

    'giphy' => [
        'trending' => 'Popularne na Giphy',
        'search_placeholder' => 'Szukaj GIF-ów...',
        'no_results' => 'Nie znaleziono GIF-ów',
        'no_results_description' => 'Spróbuj innego wyszukiwanego hasła.',
        'powered_by' => 'Napędzane przez GIPHY',
    ],
    'references' => [
        'description' => 'Zdjęcia, które kierują generowaniem obrazów przez AI: twarze do zachowania, logo, produkty i style wizualne. Maksymalnie :max zdjęć.',
        'kind_label' => 'Typ zdjęcia',
        'label_label' => 'Etykieta (opcjonalnie)',
        'label_placeholder' => 'np. Sara, portret na wprost',
        'kinds' => [
            'face_closeup' => 'Zbliżenie twarzy',
            'full_body' => 'Cała sylwetka',
            'logo' => 'Logo',
            'product' => 'Produkt',
            'style' => 'Styl',
            'other' => 'Inne',
        ],
        'upload' => [
            'drag_drop' => 'Przeciągnij zdjęcia referencyjne tutaj lub kliknij, aby wybrać',
            'formats' => 'JPEG, PNG, GIF, WebP',
        ],
        'empty' => [
            'title' => 'Brak jeszcze referencji marki',
            'description' => 'Dodaj 3–5 wyraźnych zdjęć: portret na wprost w dobrym oświetleniu, swoje logo oraz produkty lub style, które AI ma ponownie wykorzystywać.',
        ],
        'add_from_assets' => 'Dodaj z zasobów',
        'from_assets_title' => 'Wybierz zdjęcia ze swoich zasobów',
        'promote' => 'Zapisz jako referencję',
        'promoted' => 'Zapisano jako referencję marki.',
        'promote_limit' => 'Można dodać tylko :remaining zdjęć więcej (maks. :max).',
        'updated' => 'Zdjęcie referencyjne zaktualizowane.',
        'deleted_title' => 'Usuń zdjęcie referencyjne',
        'deleted_description' => 'Czy na pewno chcesz usunąć to zdjęcie referencyjne? Generowanie AI nie będzie go już używać. Tej operacji nie można cofnąć.',
        'limit_reached' => 'Masz już :max zdjęć referencyjnych marki. Usuń jedno przed dodaniem kolejnego.',
        'search_placeholder' => 'Szukaj referencji...',
        'all_kinds' => 'Wszystkie typy',
        'edit' => 'Edytuj etykietę i typ',
        'save' => 'Zapisz',
        'manage' => 'Zarządzaj w zasobach',
    ],
];
