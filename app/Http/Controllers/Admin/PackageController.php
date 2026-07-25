<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(): Response
    {
        $packages = Package::orderBy('sort_order')->get();

        return Inertia::render('Admin/Packages', ['packages' => $packages]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:30', 'alpha_dash', 'unique:packages,slug'],
            'billing_period' => ['required', 'in:trial,monthly,yearly'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:150'],
            'sort_order' => ['nullable', 'integer'],

            'discount_type' => ['nullable', 'in:percent,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'discount_label' => ['nullable', 'string', 'max:60'],
            'discount_starts_at' => ['nullable', 'date'],
            'discount_ends_at' => ['nullable', 'date', 'after_or_equal:discount_starts_at'],
        ]);

        Package::create($validated);

        return back()->with('success', 'Paket berhasil ditambahkan!');
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'billing_period' => ['required', 'in:trial,monthly,yearly'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:150'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],

            'discount_type' => ['nullable', 'in:percent,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'discount_label' => ['nullable', 'string', 'max:60'],
            'discount_starts_at' => ['nullable', 'date'],
            'discount_ends_at' => ['nullable', 'date', 'after_or_equal:discount_starts_at'],
        ]);

        $package->update($validated);

        return back()->with('success', "Paket {$package->name} berhasil diupdate!");
    }

    public function destroy(Package $package): RedirectResponse
    {
        $package->delete();

        return back()->with('success', 'Paket berhasil dihapus.');
    }
}
