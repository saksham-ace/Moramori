<?php
/**
 * Small automatic schema upgrades for stores installed with an older version.
 * Each step runs once; the current version is kept in settings.schema_version.
 */
const SCHEMA_VERSION = 2;

function run_migrations(): void
{
    $v = (int)setting('schema_version', '1');
    if ($v >= SCHEMA_VERSION) return;

    $steps = [
        2 => [
            "ALTER TABLE products ADD COLUMN allow_backorder TINYINT(1) NOT NULL DEFAULT 0 AFTER stock",
            "ALTER TABLE delivery_locations ADD COLUMN cod_available TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active",
            "ALTER TABLE orders ADD COLUMN payment_method ENUM('qr','cod') NOT NULL DEFAULT 'qr' AFTER total",
        ],
    ];
    foreach ($steps as $ver => $sqls) {
        if ($v >= $ver) continue;
        foreach ($sqls as $sql) {
            try {
                db()->exec($sql);
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) !== 1060) throw $e; // 1060 = column already exists (fresh install)
            }
        }
        save_setting('schema_version', (string)$ver);
    }
}

try {
    run_migrations();
} catch (PDOException $e) {
    // database not installed yet — install.php will handle it
}
