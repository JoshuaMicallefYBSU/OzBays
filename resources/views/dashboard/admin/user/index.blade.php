@extends('layouts.app')

@section('content')
    <h1>User Admin View</h1>
    <p>View all users that have signed into OzBays. <i>Data beyond this page is not accessable yet,</i></p>

    <table id="dataTable" class="table table-hover" style="text-align: center; font-size: 12px;">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">User</th>
                    <th scope="col">Rating</th>
                    <th scope="col">Last Seen</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{$user->id}}</td>
                        <td>{{$user->fullName('FL')}}</td>
                        <td>Not Recorded (Yet)</td>
                        <td>{{\Carbon\Carbon::parse($user->last_seen)->format('d/m/Y @ h:i').'Z' ?? N/A}}</td>
                        <td>N/A</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
@endsection