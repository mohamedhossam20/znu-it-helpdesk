<?php

namespace Database\Seeders;

use App\Enums\RoomType;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Category;
use App\Models\Department;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample buildings, people and devices for local development only.
 * All demo accounts share the password in DEMO_USER_PASSWORD (.env).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = $this->seedLocations();
        $departments = $this->seedDepartments();
        $password = env('DEMO_USER_PASSWORD', 'password');

        $users = [
            'admin' => $this->user('مدير النظام', 'admin@znu.test', UserRole::SuperAdmin, $password),
            'supervisor' => $this->user('مشرف الدعم الفني', 'supervisor@znu.test', UserRole::ItSupervisor, $password, room: $rooms['MAIN-G-IT']),
            'tech_pc' => $this->user('فني الأجهزة', 'tech.pc@znu.test', UserRole::Technician, $password, room: $rooms['MAIN-G-IT']),
            'tech_av' => $this->user('فني الصوت والعرض', 'tech.av@znu.test', UserRole::Technician, $password, room: $rooms['MAIN-G-IT']),
            'student_affairs' => $this->user('موظف شؤون الطلاب', 'staff@znu.test', UserRole::Requester, $password,
                room: $rooms['MAIN-2-204'], department: $departments['student_affairs'], extension: '2145', jobTitle: 'أخصائي شؤون طلاب'),
            'doctor' => $this->user('د. عضو هيئة التدريس', 'doctor@znu.test', UserRole::Requester, $password,
                department: $departments['engineering'], jobTitle: 'مدرس'),
        ];

        $this->linkTechnicians($users);
        $this->seedAssets($rooms, $users);
    }

    /**
     * @return array<string, Room>
     */
    private function seedLocations(): array
    {
        $layout = [
            ['MAIN', 'المبنى الرئيسي', 'Main Building', [
                [0, 'الدور الأرضي', 'Ground floor', [
                    ['IT', 'مكتب الدعم الفني', 'IT support office', RoomType::Office],
                    ['SRV', 'غرفة السيرفرات', 'Server room', RoomType::ServerRoom],
                ]],
                [2, 'الدور الثاني', 'Second floor', [
                    ['204', 'مكتب شؤون الطلاب', 'Student affairs office', RoomType::Office],
                    ['210', 'قاعة الاجتماعات', 'Meeting room', RoomType::MeetingRoom],
                ]],
            ]],
            ['ENG', 'مبنى كلية الهندسة', 'Faculty of Engineering', [
                [1, 'الدور الأول', 'First floor', [
                    ['105', 'قاعة 105', 'Hall 105', RoomType::LectureHall],
                    ['LAB-3', 'معمل الحاسب 3', 'Computer lab 3', RoomType::Lab],
                ]],
            ]],
        ];

        $rooms = [];

        foreach ($layout as [$buildingCode, $buildingAr, $buildingEn, $floors]) {
            $building = Building::updateOrCreate(['code' => $buildingCode], ['name_ar' => $buildingAr, 'name_en' => $buildingEn]);

            foreach ($floors as [$level, $floorAr, $floorEn, $floorRooms]) {
                $floor = $building->floors()->updateOrCreate(['level' => $level], ['name_ar' => $floorAr, 'name_en' => $floorEn]);
                $floorKey = $level === 0 ? 'G' : (string) $level;

                foreach ($floorRooms as [$code, $roomAr, $roomEn, $type]) {
                    $rooms["{$buildingCode}-{$floorKey}-{$code}"] = $floor->rooms()->updateOrCreate(
                        ['code' => $code],
                        ['name_ar' => $roomAr, 'name_en' => $roomEn, 'type' => $type],
                    );
                }
            }
        }

        return $rooms;
    }

    /**
     * @return array<string, Department>
     */
    private function seedDepartments(): array
    {
        return [
            'student_affairs' => Department::updateOrCreate(['name_ar' => 'شؤون الطلاب'], ['name_en' => 'Student Affairs']),
            'engineering' => Department::updateOrCreate(['name_ar' => 'كلية الهندسة'], ['name_en' => 'Faculty of Engineering']),
        ];
    }

    private function user(
        string $name,
        string $email,
        UserRole $role,
        string $password,
        ?Room $room = null,
        ?Department $department = null,
        ?string $extension = null,
        ?string $jobTitle = null,
    ): User {
        $user = User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => $password,
            'default_room_id' => $room?->id,
            'department_id' => $department?->id,
            'extension' => $extension,
            'job_title' => $jobTitle,
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$role->value]);

        return $user;
    }

    /**
     * @param  array<string, User>  $users
     */
    private function linkTechnicians(array $users): void
    {
        $roots = Category::whereNull('parent_id')->get()->keyBy('name_en');

        $roots['Computer / laptop']->technicians()->syncWithoutDetaching([$users['tech_pc']->id]);
        $roots['Printer / scanner']->technicians()->syncWithoutDetaching([$users['tech_pc']->id]);
        $roots['Screens & display']->technicians()->syncWithoutDetaching([$users['tech_av']->id]);
        $roots['Audio']->technicians()->syncWithoutDetaching([$users['tech_av']->id]);
        $roots['Network & internet']->technicians()->syncWithoutDetaching([$users['supervisor']->id]);
    }

    /**
     * @param  array<string, Room>  $rooms
     * @param  array<string, User>  $users
     */
    private function seedAssets(array $rooms, array $users): void
    {
        $types = AssetType::pluck('id', 'code');

        $assets = [
            ['PC-0123', 'PC', 'MAIN-2-204', $users['student_affairs'], 'Dell', 'OptiPlex 7090', ['os' => 'Windows 11', 'ram' => '16GB']],
            ['PR-0045', 'PRINTER', 'MAIN-2-204', null, 'HP', 'LaserJet M404', null],
            ['TEL-2145', 'PHONE', 'MAIN-2-204', $users['student_affairs'], 'Panasonic', 'KX-TS500', null],
            ['SCR-0010', 'SCREEN', 'MAIN-2-210', null, 'Samsung', 'QM65', null],
            ['PJ-0010', 'PROJECTOR', 'ENG-1-105', null, 'Epson', 'EB-X51', null],
            ['SP-0022', 'SPEAKER', 'ENG-1-105', null, 'JBL', 'Control 25', null],
            ['MIC-0007', 'MIC', 'ENG-1-105', null, 'Shure', 'BLX24', null],
            ['SW-0003', 'SWITCH', 'ENG-1-LAB-3', null, 'Cisco', 'CBS350-24', null],
        ];

        foreach ($assets as [$tag, $type, $roomKey, $owner, $brand, $model, $specs]) {
            Asset::updateOrCreate(['asset_tag' => $tag], [
                'asset_type_id' => $types[$type],
                'room_id' => $rooms[$roomKey]->id,
                'assigned_user_id' => $owner?->id,
                'brand' => $brand,
                'model' => $model,
                'specs' => $specs,
            ]);
        }
    }
}
