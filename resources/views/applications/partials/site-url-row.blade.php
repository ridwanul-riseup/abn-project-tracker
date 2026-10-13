@php
    $credentials = collect($urlRow['credentials'] ?? [])->values();
@endphp
<div class="border rounded p-3 mb-3 hub-site-url-row" data-site-url-row>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        <strong class="small">Site URL</strong>
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-site-url data-confirm="Remove this site URL and all of its credentials?">Remove URL</button>
    </div>
    @if (! empty($urlRow['id']))
        <input type="hidden" name="site_urls[{{ $urlIndex }}][id]" value="{{ $urlRow['id'] }}">
    @endif
    <div class="row">
        <div class="col-md-4 mb-2">
            <label class="form-label small">URL label</label>
            <input type="text" name="site_urls[{{ $urlIndex }}][label]" value="{{ old("site_urls.$urlIndex.label", $urlRow['label'] ?? '') }}"
                   class="form-control form-control-sm" maxlength="100" placeholder="Development, Staging, Production">
        </div>
        <div class="col-md-8 mb-2">
            <label class="form-label small">Live site URL <span class="text-danger">*</span></label>
            <input type="url" name="site_urls[{{ $urlIndex }}][url]" value="{{ old("site_urls.$urlIndex.url", $urlRow['url'] ?? '') }}"
                   class="form-control form-control-sm @error("site_urls.$urlIndex.url") is-invalid @enderror" required maxlength="500" placeholder="https://app.example.com">
            @error("site_urls.$urlIndex.url")<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="mt-2">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small fw-semibold">Credentials</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-credential><i class="bi bi-plus"></i> Add credential</button>
        </div>
        <div data-credential-list>
            @foreach ($credentials as $credIndex => $credRow)
                @include('applications.partials.credential-row', ['urlIndex' => $urlIndex, 'credIndex' => $credIndex, 'credRow' => $credRow])
            @endforeach
        </div>
    </div>
</div>
