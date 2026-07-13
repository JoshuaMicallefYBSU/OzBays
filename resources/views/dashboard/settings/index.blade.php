@extends('layouts.app')

@section('content')
<h2>Your OzBays Settings, {{Auth::user()->fullName('F')}}</h2>
<p>See all the settings which you can control throughout OzBays. Make sure to press the save button with any changes you would like!</p>
<div class="pb-3">
    <a href="{{route('dashboard.index')}}" style="color: black;"> <i class="fas fa-arrow-left"></i> Return to your Dashboard</a>
</div>

<hr>
<form action="{{route('dashboard.settings.save')}}" method="POST">
    @csrf

    {{-- Hidden Items --}}
    <input required type="hidden" value={{Auth::user()->id}} name="id" maxlength="10" id="id" class="form-control">

    {{-- General Sections --}}
    <h2><u>General</u></h2>

    {{-- Name Format --}}
    <div class="d-flex flex-row justify-content-between mt-2">
        <div>
            <h4 class="font-weight-bold blue-text">Name Format</h4>
            <p>Select your VATSIM Code of Conduct Name Format</p>
        </div>

        <div style="width: 30%;">
            <select name="name_format" class="form-control">
                <option value="0" @if(Auth::user()->userPreferences->name_format == 0) selected @endif>{{Auth::user()->id}} (CID Only)</option>
                <option value="1" @if(Auth::user()->userPreferences->name_format == 1) selected @endif>{{Auth::user()->fname}} - {{Auth::user()->id}} (First Name + CID)</option>
                <option value="2" @if(Auth::user()->userPreferences->name_format == 2) selected @endif>{{Auth::user()->fname}} {{substr(Auth::user()->lname, 0, 1)}} - {{Auth::user()->id}} (First Name, Initial Last Name + CID)</option>
                <option value="3" @if(Auth::user()->userPreferences->name_format == 3) selected @endif>{{Auth::user()->fname}} {{Auth::user()->lname}} - {{Auth::user()->id}} (First & Last Name + CID)</option>
            </select>
        </div>
    </div>

    {{-- Hoppie Usage --}}
    <div class="d-flex flex-row justify-content-between mt-2">
        <div>
            <h4 class="font-weight-bold blue-text">Use the Hoppie Network?</h4>
            <p>Do you want OzBays to send you messages via the Hoppie Network when you are connected on VATSIM?
                <br>Note: <i>You must be connected to the Hoppie Network via your Aircraft as well for this to work...</i>
            </p>
        </div>
        <div style="width: 30%;">
            <select name="hoppie_usage" class="form-control">
                <option value="1" @if(Auth::user()->userPreferences->hoppie_usage == 1) selected @endif>Yes - Send me a message via the Hoppie Network</option>
                <option value="0" @if(Auth::user()->userPreferences->hoppie_usage == 0) selected @endif>No - Never send me hoppie messages</option>
            </select>
        </div>
    </div>

    <hr>

    {{-- Email Sections --}}
    <h2><u>Emails</u></h2>

    {{-- Feedback Email --}}
    <div class="d-flex flex-row justify-content-between mt-2">
        <div>
            <h4 class="font-weight-bold blue-text">Feedback Emails</h4>
            <p>Recieve emails from OzBays asking for feedback about your experience? (Maximum once per month)</p>
        </div>
        <div style="width: 30%;">
            <select name="email_feedback" class="form-control">
                <option value="1" @if(Auth::user()->userPreferences->email_feedback == 1) selected @endif>Yes - Recieve feedback from OzBays about your experience</option>
                <option value="0" @if(Auth::user()->userPreferences->email_feedback == 0) selected @endif>No - I do not want to provide feedback</option>
            </select>
        </div>
    </div>
    <hr>

    {{-- Save Button --}}
    <input type="submit" class="btn btn-success" value="Save Preferences">
</form>
@endsection