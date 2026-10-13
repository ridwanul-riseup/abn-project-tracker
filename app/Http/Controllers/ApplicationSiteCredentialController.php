<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationSiteCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Cross-application list of site credentials (one row per credential).
 */
class ApplicationSiteCredentialController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('applications.view');

        $user = $this->user();
        $credentialAppIds = Application::query()->visibleTo($user)->get()
            ->filter(fn (Application $a) => Gate::forUser($user)->allows('viewCredentials', $a))
            ->pluck('id');

        $query = ApplicationSiteCredential::query()
            ->with(['siteUrl.application.product'])
            ->whereHas('siteUrl', fn ($q) => $q->whereIn('application_id', $credentialAppIds->all() ?: [0]));

        if ($productId = $request->integer('product_id')) {
            $query->whereHas('siteUrl.application', fn ($q) => $q->where('product_id', $productId));
        }
        if ($applicationId = $request->integer('application_id')) {
            $query->whereHas('siteUrl', fn ($q) => $q->where('application_id', $applicationId));
        }
        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(fn ($w) => $w
                ->where('title', 'like', "%{$q}%")
                ->orWhere('account_login', 'like', "%{$q}%")
                ->orWhere('short_description', 'like', "%{$q}%")
                ->orWhereHas('siteUrl', fn ($u) => $u->where('url', 'like', "%{$q}%")->orWhere('label', 'like', "%{$q}%"))
                ->orWhereHas('siteUrl.application', fn ($a) => $a->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")));
        }

        $credentials = $query->orderBy('application_site_url_id')->orderBy('sort_order')
            ->paginate($this->perPage())
            ->withQueryString();

        $credentials->getCollection()->transform(function (ApplicationSiteCredential $credential) use ($user) {
            $credential->setAttribute('can_edit', Gate::forUser($user)->allows('update', $credential->siteUrl->application));

            return $credential;
        });

        return view('application-credentials.index', [
            'credentials' => $credentials,
            'products' => $this->productChoices(),
            'applications' => Application::query()->visibleTo($user)->orderBy('code')->get()
                ->mapWithKeys(fn (Application $a) => [$a->id => $a->code.' — '.$a->name]),
        ]);
    }
}
