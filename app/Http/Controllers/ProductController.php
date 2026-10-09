<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\AccessRule;
use App\Models\Application;
use App\Models\ComponentDependency;
use App\Models\DatabaseReference;
use App\Models\Document;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Task;
use App\Support\Access\AccessService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->user();
        $query = Product::query()->visibleTo($user)->with('status')
            ->withCount([
                'applications' => fn ($q) => $q->visibleTo($user),
                'repositories' => fn ($q) => $q->visibleTo($user),
                'modules' => fn ($q) => $q->visibleTo($user),
            ]);

        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(fn ($w) => $w->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%"));
        }
        if ($status = $request->integer('status')) {
            $query->where('status_id', $status);
        }
        if (! $request->boolean('archived')) {
            $query->notArchived();
        }

        return view('products.index', [
            'products' => $query->orderBy('code')->paginate($this->perPage())->withQueryString(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('products.create');

        return view('products.form', ['product' => new Product]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        Gate::authorize('products.create');
        $user = $this->user();

        $product = DB::transaction(function () use ($request, $user) {
            $product = Product::query()->create($request->validated());

            // The creator keeps write access to what they created.
            if (! $this->access()->isSuperAdmin($user) && ! $this->access()->canWrite($user, $product)) {
                $rule = AccessRule::query()->create([
                    'user_id' => $user->id,
                    'scope_type' => 'product',
                    'scope_id' => $product->id,
                    'permission' => AccessService::WRITE,
                    'reason' => 'Product creator',
                    'granted_by' => $user->id,
                ]);
                Audit::record('access.granted', $user, ['rule' => $rule->auditLabel()]);
            }

            return $product;
        });

        return redirect()->route('products.show', $product)->with('status', 'Product created.');
    }

    public function show(Product $product): View
    {
        $this->authorizeView($product);
        $user = $this->user();
        $openStatuses = $this->options()->statusIdsWithBehavior(['open', 'blocked']);

        return view('products.show', [
            'product' => $product->load('status'),
            'applications' => Application::query()->visibleTo($user)->where('product_id', $product->id)->with(['applicationType', 'classification', 'status'])->orderBy('code')->get(),
            'repositories' => Repository::query()->visibleTo($user)->where('product_id', $product->id)->with(['layer', 'status', 'application'])->orderBy('code')->get(),
            'modules' => Module::query()->visibleTo($user)->where('product_id', $product->id)->with(['status', 'primaryRepository'])->orderBy('code')->get(),
            'databases' => DatabaseReference::query()->visibleTo($user)->where('product_id', $product->id)->orderBy('code')->get(),
            'documents' => Document::query()->visibleTo($user)->notArchived()->where('product_id', $product->id)->with('category')->orderBy('title')->get(),
            'openTasks' => Task::query()->visibleTo($user)->where('product_id', $product->id)->whereIn('status_id', $openStatuses)->count(),
            'dependencyCount' => ComponentDependency::query()->visibleTo($user)
                ->where(fn ($q) => $q->where('source_type', 'repository')->whereIn('source_id', Repository::query()->where('product_id', $product->id)->select('id')))
                ->count(),
            'clarifications' => $this->clarificationsFor($product),
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorizeView($product);
        Gate::authorize('update', $product);

        return view('products.form', ['product' => $product]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeView($product);
        Gate::authorize('update', $product);
        $product->update($request->validated());

        return redirect()->route('products.show', $product)->with('status', 'Product updated.');
    }

    public function archive(Product $product): RedirectResponse
    {
        $this->authorizeView($product);
        Gate::authorize('delete', $product);
        $product->update(['archived_at' => now()]);

        return redirect()->route('products.show', $product)->with('status', 'Product archived. It is hidden from default lists but its history is kept.');
    }

    public function restore(Product $product): RedirectResponse
    {
        $this->authorizeView($product);
        Gate::authorize('delete', $product);
        $product->update(['archived_at' => null]);

        return redirect()->route('products.show', $product)->with('status', 'Product restored.');
    }
}
