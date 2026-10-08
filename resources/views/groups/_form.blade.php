@csrf
<div class="mb-3">
    <label for="facebook_group_id" class="form-label">Facebook Group ID</label>
    @if ($group->exists)
        {{-- Posts and crawl history are tied to this ID, so it cannot be changed. --}}
        <input id="facebook_group_id" type="text" value="{{ $group->facebook_group_id }}" class="form-control" disabled>
        <div class="form-text">Không sửa được Group ID. Nếu nhập sai, hãy xoá group và thêm lại.</div>
    @else
        <input id="facebook_group_id" name="facebook_group_id" type="text" inputmode="numeric" required
               value="{{ old('facebook_group_id') }}" placeholder="123456789"
               class="form-control @error('facebook_group_id') is-invalid @enderror">
        @error('facebook_group_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @endif
</div>
<div class="mb-3">
    <label for="url" class="form-label">Group URL</label>
    <input id="url" name="url" type="url" required value="{{ old('url', $group->url) }}"
           placeholder="https://www.facebook.com/groups/123456789/"
           class="form-control @error('url') is-invalid @enderror">
    @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label for="name" class="form-label">Group Name</label>
    <input id="name" name="name" type="text" maxlength="255" value="{{ old('name', $group->name) }}"
           class="form-control @error('name') is-invalid @enderror">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="form-check mb-3">
    <input type="hidden" name="is_active" value="0">
    <input id="is_active" name="is_active" type="checkbox" value="1" class="form-check-input"
           @checked(old('is_active', $group->is_active))>
    <label for="is_active" class="form-check-label">Active (được crawl tự động)</label>
</div>
<div>
    <button type="submit" class="btn btn-primary">Lưu</button>
    <a href="{{ route('groups.index') }}" class="btn btn-link">Huỷ</a>
</div>
