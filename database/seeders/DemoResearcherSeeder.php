<?php

namespace Database\Seeders;

use App\Exceptions\AoiValidationException;
use App\Models\AreaOfInterest;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Role;
use App\Models\User;
use App\Services\Geo\AoiValidator;
use App\Services\Geo\PolygonGeometry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Ten demo researcher accounts, one project each, with a real AOI polygon
 * that passes the same AoiValidator the live "Confirm AOI" flow uses --
 * seeded for the public landing page's stats section (real DB aggregates,
 * not hardcoded marketing numbers) and so there's something to actually
 * click through during a demo.
 *
 * Site locations are approximate public town-center coordinates for real
 * coastal municipalities of Davao Occidental, Philippines (not surveyed
 * GPS data) -- chosen because the AOI map search is already scoped to
 * that province (see map-workspace.js).
 */
class DemoResearcherSeeder extends Seeder
{
    public function run(): void
    {
        $researcherRole = Role::where('name', 'researcher')->first();
        $admin = User::where('email', 'admin@mangrove.ph')->first();

        if (!$researcherRole || !$admin) {
            $this->command->error('Researcher role or admin account not found; run RoleSeeder/UserSeeder first.');
            return;
        }

        $password = Hash::make(env('SEED_DEFAULT_PASSWORD', 'ChangeMe123!'));

        $sites = [
            ['town' => 'Malita', 'lat' => 6.4147, 'lng' => 125.6234],
            ['town' => 'Santa Maria', 'lat' => 6.2833, 'lng' => 125.5667],
            ['town' => 'Jose Abad Santos', 'lat' => 5.8333, 'lng' => 125.3667],
            ['town' => 'Don Marcelino', 'lat' => 6.1167, 'lng' => 125.5333],
            ['town' => 'Sarangani', 'lat' => 5.9667, 'lng' => 125.4500],
        ];

        $researchers = [
            ['name' => 'Maria Fernandez', 'email' => 'maria.fernandez@mangrove.ph'],
            ['name' => 'Renz Villanueva', 'email' => 'renz.villanueva@mangrove.ph'],
            ['name' => 'Angelica Domingo', 'email' => 'angelica.domingo@mangrove.ph'],
            ['name' => 'Paolo Estrada', 'email' => 'paolo.estrada@mangrove.ph'],
            ['name' => 'Kristine Aquino', 'email' => 'kristine.aquino@mangrove.ph'],
            ['name' => 'Miguel Bautista', 'email' => 'miguel.bautista@mangrove.ph'],
            ['name' => 'Jasmine Ramos', 'email' => 'jasmine.ramos@mangrove.ph'],
            ['name' => 'Carlo Mendoza', 'email' => 'carlo.mendoza@mangrove.ph'],
            ['name' => 'Bianca Torres', 'email' => 'bianca.torres@mangrove.ph'],
            ['name' => 'Dennis Ocampo', 'email' => 'dennis.ocampo@mangrove.ph'],
        ];

        // One label per researcher/project, two per town (paired with $sites
        // via intdiv($i, 2) below).
        $projectLabels = [
            'North Zone', 'South Zone',
            'Restoration Site A', 'Restoration Site B',
            'Shoreline Recovery Project', 'Mangrove Nursery Expansion',
            'Coastal Buffer Rehabilitation', 'Reforestation Site',
            'Bay Monitoring Project', 'Coastal Rehabilitation Initiative',
        ];

        // [width_m, height_m, rotation_deg] per project -- all comfortably
        // inside AoiValidator::MAX_AREA_M2 / MAX_DIMENSION_M (4 ha / 200 m),
        // varied so the "hectares mapped" total looks like real fieldwork
        // rather than ten identical squares.
        $footprints = [
            [180, 150, 0], [140, 120, 15], [160, 140, -10], [190, 110, 5],
            [120, 100, 20], [170, 160, 0], [150, 130, -15], [130, 110, 10],
            [175, 145, -5], [155, 125, 8],
        ];

        $monthsAgo = [8, 7, 7, 6, 5, 5, 4, 3, 2, 1];

        foreach ($researchers as $i => $person) {
            $site = $sites[intdiv($i, 2)];
            $label = $projectLabels[$i];
            $startedAt = now()->subMonths($monthsAgo[$i]);

            $user = User::firstOrCreate(
                ['email' => $person['email']],
                [
                    'role_id' => $researcherRole->id,
                    'name' => $person['name'],
                    'password' => $password,
                    'must_change_password' => false,
                    'is_active' => true,
                    'status' => 'approved',
                    'approved_by' => $admin->id,
                    'approved_at' => $startedAt,
                    'onboarding_completed_at' => $startedAt,
                ]
            );

            $projectName = "{$site['town']} Mangrove Rehabilitation \xE2\x80\x94 {$label}";

            $project = Project::firstOrCreate(
                ['name' => $projectName],
                [
                    'created_by' => $user->id,
                    'description' => "Coastal mangrove rehabilitation and monitoring site in {$site['town']}, Davao Occidental.",
                    'status' => 'active',
                    'started_at' => $startedAt,
                ]
            );

            ProjectMember::firstOrCreate(
                ['project_id' => $project->id, 'user_id' => $user->id],
                ['role' => 'lead', 'assigned_at' => $startedAt]
            );

            if ($project->areasOfInterest()->exists()) {
                continue; // already seeded on a previous run
            }

            [$widthM, $heightM, $rotationDeg] = $footprints[$i];
            $refLat = $site['lat'] + ($i % 2 === 0 ? 1 : -1) * 0.004;
            $refLng = $site['lng'];

            $hw = $widthM / 2;
            $hh = $heightM / 2;
            $local = [[-$hw, -$hh], [$hw, -$hh], [$hw, $hh], [-$hw, $hh]];
            if ($rotationDeg !== 0) {
                $local = PolygonGeometry::rotate($local, $rotationDeg, [0.0, 0.0]);
            }

            $boundary = PolygonGeometry::toLatLng($local, $refLat, $refLng);
            $boundary[] = $boundary[0]; // explicit closing vertex, matching ProjectController::confirmAoi

            try {
                $metrics = AoiValidator::validate($boundary);
            } catch (AoiValidationException $e) {
                $this->command->warn("Skipping AOI for {$projectName}: {$e->getMessage()}");
                continue;
            }

            $aoi = AreaOfInterest::create([
                'project_id' => $project->id,
                'created_by' => $user->id,
                'name' => $project->name . ' AOI',
                'boundary_coordinates' => $boundary,
                'center_latitude' => $refLat,
                'center_longitude' => $refLng,
                'area_sqm' => round($metrics['area_m2'], 2),
                'status' => 'active',
                'notes' => sprintf(
                    'Seeded AOI: %s m² (%s m longest dimension).',
                    number_format($metrics['area_m2'], 0),
                    number_format($metrics['longest_dimension_m'], 0)
                ),
            ]);

            $this->persistBoundaryPolygon($aoi, $boundary);
        }

        $this->command->info('Seeded 10 demo researcher accounts with projects and AOIs.');
    }

    private function persistBoundaryPolygon(AreaOfInterest $aoi, array $coordinates): void
    {
        $points = array_map(fn (array $p) => sprintf('%.8F %.8F', $p[1], $p[0]), $coordinates);
        $wkt = 'POLYGON((' . implode(', ', $points) . '))';

        if (DB::getDriverName() !== 'pgsql') {
            $aoi->update(['boundary_polygon' => $wkt]);

            return;
        }

        DB::update(
            'UPDATE areas_of_interest SET boundary_polygon = ?::geometry WHERE id = ?',
            ['SRID=4326;' . $wkt, $aoi->id]
        );
    }
}
