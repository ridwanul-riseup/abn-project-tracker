<x-layouts.app :title="$record->code" :breadcrumbs="[$definition['title'] => route('registries.index', $type), $record->code => null]">
    <x-page-header :title="$definition['singular'].' '.$record->code" :subtitle="$record->getAttribute('name')">
        <x-slot:actions>
            @can('update', $record)
                <a href="{{ route('registries.edit', [$type, $record->id]) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            @endcan
            @can('delete', $record)
                <form method="POST" action="{{ route('registries.destroy', [$type, $record->id]) }}" data-confirm="Delete {{ $record->code }}? Records still referenced elsewhere cannot be deleted.">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>
    <x-needs-clarification :items="$clarifications" />

    <div class="card"><div class="card-body">
        <dl class="row mb-0">
            @foreach ($definition['fields'] as $field)
                <dt class="col-sm-3 small text-muted">{{ $field['label'] }}</dt>
                <dd class="col-sm-9">@include('registries._value', ['field' => $field, 'record' => $record])</dd>
            @endforeach
            <dt class="col-sm-3 small text-muted">Last updated</dt>
            <dd class="col-sm-9 small">{{ $record->updated_at?->format('Y-m-d H:i') }}</dd>
        </dl>
    </div></div>

    @if ($type === 'environments')
        <div class="card mt-3">
            <div class="card-header">Repositories using this environment</div>
            <div class="card-body small">
                @php $repos = $record->repositories()->visibleTo(auth()->user())->orderBy('code')->get(); @endphp
                @forelse ($repos as $repo)<x-ref :model="$repo" />@if (! $loop->last), @endif @empty <span class="text-muted">None (that you can see).</span> @endforelse
            </div>
        </div>
    @endif
</x-layouts.app>
