<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RememberTokenMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_migrations_create_remember_token_once(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'remember_token'));
        $columns = Schema::getColumnListing('users');
        $this->assertCount(1, array_keys($columns, 'remember_token', true));
    }
}
