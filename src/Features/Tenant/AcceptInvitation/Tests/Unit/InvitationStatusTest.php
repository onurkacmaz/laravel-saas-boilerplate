<?php

use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('evaluates invitation status helpers', function (): void {
    $pending = Invitation::factory()->pending()->create();
    expect($pending->isPending())->toBeTrue();
    expect($pending->canBeAccepted())->toBeTrue();
    expect($pending->isExpired())->toBeFalse();

    $expired = Invitation::factory()->expired()->create();
    expect($expired->isExpired())->toBeTrue();
    expect($expired->canBeAccepted())->toBeFalse();

    $accepted = Invitation::factory()->accepted()->create();
    expect($accepted->isAccepted())->toBeTrue();
    expect($accepted->isPending())->toBeFalse();

    $rejected = Invitation::factory()->rejected()->create();
    expect($rejected->isRejected())->toBeTrue();

    $canceled = Invitation::factory()->canceled()->create();
    expect($canceled->isCanceled())->toBeTrue();
});
