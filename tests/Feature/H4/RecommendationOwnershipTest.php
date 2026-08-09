<?php

use App\Enums\RecoStatus;
use App\Models\Campaign;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farmer;
use App\Models\Feedback;
use App\Models\Plot;
use App\Models\Recommendation;
use App\Models\Rule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Hypothèse H4 sur la restitution (SPEC section 14.2).
|
| Deux frontières se distinguent. Entre coopératives, la ressource
| n'existe pas : c'est un 404, jamais un refus, qui trahirait son
| existence. À l'intérieur d'une même coopérative, la recommandation d'un
| autre producteur existe bien mais ne le regarde pas : c'est un 403.
*/

function cycleOfFarmer(Cooperative $cooperative, Farmer $farmer): CropCycle
{
    $crop = Crop::factory()->create();
    $campaign = Campaign::factory()->for($cooperative)
        ->between('2026-03-01', '2026-11-30')->create();
    $plot = Plot::factory()->ofFarmer($farmer)->withArea(1.0)->create();

    return CropCycle::factory()->on($plot, $campaign, $crop)->create();
}

function recommendationOf(Cooperative $cooperative, Farmer $farmer): Recommendation
{
    return Recommendation::factory()
        ->forCycle(cycleOfFarmer($cooperative, $farmer))
        ->fromRule(Rule::factory()->create())
        ->create();
}

beforeEach(function () {
    $this->cooperative = Cooperative::factory()->create();

    $this->account = User::factory()->farmer()->for($this->cooperative)->create();
    $this->farmer = Farmer::factory()->for($this->cooperative)
        ->create(['user_id' => $this->account->id]);

    $this->neighbourAccount = User::factory()->farmer()->for($this->cooperative)->create();
    $this->neighbour = Farmer::factory()->for($this->cooperative)
        ->create(['user_id' => $this->neighbourAccount->id]);
});

test('a producer only sees the recommendations of their own plots', function () {
    recommendationOf($this->cooperative, $this->farmer);
    recommendationOf($this->cooperative, $this->neighbour);

    $this->actingAs($this->account)
        ->get(route('recommendations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recommendations.data', 1)
            ->where('recommendations.data.0.cycle.farmer', $this->farmer->fullName()),
        );
});

test('a technician sees the recommendations of every producer they follow', function () {
    recommendationOf($this->cooperative, $this->farmer);
    recommendationOf($this->cooperative, $this->neighbour);

    $this->actingAs(User::factory()->technician()->for($this->cooperative)->create())
        ->get(route('recommendations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('recommendations.data', 2));
});

/*
| À l'intérieur de la coopérative : la recommandation existe, elle ne le
| regarde pas. 403.
*/

test('a producer cannot record a feedback on the recommendation of another', function () {
    $theirs = recommendationOf($this->cooperative, $this->neighbour);

    $this->actingAs($this->account)
        ->post(route('feedbacks.store', $theirs), ['applied' => true])
        ->assertForbidden();

    expect(Feedback::withoutGlobalScopes()->count())->toBe(0);
});

test('a producer cannot set aside the recommendation of another', function () {
    $theirs = recommendationOf($this->cooperative, $this->neighbour);

    $this->actingAs($this->account)
        ->patch(route('recommendations.update', $theirs), [
            'status' => RecoStatus::Dismissed->value,
        ])
        ->assertForbidden();

    expect($theirs->fresh()->status)->toBe(RecoStatus::New);
});

test('a technician of the cooperative does not answer in the place of the producer', function () {
    $recommendation = recommendationOf($this->cooperative, $this->farmer);

    $this->actingAs(User::factory()->technician()->for($this->cooperative)->create())
        ->post(route('feedbacks.store', $recommendation), ['applied' => true])
        ->assertForbidden();
});

/*
| Entre coopératives : la ressource n'existe pas. 404.
*/

test('a recommendation of another cooperative answers not found, not forbidden', function () {
    $otherCooperative = Cooperative::factory()->create();
    $stranger = Farmer::factory()->for($otherCooperative)->create();
    $foreign = recommendationOf($otherCooperative, $stranger);

    $this->actingAs($this->account)
        ->patch(route('recommendations.update', $foreign), [
            'status' => RecoStatus::Dismissed->value,
        ])
        ->assertNotFound();
});

test('a feedback on the recommendation of another cooperative answers not found', function () {
    $otherCooperative = Cooperative::factory()->create();
    $stranger = Farmer::factory()->for($otherCooperative)->create();
    $foreign = recommendationOf($otherCooperative, $stranger);

    $this->actingAs($this->account)
        ->post(route('feedbacks.store', $foreign), ['applied' => true])
        ->assertNotFound();

    expect(Feedback::withoutGlobalScopes()->count())->toBe(0);
});

test('the feedbacks of a cooperative stay out of reach of the next one', function () {
    $recommendation = recommendationOf($this->cooperative, $this->farmer);
    Feedback::factory()->on($recommendation)->create();

    $otherCooperative = Cooperative::factory()->create();
    $stranger = Farmer::factory()->for($otherCooperative)->create();
    $theirRecommendation = recommendationOf($otherCooperative, $stranger);
    Feedback::factory()->on($theirRecommendation)->create();

    $this->actingAs($this->account);

    expect(Feedback::count())->toBe(1)
        ->and(Feedback::first()->cooperative_id)->toBe($this->cooperative->id);
});

test('a producer with no producer sheet sees nothing rather than everything', function () {
    recommendationOf($this->cooperative, $this->farmer);

    $orphan = User::factory()->farmer()->for($this->cooperative)->create();

    $this->actingAs($orphan)
        ->get(route('recommendations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('recommendations.data', 0));
});
