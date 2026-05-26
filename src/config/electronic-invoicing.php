<?php

/*
 * DIAN Electronic Invoicing configuration.
 *
 * Source of truth: specs/features/electronic-invoicing-dian.spec.md section
 * "Configuracion (config/electronic-invoicing.php)".
 *
 * No literal secrets here: PINs, certificate passwords and software IDs are
 * resolved through SecretManagerInterface and DianSoftwareCredential records.
 */

return [
    'enabled' => env('ELECTRONIC_INVOICING_ENABLED', false),
    'environment' => env('ELECTRONIC_INVOICING_ENV', 'habilitacion'),

    'webservice' => [
        'habilitacion_url' => env(
            'DIAN_HAB_WSDL',
            'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl'
        ),
        'production_url' => env(
            'DIAN_PROD_WSDL',
            'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl'
        ),
        'timeout_seconds' => env('DIAN_HTTP_TIMEOUT', 30),
        'connect_timeout_seconds' => env('DIAN_CONNECT_TIMEOUT', 8),
        'tls_version' => 'TLSv1_2',
        'verify_peer' => true,
    ],

    'signature' => [
        'algorithm' => 'RSA-SHA256',
        'canonicalization' => 'http://www.w3.org/2001/10/xml-exc-c14n#',
        'policy_oid' => env('DIAN_SIGNATURE_POLICY_OID'),
        'policy_url' => env('DIAN_SIGNATURE_POLICY_URL'),
        'policy_hash_b64' => env('DIAN_SIGNATURE_POLICY_HASH_B64'),
    ],

    /*
     * Wave-level feature flags. Off by default so existing tests that assert
     * documents end at `ubl_built` keep passing. The xades-integration slice
     * cables XadesEpesSigner behind `signing.enabled`; the dian-dispatcher
     * slice cables SOAP delivery behind `dispatch.enabled`.
     */
    'signing' => [
        'enabled' => env('ELECTRONIC_INVOICING_SIGNING_ENABLED', false),
    ],
    'dispatch' => [
        'enabled' => env('ELECTRONIC_INVOICING_DISPATCH_ENABLED', false),
        'mode' => env('ELECTRONIC_INVOICING_DISPATCH_MODE', 'sync'),
    ],

    'certificate' => [
        'storage_disk' => env('FISCAL_DISK', 'fiscal'),
        'rotation_alert_days' => 30,
    ],

    'circuit_breaker' => [
        'failure_threshold' => 5,
        'recovery_seconds' => 60,
    ],

    'retries' => [
        'max_attempts' => 6,
        'backoff_seconds' => [10, 30, 120, 600, 1800, 3600],
    ],

    'reconciler' => [
        'interval_minutes' => 5,
        'stuck_after_minutes' => 10,
    ],

    'contingency' => [
        'auto_threshold_failures' => 3,
        'window_seconds' => 120,
        'sync_interval_minutes' => 15,
        'max_window_hours' => 48,
    ],

    'storage_disk' => env('ELECTRONIC_INVOICING_DISK', 'fiscal'),
    'retention_years' => 5,

    'qr' => [
        'base_url_hab' => env(
            'DIAN_QR_HAB_URL',
            'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey='
        ),
        'base_url_prod' => env(
            'DIAN_QR_PROD_URL',
            'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='
        ),
    ],
];
