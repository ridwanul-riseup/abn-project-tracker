<x-layouts.app title="Global settings" :breadcrumbs="['Administration' => null, 'Global settings' => null]">
    <x-page-header title="Global settings" subtitle="Configurable dropdown values (labels, order, defaults, active state) used throughout the hub. Security rules are not configurable here.">
        <x-slot:actions>
            <a href="{{ route('admin.templates.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-richtext"></i> Document templates</a>
        </x-slot:actions>
    </x-page-header>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Group</th><th>Key</th><th>Description</th><th class="text-end">Active / total</th></tr></thead>
            <tbody>
            @foreach ($groups as $group)
                <tr>
                    <td><a class="fw-semibold" href="{{ route('admin.options.show', $group) }}">{{ $group->name }}</a></td>
                    <td><code class="small">{{ $group->key }}</code></td>
                    <td class="small text-muted">{{ $group->description }}</td>
                    <td class="text-end">{{ $group->active_count }} / {{ $group->options_count }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
</x-layouts.app>
