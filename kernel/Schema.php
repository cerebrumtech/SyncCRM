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
        $applied += self::repairDeleteRules($db);
        return $applied;
    }

    /**
     * Links between records must survive the other side being deleted.
     *
     * These three constraints shipped as ON DELETE CASCADE ON UPDATE SET NULL, which is
     * the two clauses the wrong way round: deleting a company destroyed its contacts and
     * deals, and deleting a contact destroyed its deals, although the confirmation dialog
     * promises they only lose the link. CREATE TABLE statements do not alter a table that
     * already exists, so existing databases are corrected here.
     */
    private static function repairDeleteRules($db): int
    {
        $wanted = [
            'contacts' => ['contacts_company_id_foreign', 'company_id', 'companies'],
            'deals'    => ['deals_company_id_foreign', 'company_id', 'companies'],
            'deals2'   => ['deals_contact_id_foreign', 'contact_id', 'contacts'],
        ];
        $fixed = 0;
        foreach ($wanted as $key => [$name, $column, $parent]) {
            $table = rtrim($key, '2');
            $rows = $db->query(
                'SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS'
                . ' WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?',
                [$name]
            )->getResultArray();
            if (! $rows || strtoupper($rows[0]['DELETE_RULE']) === 'SET NULL') {
                continue;
            }
            $db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
            $db->query("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`)"
                . " REFERENCES `{$parent}` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
            $fixed++;
        }
        if ($fixed > 0) {
            echo "Corrected {$fixed} foreign key(s) that deleted linked records instead of unlinking them.\n";
        }
        return $fixed;
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
