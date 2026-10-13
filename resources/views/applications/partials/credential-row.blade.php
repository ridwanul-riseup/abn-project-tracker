<div class="row g-2 align-items-end border-top pt-2 mt-2 hub-credential-row" data-credential-row>
    <div class="col-md-3">
        <label class="form-label small">Title <span class="text-danger">*</span></label>
        @if (! empty($credRow['id']))
            <input type="hidden" name="site_urls[{{ $urlIndex }}][credentials][{{ $credIndex }}][id]" value="{{ $credRow['id'] }}">
        @endif
        <input type="text" name="site_urls[{{ $urlIndex }}][credentials][{{ $credIndex }}][title]"
               value="{{ old("site_urls.$urlIndex.credentials.$credIndex.title", $credRow['title'] ?? '') }}"
               class="form-control form-control-sm" required maxlength="150" placeholder="Admin panel">
    </div>
    <div class="col-md-2">
        <label class="form-label small">User ID <span class="text-danger">*</span></label>
        <input type="text" name="site_urls[{{ $urlIndex }}][credentials][{{ $credIndex }}][account_login]"
               value="{{ old("site_urls.$urlIndex.credentials.$credIndex.account_login", $credRow['account_login'] ?? '') }}"
               class="form-control form-control-sm" required maxlength="255" autocomplete="off">
    </div>
    <div class="col-md-3">
        <label class="form-label small">Password @if (empty($credRow['id']))<span class="text-danger">*</span>@endif</label>
        <div class="input-group input-group-sm">
            <input type="password" name="site_urls[{{ $urlIndex }}][credentials][{{ $credIndex }}][password]"
                   class="form-control" maxlength="500" autocomplete="new-password"
                   @if (empty($credRow['id'])) required @endif
                   placeholder="{{ empty($credRow['id']) ? '' : 'Leave blank to keep unchanged' }}">
            <button type="button" class="btn btn-outline-secondary" data-password-toggle aria-label="Show or hide password"><i class="bi bi-eye"></i></button>
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label small">Short description</label>
        <input type="text" name="site_urls[{{ $urlIndex }}][credentials][{{ $credIndex }}][short_description]"
               value="{{ old("site_urls.$urlIndex.credentials.$credIndex.short_description", $credRow['short_description'] ?? '') }}"
               class="form-control form-control-sm" maxlength="500">
    </div>
    <div class="col-md-1 text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-credential data-confirm="Remove this credential?"><i class="bi bi-trash"></i></button>
    </div>
</div>
