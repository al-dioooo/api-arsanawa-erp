<?php

use App\Modules\Platform\Contracts\WhatsAppGateway;
use App\Modules\Platform\Services\WhatsApp\FonnteDriver;
use App\Modules\Platform\Services\WhatsApp\LogDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

describe('WhatsApp gateway resolution', function () {
    it('resolves the log driver when configured', function (): void {
        config(['services.whatsapp.driver' => 'log']);

        expect(app(WhatsAppGateway::class))->toBeInstanceOf(LogDriver::class);
    });

    it('resolves the fonnte driver when configured', function (): void {
        config([
            'services.whatsapp.driver' => 'fonnte',
            'services.whatsapp.fonnte.base_url' => 'https://api.fonnte.com/send',
            'services.whatsapp.fonnte.token' => 'test-token',
        ]);

        expect(app(WhatsAppGateway::class))->toBeInstanceOf(FonnteDriver::class);
    });
});

describe('Fonnte driver', function () {
    it('maps a successful response to an ok result with a message id', function (): void {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['998877']], 200),
        ]);

        $driver = new FonnteDriver('https://api.fonnte.com/send');
        $result = $driver->send('628123456789', 'hello', ['token' => 'abc']);

        expect($result->ok)->toBeTrue()
            ->and($result->provider)->toBe('fonnte')
            ->and($result->providerMessageId)->toBe('998877');
    });

    it('maps a 401 response to a failed result without throwing', function (): void {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'invalid token'], 401),
        ]);

        $driver = new FonnteDriver('https://api.fonnte.com/send');
        $result = $driver->send('628123456789', 'hello', ['token' => 'bad']);

        expect($result->ok)->toBeFalse()
            ->and($result->error)->toContain('invalid token');
    });

    it('fails fast when no token is available', function (): void {
        Http::fake();

        $driver = new FonnteDriver('https://api.fonnte.com/send');
        $result = $driver->send('628123456789', 'hello', []);

        expect($result->ok)->toBeFalse()
            ->and($result->error)->toContain('token');
        Http::assertNothingSent();
    });
});

describe('Log driver', function () {
    it('logs the payload and reports success', function (): void {
        Log::spy();

        $driver = new LogDriver();
        $result = $driver->send('628123456789', 'hi there');

        expect($result->ok)->toBeTrue()
            ->and($result->provider)->toBe('log');
        Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'whatsapp.outbound'
            && ($context['to'] ?? null) === '628123456789'
            && ($context['body'] ?? null) === 'hi there');
    });
});
