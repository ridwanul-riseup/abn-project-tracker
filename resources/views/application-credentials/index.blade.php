<x-layouts.app title="Application credentials" :breadcrumbs="['Applications' => route('applications.index'), 'Credentials' => null]">
    <x-page-header title="Application credentials" subtitle="One row per login credential. Passwords are encrypted and never shown here." />

    <form method="GET" class="card mb-3"><div class="card-body">
        <div class="row g-2 align-items-end">
            <x-form.input name="q" label="Search" :value="request('q')" col="col-md-4 mb-0" placeholder="Application, URL, title, user ID…" />
            <x-form.select name="product_id" label="Product" :options="$products" :selected="request('product_id')" col="col-md-3 mb-0" placeholder="All products" data-autosubmit />
            <x-form.select name="application_id" label="Application" :options="$applications" :selected="request('application_id')" col="col-md-3 mb-0" placeholder="All applications" data-autosubmit />
            <div class="col-md-2 mb-0"><button class="btn btn-primary btn-sm w-100">Filter</button></div>
        </div>
    </div></form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Application</th>
                        <th>Environment</th>
                        <th>Site URL</th>
                        <th>Credential</th>
                        <th>User ID</th>
                        <th>Description</th>
                        <th>Password</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($credentials as $credential)
                    @php $app = $credential->siteUrl->application; @endphp
                    <tr>
                        <td class="small"><a href="{{ route('applications.show', $app) }}">{{ $app->code }}</a><div class="text-muted">{{ $app->name }}</div></td>
                        <td class="small">{{ $credential->siteUrl->label ?: '—' }}</td>
                        <td class="small"><x-ext-link :url="$credential->siteUrl->url" /></td>
                        <td class="small">{{ $credential->title }}</td>
                        <td class="small"><code>{{ $credential->account_login }}</code></td>
                        <td class="small text-muted">{{ $credential->short_description ?: '—' }}</td>
                        <td class="small"><span class="text-muted">••••••••</span></td>
                        <td class="text-nowrap text-end small">
                            <a href="{{ route('applications.show', $app) }}#credentials" class="btn btn-sm btn-outline-secondary">View</a>
                            @if ($credential->can_edit)
                                <a href="{{ route('applications.edit', $app) }}#live-site-urls" class="btn btn-sm btn-outline-primary">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty message="No credentials match your filters." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-list-pager :items="$credentials" />
    </div>
</x-layouts.app>
