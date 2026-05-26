<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Seeds the fine-grained permissions used by the Electronic Invoicing (DIAN)
 * routes and UI. Today the `electronic-invoicing/**` API endpoints only
 * enforce `auth:sanctum`; this seeder declares the permission names that
 * upcoming slices (`observability-and-permissions`, `documents-detail-and-downloads`,
 * `fiscal-admin-certificate`, `radian`) will attach to their respective
 * routes through the `permission:<name>,cerrajero` middleware pattern.
 *
 * Placing the declaration here ahead of time lets the wave-A slices run in
 * parallel without racing for who creates the seeder first, and gives the
 * deploy team a single artifact to assign to admin roles before any route
 * gets restricted.
 */
class ElectronicInvoicingPermissionsSeeder extends Seeder
{
    private const GUARD = 'cerrajero';

    /**
     * Source of truth for the EI permission catalogue. Each slice must use
     * one of these names exactly as listed; extending the catalogue is
     * allowed, renaming is not.
     */
    public const PERMISSIONS = [
        'electronic_documents.view' => 'Listar y consultar documentos electrónicos DIAN',
        'electronic_documents.emit' => 'Emitir FEV / DEE POS directamente',
        'electronic_documents.emit_note' => 'Emitir Notas Crédito / Débito',
        'electronic_documents.retry' => 'Reintentar el envío de un documento a DIAN',
        'electronic_documents.cancel' => 'Marcar documentos para reposo / contingencia / dead letter',
        'electronic_documents.admin' => 'Administrar configuración fiscal (certificado, software, resoluciones, promoción)',
        'electronic_documents.radian' => 'Emitir eventos RADIAN 030/031/032/033/034',
    ];

    public function run(): void
    {
        $this->command?->info('Creando permisos de Facturación Electrónica DIAN...');

        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command?->info('  ✓ ' . count(self::PERMISSIONS) . ' permisos EI declarados (guard=' . self::GUARD . ').');
    }
}
