<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\TransactionCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryAdminController extends Controller
{
    public function index()
    {
        $categories = TransactionCategory::orderBy('type')->orderBy('sort_order')->get()->map(fn($c) => [
            'id'         => $c->id,
            'type'       => $c->type,
            'name'       => $c->name,
            'emoji'      => $c->emoji,
            'icon_url'   => $c->icon_url,
            'is_system'  => $c->is_system,
            'sort_order' => $c->sort_order,
        ]);

        return inertia('Admin/Categories', ['categories' => $categories]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'  => 'required|in:income,expense',
            'name'  => 'required|max:50',
            'emoji' => 'nullable|max:10',
        ]);

        TransactionCategory::create([
            'type'       => $request->type,
            'name'       => $request->name,
            'emoji'      => $request->emoji,
            'sort_order' => $request->sort_order ?? 99,
            'is_system'  => false,
        ]);

        return back()->with('success', 'Kategori ditambahkan!');
    }

    public function uploadIcon(Request $request, TransactionCategory $category)
    {
        $request->validate([
            'icon' => ['required', 'image', 'max:512', 'mimes:png,jpg,jpeg,svg,webp'],
        ]);

        if ($category->icon_path) {
            Storage::disk('public')->delete($category->icon_path);
        }

        $path = $request->file('icon')->store('category-icons', 'public');
        $category->update(['icon_path' => $path]);

        return back()->with('success', "Icon kategori {$category->name} berhasil diganti!");
    }

    public function resetIcon(TransactionCategory $category)
    {
        if ($category->icon_path) {
            Storage::disk('public')->delete($category->icon_path);
        }
        $category->update(['icon_path' => null]);

        return back()->with('success', "Icon kategori {$category->name} dikembalikan ke emoji default.");
    }

    public function destroy(TransactionCategory $category)
    {
        if ($category->is_system) {
            return back()->with('error', 'Kategori sistem tidak bisa dihapus.');
        }
        if ($category->icon_path) {
            Storage::disk('public')->delete($category->icon_path);
        }
        $category->delete();

        return back()->with('success', 'Kategori dihapus.');
    }
}
