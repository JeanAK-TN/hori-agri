<?php

use App\Models\Concerns\BelongsToCooperative;
use App\Models\Cooperative;
use App\Models\Scopes\CurrentCooperative;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Minimal business model used to exercise the isolation mechanism on its
 * own. The first real one (farmers) arrives with module 2.
 */
class ScopedResource extends Model
{
    use BelongsToCooperative;

    protected $table = 'scoped_resources';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('scoped_resources', function (Blueprint $table) {
        $table->id();
        $table->foreignId('cooperative_id')->constrained();
        $table->string('label');
    });

    CurrentCooperative::clear();
});

afterEach(function () {
    CurrentCooperative::clear();
    Schema::dropIfExists('scoped_resources');
});

test('the cooperative column is filled on creation without any help from the caller', function () {
    $cooperative = Cooperative::factory()->create();
    $technician = User::factory()->technician()->for($cooperative)->create();

    $this->actingAs($technician);

    $resource = ScopedResource::create(['label' => 'Sans cooperative_id explicite']);

    expect($resource->cooperative_id)->toBe($cooperative->id);
});

test('a query only returns the rows of the current cooperative', function () {
    $mine = Cooperative::factory()->create();
    $other = Cooperative::factory()->create();

    ScopedResource::create(['cooperative_id' => $mine->id, 'label' => 'À moi']);
    ScopedResource::create(['cooperative_id' => $other->id, 'label' => 'À une autre']);

    $this->actingAs(User::factory()->technician()->for($mine)->create());

    expect(ScopedResource::pluck('label')->all())->toBe(['À moi']);
});

test('a resource of another cooperative cannot be retrieved by its identifier', function () {
    $mine = Cooperative::factory()->create();
    $other = Cooperative::factory()->create();

    $foreign = ScopedResource::create(['cooperative_id' => $other->id, 'label' => 'À une autre']);

    $this->actingAs(User::factory()->technician()->for($mine)->create());

    expect(ScopedResource::find($foreign->id))->toBeNull();
});

test('the super_admin is not filtered', function () {
    $first = Cooperative::factory()->create();
    $second = Cooperative::factory()->create();

    ScopedResource::create(['cooperative_id' => $first->id, 'label' => 'Première']);
    ScopedResource::create(['cooperative_id' => $second->id, 'label' => 'Seconde']);

    $this->actingAs(User::factory()->superAdmin()->create());

    expect(ScopedResource::count())->toBe(2);
});

test('outside an HTTP context nothing is filtered until a cooperative is set', function () {
    $first = Cooperative::factory()->create();
    $second = Cooperative::factory()->create();

    ScopedResource::create(['cooperative_id' => $first->id, 'label' => 'Première']);
    ScopedResource::create(['cooperative_id' => $second->id, 'label' => 'Seconde']);

    expect(ScopedResource::count())->toBe(2);

    CurrentCooperative::set($second->id);

    expect(ScopedResource::pluck('label')->all())->toBe(['Seconde']);
});

test('an explicitly set cooperative is used for creation in a background context', function () {
    $cooperative = Cooperative::factory()->create();

    CurrentCooperative::set($cooperative->id);

    $resource = ScopedResource::create(['label' => 'Traitement en arrière-plan']);

    expect($resource->cooperative_id)->toBe($cooperative->id);
});
