<?php

namespace Tests\Feature\Imports;

use App\Exports\ImportTemplateExport;
use App\Imports\AssetsImporter;
use App\Imports\DepartmentsImporter;
use App\Imports\ImportType;
use App\Imports\LocationsImporter;
use App\Imports\SpreadsheetReader;
use App\Imports\UsersImporter;
use App\Models\Asset;
use App\Models\Building;
use App\Models\Department;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ImportersTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /**
     * @return array<string, mixed>
     */
    private function locationRow(array $overrides = []): array
    {
        return [
            'building_code' => 'com',
            'building_name_ar' => 'مبنى كلية التجارة',
            'building_name_en' => 'Faculty of Commerce',
            'floor_level' => 3.0,
            'floor_name_ar' => null,
            'room_code' => 301.0,
            'room_name_ar' => 'قاعة 301',
            'room_name_en' => null,
            'room_type' => 'lecture_hall',
            ...$overrides,
        ];
    }

    public function test_locations_import_creates_building_floor_and_rooms(): void
    {
        $report = app(LocationsImporter::class)->run([
            $this->locationRow(),
            $this->locationRow(['building_name_ar' => null, 'room_code' => '302', 'room_name_ar' => 'قاعة 302']),
        ], dryRun: false);

        $this->assertTrue($report->saved);
        $this->assertSame(2, $report->created);

        $building = Building::firstWhere('code', 'COM');
        $this->assertNotNull($building);
        $floor = $building->floors()->firstWhere('level', 3);
        $this->assertSame('الدور 3', $floor->name_ar);
        $this->assertEqualsCanonicalizing(['301', '302'], $floor->rooms()->pluck('code')->all());
    }

    public function test_reimporting_updates_instead_of_duplicating(): void
    {
        $importer = app(LocationsImporter::class);
        $importer->run([$this->locationRow()], dryRun: false);

        $report = $importer->run([$this->locationRow(['room_name_ar' => 'قاعة 301 (مجددة)'])], dryRun: false);

        $this->assertSame(0, $report->created);
        $this->assertSame(1, $report->updated);
        $this->assertSame(1, Room::where('code', '301')->count());
        $this->assertSame('قاعة 301 (مجددة)', Room::firstWhere('code', '301')->name_ar);
    }

    public function test_dry_run_reports_counts_but_saves_nothing(): void
    {
        $report = app(LocationsImporter::class)->run([$this->locationRow()], dryRun: true);

        $this->assertFalse($report->saved);
        $this->assertSame(1, $report->created);
        $this->assertNull(Building::firstWhere('code', 'COM'));
    }

    public function test_one_bad_row_rolls_back_the_whole_file(): void
    {
        $report = app(LocationsImporter::class)->run([
            $this->locationRow(),
            $this->locationRow(['room_code' => null, 'room_type' => 'kitchen']),
        ], dryRun: false);

        $this->assertFalse($report->saved);
        $this->assertSame([3], array_keys($report->errors));
        $this->assertCount(2, $report->errors[3]);
        $this->assertNull(Building::firstWhere('code', 'COM'));
    }

    public function test_new_building_without_a_name_is_an_error(): void
    {
        $report = app(LocationsImporter::class)->run([$this->locationRow(['building_name_ar' => null])], dryRun: false);

        $this->assertArrayHasKey(2, $report->errors);
        $this->assertStringContainsString('COM', $report->errors[2][0]);
    }

    public function test_users_import_sets_fixed_room_department_and_role(): void
    {
        app(DepartmentsImporter::class)->run([
            ['name_ar' => 'كلية الهندسة', 'name_en' => null, 'parent_name_ar' => null],
            ['name_ar' => 'قسم الكهرباء', 'name_en' => 'Electrical', 'parent_name_ar' => 'كلية الهندسة'],
        ], dryRun: false);

        $report = app(UsersImporter::class)->run([[
            'name' => 'مهندس معمل', 'email' => 'Lab.Eng@ZNU.test', 'phone' => null, 'extension' => 3001.0,
            'job_title' => 'مهندس معمل', 'department_name_ar' => 'قسم الكهرباء',
            'building_code' => 'eng', 'floor_level' => 1, 'room_code' => 'LAB-3', 'role' => 'technician',
        ]], dryRun: false);

        $this->assertTrue($report->saved, json_encode($report->errors, JSON_UNESCAPED_UNICODE));

        $user = User::where('email', 'lab.eng@znu.test')->firstOrFail();
        $this->assertSame('LAB-3', $user->defaultRoom->code);
        $this->assertSame('قسم الكهرباء', $user->department->name_ar);
        $this->assertSame('3001', $user->extension);
        $this->assertTrue($user->hasRole('technician'));
        $this->assertFalse(Hash::check('', $user->password));
        $this->assertSame('كلية الهندسة', Department::firstWhere('name_ar', 'قسم الكهرباء')->parent->name_ar);
    }

    public function test_users_import_refuses_super_admin_role_and_unknown_room(): void
    {
        $report = app(UsersImporter::class)->run([
            ['name' => 'x', 'email' => 'x@znu.test', 'phone' => null, 'extension' => null, 'job_title' => null, 'department_name_ar' => null,
                'building_code' => null, 'floor_level' => null, 'room_code' => null, 'role' => 'super_admin'],
            ['name' => 'y', 'email' => 'y@znu.test', 'phone' => null, 'extension' => null, 'job_title' => null, 'department_name_ar' => null,
                'building_code' => 'MAIN', 'floor_level' => 9, 'room_code' => '999', 'role' => null],
            ['name' => 'z', 'email' => 'admin@znu.test', 'phone' => null, 'extension' => null, 'job_title' => null, 'department_name_ar' => null,
                'building_code' => null, 'floor_level' => null, 'room_code' => null, 'role' => null],
        ], dryRun: false);

        $this->assertSame([2, 3, 4], array_keys($report->errors));
        $this->assertDatabaseMissing('users', ['email' => 'x@znu.test']);
        $this->assertTrue(User::firstWhere('email', 'admin@znu.test')->hasRole('super_admin'));
    }

    public function test_assets_import_generates_missing_tags_and_links_owner(): void
    {
        $report = app(AssetsImporter::class)->run([
            ['asset_tag' => null, 'type_code' => 'pc', 'brand' => 'HP', 'model' => 'ProDesk', 'serial_number' => null,
                'building_code' => 'MAIN', 'floor_level' => 2, 'room_code' => '204', 'assigned_user_email' => 'staff@znu.test', 'status' => null],
            ['asset_tag' => null, 'type_code' => 'PC', 'brand' => 'HP', 'model' => 'ProDesk', 'serial_number' => null,
                'building_code' => 'MAIN', 'floor_level' => 2, 'room_code' => '204', 'assigned_user_email' => null, 'status' => 'under_repair'],
        ], dryRun: false);

        $this->assertTrue($report->saved, json_encode($report->errors, JSON_UNESCAPED_UNICODE));
        // Seeded PC-0123 is the highest PC tag, so the next two follow it.
        $this->assertSame(User::firstWhere('email', 'staff@znu.test')->id, Asset::firstWhere('asset_tag', 'PC-0124')->assigned_user_id);
        $this->assertNull(Asset::firstWhere('asset_tag', 'PC-0125')->assigned_user_id);
    }

    public function test_downloaded_template_can_be_read_back_and_imported(): void
    {
        Storage::fake('local');
        Excel::store(new ImportTemplateExport(app(LocationsImporter::class)), 'imports/locations.xlsx', 'local');

        $rows = app(SpreadsheetReader::class)->read('imports/locations.xlsx', 'local');

        $this->assertSame(app(LocationsImporter::class)->headings(), array_keys($rows[0]));

        $report = ImportType::Locations->importer()->run($rows, dryRun: true);
        $this->assertFalse($report->hasErrors(), json_encode($report->errors, JSON_UNESCAPED_UNICODE));
        $this->assertSame(2, $report->totalRows);
    }

    public function test_template_download_endpoint(): void
    {
        $this->actingAs(User::where('email', 'supervisor@znu.test')->firstOrFail())
            ->get(route('admin.import-templates', 'assets'))
            ->assertOk()
            ->assertDownload('assets-template.xlsx');
    }
}
