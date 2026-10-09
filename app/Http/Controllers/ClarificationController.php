<?php

namespace App\Http\Controllers;

use App\Models\Clarification;
use App\Rules\NoSecrets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Needs Clarification" items raised during the workbook import (or later).
 */
class ClarificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = Clarification::query()->visibleTo($this->user())->with(['product', 'resolver', 'subject']);

        $status = $request->string('status', Clarification::OPEN)->toString();
        if (in_array($status, [Clarification::OPEN, Clarification::RESOLVED], true)) {
            $query->where('status', $status);
        }
        if ($productId = $request->integer('product_id')) {
            $query->where('product_id', $productId);
        }
        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(fn ($w) => $w->where('issue', 'like', "%{$q}%")->orWhere('field', 'like', "%{$q}%")->orWhere('original_value', 'like', "%{$q}%"));
        }

        return view('clarifications.index', [
            'items' => $query->orderBy('product_id')->orderBy('id')->paginate(50)->withQueryString(),
            'products' => $this->productChoices(),
            'status' => $status,
        ]);
    }

    public function resolve(Request $request, Clarification $clarification): RedirectResponse
    {
        $this->authorizeView($clarification);
        Gate::authorize('update', $clarification);

        $validated = $request->validate([
            'resolution' => ['required', 'string', 'max:2000', new NoSecrets],
        ]);

        $clarification->update([
            'status' => Clarification::RESOLVED,
            'resolution' => $validated['resolution'],
            'resolved_by' => $this->user()->id,
            'resolved_at' => now(),
        ]);

        return back()->with('status', 'Clarification marked as resolved. Remember to update the affected record.');
    }

    public function reopen(Clarification $clarification): RedirectResponse
    {
        $this->authorizeView($clarification);
        Gate::authorize('update', $clarification);

        $clarification->update(['status' => Clarification::OPEN, 'resolved_by' => null, 'resolved_at' => null]);

        return back()->with('status', 'Clarification reopened.');
    }
}
