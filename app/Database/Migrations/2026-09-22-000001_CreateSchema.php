<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * SyncCRM schema. Every business table carries organization_id so the data model stays
 * multi-tenant ready. Enum-like columns are VARCHAR for portability; lists are JSON.
 */
class CreateSchema extends Migration
{
    private array $tableAttrs = ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];

    private function id(): array
    {
        return ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true];
    }

    private function fk(bool $null = false): array
    {
        return ['type' => 'INT', 'unsigned' => true, 'null' => $null];
    }

    private function str(int $len = 255, bool $null = false, ?string $default = null): array
    {
        $c = ['type' => 'VARCHAR', 'constraint' => $len, 'null' => $null];
        if ($default !== null) {
            $c['default'] = $default;
        }
        return $c;
    }

    private function bool(bool $default = false): array
    {
        return ['type' => 'TINYINT', 'constraint' => 1, 'default' => $default ? 1 : 0];
    }

    private function money(string $default = '0.00'): array
    {
        return ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => $default];
    }

    private function timestamps(): array
    {
        return [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];
    }

    public function up(): void
    {
        $f = $this->forge;

        $f->addField(['id' => $this->id(), 'name' => $this->str(160), 'timezone' => $this->str(64, false, 'Asia/Kolkata'), 'currency' => $this->str(8, false, 'INR'),
            'settings' => ['type' => 'JSON', 'null' => true]] + $this->timestamps());
        $f->addKey('id', true);
        $f->createTable('organizations', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'email' => $this->str(190), 'name' => $this->str(120), 'password_hash' => $this->str(255),
            'role' => $this->str(16, false, 'MEMBER'), 'is_active' => $this->bool(true), 'color' => $this->str(16, false, '#0068FF'),
            'last_login_at' => ['type' => 'DATETIME', 'null' => true]] + $this->timestamps());
        $f->addKey('id', true);
        $f->addUniqueKey('email');
        $f->addKey('organization_id');
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('users', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'email' => $this->str(190), 'role' => $this->str(16, false, 'MEMBER'), 'token' => $this->str(64),
            'status' => $this->str(16, false, 'PENDING'), 'invited_by_id' => $this->fk(), 'expires_at' => ['type' => 'DATETIME'], 'created_at' => ['type' => 'DATETIME', 'null' => true]]);
        $f->addKey('id', true);
        $f->addUniqueKey('token');
        $f->addKey(['organization_id', 'email']);
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('invited_by_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('invites', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'name' => $this->str(120), 'created_at' => ['type' => 'DATETIME', 'null' => true]]);
        $f->addKey('id', true);
        $f->addUniqueKey(['organization_id', 'name']);
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('teams', true, $this->tableAttrs);

        $f->addField(['team_id' => $this->fk(), 'user_id' => $this->fk()]);
        $f->addKey(['team_id', 'user_id'], true);
        $f->addForeignKey('team_id', 'teams', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('team_members', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'entity' => $this->str(16), 'entity_id' => $this->fk(), 'user_id' => $this->fk(true), 'team_id' => $this->fk(true), 'can_edit' => $this->bool()]);
        $f->addKey('id', true);
        $f->addKey(['organization_id', 'entity', 'entity_id']);
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('team_id', 'teams', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('record_shares', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'name' => $this->str(60), 'color' => $this->str(16, false, '#EBF3FF')]);
        $f->addKey('id', true);
        $f->addUniqueKey(['organization_id', 'name']);
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('tags', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'name' => $this->str(160), 'industry' => $this->str(120, true), 'website' => $this->str(255, true),
            'phone' => $this->str(30, true), 'email' => $this->str(190, true), 'address_line' => $this->str(255, true), 'city' => $this->str(80, true), 'state' => $this->str(80, true),
            'postal_code' => $this->str(16, true), 'country' => $this->str(80, true, 'India'), 'description' => ['type' => 'TEXT', 'null' => true],
            'tags' => ['type' => 'JSON', 'null' => true], 'custom_fields' => ['type' => 'JSON', 'null' => true], 'owner_id' => $this->fk(true)] + $this->timestamps());
        $f->addKey('id', true);
        $f->addKey(['organization_id', 'name']);
        $f->addKey(['organization_id', 'owner_id']);
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('owner_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $f->createTable('companies', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'first_name' => $this->str(80), 'last_name' => $this->str(80, true), 'email' => $this->str(190, true),
            'phone' => $this->str(30, true), 'phone_normalized' => $this->str(20, true), 'whatsapp_number' => $this->str(30, true), 'job_title' => $this->str(120, true),
            'company_id' => $this->fk(true), 'tags' => ['type' => 'JSON', 'null' => true], 'custom_fields' => ['type' => 'JSON', 'null' => true], 'owner_id' => $this->fk(true)] + $this->timestamps());
        $f->addKey('id', true);
        $f->addKey(['organization_id', 'email']);
        $f->addKey(['organization_id', 'phone_normalized']);
        $f->addKey(['organization_id', 'company_id']);
        $f->addKey(['organization_id', 'owner_id']);
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('company_id', 'companies', 'id', 'SET NULL', 'CASCADE');
        $f->addForeignKey('owner_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $f->createTable('contacts', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'name' => $this->str(120), 'position' => ['type' => 'INT', 'default' => 0], 'is_default' => $this->bool(), 'created_at' => ['type' => 'DATETIME', 'null' => true]]);
        $f->addKey('id', true);
        $f->addUniqueKey(['organization_id', 'name']);
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('pipelines', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'pipeline_id' => $this->fk(), 'name' => $this->str(120), 'position' => ['type' => 'INT', 'default' => 0], 'probability' => ['type' => 'INT', 'default' => 0],
            'is_won' => $this->bool(), 'is_lost' => $this->bool(), 'required_fields' => ['type' => 'JSON', 'null' => true], 'color' => $this->str(16, false, '#0068FF')]);
        $f->addKey('id', true);
        $f->addKey(['pipeline_id', 'position']);
        $f->addForeignKey('pipeline_id', 'pipelines', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('stages', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'title' => $this->str(160), 'pipeline_id' => $this->fk(), 'stage_id' => $this->fk(), 'status' => $this->str(16, false, 'OPEN'),
            'amount' => $this->money(), 'amount_is_manual' => $this->bool(true), 'expected_close_date' => ['type' => 'DATE', 'null' => true], 'closed_at' => ['type' => 'DATETIME', 'null' => true],
            'lost_reason' => $this->str(200, true), 'contact_id' => $this->fk(true), 'company_id' => $this->fk(true), 'owner_id' => $this->fk(true), 'position' => ['type' => 'INT', 'default' => 0],
            'tags' => ['type' => 'JSON', 'null' => true], 'custom_fields' => ['type' => 'JSON', 'null' => true], 'source_deal_id' => $this->fk(true)] + $this->timestamps());
        $f->addKey('id', true);
        $f->addKey(['organization_id', 'pipeline_id', 'stage_id']);
        $f->addKey(['organization_id', 'owner_id']);
        $f->addKey(['organization_id', 'status']);
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('pipeline_id', 'pipelines', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('stage_id', 'stages', 'id', 'RESTRICT', 'CASCADE');
        $f->addForeignKey('contact_id', 'contacts', 'id', 'SET NULL', 'CASCADE');
        $f->addForeignKey('company_id', 'companies', 'id', 'SET NULL', 'CASCADE');
        $f->addForeignKey('owner_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $f->addForeignKey('source_deal_id', 'deals', 'id', 'SET NULL', 'CASCADE');
        $f->createTable('deals', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'name' => $this->str(160), 'sku' => $this->str(64, true), 'description' => ['type' => 'TEXT', 'null' => true],
            'price' => $this->money(), 'tax_rate' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '18.00'], 'is_active' => $this->bool(true), 'image_url' => $this->str(255, true)] + $this->timestamps());
        $f->addKey('id', true);
        $f->addUniqueKey(['organization_id', 'sku']);
        $f->addKey(['organization_id', 'is_active']);
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('products', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'deal_id' => $this->fk(), 'product_id' => $this->fk(true), 'name' => $this->str(160), 'quantity' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => '1.00'],
            'unit_price' => $this->money(), 'discount_percent' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '0.00'], 'tax_rate' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '0.00'],
            'total' => $this->money(), 'position' => ['type' => 'INT', 'default' => 0]]);
        $f->addKey('id', true);
        $f->addKey('deal_id');
        $f->addForeignKey('deal_id', 'deals', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('product_id', 'products', 'id', 'SET NULL', 'CASCADE');
        $f->createTable('deal_line_items', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'type' => $this->str(16), 'title' => $this->str(160), 'description' => ['type' => 'TEXT', 'null' => true],
            'status' => $this->str(16, false, 'OPEN'), 'due_at' => ['type' => 'DATETIME', 'null' => true], 'end_at' => ['type' => 'DATETIME', 'null' => true], 'all_day' => $this->bool(),
            'location' => $this->str(200, true), 'recurrence' => $this->str(16, false, 'NONE'), 'recurrence_until' => ['type' => 'DATE', 'null' => true], 'reminder_minutes' => ['type' => 'INT', 'null' => true],
            'attendees' => ['type' => 'JSON', 'null' => true], 'call_direction' => $this->str(16, true), 'call_duration_sec' => ['type' => 'INT', 'null' => true], 'call_outcome' => $this->str(200, true),
            'assignee_id' => $this->fk(true), 'contact_id' => $this->fk(true), 'company_id' => $this->fk(true), 'deal_id' => $this->fk(true), 'created_by_id' => $this->fk(),
            'completed_at' => ['type' => 'DATETIME', 'null' => true]] + $this->timestamps());
        $f->addKey('id', true);
        $f->addKey(['organization_id', 'due_at']);
        $f->addKey(['organization_id', 'assignee_id', 'status']);
        $f->addKey('contact_id');
        $f->addKey('company_id');
        $f->addKey('deal_id');
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('assignee_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $f->addForeignKey('created_by_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('deal_id', 'deals', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('activities', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'body' => ['type' => 'TEXT'], 'contact_id' => $this->fk(true), 'company_id' => $this->fk(true), 'deal_id' => $this->fk(true), 'author_id' => $this->fk()] + $this->timestamps());
        $f->addKey('id', true);
        $f->addKey('contact_id');
        $f->addKey('company_id');
        $f->addKey('deal_id');
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('author_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('deal_id', 'deals', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('notes', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'filename' => $this->str(255), 'storage_path' => $this->str(255), 'mime_type' => $this->str(120), 'size' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'contact_id' => $this->fk(true), 'company_id' => $this->fk(true), 'deal_id' => $this->fk(true), 'uploaded_by_id' => $this->fk(), 'created_at' => ['type' => 'DATETIME', 'null' => true]]);
        $f->addKey('id', true);
        $f->addKey('contact_id');
        $f->addKey('company_id');
        $f->addKey('deal_id');
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('uploaded_by_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('deal_id', 'deals', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('attachments', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'entity' => $this->str(16), 'field_key' => $this->str(64), 'label' => $this->str(120), 'type' => $this->str(16, false, 'TEXT'),
            'options' => ['type' => 'JSON', 'null' => true], 'required' => $this->bool(), 'position' => ['type' => 'INT', 'default' => 0]]);
        $f->addKey('id', true);
        $f->addUniqueKey(['organization_id', 'entity', 'field_key']);
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('custom_field_definitions', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'entity' => $this->str(16), 'name' => $this->str(120), 'filters' => ['type' => 'JSON', 'null' => true],
            'owner_id' => $this->fk(), 'is_shared' => $this->bool(), 'created_at' => ['type' => 'DATETIME', 'null' => true]]);
        $f->addKey('id', true);
        $f->addKey(['organization_id', 'entity']);
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('owner_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $f->createTable('saved_views', true, $this->tableAttrs);

        $f->addField(['id' => $this->id(), 'organization_id' => $this->fk(), 'actor_id' => $this->fk(true), 'action' => $this->str(40), 'entity' => $this->str(40), 'entity_id' => $this->str(40),
            'entity_label' => $this->str(255, true), 'before_data' => ['type' => 'JSON', 'null' => true], 'after_data' => ['type' => 'JSON', 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true]]);
        $f->addKey('id', true);
        $f->addKey(['organization_id', 'created_at']);
        $f->addKey(['organization_id', 'entity', 'entity_id']);
        $f->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $f->addForeignKey('actor_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $f->createTable('audit_logs', true, $this->tableAttrs);
    }

    public function down(): void
    {
        foreach (['audit_logs', 'saved_views', 'custom_field_definitions', 'attachments', 'notes', 'activities', 'deal_line_items', 'products', 'deals', 'stages', 'pipelines', 'contacts', 'companies', 'tags', 'record_shares', 'team_members', 'teams', 'invites', 'users', 'organizations'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
