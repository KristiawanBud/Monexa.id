<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use App\Models\UserWallet;
use App\Models\Bank;
use App\Services\WaGatewayService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function __construct(private WaGatewayService $gatewayService) {}

    public function step1(): \Inertia\Response
    {
        return Inertia::render('Onboarding/Step1Profile');
    }

    public function saveStep1(Request $request)
    {
        $request->validate([
            'wa_number' => 'required|string|max:20',
            'currency'  => 'required|in:IDR,USD,SGD',
        ]);

        $user = $request->user();
        $user->update(['wa_number' => $request->wa_number]);

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'currency' => $request->currency ?? 'IDR',
                'timezone' => 'Asia/Jakarta',
            ]
        );

        // Auto-assign WA Gateway setelah nomor WA diisi
        $this->gatewayService->assignGateway($user);

        return redirect()->route('onboarding.step2');
    }

    public function step2(): \Inertia\Response
    {
        return Inertia::render('Onboarding/Step2Bank', [
            'banks' => Bank::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function saveStep2(Request $request)
    {
        $request->validate([
            'wallets'                => 'required|array|min:1',
            'wallets.*.bank_id'      => 'nullable|exists:banks,id',
            'wallets.*.display_name' => 'required|string|max:60',
            'wallets.*.type'         => 'required|in:cash_flow,saving,both,investment',
        ]);

        $user = $request->user();
        foreach ($request->wallets as $index => $w) {
            UserWallet::create([
                'user_id'      => $user->id,
                'bank_id'      => $w['bank_id'] ?? null,
                'display_name' => $w['display_name'],
                'type'         => $w['type'],
                'sort_order'   => $index,
            ]);
        }
        if (!collect($request->wallets)->contains('display_name', 'Cash')) {
            UserWallet::create([
                'user_id'      => $user->id,
                'bank_id'      => null,
                'display_name' => 'Cash',
                'type'         => 'both',
                'sort_order'   => 99,
            ]);
        }
        return redirect()->route('onboarding.step3');
    }

    public function step3(Request $request): \Inertia\Response
    {
        $user       = $request->user()->load('waGatewayAssignment.gateway');
        $assignment = $user->waGatewayAssignment;
        $gateway    = $assignment?->gateway;

        return Inertia::render('Onboarding/Step3Done', [
            'bot_gateway' => $gateway ? [
                'phone_number' => $gateway->phone_number,
                'name'         => $gateway->name,
                'status'       => $gateway->status,
                'assigned_at'  => $assignment->assigned_at->format('d M Y'),
            ] : null,
        ]);
    }
}
