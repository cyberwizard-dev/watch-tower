<?php

use App\Models\Project;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $this->withMiddleware();
});

it('rejects agent-auth without a bearer token', function () {
    $this->postJson('/api/agent-auth', [])
        ->assertStatus(401)
        ->assertJson([
            'message' => 'Missing environment token',
            'stop' => true,
        ]);
});

it('rejects agent-auth with an unknown token', function () {
    $this->withHeader('Authorization', 'Bearer pk_unknown')
        ->postJson('/api/agent-auth', [])
        ->assertStatus(401)
        ->assertJson([
            'message' => 'Invalid environment token',
            'stop' => true,
        ]);
});

it('issues a short-lived ingest token for a known project', function () {
    $project = Project::factory()->withApiKey('pk_test_known')->create();

    $response = $this->withHeader('Authorization', 'Bearer pk_test_known')
        ->postJson('/api/agent-auth', []);

    $response->assertOk()
        ->assertJsonStructure(['token', 'expires_in', 'refresh_in', 'ingest_url']);

    $token = $response->json('token');
    $payload = json_decode(Crypt::decryptString($token), true);

    expect($payload['project_id'])->toBe($project->id);
    expect($payload['expires_at'])->toBeGreaterThan(time());
    expect($response->json('ingest_url'))->toBe(route('api.nightwatch.ingest'));
});
