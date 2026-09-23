<?php

namespace Sync;

use RuntimeException;

/** Creates the database tables from schema/schema.sql. Safe to run repeatedly. */
class Schema
{
    public static function install(): int
    {
        $file = ROOTPATH . 'schema/schema.sql';
        if (! is_file($file)) {
            throw new RuntimeException('schema/schema.sql is missing.');
        }
        $sql = (string) file_get_contents($file);
        // Strip comment lines, then split on statement terminators.
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $statements = array_filter(array_map('trim', explode(';', $sql)), static function ($s) {
            return $s !== '';
        });

        $db = db_connect();
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        $applied = 0;
        try {
            foreach ($statements as $statement) {
                $db->query($statement);
                $applied++;
            }
        } finally {
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
        return $applied;
    }

    public static function isInstalled(): bool
    {
        try {
            return in_array('organizations', db_connect()->listTables(), true);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
