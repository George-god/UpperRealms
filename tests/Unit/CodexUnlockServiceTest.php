<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CodexEntry;
use App\Models\User;
use App\Models\UserCodexEntry;
use App\Services\Codex\CodexCatalogSyncService;
use App\Services\Codex\CodexUnlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CodexUnlockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('codex_entries')) {
            Schema::create('codex_entries', function ($table) {
                $table->id();
                $table->string('category', 32);
                $table->string('entry_key', 120)->unique();
                $table->string('title', 160);
                $table->string('teaser', 500)->nullable();
                $table->text('body')->nullable();
                $table->string('icon', 16)->nullable();
                $table->string('source_type', 48)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('source_ref', 120)->nullable();
                $table->string('unlock_hint', 255)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_codex_entries')) {
            Schema::create('user_codex_entries', function ($table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->unsignedBigInteger('codex_entry_id');
                $table->timestamp('unlocked_at')->useCurrent();
                $table->timestamp('first_seen_at')->nullable();
                $table->boolean('is_new')->default(true);
                $table->timestamps();
            });
        }
    }

    public function test_unlocks_realm_entries_based_on_user_realm(): void
    {
        $user = User::factory()->create(['realm_id' => 1]);

        CodexEntry::query()->create([
            'category' => 'realms',
            'entry_key' => 'realm_1',
            'title' => 'Qi Refining',
            'source_type' => 'realm',
            'source_id' => 1,
            'sort_order' => 1,
        ]);

        $entryId = (int) CodexEntry::query()->first()->id;

        $service = app(CodexUnlockService::class);
        $result = $service->syncForUser((int) $user->id);

        $this->assertSame(1, $result['new_count']);
        $this->assertTrue(
            UserCodexEntry::query()->where('user_id', $user->id)->where('codex_entry_id', $entryId)->exists()
        );
    }
}
