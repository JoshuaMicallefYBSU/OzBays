@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <h1>News Articles</h1>
            <p>Manage the news articles shown on the OzBays homepage.</p>
        </div>
        <div class="col-md-4 text-right">
            <a href="{{route('dashboard.admin.news.create')}}" class="btn oz-btn oz-btn-primary">
                <i class="fa fa-plus"></i> New Article
            </a>
        </div>
    </div>

    <table id="dataTable" class="table table-hover" style="text-align: center">
        <thead>
            <tr>
                <th scope="col">Subject</th>
                <th scope="col">Author</th>
                <th scope="col">Published</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($articles as $article)
                <tr>
                    <td>{{$article->subject}}</td>
                    <td>{{$article->author}}</td>
                    <td>{{$article->created_at->format('d/m/Y @ h:i A')}}</td>
                    <td>
                        <a href="{{route('news.show', $article)}}" target="_blank">View</a>
                        &middot;
                        <a href="{{route('dashboard.admin.news.edit', $article)}}">Edit</a>
                        &middot;
                        <a data-target="#deleteModal{{$article->id}}" data-toggle="modal" style="cursor: pointer">Delete</a>
                    </td>
                </tr>

                <div class="modal fade" id="deleteModal{{$article->id}}" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Are you sure?</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>

                            <form action="{{route('dashboard.admin.news.destroy', $article)}}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-body">
                                    <p>This will permanently delete "<b>{{$article->subject}}</b>" and its image. This cannot be undone.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
                                    <input type="submit" class="btn btn-danger" value="Delete Article">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </tbody>
    </table>
@endsection
