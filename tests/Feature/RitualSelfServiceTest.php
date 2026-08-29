<?php

namespace Tests\Feature;

use App\Enums\RitualVisibilityScope;
use App\Models\Lodge;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\Person;
use App\Models\PersonRitualAvailability;
use App\Models\User;
use App\Services\RitualSelfService;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RitualSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->seed(PeopleMembershipReferenceSeeder::class); }

    public function test_only_a_linked_active_member_of_an_active_lodge_can_use_ritual_self_service(): void
    {
        $service = app(RitualSelfService::class);
        $unlinked = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        $service->personFor($unlinked);
    }

    public function test_settings_are_person_owned_and_default_to_hidden(): void
    {
        $user = $this->activeUser();
        $setting = app(RitualSelfService::class)->updateSettings($user, ['visibility_scope' => 'participating_lodges', 'public_availability_note' => ' Evenings ']);
        $this->assertSame(RitualVisibilityScope::ParticipatingLodges, $setting->visibility_scope);
        $this->assertSame('Evenings', $setting->public_availability_note);
    }

    public function test_active_member_can_open_personal_dashboard_but_unlinked_user_is_denied(): void
    {
        $this->actingAs($this->activeUser())->get('/ritual')->assertOk();
        $this->actingAs(User::factory()->create())->get('/ritual')->assertForbidden();
    }

    public function test_availability_replacement_is_transactional(): void
    {
        $user = $this->activeUser();
        $person = $user->person;
        PersonRitualAvailability::factory()->create(['person_id' => $person->id, 'day_of_week' => 1, 'daypart' => 'morning']);
        try {
            app(RitualSelfService::class)->replaceAvailability($user, [
                ['day_of_week' => 2, 'daypart' => 'afternoon'],
                ['day_of_week' => 2, 'daypart' => 'afternoon'],
            ]);
            $this->fail('Expected duplicate availability windows to fail.');
        } catch (QueryException) {
            $this->assertDatabaseHas('person_ritual_availabilities', ['person_id' => $person->id, 'day_of_week' => 1, 'daypart' => 'morning']);
        }
    }

    private function activeUser(): User
    {
        $person = Person::factory()->create();
        $lodge = Lodge::factory()->create();
        Membership::factory()->create(['person_id' => $person->id, 'lodge_id' => $lodge->id, 'membership_status_id' => MembershipStatus::query()->where('key', 'active')->sole()->id]);
        return User::factory()->create(['person_id' => $person->id]);
    }
}
