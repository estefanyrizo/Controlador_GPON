<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ISP;
use App\Models\OLT;
use App\Models\Status;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected Status $activeStatus;
    protected UserType $superadminType;
    protected UserType $mainProviderType;
    protected UserType $repType;
    protected ISP $ispOne;
    protected ISP $ispTwo;
    protected OLT $oltOne;
    protected OLT $oltTwo;
    protected Customer $customerIspOne;
    protected Customer $customerIspTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\CatalogsSeeder::class);

        $this->activeStatus = Status::where('code', 'active')->firstOrFail();
        $this->superadminType = UserType::where('code', 'superadmin')->firstOrFail();
        $this->mainProviderType = UserType::where('code', 'main_provider')->firstOrFail();
        $this->repType = UserType::where('code', 'isp_representative')->firstOrFail();

        $this->ispOne = ISP::create([
            'name' => 'ISP Uno',
            'description' => 'ISP principal',
            'status_id' => $this->activeStatus->id,
        ]);

        $this->ispTwo = ISP::create([
            'name' => 'ISP Dos',
            'description' => 'ISP secundario',
            'status_id' => $this->activeStatus->id,
        ]);

        $this->oltOne = OLT::create([
            'name' => 'OLT-1',
            'ip_olt' => '10.0.0.1',
            'status_id' => $this->activeStatus->id,
        ]);

        $this->oltTwo = OLT::create([
            'name' => 'OLT-2',
            'ip_olt' => '10.0.0.2',
            'status_id' => $this->activeStatus->id,
        ]);

        $this->oltOne->isps()->attach($this->ispOne->id, ['status_id' => $this->activeStatus->id]);
        $this->oltTwo->isps()->attach($this->ispTwo->id, ['status_id' => $this->activeStatus->id]);

        $this->customerIspOne = Customer::create([
            'customer_name' => 'Cliente ISP Uno',
            'isp_id' => $this->ispOne->id,
            'olt_id' => $this->oltOne->id,
            'gpon_interface' => '1/1/1:1',
            'service_number' => '001',
        ]);

        $this->customerIspTwo = Customer::create([
            'customer_name' => 'Cliente ISP Dos',
            'isp_id' => $this->ispTwo->id,
            'olt_id' => $this->oltTwo->id,
            'gpon_interface' => '1/1/1:2',
            'service_number' => '002',
        ]);
    }

    #[Test]
    public function isp_representative_only_sees_their_customers(): void
    {
        $rep = $this->createUser($this->repType, $this->ispOne);

        $response = $this->actingAs($rep, 'api')->getJson('/api/customers');

        $response->assertOk();
        $response->assertJsonMissing(['customer_name' => $this->customerIspTwo->customer_name]);
        $this->assertEquals(1, collect($response->json('customers'))->count());
    }

    #[Test]
    public function isp_representative_cannot_access_admin_routes(): void
    {
        $rep = $this->createUser($this->repType, $this->ispOne);

        $this->actingAs($rep, 'api')->getJson('/api/olts')->assertStatus(403);

        $this->actingAs($rep, 'api')->postJson('/api/customers', [
            'customer_name' => 'Nuevo Cliente',
            'gpon_interface' => '1/1/1:10',
            'olt_id' => $this->oltOne->id,
        ])->assertStatus(403);
    }

    #[Test]
    public function superadmin_can_manage_resources(): void
    {
        $superadmin = $this->createUser($this->superadminType);

        $this->actingAs($superadmin, 'api')->getJson('/api/olts')->assertOk();

        $this->actingAs($superadmin, 'api')->postJson('/api/customers', [
            'customer_name' => 'Cliente Admin',
            'gpon_interface' => '2/1/1:1',
            'olt_id' => $this->oltOne->id,
        ])->assertStatus(201);
    }

    protected function createUser(UserType $type, ?ISP $isp = null): User
    {
        return User::factory()->create([
            'username' => fake()->unique()->userName(),
            'user_type_id' => $type->id,
            'status_id' => $this->activeStatus->id,
            'isp_id' => $isp?->id,
        ]);
    }
}

