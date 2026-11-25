<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobileApp;
use App\Support\ModuleRegistry;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $modules = [];
        foreach (ModuleRegistry::all() as $key => $cfg) {
            // Gallery modules count images across their one-per-category records.
            $items = !empty($cfg['gallery'])
                ? $cfg['item_model']::all(['images'])->sum(fn ($r) => count($r->images ?? []))
                : $cfg['item_model']::count();

            $modules[$key] = $cfg + [
                'key'        => $key,
                'categories' => $cfg['category_model']::count(),
                'items'      => $items,
                'active'     => $cfg['item_model']::where('status', true)->count(),
            ];
        }

        return view('dashboard', [
            'modules'    => $modules,
            'appCount'   => MobileApp::count(),
            'activeApps' => MobileApp::where('status', true)->count(),
            'recentApps' => MobileApp::latest('id')->take(5)->get(),
        ]);
    }
}
