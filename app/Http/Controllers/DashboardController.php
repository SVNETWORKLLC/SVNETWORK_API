<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardUserResource;
use App\Http\Resources\DashboardUsersResource;
use App\Models\Company;
use App\Models\Matches;
use App\Models\NoMatches;
use App\Models\Project;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function registeredUsers()
    {
        // Obtener el rango de fechas (últimos 30 días o desde el primer usuario)
        $startDate = User::where('is_admin', 0)->min('created_at')
            ? Carbon::parse(User::where('is_admin', 0)->min('created_at'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Generar todas las fechas en el rango
        $allDates = collect();
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $allDates->push($currentDate->copy());
            $currentDate->addDay();
        }

        // Obtener usuarios agrupados por fecha (excluyendo admins)
        $usersByDate = User::selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('is_admin', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->pluck('total', 'date');

        // Construir arrays finales con 0 para fechas sin registros
        $categories = [];
        $series = [];

        foreach ($allDates as $date) {
            $dateStr = $date->format('Y-m-d');
            $categories[] = $date->format('M d Y');
            $series[] = $usersByDate->get($dateStr, 0);
        }

        $data = [
            'categories' => $categories,
            'series' => $series
        ];
        return $data;
    }

    public function registeredCompanies()
    {
        // Obtener el rango de fechas (últimos 30 días o desde la primera empresa)
        $startDate = Company::min('created_at')
            ? Carbon::parse(Company::min('created_at'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Generar todas las fechas en el rango
        $allDates = collect();
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $allDates->push($currentDate->copy());
            $currentDate->addDay();
        }

        // Obtener empresas agrupadas por fecha
        $companiesByDate = Company::selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->pluck('total', 'date');

        // Construir arrays finales con 0 para fechas sin registros
        $categories = [];
        $series = [];

        foreach ($allDates as $date) {
            $dateStr = $date->format('Y-m-d');
            $categories[] = $date->format('M d Y');
            $series[] = $companiesByDate->get($dateStr, 0);
        }

        $data = [
            'categories' => $categories,
            'series' => $series
        ];
        return $data;
    }

    public function topServices()
    {
        $matchesPorMes = Project::selectRaw('DATE_FORMAT(created_at, "%M-%Y") as mes')
        ->distinct()
        ->orderByRaw('YEAR(created_at), MONTH(created_at)')
        ->get();

        $meses = $matchesPorMes->pluck('mes')->toArray();

        $matchesPorServicioYMes = Project::selectRaw('service_id, MONTH(created_at) as mes, count(service_id) as total')
            ->groupBy('service_id', DB::raw('MONTH(created_at)'))
            ->get();

        $resultados = [];
        foreach ($matchesPorServicioYMes as $match) {
            $service = Service::withTrashed()->find($match->service_id)?->name;
            $total = $match->total;

                if (!isset($resultados[$service])) {
                    $resultados[$service] = ['name' => $service];
                }
                $resultados[$service]['data'][] = $total;

        }

        // Convertir el array asociativo en un array simple
        $resultados = array_values($resultados);
        return ['series' => $resultados, 'categories' => $meses];
    }

    public function totalMatches()
    {
        // Obtener el rango de fechas (últimos 30 días o desde el primer match)
        $startDate = Matches::min('created_at')
            ? Carbon::parse(Matches::min('created_at'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Generar todas las fechas en el rango
        $allDates = collect();
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $allDates->push($currentDate->copy());
            $currentDate->addDay();
        }

        // Obtener matches agrupados por fecha
        $matchesByDate = Matches::selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->pluck('total', 'date');

        // Construir arrays finales con 0 para fechas sin matches
        $categories = [];
        $series = [];

        foreach ($allDates as $date) {
            $dateStr = $date->format('Y-m-d');
            $categories[] = $date->format('M d Y');
            $series[] = $matchesByDate->get($dateStr, 0);
        }

        $data = [
            'categories' => $categories,
            'series' => $series
        ];
        return $data;
    }
    public function totalNomatches()
    {
        // Obtener el rango de fechas (últimos 30 días o desde el primer nomatch)
        $startDate = NoMatches::min('created_at')
            ? Carbon::parse(NoMatches::min('created_at'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Generar todas las fechas en el rango
        $allDates = collect();
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $allDates->push($currentDate->copy());
            $currentDate->addDay();
        }

        // Obtener nomatches agrupados por fecha
        $nomatchesByDate = NoMatches::selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->pluck('total', 'date');

        // Construir arrays finales con 0 para fechas sin nomatches
        $categories = [];
        $series = [];

        foreach ($allDates as $date) {
            $dateStr = $date->format('Y-m-d');
            $categories[] = $date->format('M d Y');
            $series[] = $nomatchesByDate->get($dateStr, 0);
        }

        $data = [
            'categories' => $categories,
            'series' => $series
        ];
        return $data;
    }

    public function getStats()
    {
        $users = User::whereNotNull('password')->where('is_admin', 0)->count();
        $companies = Company::all()->count();
        $matches = Matches::all()->count();
        $noMatches = NoMatches::all()->count();

        $data = [
            'users' => $users,
            'companies' => $companies,
            'matches' => $matches,
            'noMatches' => $noMatches,
        ];

        return $data;
    }
}
