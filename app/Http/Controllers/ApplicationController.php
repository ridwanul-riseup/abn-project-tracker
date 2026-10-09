<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->user();
        $query = Application::query()->visibleTo($user)
            ->with(['product', 'applicationType', 'classification', 'status'])
            ->withCount(['repositories' => fn ($q) => $q->visibleTo($user)]);

        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(fn ($w) => $w->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
        }
        foreach (['product' => 'product_id', 'type' => 'application_type_id', 'classification' => 'classification_id', 'status' => 'status_id'] as $param => $column) {
            if ($value = $request->integer($param)) {
                $query->where($column, $value);
            }
        }
        if (! $request->boolean('archived')) {
            $query->notArchived();
        }

        return view('applications.index', [
            'applications' => $query->orderBy('code')->paginate($this->perPage())->withQueryString(),
            'products' => $this->productChoices(),
        ]);
    }

    public function create(Request $request): View
    {
        $product = Product::query()->find($request->integer('product_id'));
        Gate::authorize('inventory.create-in', $product ?? $this->firstWritableProduct());

        return view('applications.form', [
            'application' => new Application(['product_id' => $product?->id]),
            'products' => $this->productChoices(writableOnly: true),
        ]);
    }

    public function store(ApplicationRequest $request): RedirectResponse
    {
        $application = Application::query()->create($request->validated());

        return redirect()->route('applications.show', $application)->with('status', 'Application created.');
    }

    public function show(Application $application): View
    {
        $this->authorizeView($application);
        $user = $this->user();

        return view('applications.show', [
            'application' => $application->load(['product', 'applicationType', 'classification', 'status']),
            'repositories' => Repository::query()->visibleTo($user)->where('application_id', $application->id)->with(['layer', 'status'])->orderBy('code')->get(),
            'modules' => Module::query()->visibleTo($user)->where('application_id', $application->id)->with('status')->orderBy('code')->get(),
            'clarifications' => $this->clarificationsFor($application),
        ]);
    }

    public function edit(Application $application): View
    {
        $this->authorizeView($application);
        Gate::authorize('update', $application);

        return view('applications.form', [
            'application' => $application,
            'products' => $this->productChoices(writableOnly: true),
        ]);
    }

    public function update(ApplicationRequest $request, Application $application): RedirectResponse
    {
        $application->update($request->validated());

        return redirect()->route('applications.show', $application)->with('status', 'Application updated.');
    }

    public function archive(Application $application): RedirectResponse
    {
        $this->authorizeView($application);
        Gate::authorize('delete', $application);
        $application->update(['archived_at' => now()]);

        return back()->with('status', 'Application archived.');
    }

    public function restore(Application $application): RedirectResponse
    {
        $this->authorizeView($application);
        Gate::authorize('delete', $application);
        $application->update(['archived_at' => null]);

        return back()->with('status', 'Application restored.');
    }
}
