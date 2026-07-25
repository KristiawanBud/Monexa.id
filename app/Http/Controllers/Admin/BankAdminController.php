<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BankAdminController extends Controller
{
    public function index()
    {
        return inertia('Admin/Banks', [
            'banks' => Bank::orderBy('sort_order')->get()->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'short_name' => $b->short_name,
                'type' => $b->type,
                'logo_color' => $b->logo_color,
                'logo_initial' => $b->logo_initial,
                'logo_url' => $b->logo_url ? Storage::url($b->logo_url) : null,
                'is_active' => $b->is_active,
                'sort_order' => $b->sort_order,
            ]),
        ]);
    }

    // ─────────────────────────────────────────────
    // PRIORITAS #5: Tambah bank + upload logo (opsional)
    //
    // Kalau logo tidak di-upload, sistem fallback ke
    // logo_initial + logo_color (badge teks berwarna).
    // ─────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:80',
            'short_name' => 'required|string|max:20',
            'type' => 'required|in:conventional,syariah,digital,other',
            'logo_color' => 'nullable|string|max:7',
            'logo_initial' => 'nullable|string|max:2',
            'logo_file' => 'nullable|image|max:512', // max 512KB
            'sort_order' => 'nullable|integer',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo_file')) {
            $logoPath = $request->file('logo_file')->store('bank-logos', 'public');
        }

        Bank::create([
            'name' => $request->name,
            'short_name' => $request->short_name,
            'type' => $request->type,
            'logo_color' => $request->logo_color ?? '#2563EB',
            'logo_initial' => $request->logo_initial ?? strtoupper(substr($request->short_name, 0, 2)),
            'logo_url' => $logoPath,
            'sort_order' => $request->sort_order ?? 99,
        ]);

        return back()->with('success', 'Bank berhasil ditambahkan!');
    }

    // ─────────────────────────────────────────────
    // PRIORITAS #5: Update bank + ganti/upload logo
    // ─────────────────────────────────────────────
    public function update(Request $request, Bank $bank)
    {
        $request->validate([
            'name' => 'required|string|max:80',
            'short_name' => 'required|string|max:20',
            'type' => 'required|in:conventional,syariah,digital,other',
            'logo_color' => 'nullable|string|max:7',
            'logo_initial' => 'nullable|string|max:2',
            'logo_file' => 'nullable|image|max:512',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data = $request->only('name', 'short_name', 'type', 'logo_color', 'logo_initial', 'is_active', 'sort_order');

        if ($request->hasFile('logo_file')) {
            // Hapus logo lama kalau ada
            if ($bank->logo_url) {
                Storage::disk('public')->delete($bank->logo_url);
            }
            $data['logo_url'] = $request->file('logo_file')->store('bank-logos', 'public');
        }

        $bank->update($data);

        return back()->with('success', 'Bank berhasil diupdate!');
    }

    // ─────────────────────────────────────────────
    // PRIORITAS #5: Hapus logo (kembali ke fallback inisial+warna)
    // ─────────────────────────────────────────────
    public function removeLogo(Bank $bank)
    {
        if ($bank->logo_url) {
            Storage::disk('public')->delete($bank->logo_url);
            $bank->update(['logo_url' => null]);
        }

        return back()->with('success', 'Logo dihapus, kembali ke tampilan inisial.');
    }
}
