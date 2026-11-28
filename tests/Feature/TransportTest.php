<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\StudentFee;
use App\Models\Transport\FuelEntry;
use App\Models\Transport\RouteChange;
use App\Models\Transport\StaffSalary;
use App\Models\Transport\TransportRoute;
use App\Models\Transport\TransportStaff;
use App\Models\Transport\TransportTrip;
use App\Models\Transport\Vehicle;
use App\Models\Transport\VehicleMaintenance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TransportTest extends SchoolTestCase
{
    /** Builds a bus, a driver, a conductor and a route with two stops (as the transport manager). */
    private function setUpFleet(): array
    {
        $this->actingAs($this->user('transport'));
        $this->post(route('transport.vehicles.store'), ['bus_no' => 'Bus 1', 'registration_no' => 'up32 ab 1234', 'make' => 'Tata', 'model' => 'Starbus', 'seats' => 2,
            'fuel_type' => 'diesel', 'ownership' => 'owned', 'monthly_emi' => 15000, 'insurance_expiry' => now()->addDays(10)->toDateString(),
            'puc_expiry' => now()->subDay()->toDateString(), 'odometer' => 10000, 'service_interval_km' => 5000])->assertSessionHasNoErrors();
        $bus = Vehicle::firstOrFail();
        $this->assertSame('UP32 AB 1234', $bus->registration_no);

        $this->post(route('transport.staff.store'), ['type' => 'driver', 'name' => 'Ramesh Driver', 'phone' => '9876500001', 'licence_no' => 'UP3220100012345',
            'licence_expiry' => now()->addDays(20)->toDateString(), 'verification_status' => 'verified', 'vehicle_id' => $bus->id, 'monthly_salary' => 12000])->assertSessionHasNoErrors();
        $this->post(route('transport.staff.store'), ['type' => 'conductor', 'name' => 'Suresh Conductor', 'verification_status' => 'pending', 'vehicle_id' => $bus->id, 'monthly_salary' => 8000])->assertSessionHasNoErrors();
        $driver = TransportStaff::drivers()->firstOrFail();
        $conductor = TransportStaff::conductors()->firstOrFail();
        $this->assertSame('DRV-001', $driver->employee_id);
        $this->assertSame('CON-001', $conductor->employee_id);

        $this->post(route('transport.routes.store'), ['name' => 'Gomti Nagar', 'route_no' => 'R1', 'shift' => 'both', 'trip_no' => 1, 'distance_km' => 12,
            'vehicle_id' => $bus->id, 'driver_id' => $driver->id, 'conductor_id' => $conductor->id, 'monthly_fee' => 1000])->assertSessionHasNoErrors();
        $route = TransportRoute::firstOrFail();
        $this->post(route('transport.stops.store', $route), ['name' => 'Near stop', 'distance_km' => 3, 'pickup_time' => '07:40'])->assertSessionHasNoErrors();
        $this->post(route('transport.stops.store', $route), ['name' => 'Far stop', 'distance_km' => 10, 'monthly_fee' => 1500, 'pickup_time' => '07:10'])->assertSessionHasNoErrors();
        $this->post(route('transport.stops.sort', $route))->assertSessionHas('success');
        $this->assertSame(['Far stop', 'Near stop'], $route->stops()->pluck('name')->all());

        return [$bus, $driver, $conductor, $route->fresh('stops')];
    }

    public function test_students_on_a_route_get_monthly_transport_fee_and_it_counts_as_route_income(): void
    {
        $student = $this->admit();
        [$bus, , , $route] = $this->setUpFleet();
        $far = $route->stops->firstWhere('name', 'Far stop');
        $from = now()->startOfMonth();

        $this->post(route('transport.routes.students', $route), ['student_ids' => [$student->id], 'route_stop_id' => $far->id, 'from' => $from->toDateString()])
            ->assertSessionHas('success');
        $student->refresh();
        $this->assertSame($route->id, $student->transport_route_id);
        $this->assertEquals(1500, $student->transport_fee, 'stop fee wins over route fee');

        $dues = StudentFee::where('student_id', $student->id)->whereNotNull('transport_route_id')->get();
        $session = \App\Models\AcademicSession::current();
        $expected = collect($session->months())->filter(fn ($m) => $m->gte($from))->count();
        $this->assertCount($expected, $dues);
        $this->assertTrue($dues->every(fn ($d) => (float) $d->amount === 1500.0));

        // change fee from next month: future unpaid dues are replaced, not duplicated
        $this->put(route('transport.student.update', $student), ['transport_route_id' => $route->id, 'route_stop_id' => $far->id, 'transport_fee' => 1200,
            'from' => $from->copy()->addMonth()->toDateString()])->assertSessionHas('success');
        $dues = StudentFee::where('student_id', $student->id)->whereNotNull('transport_route_id')->orderBy('due_date')->get();
        $this->assertCount($expected, $dues);
        $this->assertEquals(1500, $dues->first()->amount);
        if ($dues->count() > 1) {
            $this->assertEquals(1200, $dues->last()->amount);
        }

        // accounts collect the first transport fee → route & bus income
        $first = $dues->first();
        $this->actingAs($this->user('accountant'))->post(route('payments.store', $student), [
            'lines' => [$first->id => ['amount' => 1500]], 'payment_date' => now()->toDateString(), 'method' => 'cash'])->assertSessionHas('success');
        $svc = app(\App\Services\TransportService::class);
        $this->assertEquals(1500, $svc->incomeByRoute(now()->startOfMonth(), now())[$route->id]);
        $this->assertEquals(1500, $svc->vehicleFinance(now()->startOfMonth(), now())[$bus->id]['income']);

        // stopping transport removes only future unpaid dues
        $this->actingAs($this->user('transport'))->put(route('transport.student.update', $student), ['remove' => 1, 'from' => $from->copy()->addMonth()->toDateString()])
            ->assertSessionHas('success');
        $this->assertNull($student->fresh()->transport_route_id);
        $this->assertSame(1, StudentFee::where('student_id', $student->id)->whereNotNull('transport_route_id')->count());
    }

    public function test_costs_profit_and_cost_per_km(): void
    {
        $student = $this->admit();
        [$bus, $driver, $conductor, $route] = $this->setUpFleet();
        $this->post(route('transport.routes.students', $route), ['student_ids' => [$student->id], 'from' => now()->startOfMonth()->toDateString()]);

        // fuel with bill upload
        Storage::fake('local');
        $this->post(route('transport.fuel.store'), ['vehicle_id' => $bus->id, 'entry_date' => now()->toDateString(), 'fuel_type' => 'diesel', 'quantity' => 50, 'rate' => 90,
            'odometer' => 10100, 'full_tank' => 1, 'bill' => UploadedFile::fake()->image('bill.jpg')])->assertSessionHasNoErrors();
        $fuel = FuelEntry::firstOrFail();
        $this->assertEquals(4500, $fuel->amount);
        $this->get(route('transport.fuel.bill', $fuel))->assertOk();

        // daily trips: 100 km
        $this->post(route('transport.trips.store'), ['trip_date' => now()->toDateString(), 'shift' => 'morning', 'trips' => [
            ['save' => 1, 'vehicle_id' => $bus->id, 'transport_route_id' => $route->id, 'driver_id' => $driver->id, 'conductor_id' => $conductor->id, 'start_km' => 10100, 'end_km' => 10200],
        ]])->assertSessionHas('success');
        $this->assertSame(100, TransportTrip::firstOrFail()->km());

        // repair, EMI, salary
        $this->post(route('transport.maintenance.store'), ['vehicle_id' => $bus->id, 'service_date' => now()->toDateString(), 'type' => 'service', 'title' => 'Oil change',
            'status' => 'done', 'odometer' => 10200, 'parts_cost' => 1500, 'labour_cost' => 500])->assertSessionHasNoErrors();
        $this->assertSame(15200, VehicleMaintenance::firstOrFail()->next_due_km, 'next service from the 5000 km interval');
        $this->post(route('transport.expenses.recurring'), ['month' => now()->format('Y-m')])->assertSessionHas('success');
        $this->actingAs($this->user('accountant'))->post(route('transport.salaries.store'), ['month' => now()->format('Y-m'), 'paid_on' => now()->toDateString(), 'method' => 'cash',
            'pay' => [$driver->id => ['save' => 1, 'amount' => 12000, 'deduction' => 400], $conductor->id => ['amount' => 8000]]])->assertSessionHas('success');
        $this->assertSame(1, StaffSalary::count(), 'only ticked people are paid');

        $row = app(\App\Services\TransportService::class)->vehicleFinance(now()->startOfMonth(), now()->endOfMonth())[$bus->id];
        $this->assertEquals(4500, $row['fuel']);
        $this->assertEquals(2000, $row['maintenance']);
        $this->assertEquals(15000, $row['emi']);
        $this->assertEquals(11600, $row['salary']);
        $this->assertEquals(33100, $row['cost']);
        $this->assertSame(100, $row['km']);
        $this->assertEquals(331, $row['cost_per_km']);
        $this->assertEquals(-33100, $row['profit']);

        // every report renders, and exports
        foreach (array_keys(\App\Http\Controllers\Transport\ReportController::REPORTS) as $type) {
            $this->get(route('transport.reports.show', $type))->assertOk();
        }
        $this->get(route('transport.reports.show', ['vehicles', 'format' => 'xlsx']))->assertOk()->assertHeader('Content-Disposition');
        $this->get(route('transport.reports.show', ['monthly', 'format' => 'pdf']))->assertOk();
        $this->get(route('transport.reports.show', 'vehicles'))->assertSee('Bus 1')->assertSee('33,100.00');
    }

    public function test_reminders_leave_substitute_and_temporary_change(): void
    {
        [$bus, $driver, , $route] = $this->setUpFleet();
        $this->post(route('transport.staff.store'), ['type' => 'driver', 'name' => 'Spare Driver', 'licence_no' => 'X1', 'licence_expiry' => now()->addYears(3)->toDateString(), 'verification_status' => 'verified']);
        $spare = TransportStaff::where('name', 'Spare Driver')->firstOrFail();

        $res = $this->get(route('transport.dashboard'))->assertOk();
        $res->assertSee('Insurance')->assertSee('Pollution (PUC)')->assertSee('Driving licence')->assertSee('expired');

        // approved leave with substitute → route change for those days + attendance marked
        $this->post(route('transport.leaves.store'), ['transport_staff_id' => $driver->id, 'from_date' => now()->toDateString(), 'to_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Wedding', 'status' => 'approved', 'substitute_id' => $spare->id])->assertSessionHas('success');
        $change = RouteChange::firstOrFail();
        $this->assertSame($spare->id, $change->driver_id);
        $this->assertSame($spare->id, $route->changeOn(now())->driver_id);
        $this->assertSame('leave', $driver->attendances()->first()->status);

        // trip sheet suggests the substitute
        $this->get(route('transport.trips.index', ['shift' => 'morning']))->assertOk()->assertSee('changed today');

        // attendance
        $this->post(route('transport.attendance.store'), ['date' => now()->toDateString(), 'att' => [$spare->id => ['status' => 'present', 'in_time' => '06:30']]])->assertSessionHas('success');
        $this->get(route('transport.attendance.monthly'))->assertOk()->assertSee('Spare Driver');

        // breakdown shows as off road
        $this->post(route('transport.maintenance.store'), ['vehicle_id' => $bus->id, 'service_date' => now()->toDateString(), 'type' => 'breakdown', 'title' => 'Clutch failed',
            'status' => 'open', 'driver_id' => $driver->id])->assertSessionHasNoErrors();
        $this->get(route('transport.dashboard'))->assertSee('Vehicles off road')->assertSee('Clutch failed');
        $this->assertSame(1, app(\App\Services\TransportService::class)->staffPerformance(now()->startOfMonth(), now())->firstWhere('staff.id', $driver->id)['breakdowns']);
    }

    public function test_photos_documents_and_every_page_renders(): void
    {
        $student = $this->admit();
        [$bus, $driver, , $route] = $this->setUpFleet();
        Storage::fake('local');
        $this->post(route('transport.files.store'), ['owner_type' => 'vehicle', 'owner_id' => $bus->id, 'kind' => 'photo', 'title' => 'Front',
            'files' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->create('rc.pdf', 50, 'application/pdf')]])->assertSessionHas('success');
        $this->assertCount(2, $bus->files);
        $this->get(route('transport.files.show', $bus->files->first()))->assertOk();

        $this->post(route('transport.vendors.store'), ['name' => 'Ali Motors', 'type' => 'workshop'])->assertSessionHas('success');
        $fuel = FuelEntry::create(['vehicle_id' => $bus->id, 'entry_date' => now(), 'fuel_type' => 'diesel', 'quantity' => 10, 'rate' => 90, 'amount' => 900]);
        $m = VehicleMaintenance::create(['vehicle_id' => $bus->id, 'service_date' => now(), 'type' => 'tyre', 'title' => 'Tyres', 'status' => 'done', 'total_cost' => 9000]);
        $e = \App\Models\Transport\TransportExpense::create(['expense_date' => now(), 'category' => 'toll', 'title' => 'Toll', 'amount' => 50]);

        foreach ([route('transport.dashboard'), route('transport.vehicles.index'), route('transport.vehicles.create'), route('transport.vehicles.show', $bus), route('transport.vehicles.edit', $bus),
            route('transport.staff.index'), route('transport.staff.create'), route('transport.staff.show', $driver), route('transport.staff.edit', $driver),
            route('transport.routes.index'), route('transport.routes.create'), route('transport.routes.show', $route), route('transport.routes.show', [$route, 'class_id' => $student->school_class_id]),
            route('transport.routes.edit', $route), route('transport.student.edit', $student), route('transport.trips.index'), route('transport.attendance.index'),
            route('transport.attendance.monthly'), route('transport.leaves.index'), route('transport.fuel.index'), route('transport.fuel.create'), route('transport.fuel.edit', $fuel),
            route('transport.maintenance.index'), route('transport.maintenance.create'), route('transport.maintenance.edit', $m), route('transport.vendors.index'),
            route('transport.expenses.index'), route('transport.expenses.create'), route('transport.expenses.edit', $e), route('transport.reports.index'), route('students.show', $student)] as $url) {
            $this->get($url)->assertOk();
        }
        $this->actingAs($this->user('admin'))->get(route('transport.salaries.index'))->assertOk();
    }

    public function test_who_can_do_what(): void
    {
        [$bus] = $this->setUpFleet();

        // transport manager: no fees, no salaries, no settings
        $this->get(route('payments.create'))->assertForbidden();
        $this->get(route('transport.salaries.index'))->assertForbidden();
        $this->get(route('settings.edit'))->assertForbidden();

        // principal: sees transport, cannot change it
        $this->actingAs($this->user('principal'));
        $this->get(route('transport.dashboard'))->assertOk()->assertSee('View only');
        $this->get(route('transport.salaries.index'))->assertOk();
        $this->post(route('transport.vehicles.store'), ['bus_no' => 'X', 'registration_no' => 'Y', 'seats' => 10, 'fuel_type' => 'diesel', 'ownership' => 'owned'])->assertForbidden();
        $this->post(route('transport.vehicles.toggle', $bus))->assertForbidden();

        // reception: no transport
        $this->actingAs($this->user('receptionist'))->get(route('transport.dashboard'))->assertForbidden();

        // accounts: can manage transport and pay salaries
        $this->actingAs($this->user('accountant'))->get(route('transport.vehicles.create'))->assertOk();
        $this->get(route('transport.salaries.index'))->assertOk();

        // another school cannot open this school's bus
        $other = \App\Models\School::create(['name' => 'Other', 'email' => 'o@x.test', 'paid_until' => now()->addMonth()]);
        $this->actingAs($this->user('admin', $other))->get(route('transport.vehicles.show', $bus))->assertNotFound();
    }
}
