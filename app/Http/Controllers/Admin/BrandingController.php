<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function update(Request $request)
    {
        // TODO: simpan ke settings table atau config
        return back()->with('success', 'Branding disimpan!');
    }
}
