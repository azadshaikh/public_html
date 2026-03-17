<?php

namespace Tests\Feature;

use Tests\TestCase;

class ScaffoldDoctorCommandTest extends TestCase
{
    public function test_scaffold_doctor_passes_for_current_application_state(): void
    {
        $this->artisan('scaffold:doctor')
            ->expectsOutputToContain('Scaffold doctor passed')
            ->assertSuccessful();
    }
}
