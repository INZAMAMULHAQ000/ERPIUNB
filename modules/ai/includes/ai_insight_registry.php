<?php
/**
 * AI F14 — Insight Registry.
 *
 * Centralized registry of insight definitions.
 * Each definition specifies how to detect, evaluate, and notify about an insight.
 */
class AIInsightRegistry
{
    private static array $definitions = [];

    /**
     * Register an insight definition.
     */
    public static function register(array $definition): void
    {
        $key = $definition['id'] ?? $definition['definition_key'] ?? '';
        if (empty($key)) {
            throw new \InvalidArgumentException('Insight definition must have an id.');
        }
        self::$definitions[$key] = $definition;
    }

    /**
     * Get a definition by key.
     */
    public static function get(string $key): ?array
    {
        return self::$definitions[$key] ?? null;
    }

    /**
     * Get all registered definitions.
     */
    public static function getAll(): array
    {
        return self::$definitions;
    }

    /**
     * Get definitions for a specific module.
     */
    public static function getByModule(string $module): array
    {
        return array_filter(self::$definitions, fn($d) => ($d['module'] ?? '') === $module);
    }

    /**
     * Get enabled definitions only.
     */
    public static function getEnabled(): array
    {
        return array_filter(self::$definitions, fn($d) => ($d['enabled'] ?? true) === true);
    }

    /**
     * Sync definitions to database (upsert).
     */
    public static function syncToDatabase(PDO $db): array
    {
        $synced = 0;
        $errors = 0;

        foreach (self::$definitions as $key => $def) {
            try {
                $existing = $db->prepare("SELECT id FROM ai_insight_definitions WHERE definition_key = ?");
                $existing->execute([$key]);
                $row = $existing->fetch();

                if ($row) {
                    $db->prepare("
                        UPDATE ai_insight_definitions SET
                            name = ?, description = ?, module = ?, category = ?,
                            insight_type = ?, enabled = ?, required_permissions = ?,
                            evaluation_config = ?, severity_rules = ?, target_users_config = ?,
                            navigation_config = ?, dedup_strategy = ?, evaluation_frequency = ?,
                            max_records = ?, max_runtime_seconds = ?, updated_at = NOW()
                        WHERE definition_key = ?
                    ")->execute([
                        $def['name'] ?? $key,
                        $def['description'] ?? null,
                        $def['module'] ?? 'general',
                        $def['category'] ?? 'general',
                        $def['type'] ?? 'rule_based',
                        ($def['enabled'] ?? true) ? 1 : 0,
                        json_encode($def['required_permissions'] ?? []),
                        json_encode($def['evaluation'] ?? []),
                        json_encode($def['severity_rules'] ?? []),
                        json_encode($def['target_users'] ?? []),
                        json_encode($def['navigation'] ?? []),
                        $def['dedup_strategy'] ?? 'same_hash',
                        $def['evaluation_frequency'] ?? 'hourly',
                        $def['max_records'] ?? 100,
                        $def['max_runtime_seconds'] ?? 30,
                        $key,
                    ]);
                } else {
                    $db->prepare("
                        INSERT INTO ai_insight_definitions
                        (definition_key, name, description, module, category, insight_type, enabled,
                         required_permissions, evaluation_config, severity_rules, target_users_config,
                         navigation_config, dedup_strategy, evaluation_frequency, max_records, max_runtime_seconds)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $key,
                        $def['name'] ?? $key,
                        $def['description'] ?? null,
                        $def['module'] ?? 'general',
                        $def['category'] ?? 'general',
                        $def['type'] ?? 'rule_based',
                        ($def['enabled'] ?? true) ? 1 : 0,
                        json_encode($def['required_permissions'] ?? []),
                        json_encode($def['evaluation'] ?? []),
                        json_encode($def['severity_rules'] ?? []),
                        json_encode($def['target_users'] ?? []),
                        json_encode($def['navigation'] ?? []),
                        $def['dedup_strategy'] ?? 'same_hash',
                        $def['evaluation_frequency'] ?? 'hourly',
                        $def['max_records'] ?? 100,
                        $def['max_runtime_seconds'] ?? 30,
                    ]);
                }
                $synced++;
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Load definitions from database into registry.
     */
    public static function loadFromDatabase(PDO $db): void
    {
        $stmt = $db->query("SELECT * FROM ai_insight_definitions WHERE enabled = 1 ORDER BY module, name");
        while ($row = $stmt->fetch()) {
            self::$definitions[$row['definition_key']] = [
                'id' => $row['definition_key'],
                'definition_key' => $row['definition_key'],
                'name' => $row['name'],
                'description' => $row['description'],
                'module' => $row['module'],
                'category' => $row['category'],
                'type' => $row['insight_type'],
                'enabled' => (bool) $row['enabled'],
                'required_permissions' => json_decode($row['required_permissions'] ?? '[]', true),
                'evaluation' => json_decode($row['evaluation_config'] ?? '{}', true),
                'severity_rules' => json_decode($row['severity_rules'] ?? '{}', true),
                'target_users' => json_decode($row['target_users_config'] ?? '{}', true),
                'navigation' => json_decode($row['navigation_config'] ?? '{}', true),
                'dedup_strategy' => $row['dedup_strategy'],
                'evaluation_frequency' => $row['evaluation_frequency'],
                'max_records' => (int) $row['max_records'],
                'max_runtime_seconds' => (int) $row['max_runtime_seconds'],
                'db_id' => (int) $row['id'],
            ];
        }
    }
}
