<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL redirects to the calendar (guests then bounce to login).
     */
    public function test_the_root_url_redirects_to_the_calendar(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/calendar');
    }
}
