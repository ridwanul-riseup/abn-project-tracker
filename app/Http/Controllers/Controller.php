<?php

namespace App\Http\Controllers;

use App\Models\Clarification;
use App\Models\Product;
use App\Models\User;
use App\Support\Access\AccessService;
use App\Support\Options\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class Controller
{
    protected function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    protected function access(): AccessService
    {
        return app(AccessService::class);
    }

    protected function options(): Options
    {
        return app(Options::class);
    }

    protected function perPage(): int
    {
        return (int) config('hub.per_page', 25);
    }

    /**
     * Paginate a related list on a detail page. Each list uses its own page
     * parameter so several lists on one page can be paged independently.
     *
     * @param  Builder<covariant Model>|Relation<covariant Model, covariant Model, mixed>  $query
     * @return LengthAwarePaginator<int, Model>
     */
    protected function paginateList(Builder|Relation $query, string $pageName): LengthAwarePaginator
    {
        return $query->paginate((int) config('hub.detail_per_page', 10), ['*'], $pageName)
            ->withQueryString()->fragment($pageName);
    }

    /**
     * Products the current user may read, for filters and forms.
     *
     * @return array<int, string>
     */
    protected function productChoices(bool $writableOnly = false): array
    {
        return Product::query()
            ->visibleTo($this->user())
            ->notArchived()
            ->orderBy('code')
            ->get()
            ->filter(fn ($p) => ! $writableOnly || $this->access()->canWrite($this->user(), $p))
            ->mapWithKeys(fn (Product $p) => [$p->id => $p->code.' — '.$p->name])
            ->all();
    }

    /**
     * Used to decide whether "create" screens are reachable when no product is preselected.
     */
    protected function firstWritableProduct(): ?Product
    {
        return Product::query()->visibleTo($this->user())->notArchived()->get()
            ->first(fn ($p) => $this->access()->canWrite($this->user(), $p));
    }

    /**
     * Open clarification items attached to a record.
     *
     * @return Collection<int, Clarification>
     */
    protected function clarificationsFor(Model $model): Collection
    {
        return Clarification::query()
            ->open()
            ->where('subject_type', $model->getMorphClass())
            ->where('subject_id', $model->getKey())
            ->get();
    }

    /**
     * Abort with 404 (not 403) so the existence of restricted records is not revealed.
     */
    protected function authorizeView(Model $model): void
    {
        if (! $this->access()->canRead($this->user(), $model)) {
            abort(404);
        }
    }
}
