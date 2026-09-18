<?php

/*
|--------------------------------------------------------------------------
| Ozellik bayraklari (docs/planning/01 §18, 16 §1)
|--------------------------------------------------------------------------
|
| Kod dagitimi ile sema aktivasyonu ayridir: bir ekran, hem bayragi acik
| olacak hem de bagli oldugu migration grubu uygulanmis olacaktir. Grubun
| uygulanip uygulanmadigi dogrudan veritabani semasindan okunur (D-92,
| bkz. App\Services\Platform\SchemaReadiness); ortam degiskeni yoktur.
|
| Bayrak adlari noktali tek anahtardir; FeatureFlags once tum diziyi ceker.
|
*/

return [

    'flags' => [
        'personnel.admin_ui' => (bool) env('FEATURE_PERSONNEL_ADMIN_UI', true),
        'documents.admin_ui' => (bool) env('FEATURE_DOCUMENTS_ADMIN_UI', true),
        'acquisition.admin_ui' => (bool) env('FEATURE_ACQUISITION_ADMIN_UI', true),
        'projects.admin_ui' => (bool) env('FEATURE_PROJECTS_ADMIN_UI', true),
        'activity.admin_ui' => (bool) env('FEATURE_ACTIVITY_ADMIN_UI', true),
        'approvals.admin_ui' => (bool) env('FEATURE_APPROVALS_ADMIN_UI', true),
        'notifications.database' => (bool) env('FEATURE_DATABASE_NOTIFICATIONS', true),
        'chat.admin_ui' => (bool) env('FEATURE_CHAT_UI', true),
        'work_requests.admin_ui' => (bool) env('FEATURE_WORK_REQUESTS_UI', true),
        'reports.admin_ui' => (bool) env('FEATURE_REPORTS_UI', true),
        'social_media.admin_ui' => (bool) env('FEATURE_SOCIAL_MEDIA_UI', true),
    ],
];
