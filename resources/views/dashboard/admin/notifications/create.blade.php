@extends('layouts.app')

@section('content')
    <h1>Send Notification</h1>
    <p>Push a notification to every user in the selected role(s). It appears in their notification bell immediately.</p>

    <form action="{{route('dashboard.admin.notifications.store')}}" method="POST">
        @csrf

        <div class="form-group">
            <label>Send To</label>
            <div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="recipient_mode" id="recipient-all" value="all" {{ old('recipient_mode', 'all') == 'all' ? 'checked' : '' }}>
                    <label class="form-check-label" for="recipient-all">All Users</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="recipient_mode" id="recipient-roles" value="roles" {{ old('recipient_mode') == 'roles' ? 'checked' : '' }}>
                    <label class="form-check-label" for="recipient-roles">Selected Roles</label>
                </div>
            </div>

            <div id="role-checkboxes" class="mt-2">
                @foreach($roles as $role)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input recipient-role-checkbox" type="checkbox" name="roles[]" id="role-{{$role->id}}" value="{{$role->id}}" {{ in_array($role->id, old('roles', [])) ? 'checked' : '' }}>
                        <label class="form-check-label" for="role-{{$role->id}}">{{$role->name}}</label>
                    </div>
                @endforeach
            </div>
            @error('roles') <div class="invalid-feedback d-block">{{$message}}</div> @enderror

            <p class="text-muted mt-2 mb-0">Will be sent to <strong id="recipient-count">{{$allUserCount}}</strong> user(s).</p>
        </div>

        <div class="form-group">
            <label>Send As</label>
            <div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="sender_type" id="sender-name-role" value="name_role" {{ old('sender_type', 'system') == 'name_role' ? 'checked' : '' }}>
                    <label class="form-check-label" for="sender-name-role">Name, Role ({{auth()->user()->fullName('FL')}}, {{auth()->user()->highestRole()->name}})</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="sender_type" id="sender-system" value="system" {{ old('sender_type', 'system') == 'system' ? 'checked' : '' }}>
                    <label class="form-check-label" for="sender-system">System</label>
                </div>
            </div>
            @error('sender_type') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
        </div>

        <div class="form-group">
            <label for="icon">Icon</label>
            <select name="icon" id="icon" class="form-control @error('icon') is-invalid @enderror">
                <option value="fa-bell" {{ old('icon') == 'fa-bell' ? 'selected' : '' }}>Bell (General)</option>
                <option value="fa-bullhorn" {{ old('icon') == 'fa-bullhorn' ? 'selected' : '' }}>Bullhorn (Announcement)</option>
                <option value="fa-newspaper" {{ old('icon') == 'fa-newspaper' ? 'selected' : '' }}>Newspaper (News)</option>
                <option value="fa-triangle-exclamation" {{ old('icon') == 'fa-triangle-exclamation' ? 'selected' : '' }}>Warning</option>
                <option value="fa-circle-info" {{ old('icon') == 'fa-circle-info' ? 'selected' : '' }}>Info</option>
                <option value="fa-wrench" {{ old('icon') == 'fa-wrench' ? 'selected' : '' }}>Wrench (Maintenance)</option>
            </select>
            @error('icon') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
        </div>

        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{old('title')}}" required maxlength="255">
            @error('title') <div class="invalid-feedback">{{$message}}</div> @enderror
        </div>

        <div class="form-group">
            <label for="message">Message</label>
            <textarea name="message" id="message" class="form-control @error('message') is-invalid @enderror" maxlength="255" rows="2" required placeholder="Short summary shown in the notification list.">{{old('message')}}</textarea>
            @error('message') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
        </div>

        <p class="text-muted">Choose one below &mdash; a URL takes the user straight there when clicked; a body opens an in-app detail page instead. Leave both blank to just mark the notification read.</p>

        <div class="form-group">
            <label for="url">Link URL <span class="text-muted">(optional)</span></label>
            <input type="url" name="url" id="url" class="form-control @error('url') is-invalid @enderror" value="{{old('url')}}" placeholder="https://...">
            @error('url') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
        </div>

        <div class="form-group">
            <label for="body">More Information <span class="text-muted">(optional)</span></label>
            <textarea name="body" id="body" class="form-control @error('body') is-invalid @enderror" rows="5" placeholder="Full detail text shown on an in-app page when the notification is clicked.">{{old('body')}}</textarea>
            @error('body') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
        </div>

        <input type="submit" class="btn oz-btn oz-btn-primary" value="Send Notification">
    </form>

    <script>
        const roleUserIds = @json($roleUserIds);
        const allUserCount = {{ $allUserCount }};

        const recipientModeInputs = document.querySelectorAll('input[name="recipient_mode"]');
        const roleCheckboxesWrap = document.getElementById('role-checkboxes');
        const roleCheckboxes = document.querySelectorAll('.recipient-role-checkbox');
        const recipientCount = document.getElementById('recipient-count');

        function updateRecipientCount() {
            const mode = document.querySelector('input[name="recipient_mode"]:checked')?.value;

            roleCheckboxesWrap.style.display = mode === 'roles' ? '' : 'none';

            if (mode === 'all') {
                recipientCount.textContent = allUserCount;
                return;
            }

            const userIds = new Set();
            roleCheckboxes.forEach(cb => {
                if (cb.checked) {
                    (roleUserIds[cb.value] || []).forEach(id => userIds.add(id));
                }
            });

            recipientCount.textContent = userIds.size;
        }

        recipientModeInputs.forEach(input => input.addEventListener('change', updateRecipientCount));
        roleCheckboxes.forEach(cb => cb.addEventListener('change', updateRecipientCount));

        updateRecipientCount();
    </script>
@endsection
