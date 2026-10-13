@php
    /** @var \App\Models\ApplicationSiteUrl $siteUrl */
    $modalId = 'site-url-creds-'.$siteUrl->id;
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="{{ $modalId }}-label">
                    Credentials
                    @if ($siteUrl->label)
                        — {{ $siteUrl->label }}
                    @endif
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted text-break-all mb-3">
                    <a href="{{ $siteUrl->url }}" rel="noopener noreferrer">{{ $siteUrl->url }}</a>
                </p>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Credential</th>
                                <th>User ID</th>
                                <th>Description</th>
                                <th>Password</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($siteUrl->credentials as $credential)
                            <tr>
                                <td class="small">{{ $credential->title }}</td>
                                <td class="small"><code>{{ $credential->account_login }}</code></td>
                                <td class="small text-muted">{{ $credential->short_description ?: '—' }}</td>
                                <td class="small"><span class="text-muted">••••••••</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
