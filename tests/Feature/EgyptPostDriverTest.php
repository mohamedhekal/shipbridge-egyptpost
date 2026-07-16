<?php

declare(strict_types=1);

use Hekal\ShipBridge\DTOs\Address;
use Hekal\ShipBridge\DTOs\CreateShipmentRequest;
use Hekal\ShipBridge\DTOs\Parcel;
use Hekal\ShipBridge\DTOs\ReturnShipmentRequest;
use Hekal\ShipBridge\Enums\ShipmentStatus;
use Hekal\ShipBridge\Exceptions\ShipBridgeException;
use Hekal\ShipBridge\Facades\ShipBridge;
use Illuminate\Support\Facades\Http;

it('creates a shipment via partner gateway', function (): void {
    Http::fake([
        'https://egyptpost.test/v1/shipments' => Http::response([
            'id' => 'EGYPTPOST-1',
            'tracking_number' => 'TRK-EGYPTPOST-1',
            'status' => 'created',
            'carrier' => 'egyptpost',
            'label_url' => 'https://labels.test/egyptpost.pdf',
        ], 200),
    ]);

    $result = ShipBridge::driver('egyptpost')->createShipment(new CreateShipmentRequest(
        origin: new Address('Warehouse', '1 Industrial Rd', 'Cairo', 'EG'),
        destination: new Address('Customer', '12 Nile St', 'Giza', 'EG', phone: '01000000000'),
        parcels: [new Parcel(weightKg: 1.5)],
        reference: 'ORD-100',
    ));

    expect($result->id)->toBe('EGYPTPOST-1')
        ->and($result->trackingNumber)->toBe('TRK-EGYPTPOST-1')
        ->and($result->carrier)->toBe('egyptpost')
        ->and($result->status)->toBe(ShipmentStatus::Created);

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://egyptpost.test/v1/shipments'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && ($request->data()['reference'] ?? null) === 'ORD-100'
            && ($request->data()['destination']['phone'] ?? null) === '01000000000';
    });
});

it('tracks via official TrackTrace endpoint', function (): void {
    Http::fake([
        'https://track.test/GetShipmentDetails*' => Http::response([
            'Success' => true,
            'Data' => [
                'Barcode' => 'EP-12345',
                'Status' => 'OUT_FOR_DELIVERY',
                'History' => [
                    [
                        'Status' => 'REGISTERED',
                        'Description' => 'Shipment accepted',
                        'Date' => '2026-07-15 09:00:00',
                        'Location' => 'Cairo Main Office',
                    ],
                    [
                        'Status' => 'OUT_FOR_DELIVERY',
                        'Description' => 'With courier',
                        'Date' => '2026-07-16 10:00:00',
                        'Location' => 'Giza',
                    ],
                ],
            ],
        ], 200),
    ]);

    $tracking = ShipBridge::driver('egyptpost')->track('EP-12345');

    expect($tracking->trackingNumber)->toBe('EP-12345')
        ->and($tracking->status)->toBe(ShipmentStatus::OutForDelivery)
        ->and($tracking->events)->toHaveCount(2);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'https://track.test/GetShipmentDetails')
            && ($request->data()['Barcode'] ?? null) === 'EP-12345';
    });
});

it('parses embedded JSON from TrackTrace HTML responses', function (): void {
    Http::fake([
        'https://track.test/GetShipmentDetails*' => Http::response(
            '<html><body>{"barcode":"EP-99","status":"DELIVERED","events":[{"status":"DELIVERED","description":"Delivered to recipient","date":"2026-07-16"}]}</body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $tracking = ShipBridge::driver('egyptpost')->track('EP-99');

    expect($tracking->status)->toBe(ShipmentStatus::Delivered)
        ->and($tracking->events)->toHaveCount(1);
});

it('returns public track URL as label in track_only mode', function (): void {
    config()->set('shipbridge.drivers.egyptpost.mode', 'track_only');
    config()->set('shipbridge.drivers.egyptpost.api_key', null);

    $label = ShipBridge::driver('egyptpost')->label('EP-LABEL-1');

    expect($label->url)->toBe('https://track.test/portal?Barcode=EP-LABEL-1')
        ->and($label->contents)->toBe('')
        ->and($label->base64Encoded)->toBeFalse();
});

it('fetches partner label when mode is partner', function (): void {
    Http::fake([
        'https://egyptpost.test/v1/shipments/EP-1/label*' => Http::response([
            'contents' => base64_encode('%PDF-fake'),
            'base64' => true,
            'url' => 'https://labels.test/ep.pdf',
        ], 200),
    ]);

    $label = ShipBridge::driver('egyptpost')->label('EP-1');

    expect($label->contents)->not->toBeEmpty()
        ->and($label->url)->toBe('https://labels.test/ep.pdf');
});

it('throws helpful exception when creating in track_only mode', function (): void {
    config()->set('shipbridge.drivers.egyptpost.mode', 'track_only');
    config()->set('shipbridge.drivers.egyptpost.api_key', null);

    ShipBridge::driver('egyptpost')->createShipment(new CreateShipmentRequest(
        origin: new Address('Warehouse', '1 Industrial Rd', 'Cairo', 'EG'),
        destination: new Address('Customer', '12 Nile St', 'Giza', 'EG', phone: '01000000000'),
        parcels: [new Parcel(weightKg: 1.0)],
    ));
})->throws(ShipBridgeException::class, 'Wassalha');

it('creates a return via partner gateway', function (): void {
    Http::fake([
        'https://egyptpost.test/v1/shipments/EP-ORIG/returns' => Http::response([
            'id' => 'RET-1',
            'tracking_number' => 'TRK-RET-1',
            'status' => 'returned',
        ], 200),
    ]);

    $result = ShipBridge::driver('egyptpost')->createReturn(new ReturnShipmentRequest(
        originalShipmentId: 'EP-ORIG',
        returnTo: new Address('Warehouse', '1 Industrial Rd', 'Cairo', 'EG', phone: '01011111111'),
        pickupFrom: new Address('Customer', '12 Nile St', 'Giza', 'EG', phone: '01000000000'),
        reason: 'Wrong size',
    ));

    expect($result->status)->toBe(ShipmentStatus::Returned)
        ->and($result->trackingNumber)->toBe('TRK-RET-1');
});

it('requires destination phone for partner create', function (): void {
    ShipBridge::driver('egyptpost')->createShipment(new CreateShipmentRequest(
        origin: new Address('Warehouse', '1 Industrial Rd', 'Cairo', 'EG'),
        destination: new Address('Customer', '12 Nile St', 'Giza', 'EG'),
        parcels: [new Parcel(weightKg: 1.0)],
    ));
})->throws(ShipBridgeException::class, 'destination phone');
