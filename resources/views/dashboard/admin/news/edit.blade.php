@extends('layouts.app')

@section('content')
    <h1>Edit News Article</h1>
    <p>Editing "{{$article->subject}}" &mdash; published as <strong>{{$article->author}}</strong>.</p>

    <div class="pb-3">
        <a href="{{route('dashboard.admin.news.index')}}"> <i class="fas fa-arrow-left"></i> Back to News Articles</a>
    </div>

    <form action="{{route('dashboard.admin.news.update', $article)}}" method="POST" enctype="multipart/form-data" onsubmit="tinymce.triggerSave()">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="subject">Subject</label>
            <input type="text" name="subject" id="subject" class="form-control @error('subject') is-invalid @enderror" value="{{old('subject', $article->subject)}}" required maxlength="255">
            @error('subject') <div class="invalid-feedback">{{$message}}</div> @enderror
        </div>

        <div class="form-group">
            <label for="excerpt">Excerpt</label>
            <textarea name="excerpt" id="excerpt" class="form-control @error('excerpt') is-invalid @enderror" maxlength="500" rows="2" placeholder="Short summary shown on news cards. Leave blank to auto-generate from the article body.">{{old('excerpt', $article->getRawOriginal('excerpt'))}}</textarea>
            @error('excerpt') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
        </div>

        <div class="form-group">
            <label>Current Background Photo</label><br>
            <img src="{{$article->image_url}}" alt="{{$article->subject}}" style="max-width: 320px; max-height: 180px; object-fit: {{$article->has_custom_image ? 'cover' : 'contain'}}; border-radius: 8px; {{$article->has_custom_image ? '' : 'background: var(--oz-bg-alt); padding: 1.5rem;'}}">
            @unless($article->has_custom_image)
                <small class="form-text text-muted">No custom photo uploaded &mdash; showing the generic OzBays logo background.</small>
            @endunless
        </div>

        <div class="form-group">
            <label for="image">Replace Background Photo (optional)</label>
            <input type="file" name="image" id="image" class="form-control-file @error('image') is-invalid @enderror" accept="image/png, image/jpeg, image/webp">
            @error('image') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
        </div>

        <div class="form-group">
            <label for="body">Article Body</label>
            <textarea name="body" id="body" class="form-control @error('body') is-invalid @enderror">{{old('body', $article->body)}}</textarea>
            @error('body') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
        </div>

        <input type="submit" class="btn oz-btn oz-btn-primary" value="Save Changes">
    </form>

    <script>
        tinymce.init({
            selector: '#body',
            menubar: false,
            height: 400,
            plugins: 'lists link',
            toolbar: 'undo redo | bold italic | bullist numlist | link',
            skin: 'oxide-dark',
            content_css: 'dark',
        });
    </script>
@endsection
