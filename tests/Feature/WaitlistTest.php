<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Waitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_can_join_waitlist()
    {
        $response = $this->postJson('/api/waitlist', [
            'name'         => 'Test Waitlister',
            'email'        => 'waitlist@example.com',
            'organization' => 'Worship Center',
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'You have been added to the waitlist.',
                 ]);

        $this->assertDatabaseHas('waitlist', [
            'email'        => 'waitlist@example.com',
            'name'         => 'Test Waitlister',
            'organization' => 'Worship Center',
            'status'       => 'pending',
        ]);
    }

    public function test_cannot_join_waitlist_with_existing_email()
    {
        Waitlist::create([
            'name'  => 'Existing Waitlister',
            'email' => 'waitlist@example.com',
        ]);

        $response = $this->postJson('/api/waitlist', [
            'name'  => 'Another Person',
            'email' => 'waitlist@example.com',
        ]);

        $response->assertStatus(409)
                 ->assertJson([
                     'success'        => false,
                     'already_exists' => true,
                 ]);
    }

    public function test_can_validate_invite_token()
    {
        $token = Str::random(64);
        $entry = Waitlist::create([
            'name'         => 'Approved User',
            'email'        => 'approved@example.com',
            'status'       => 'approved',
            'invite_token' => $token,
        ]);

        $response = $this->getJson("/api/waitlist/setup/{$token}");

        $response->assertStatus(200)
                 ->assertJson([
                     'valid' => true,
                     'name'  => 'Approved User',
                     'email' => 'approved@example.com',
                 ]);

        $entry->refresh();
        $this->assertNotNull($entry->invite_clicked_at);
        $this->assertNotNull($entry->invite_expires_at);
    }

    public function test_can_complete_account_setup()
    {
        $token = Str::random(64);
        $entry = Waitlist::create([
            'name'         => 'Approved User',
            'email'        => 'approved@example.com',
            'status'       => 'approved',
            'invite_token' => $token,
        ]);

        $response = $this->postJson("/api/waitlist/setup/{$token}", [
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Account created successfully.',
                 ]);

        // Assert user is created
        $this->assertDatabaseHas('users', [
            'email' => 'approved@example.com',
            'name'  => 'Approved User',
            'plan'  => 'pro',
        ]);

        $user = User::where('email', 'approved@example.com')->first();

        // Assert license is created
        $this->assertDatabaseHas('licenses', [
            'user_id'   => $user->id,
            'plan'      => 'pro',
            'is_active' => true,
        ]);

        // Assert waitlist entry is updated
        $entry->refresh();
        $this->assertEquals('registered', $entry->status);
        $this->assertNull($entry->invite_token);
        $this->assertNotNull($entry->registered_at);
    }

    public function test_admin_can_approve_waitlist_entry()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $entry = Waitlist::create([
            'name'  => 'Pending User',
            'email' => 'pending@example.com',
        ]);

        $response = $this->actingAs($admin)
                         ->postJson("/api/admin/waitlist/{$entry->id}/approve");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ]);

        $entry->refresh();
        $this->assertEquals('approved', $entry->status);
        $this->assertNotNull($entry->invite_token);
        $this->assertNotNull($entry->invite_sent_at);
    }
}
