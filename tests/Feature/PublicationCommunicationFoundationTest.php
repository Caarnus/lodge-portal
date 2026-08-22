<?php

namespace Tests\Feature;

use App\Enums\ContentVersionStatus;
use App\Enums\GalleryVisibility;
use App\Enums\LodgeCommunicationStatus;
use App\Models\CommunicationDelivery;
use App\Models\FamilyNewsletterSubscription;
use App\Models\GalleryAlbumPhoto;
use App\Models\Lodge;
use App\Models\LodgeCommunication;
use App\Models\Membership;
use App\Models\MembershipStatus;
use App\Models\NewsletterIssueVersion;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Services\LodgeRoleCatalog;
use App\Services\PersonMergeService;
use Database\Seeders\PeopleMembershipReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationCommunicationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeopleMembershipReferenceSeeder::class);
    }

    public function test_membership_preference_defaults_newsletter_print_delivery_to_false(): void
    {
        $membership = Membership::factory()->create([
            'membership_status_id' => MembershipStatus::query()->where('key', 'active')->sole()->id,
        ]);

        $preference = $membership->communicationPreference()->sole();

        $this->assertTrue($preference->receives_lodge_email);
        $this->assertFalse($preference->receives_print_newsletter);
    }

    public function test_publication_factories_keep_lodge_ownership_consistent(): void
    {
        $newsletter = NewsletterIssueVersion::factory()->create();
        $photo = GalleryAlbumPhoto::factory()->create();
        $delivery = CommunicationDelivery::factory()->create();

        $this->assertSame($newsletter->lodge_id, $newsletter->issue->lodge_id);
        $this->assertSame($photo->lodge_id, $photo->version->lodge_id);
        $this->assertSame($photo->lodge_id, $photo->mediaAsset->lodge_id);
        $this->assertSame($delivery->lodge_id, $delivery->run->lodge_id);
        $this->assertSame($delivery->lodge_id, $delivery->familyNewsletterSubscription->lodge_id);
    }

    public function test_publication_models_cast_statuses_and_visibility(): void
    {
        $newsletter = NewsletterIssueVersion::factory()->published()->create();
        $communication = LodgeCommunication::factory()->create();
        $photo = GalleryAlbumPhoto::factory()->create();

        $this->assertSame(ContentVersionStatus::Published, $newsletter->status);
        $this->assertInstanceOf(LodgeCommunicationStatus::class, $communication->status);
        $this->assertSame(GalleryVisibility::Public, $photo->version->visibility);
    }

    public function test_role_catalog_adds_publication_permissions_without_changing_custom_roles(): void
    {
        $lodge = Lodge::factory()->create();
        $custom = Role::create(['lodge_id' => $lodge->id, 'name' => 'Newsletter steward']);
        app(LodgeRoleCatalog::class)->seedPermissions();
        $custom->permissions()->attach(Permission::query()->where('key', 'people.manage')->sole());

        app(LodgeRoleCatalog::class)->ensureFor($lodge);
        app(LodgeRoleCatalog::class)->ensureFor($lodge);

        $administrator = Role::query()->where('lodge_id', $lodge->id)->where('name', 'Administrator')->sole();
        $officer = Role::query()->where('lodge_id', $lodge->id)->where('name', 'Officer')->sole();

        foreach (['newsletters.manage', 'newsletters.publish', 'galleries.manage', 'galleries.publish', 'communications.send', 'communications.settings', 'communications.recipients'] as $permission) {
            $this->assertContains($permission, $administrator->permissions()->pluck('key')->all());
        }
        $this->assertContains('communications.send', $officer->permissions()->pluck('key')->all());
        $this->assertSame(['people.manage'], $custom->fresh()->permissions()->pluck('key')->all());
    }

    public function test_publication_permissions_are_available_after_migration(): void
    {
        $this->assertSame(7, Permission::query()->whereIn('key', [
            'newsletters.manage',
            'newsletters.publish',
            'galleries.manage',
            'galleries.publish',
            'communications.send',
            'communications.settings',
            'communications.recipients',
        ])->count());
    }

    public function test_merge_moves_a_nonconflicting_family_newsletter_subscription(): void
    {
        $lodgeA = Lodge::factory()->create();
        $lodgeB = Lodge::factory()->create();
        $activeStatusId = MembershipStatus::query()->where('key', 'active')->sole()->id;
        $source = Membership::factory()->create(['lodge_id' => $lodgeA->id, 'membership_status_id' => $activeStatusId])->person;
        $survivor = Membership::factory()->create(['lodge_id' => $lodgeB->id, 'membership_status_id' => $activeStatusId])->person;
        $relative = Person::factory()->create();
        $relationship = PersonRelationship::create([
            'owning_lodge_id' => $lodgeA->id,
            'person_one_id' => $source->id,
            'person_two_id' => $relative->id,
            'relationship_type_id' => RelationshipType::query()->where('key', 'spouse')->sole()->id,
        ]);
        $subscription = FamilyNewsletterSubscription::create([
            'lodge_id' => $lodgeA->id,
            'recipient_person_id' => $relative->id,
            'sponsoring_person_id' => $source->id,
            'person_relationship_id' => $relationship->id,
            'receives_email' => true,
            'consent_source' => 'test',
            'status' => 'active',
        ]);

        app(PersonMergeService::class)->merge($source, $survivor);

        $this->assertDatabaseHas('family_newsletter_subscriptions', [
            'id' => $subscription->id,
            'sponsoring_person_id' => $survivor->id,
            'person_relationship_id' => $relationship->id,
        ]);
        $this->assertDatabaseHas('person_relationships', ['id' => $relationship->id, 'person_one_id' => $survivor->id]);
    }
}
