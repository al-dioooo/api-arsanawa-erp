<?php

describe('production deployment artifacts', function () {
    it('defines Railway pre-deploy commands for migrations, seeders, and cache warming', function () {
        $scriptPath = base_path('railway/init-app.sh');
        $configPath = base_path('railway.json');

        expect(file_exists($scriptPath))->toBeTrue();
        expect(file_exists($configPath))->toBeTrue();

        $script = file_get_contents($scriptPath);
        $config = json_decode((string) file_get_contents($configPath), true);

        expect($script)->toContain('php artisan migrate --force')
            ->and($script)->toContain('php artisan db:seed --force')
            ->and($script)->toContain('php artisan config:cache')
            ->and($script)->toContain('php artisan route:cache')
            ->and($config)->toHaveKey('deploy.preDeployCommand')
            ->and($config['deploy']['preDeployCommand'])->toContain('railway/init-app.sh')
            ->and($config['deploy']['healthcheckPath'])->toBe('/up');
    });

    it('provides a redacted Railway environment template for Supabase-backed production', function () {
        $envPath = base_path('.env.railway.example');

        expect(file_exists($envPath))->toBeTrue();

        $env = (string) file_get_contents($envPath);

        expect($env)->toContain('APP_ENV=production')
            ->and($env)->toContain('APP_DEBUG=false')
            ->and($env)->toContain('FRONTEND_URL=https://arsanawa-erp.vercel.app')
            ->and($env)->toContain('DB_CONNECTION=pgsql')
            ->and($env)->toContain('DB_SSLMODE=require')
            ->and($env)->toContain('postgres.temebxcxioszcnwycwxj')
            ->and($env)->toContain('FILESYSTEM_DISK=supabase')
            ->and($env)->toContain('SUPABASE_STORAGE_PRODUCT_IMAGES_BUCKET=arsanawa-product-images')
            ->and($env)->toContain('SUPABASE_STORAGE_IMPORTS_BUCKET=arsanawa-imports')
            ->and($env)->toContain('MAIL_MAILER=smtp')
            ->and($env)->toContain('MAIL_USERNAME=lisafronaldio123@gmail.com')
            ->and($env)->not->toContain('P6EfhcuVOqeUcupo')
            ->and($env)->not->toContain('aldio1234')
            ->and($env)->not->toContain('sekalori1234');
    });
});

describe('production cors', function () {
    it('allows the Vercel frontend origin for API requests', function () {
        config()->set('cors.allowed_origins', ['https://arsanawa-erp.vercel.app']);

        $this->withHeaders([
            'Origin' => 'https://arsanawa-erp.vercel.app',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/auth/login')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://arsanawa-erp.vercel.app');
    });

    it('does not allow unrelated origins for API requests', function () {
        config()->set('cors.allowed_origins', ['https://arsanawa-erp.vercel.app']);

        $response = $this->withHeaders([
            'Origin' => 'https://example.com',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/auth/login')
            ->assertNoContent();

        expect($response->headers->get('Access-Control-Allow-Origin'))
            ->not->toBe('*')
            ->not->toBe('https://example.com');
    });
});

describe('production queue', function () {
    it('documents sync queue execution for the single Railway web service', function () {
        $envPath = base_path('.env.railway.example');

        expect(file_exists($envPath))->toBeTrue();

        $env = (string) file_get_contents($envPath);

        expect($env)->toContain('QUEUE_CONNECTION=sync')
            ->and($env)->not->toContain('QUEUE_CONNECTION=database');
    });
});
