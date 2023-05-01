<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Film;
use App\Models\User;
use App\Models\Booking;
use App\Models\Theatre;
use App\Models\Schedule;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('generate:schedules');
    }

    public function test_home_screen_can_be_rendered(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_users_cannot_make_bookings(): void
    {
        $user = User::factory()->create();

        $this->assertGuest();

        $response = $this->post('/bookings/create', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_create_booking_screen_can_be_rendered_when_authenticated(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(RouteServiceProvider::HOME);
        $this->assertAuthenticated();  
    }

    public function test_authenticated_user_can_make_a_booking(): void
    {
        $this->withoutExceptionHandling();
        $film = Film::factory()->create();
        $theatre = Theatre::factory()->create();
        $schedule = Schedule::factory()->create([
            'film_id' => $film->id,
            'starts_at' => now()->addHours(2),
            'theatre_id' => $theatre->id,
        ]);

        $oldNumOfBookings = Booking::all()->count();

        $this->actingAs(User::factory()->create());

        $response = $this->post('/bookings/create', [
            'scheduleId' => $schedule->id,
            'numOfTickets' => 1,
        ]);

        $newNumOfBookings = Booking::all()->count();

        $response->assertRedirect('/bookings/upcoming');
        $this->assertIsArray(session()->get('notification'));
        $this->assertEquals(session()->get('notification')['status'],'success');
        $this->assertStringContainsString('Booking successful! Your Reference number is', session()->get('notification')['message']);
        $this->assertEquals($oldNumOfBookings + 1, $newNumOfBookings);
    }

    public function test_authenticated_user_cannot_make_a_booking_for_a_past_schedule(): void
    {
        $film = Film::factory()->create();
        $theatre = Theatre::factory()->create();
        $schedule = Schedule::factory()->create([
            'film_id' => $film->id,
            'starts_at' => now()->subHours(2),
            'theatre_id' => $theatre->id,
        ]);

        $oldNumOfBookings = Booking::all()->count();

        $this->actingAs(User::factory()->create());

        $this->post('/bookings/create', [
            'scheduleId' => $schedule->id,
            'numOfTickets' => 1,
        ]);

        $this->assertIsArray(session()->get('notification'));
        $this->assertEquals(session()->get('notification')['status'],'failure');
        $this->assertEquals(session()->get('notification')['message'],'Cannot book a schedule that has already started.');

        $newNumOfBookings = Booking::all()->count();
        $this->assertEquals($oldNumOfBookings, $newNumOfBookings);
    }

    public function test_upcoming_schedules_screen_can_be_rendered_when_authenticated(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/bookings/upcoming');

        $response->assertStatus(200);
    }

    public function test_past_schedules_screen_can_be_rendered_when_authenticated(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/bookings/past');

        $response->assertStatus(200);
    }

    public function test_cancelled_schedules_screen_can_be_rendered_when_authenticated(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/bookings/cancelled');

        $response->assertStatus(200);
    }

    public function test_films_screen_can_be_rendered_when_authenticated(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_upcoming_booking_can_be_cancelled_up_to_an_hour_before(): void
    {
        $film = Film::factory()->create();
        $theatre = Theatre::factory()->create();
        $schedule = Schedule::factory()->create([
            'film_id' => $film->id,
            'starts_at' => now()->addHours(2),
            'theatre_id' => $theatre->id,
        ]);

        $this->actingAs(User::factory()->create());

        $this->post('/bookings/create', [
            'scheduleId' => $schedule->id,
            'numOfTickets' => 1,
        ]);

        $this->assertIsArray(session()->get('notification'));
        $this->assertEquals(session()->get('notification')['status'],'success');

        $booking = Booking::orderBy('id', 'DESC')->first();
        $oldBookings = Booking::all()->count();

        $response = $this->delete('/bookings/'.$booking->id.'/cancel');

        $newBookings = Booking::all()->count();
        $this->assertEquals($oldBookings - 1, $newBookings);
        $response->assertStatus(302);
        $this->assertIsArray(session()->get('notification'));
        $this->assertEquals(session()->get('notification')['status'],'success');
        $this->assertEquals(session()->get('notification')['message'],'Booking deleted successfully!');
    }

    public function test_upcoming_booking_cannot_be_cancelled_an_hour_or_less_before_schedule(): void
    {
        $film = Film::factory()->create();
        $theatre = Theatre::factory()->create();
        $schedule = Schedule::factory()->create([
            'film_id' => $film->id,
            'starts_at' => now()->addMinutes(15),
            'theatre_id' => $theatre->id,
        ]);

        $this->actingAs(User::factory()->create());

        $this->post('/bookings/create', [
            'scheduleId' => $schedule->id,
            'numOfTickets' => 1,
        ]);

        $this->assertIsArray(session()->get('notification'));
        $this->assertEquals(session()->get('notification')['status'],'success');

        $booking = Booking::orderBy('id', 'DESC')->first();
        $oldBookings = Booking::all()->count();

        $this->delete('/bookings/'.$booking->id.'/cancel');

        $newBookings = Booking::all()->count();
        $this->assertEquals($oldBookings, $newBookings);
        $this->assertIsArray(session()->get('notification'));
        $this->assertEquals(session()->get('notification')['status'],'failure');
        $this->assertEquals(session()->get('notification')['message'],'Bookings can only be cancelled up to an hour before film time!');
    }

    public function test_user_cannot_cancel_others_bookings(): void
    {
        $film = Film::factory()->create();
        $theatre = Theatre::factory()->create();
        $schedule = Schedule::factory()->create([
            'film_id' => $film->id,
            'starts_at' => now()->addMinutes(15),
            'theatre_id' => $theatre->id,
        ]);

        $this->actingAs(User::factory()->create());

        $this->post('/bookings/create', [
            'scheduleId' => $schedule->id,
            'numOfTickets' => 1,
        ]);

        $this->assertIsArray(session()->get('notification'));
        $this->assertEquals(session()->get('notification')['status'],'success');

        $booking = Booking::orderBy('id', 'DESC')->first();
        $oldBookings = Booking::all()->count();

        $this->actingAs(User::factory()->create());
        $this->delete('/bookings/'.$booking->id.'/cancel');

        $newBookings = Booking::all()->count();
        $this->assertEquals($oldBookings, $newBookings);
        $this->assertIsArray(session()->get('notification'));
        $this->assertEquals(session()->get('notification')['status'],'failure');
        $this->assertEquals(session()->get('notification')['message'],'You are not authorized to delete this booking!');
    }
}