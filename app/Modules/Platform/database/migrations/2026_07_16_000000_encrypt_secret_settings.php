<?php

// Platform module — encrypt existing secret settings at rest.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Backfills credentials written before encryption existed.
 *
 * Deliberately self-contained: it uses raw queries and its own copy of the
 * registry and prefix rather than the Setting model, so a later refactor of
 * either cannot change what this migration does to historical rows.
 *
 * The model reads bare values fine, so this is not required for correctness —
 * it only closes the window where old rows sit in plaintext. Deploy order:
 * code first, this migration second, then bust the settings cache.
 */
return new class extends Migration
{
    private const ENCRYPTED_PREFIX = 'enc:v1:';

    /**
     * @var array<int, array{module: string, key: string}>
     */
    private const SECRETS = [
        ['module' => 'whatsapp', 'key' => 'token'],
    ];

    public function up(): void
    {
        foreach (self::SECRETS as $secret) {
            $this->eachSecretRow($secret, function (object $row): void {
                $decoded = json_decode($row->value, true);

                // Idempotent: skip anything already encrypted, plus null/empty.
                if (! is_string($decoded) || $decoded === '' || str_starts_with($decoded, self::ENCRYPTED_PREFIX)) {
                    return;
                }

                DB::table('settings')->where('id', $row->id)->update([
                    'value' => json_encode(self::ENCRYPTED_PREFIX.Crypt::encryptString($decoded)),
                ]);
            });
        }
    }

    public function down(): void
    {
        foreach (self::SECRETS as $secret) {
            $this->eachSecretRow($secret, function (object $row): void {
                $decoded = json_decode($row->value, true);

                if (! is_string($decoded) || ! str_starts_with($decoded, self::ENCRYPTED_PREFIX)) {
                    return;
                }

                DB::table('settings')->where('id', $row->id)->update([
                    'value' => json_encode(Crypt::decryptString(substr($decoded, strlen(self::ENCRYPTED_PREFIX)))),
                ]);
            });
        }
    }

    /**
     * @param  array{module: string, key: string}  $secret
     */
    private function eachSecretRow(array $secret, callable $handler): void
    {
        DB::table('settings')
            ->where('module', $secret['module'])
            ->where('key', $secret['key'])
            ->whereNotNull('value')
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($handler): void {
                foreach ($rows as $row) {
                    $handler($row);
                }
            });
    }
};
