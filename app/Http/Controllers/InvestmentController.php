<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Investment;
use Illuminate\Support\Facades\Auth;

class InvestmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Store multiple investments at once.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // 1) Validate incoming data
        $data = $request->validate([
            'family_id'                         => 'required|exists:families,id',
            'investments'                       => 'required|array',
            'investments.*.category_id'         => 'required|exists:investment_categories,id',
            'investments.*.current_balance'     => 'required|numeric|min:0',
            'investments.*.invested_balance'    => 'required|numeric|min:0',
        ]);

        // 2) Loop and create a new record for each category
        foreach ($data['investments'] as $inv) {
            Investment::create([
                'user_id'          => Auth::id(),
                'family_id'        => $data['family_id'],
                'category_id'      => $inv['category_id'],
                'current_balance'  => $inv['current_balance'],
                'invested_balance' => $inv['invested_balance'],
            ]);
        }

        // 3) Redirect back with success message
        return back()->with('success', 'Investimenti salvati correttamente');
    }
}
