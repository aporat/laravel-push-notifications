<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests;

use RuntimeException;

final class Keys
{
    private static ?string $ec = null;

    private static ?string $rsa = null;

    public static function ec(): string
    {
        return self::$ec ??= self::generate(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    }

    public static function rsa(): string
    {
        return self::$rsa ??= self::generate(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    }

    public static function serviceAccountJson(string $project = 'demo-project'): string
    {
        return (string) json_encode([
            'type' => 'service_account',
            'project_id' => $project,
            'client_email' => 'fcm@'.$project.'.iam.gserviceaccount.com',
            'private_key' => self::rsa(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function generate(array $config): string
    {
        $key = openssl_pkey_new($config);

        if ($key === false || ! openssl_pkey_export($key, $pem) || ! is_string($pem)) {
            throw new RuntimeException('Could not generate a key.');
        }

        return $pem;
    }
}
