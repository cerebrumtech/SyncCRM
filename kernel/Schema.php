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
        $applied += self::addMissingColumns($db);
        return $applied;
    }

    /**
     * Columns added after the first release. CREATE TABLE leaves an existing table
     * alone, so a database installed earlier would never gain them; this adds any
     * that are absent and leaves the rest untouched.
     */
    private static function addMissingColumns($db): int
    {
        $wanted = [
            'users' => [
                // Who this user may READ. 'all' (the organisation) or 'own' (their own
                // records, plus anything shared with them). See App\Libraries\Visibility.
                'visibility' => "varchar(10) NOT NULL DEFAULT 'all' AFTER `color`",
            ],
            'companies' => [
                'alt_phone'      => "varchar(30) DEFAULT NULL AFTER `phone`",
                'district'       => "varchar(80) DEFAULT NULL AFTER `postal_code`",
                'branches'       => "smallint(5) unsigned DEFAULT NULL",
                'deposits_cr'    => "decimal(12,2) DEFAULT NULL",
                'loan_book_cr'   => "decimal(12,2) DEFAULT NULL",
                'loan_customers' => "int(10) unsigned DEFAULT NULL",
                'legacy_id'      => "varchar(40) DEFAULT NULL",
            ],
            'contacts' => [
                'alt_phone' => "varchar(30) DEFAULT NULL",
                'legacy_id' => "varchar(40) DEFAULT NULL",
            ],
            'deals' => [
                'proposal_amount'    => "decimal(14,2) DEFAULT NULL",
                'amount_received'    => "decimal(14,2) DEFAULT NULL",
                'amount_pending'     => "decimal(14,2) DEFAULT NULL",
                'lead_source'        => "varchar(60) DEFAULT NULL",
                'original_lead_id'   => "varchar(20) DEFAULT NULL",
                'interest_level_pct' => "tinyint(3) unsigned DEFAULT NULL",
                'agreement_signed'   => "tinyint(1) NOT NULL DEFAULT 0",
                'lead_date'          => "date DEFAULT NULL",
                'legacy_id'          => "varchar(40) DEFAULT NULL",
            ],
        ];
        $added = 0;
        foreach ($wanted as $table => $columns) {
            $have = [];
            foreach ($db->query(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS'
                . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            )->getResultArray() as $row) {
                $have[$row['COLUMN_NAME']] = true;
            }
            if (! $have) {
                continue; // table itself is missing; the CREATE above handles that
            }
            foreach ($columns as $name => $definition) {
                if (isset($have[$name])) {
                    continue;
                }
                $db->query("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$definition}");
                $added++;
            }
        }
        if ($added > 0) {
            echo "Added {$added} column(s) for data the spreadsheet carries.\n";
        }
        return $added;
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
