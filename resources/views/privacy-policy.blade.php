@extends('layouts.app')

@section('content')

<style>
.custom {
    color: red;
    font-weight: bold;
}
</style>

<div class="card">
    <div class="card-body">
        <h3 class="card-title">
            Privacy Policy | V1.0
            <p style="font-size: 14px;"><b>Last Updated:</b> <i>27/12/2025</i></p>
        </h3>

        <p class="card-text" style="margin-bottom: 40px;">
            <h5>1. Policy Purpose</h5>
            In order to provide services and interacterbility for pilots flying into Australian Airports which recieve automatic bay assignments through OzBays, data is collected via the VATSIM SSO Service. This policy outlines how the data is collected, stored and used.
        </p>

        <p class="card-text" style="margin-bottom: 40px;">
            <h5>2. Policy Control</h5>
            Any changes to this Policy will be announced in the OzBays Discord, available for all members to join after signing in with VATSIM SSO on the OzBays site. No annotations or changes will be available on the website.
        </p>

        <p class="card-text" style="margin-bottom: 40px;">
            <h5>3. Contact</h5>
            Any questions regarding this policy can be raised with the Lead Developer, Joshua Micallef via the following methods.<br><i>Please Note: Include "OzBays Privacy Policy" in the Message to ensure a timly response.</i>
            
            <ul>
                <li>Discord DM - @joshuam02</li>
                {{-- <li>Email - info@qfa100.org (for all Privacy Related matters)</li> --}}
            </ul>
        </p>

        <p class="card-text" style="margin-bottom: 40px;">
            <h5>4. Collected Data</h5>
            <div class="row">
                <div class="col-md-6">
                    OzBays collects the following data from your VATSIM Account:
                    <ul>
                        <li>VATSIM Certificate Number (CID)</li>
                        <li>Full Name</li>
                        <li>Email Address</li>
                    </ul>
                </div>

                <div class="col-md-6">
                    OzBays the following data from your Discord Account:
                    <ul>
                        <li>Discord UID</li>
                        <li>Discord Avatar</li>
                    </ul>
                </div>


            </div>

            No Authentication Data (excluding email) is stored or accessed during Authentication with VATSIM SSO or Discord. This includes your password, private keys etc.
        </p>

        <p class="card-text" style="margin-bottom: 40px;">
            <h5>5. How We Use Your Data</h5>
            OzBays utilises your data to customise your experience on the site, and the wider VATSIM Network. This is achieved by showing your flight on the OzBays Site, along with the generated Assigned Bay. Future editions of the site will allow users to request up to 3 bays for the system to prioritise when assigning a bay, as well as utilising Real Life Callsign Arrival Info to match with VATSIM Inbound Aircraft. These changes will be rolled out over time, and may not coincide with updates to this Privacy Policy.
        </p>

        <p class="card-text" style="margin-bottom: 40px;">
            <h5>6. Data Sharing</h5>
            OzBays does not share any SSO data with any third party systems. Stored data is only accessable to Joshua Micallef (Lead Developer) and only accessed when changing role permissions for a user, aside from general website functions denoted in section 5.
        </p>

        <p class="card-text" style="margin-bottom: 40px;">
            <h5>7. Agreement</h5>
            By logging into the OzBays Website with VATSIM SSO, you automatically agree to the terms of this Privacy Policy, and concent to your data being utilised in the ways described in this document.<br>
            If you no longer agree for OzBays to have access to your data, you can request your data to be removed from our servers by contacting the contact in Section 3. This will result in any personal data being erased, including any site preferences you have created.
        </p>
    </div>
</div>
@endsection