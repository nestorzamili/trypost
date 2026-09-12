<?php

declare(strict_types=1);

use App\Broadcasting\UserAiCaptionChannel;
use App\Models\User;

test('user can join their own caption channel', function () {
    $user = User::factory()->create();
    $channel = new UserAiCaptionChannel;

    expect($channel->join($user, $user, 'some-uuid'))->toBeTrue();
});

test('user cannot join another users caption channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $channel = new UserAiCaptionChannel;

    expect($channel->join($user, $other, 'some-uuid'))->toBeFalse();
});
