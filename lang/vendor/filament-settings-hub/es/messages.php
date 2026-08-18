<?php

return [
    'title' => 'Configuración',
    'group' => 'Configuración',
    'back' => 'Volver',

    'settings' => [
        'site' => [
            'title' => 'Configuración del sitio',
            'description' => 'Administra la configuración general del sitio',
            'form' => [
                'site_name' => 'Nombre del sitio',
                'site_description' => 'Descripción del sitio',
                'site_logo' => 'Logotipo del sitio',
                'site_profile' => 'Imagen de perfil del sitio',
                'site_keywords' => 'Palabras clave',
                'site_email' => 'Correo electrónico',
                'site_phone' => 'Teléfono',
                'site_author' => 'Autor del sitio',
            ],
            'site-map' => 'Generar mapa del sitio',
            'site-map-notification' => 'El mapa del sitio se generó correctamente',
        ],

        'social' => [
            'title' => 'Redes sociales',
            'description' => 'Administra los enlaces de redes sociales',
            'form' => [
                'site_social' => 'Enlaces sociales',
                'vendor' => 'Plataforma',
                'link' => 'Enlace',
            ],
        ],

        'location' => [
            'title' => 'Configuración de ubicación',
            'description' => 'Administra la configuración de ubicación',
            'form' => [
                'site_address' => 'Dirección',
                'site_phone_code' => 'Código telefónico',
                'site_location' => 'Ubicación',
                'site_currency' => 'Moneda',
                'site_language' => 'Idioma',
            ],
        ],
    ],
];