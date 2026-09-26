<?php

return [

    /*
    | Supported locales. VI is served at "/", others under "/{prefix}/".
    */
    'locales' => [
        'vi' => ['name' => 'Tiếng Việt', 'short' => 'VI', 'prefix' => '', 'hreflang' => 'vi', 'og' => 'vi_VN'],
        'en' => ['name' => 'English', 'short' => 'EN', 'prefix' => 'en', 'hreflang' => 'en', 'og' => 'en_US'],
        'ja' => ['name' => '日本語', 'short' => 'JA', 'prefix' => 'ja', 'hreflang' => 'ja', 'og' => 'ja_JP'],
    ],

    'default_locale' => 'vi',

    'admin_locale' => env('CMS_ADMIN_LOCALE', 'en'),

    // "404" = missing translation returns 404. "fallback" = show default-locale content (needs VJU approval).
    'translation_fallback' => env('CMS_TRANSLATION_FALLBACK', '404'),

    'per_page' => 12,

    'auth' => [
        // Local/dev only. Production must use Google OIDC.
        'password_login' => (bool) env('CMS_PASSWORD_LOGIN', false),
        'google_allowed_domains' => array_filter(array_map('trim', explode(',', (string) env('GOOGLE_ALLOWED_DOMAINS', '')))),
        // New Google users get an account WITHOUT any role; an admin must grant one.
        'google_auto_provision' => (bool) env('GOOGLE_AUTO_PROVISION', true),
    ],

    'media' => [
        'disk' => env('MEDIA_DISK', 'public'),
        'max_size_kb' => 65536,
        // Detected MIME (finfo), never the extension, decides acceptance.
        'mimes' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg', // sanitized by SvgSanitizer before storage
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'video/mp4' => 'mp4',
        ],
        'derivatives' => [
            'thumb' => 400,
            'web' => 1600,
        ],
    ],

    'menu_locations' => [
        'header' => 'Header (main navigation)',
        'topbar' => 'Top bar',
        'footer' => 'Footer',
    ],

    'wordpress' => [
        'base_url' => rtrim((string) env('WP_BASE_URL', 'https://vju.ac.vn'), '/'),
        'uploads_path' => env('WP_UPLOADS_PATH'),
        'prefix' => env('WP_DB_PREFIX', 'wp_'),

        // Former domains still referenced in content (links/images are rewritten like the main host).
        'legacy_hosts' => ['vju.ac.vn', 'vju.vnu.edu.vn'],

        // WordPress theme menu location => CMS location. Fill in from `wp:inspect --source=db`.
        'menu_locations' => [
            'header' => 'header',
            'primary' => 'header',
            'main-menu' => 'header',
            'menu-1' => 'header',
            'footer' => 'footer',
            'topbar' => 'topbar',
        ],

        // JetEngine meta key => CMS structured field, per WordPress post type. Phase 00 decides these;
        // unmapped meta is still preserved under fields.wp_meta. Example: 'tuition-fees' => ['file' => 'file_id'].
        'field_map' => [
            'download-documents' => [],
            'notification' => [],
            'tuition-fees' => [],
            'current-opportunitie' => [],
        ],
    ],
];
