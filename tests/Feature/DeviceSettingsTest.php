<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Номер SIM и скорость отправки задаёт человек в панели: телефон свой номер
 * прочитать не может, а сколько он потянет — знает только оператор.
 */
class DeviceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_sets_the_sim_number_and_the_throughput(): void
    {
        $project = Project::factory()->create();
        $device = Device::factory()->for($project)->create();

        $this->actingAs($project->user)
            ->patch(route('projects.devices.update', [$project, $device]), [
                'name' => 'Redmi — касса',
                'phone_number' => '+993 65 000001',
                'throughput_per_minute' => 20,
            ])
            ->assertRedirect(route('projects.devices.index', $project));

        $device->refresh();

        $this->assertSame('Redmi — касса', $device->name);
        $this->assertSame('+99365000001', $device->phone_number);
        $this->assertSame(20, $device->throughput_per_minute);
    }

    public function test_two_phones_in_one_project_cannot_share_a_number(): void
    {
        $project = Project::factory()->create();
        Device::factory()->for($project)->create(['phone_number' => '+99365000001']);
        $second = Device::factory()->for($project)->create();

        $this->actingAs($project->user)
            ->patch(route('projects.devices.update', [$project, $second]), [
                'name' => $second->name,
                'phone_number' => '+99365000001',
                'throughput_per_minute' => 10,
            ])
            ->assertSessionHasErrors('phone_number');

        $this->assertNull($second->fresh()->phone_number);
    }

    public function test_the_same_number_may_serve_another_project(): void
    {
        // Одна и та же SIM может быть переставлена в телефон другого проекта —
        // уникальность ограничена проектом, а не всей базой.
        Device::factory()->create(['phone_number' => '+99365000001']);

        $project = Project::factory()->create();
        $device = Device::factory()->for($project)->create();

        $this->actingAs($project->user)
            ->patch(route('projects.devices.update', [$project, $device]), [
                'name' => $device->name,
                'phone_number' => '+99365000001',
                'throughput_per_minute' => 10,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_the_throughput_stays_inside_what_a_handset_can_do(): void
    {
        $project = Project::factory()->create();
        $device = Device::factory()->for($project)->create();

        foreach ([0, Device::MAX_THROUGHPUT_PER_MINUTE + 1] as $value) {
            $this->actingAs($project->user)
                ->patch(route('projects.devices.update', [$project, $device]), [
                    'name' => $device->name,
                    'throughput_per_minute' => $value,
                ])
                ->assertSessionHasErrors('throughput_per_minute');
        }
    }

    public function test_a_stranger_cannot_touch_someone_elses_phone(): void
    {
        $project = Project::factory()->create();
        $device = Device::factory()->for($project)->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('projects.devices.update', [$project, $device]), [
                'name' => 'Перехвачено',
                'throughput_per_minute' => 60,
            ])
            ->assertForbidden();

        $this->assertNotSame('Перехвачено', $device->fresh()->name);
    }

    public function test_a_phone_from_another_project_is_not_reachable_through_this_one(): void
    {
        $project = Project::factory()->create();
        $foreignDevice = Device::factory()->create();

        $this->actingAs($project->user)
            ->patch(route('projects.devices.update', [$project, $foreignDevice]), [
                'name' => 'Перехвачено',
                'throughput_per_minute' => 10,
            ])
            ->assertNotFound();
    }
}
