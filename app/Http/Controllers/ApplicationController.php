<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\ApplicationSiteCredential;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Support\Applications\SiteUrlSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $products = $this->productChoicesFor('applications.create');
        abort_if($products === [] || ($product !== null && ! isset($products[$product->id])), 403);

        return view('applications.form', [
            'application' => new Application(['product_id' => $product?->id]),
            'products' => $products,
            'canManageSiteCredentials' => Gate::allows('applications.view_credentials')
                && ($product === null || Gate::allows('create-in', ['applications.create', $product])),
            'siteUrls' => collect(),
        ]);
    }

    public function store(ApplicationRequest $request, SiteUrlSync $sync): RedirectResponse
    {
        $application = DB::transaction(function () use ($request, $sync) {
            $application = Application::query()->create($request->applicationAttributes());
            $sync->sync($application, $request->siteUrls(), $request->canManageSiteCredentials());

            return $application;
        });

        return redirect()->route('applications.show', $application)->with('status', 'Application created.');
    }

    public function show(Application $application): View
    {
        $this->authorizeView($application);
        $user = $this->user();

        $canViewCredentials = Gate::allows('viewCredentials', $application);

        return view('applications.show', [
            'application' => $application->load(['product', 'applicationType', 'classification', 'status']),
            'repositories' => $this->paginateList(Repository::query()->visibleTo($user)->where('application_id', $application->id)->with(['layer', 'status'])->orderBy('code'), 'repositories'),
            'modules' => $this->paginateList(Module::query()->visibleTo($user)->where('application_id', $application->id)->with('status')->orderBy('code'), 'modules'),
            'clarifications' => $this->clarificationsFor($application),
            'canViewCredentials' => $canViewCredentials,
            'credentialRows' => $canViewCredentials
                ? $this->paginateList(
                    ApplicationSiteCredential::query()
                        ->with('siteUrl')
                        ->whereIn('application_site_url_id', $application->siteUrls()->pluck('id'))
                        ->orderBy('application_site_url_id')
                        ->orderBy('sort_order'),
                    'credentials'
                )
                : null,
        ]);
    }

    public function edit(Application $application): View
    {
        $this->authorizeView($application);
        Gate::authorize('update', $application);

        $application->load(['siteUrls.credentials']);

        return view('applications.form', [
            'application' => $application,
            'products' => [$application->product_id => $application->product->code.' — '.$application->product->name]
                + $this->productChoicesFor('applications.create'),
            'canManageSiteCredentials' => Gate::allows('viewCredentials', $application) && Gate::allows('update', $application),
            'siteUrls' => $application->siteUrls,
        ]);
    }

    public function update(ApplicationRequest $request, Application $application, SiteUrlSync $sync): RedirectResponse
    {
        DB::transaction(function () use ($request, $application, $sync) {
            $application->update($request->applicationAttributes());
            $sync->sync($application, $request->siteUrls(), $request->canManageSiteCredentials());
        });

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
