@php
    $pb = $priceBreakdown ?? [];
    $fmt = fn ($n) => '$' . number_format((float) $n, 0, ',', '.');
@endphp

@if(!empty($pb))
    <div class="section">
        <div class="section-title">Desglose de Precio</div>
        <div class="summary-box">
            @if(($pb['subtotal_before_discount'] ?? 0) > ($pb['hospedaje'] ?? 0) && ($pb['discount'] ?? 0) > 0)
                <div class="summary-item">
                    <div class="summary-label">Subtotal hospedaje:</div>
                    <div class="summary-value">{{ $fmt($pb['subtotal_before_discount']) }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">
                        @if(!empty($pb['promotion_code']))
                            Cupón {{ $pb['promotion_code'] }}@if(!empty($pb['promotion_name'])) ({{ $pb['promotion_name'] }})@endif:
                        @else
                            Descuento:
                        @endif
                    </div>
                    <div class="summary-value" style="color: #2F6B3F;">−{{ $fmt($pb['discount']) }}</div>
                </div>
            @endif
            <div class="summary-item">
                <div class="summary-label">Hospedaje{{ ($pb['is_multi_room'] ?? false) ? ' (todas las habitaciones)' : '' }}:</div>
                <div class="summary-value">{{ $fmt($pb['hospedaje'] ?? 0) }}</div>
            </div>
            @if(($pb['additional_services_total'] ?? 0) > 0)
                <div class="summary-item">
                    <div class="summary-label">Servicios adicionales:</div>
                    <div class="summary-value">{{ $fmt($pb['additional_services_total']) }}</div>
                </div>
            @endif
            @if(($pb['courtesy_discount'] ?? 0) > 0)
                <div class="summary-item">
                    <div class="summary-label">Cortesías ({{ $pb['courtesy_guests'] ?? 0 }}):</div>
                    <div class="summary-value" style="color: #2F6B3F;">−{{ $fmt($pb['courtesy_discount']) }}</div>
                </div>
            @endif
            @if(($pb['minibar_total'] ?? 0) > 0)
                <div class="summary-item">
                    <div class="summary-label">Minibar{{ ($pb['is_multi_room'] ?? false) ? ' (todas las habitaciones)' : '' }}:</div>
                    <div class="summary-value">{{ $fmt($pb['minibar_total']) }}</div>
                </div>
            @endif
            @if(($pb['room_charges_total'] ?? 0) > 0)
                <div class="summary-item">
                    <div class="summary-label">Cargos a habitación (restaurante):</div>
                    <div class="summary-value">{{ $fmt($pb['room_charges_total']) }}</div>
                </div>
            @endif
            @if(($pb['kiosko_total'] ?? 0) > 0)
                <div class="summary-item">
                    <div class="summary-label">Kiosko{{ ($pb['is_multi_room'] ?? false) ? ' (todas las habitaciones)' : '' }}:</div>
                    <div class="summary-value">{{ $fmt($pb['kiosko_total']) }}</div>
                </div>
            @endif
            <div class="divider" style="margin: 12px 0;"></div>
            <div class="summary-item">
                <div class="summary-label">Total:</div>
                <div class="summary-value">{{ $fmt($pb['total'] ?? 0) }}</div>
            </div>
        </div>
    </div>
@endif
