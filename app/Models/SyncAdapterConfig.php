<?php

namespace App\Models;

/**
 * Read/write helper for per-instance sync-adapter configuration.
 *
 * Storage is a single JSON blob on the `config` column of
 * SyncAdapterInstance (prior shape was a separate sync_adapter_settings
 * table with one row per key, folded into the parent instance row per
 * review feedback). Adapters call the static methods here to avoid
 * having to json_decode or track dirty state themselves.
 *
 * Value semantics carry over from the old row-per-key design:
 *   - put(id, key, value) sets a value (including explicit null).
 *   - get(id, key, default) returns the stored value or the default
 *     when the key is missing entirely.
 *   - has(id, key) is true only when the key exists AND its value is
 *     not null.
 *   - forget(id, key) removes the key from the blob entirely.
 *
 * Adapters that store secrets (Fleet API tokens, Jamf passwords,
 * Intune client secrets, etc.) are responsible for Crypt::encrypt-ing
 * the value before calling put(). This class treats every value as an
 * opaque scalar and stores it verbatim inside the JSON blob.
 */
class SyncAdapterConfig
{
    public static function get(int $instanceId, string $configKey, mixed $default = null): mixed
    {
        $instance = SyncAdapterInstance::find($instanceId);
        if ($instance === null) {
            return $default;
        }

        $config = $instance->config ?? [];

        return array_key_exists($configKey, $config) ? $config[$configKey] : $default;
    }

    public static function put(int $instanceId, string $configKey, ?string $value): void
    {
        $instance = SyncAdapterInstance::find($instanceId);
        if ($instance === null) {
            return;
        }

        $config = $instance->config ?? [];
        $config[$configKey] = $value;
        $instance->config = $config;
        $instance->save();
    }

    public static function has(int $instanceId, string $configKey): bool
    {
        $instance = SyncAdapterInstance::find($instanceId);
        if ($instance === null) {
            return false;
        }

        $config = $instance->config ?? [];

        return array_key_exists($configKey, $config) && $config[$configKey] !== null;
    }

    public static function forget(int $instanceId, string $configKey): void
    {
        $instance = SyncAdapterInstance::find($instanceId);
        if ($instance === null) {
            return;
        }

        $config = $instance->config ?? [];
        if (! array_key_exists($configKey, $config)) {
            return;
        }

        unset($config[$configKey]);
        $instance->config = $config;
        $instance->save();
    }

    /**
     * All (config_key => value) pairs stored for this instance. Used
     * by callers that need to enumerate a prefix range of keys
     * (e.g. `group_mapping.*`) rather than looking one up at a time.
     *
     * @return array<string, mixed>
     */
    public static function listForInstance(int $instanceId): array
    {
        return SyncAdapterInstance::find($instanceId)?->config ?? [];
    }
}
