<?php

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use App\Models\ProfileSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('guest is redirected to login', function () {
    $this->get(route('watchlist.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can view watchlist index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('watchlist.index'))
        ->assertStatus(200);
});

test('user can search watchlisted profiles', function () {
    $user = User::factory()->create();
    Profile::factory()->create(['username' => 'elonmusk']);
    Profile::factory()->create(['username' => 'jeffbezos']);

    $response = $this->actingAs($user)
        ->get(route('watchlist.index', ['q' => 'elon']))
        ->assertStatus(200);

    $response->assertInertia(fn ($page) => $page
        ->component('Watchlist/Index')
        ->has('profiles.data', 1)
        ->where('profiles.data.0.username', 'elonmusk')
    );
});

test('user can add a profile, which normalizes handle and dispatches job', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('watchlist.store'), [
            'username' => '  @MarkZuckerberg  ',
        ])
        ->assertRedirect(route('watchlist.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('profiles', [
        'username' => 'markzuckerberg',
        'status' => 'pending',
    ]);

    Queue::assertPushed(FetchProfileJob::class, function ($job) {
        return Profile::find($job->profileId)->username === 'markzuckerberg';
    });
});

test('user can add a youtube profile with hyphens, which normalizes handle and dispatches job', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('watchlist.store'), [
            'username' => '  @Mr-Beast  ',
            'platform' => 'youtube',
        ])
        ->assertRedirect(route('watchlist.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('profiles', [
        'username' => 'mr-beast',
        'platform' => 'youtube',
        'status' => 'pending',
    ]);

    Queue::assertPushed(FetchProfileJob::class, function ($job) {
        return Profile::find($job->profileId)->username === 'mr-beast';
    });
});

test('user can add the same username handle on different platforms but not duplicate on the same platform', function () {
    Queue::fake();
    $user = User::factory()->create();

    // Add Taylorswift to Instagram
    $this->actingAs($user)
        ->post(route('watchlist.store'), [
            'username' => 'taylorswift',
            'platform' => 'instagram',
        ])
        ->assertRedirect(route('watchlist.index'));

    $this->assertDatabaseHas('profiles', [
        'username' => 'taylorswift',
        'platform' => 'instagram',
    ]);

    // Add Taylorswift to YouTube
    $this->actingAs($user)
        ->post(route('watchlist.store'), [
            'username' => 'taylorswift',
            'platform' => 'youtube',
        ])
        ->assertRedirect(route('watchlist.index'));

    $this->assertDatabaseHas('profiles', [
        'username' => 'taylorswift',
        'platform' => 'youtube',
    ]);

    // Add Taylorswift to Instagram again (should fail validation)
    $response = $this->actingAs($user)
        ->post(route('watchlist.store'), [
            'username' => 'taylorswift',
            'platform' => 'instagram',
        ]);

    $response->assertSessionHasErrors(['username']);
});

test('user can view profile details and snapshots with delta calculation', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->create(['username' => 'taylorswift']);

    // Create snapshots in chronological order
    ProfileSnapshot::create([
        'profile_id' => $profile->id,
        'followers_count' => 100,
        'following_count' => 10,
        'posts_count' => 5,
        'created_at' => now()->subHours(2),
    ]);

    ProfileSnapshot::create([
        'profile_id' => $profile->id,
        'followers_count' => 120,
        'following_count' => 10,
        'posts_count' => 5,
        'created_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('watchlist.show', $profile))
        ->assertStatus(200);

    $response->assertInertia(fn ($page) => $page
        ->component('Watchlist/Show')
        ->where('profile.username', 'taylorswift')
        ->has('snapshots.data', 2)
        ->where('snapshots.data.0.followers_delta', 20)
        ->where('snapshots.data.1.followers_delta', 0)
    );
});

test('user can manually trigger a profile refresh', function () {
    Queue::fake();
    $user = User::factory()->create();
    $profile = Profile::factory()->create(['username' => 'nike', 'status' => 'fetched']);

    $this->actingAs($user)
        ->post(route('watchlist.refetch', $profile))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertEquals('pending', $profile->fresh()->status);
    Queue::assertPushed(FetchProfileJob::class, function ($job) use ($profile) {
        return $job->profileId === $profile->id;
    });
});

test('profile image proxy works and streams successful response', function () {
    $user = User::factory()->create();

    Http::fake([
        'https://example.com/avatar.jpg' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $response = $this->actingAs($user)
        ->get(route('profile-image-proxy', ['url' => 'https://example.com/avatar.jpg']))
        ->assertStatus(200);

    $this->assertEquals('fake-image-bytes', $response->getContent());
    $response->assertHeader('Content-Type', 'image/jpeg');
    $response->assertHeader('Cache-Control', 'max-age=86400, public');
});

test('user can delete a profile from watchlist', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->create(['username' => 'test_delete_user']);

    $this->assertDatabaseHas('profiles', [
        'id' => $profile->id,
    ]);

    $this->actingAs($user)
        ->delete(route('watchlist.destroy', $profile))
        ->assertRedirect(route('watchlist.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('profiles', [
        'id' => $profile->id,
    ]);
});
