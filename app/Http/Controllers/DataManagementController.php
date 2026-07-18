<?php

namespace App\Http\Controllers;

use App\Models\DataChangeRequest;
use App\Services\AirportDataManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DataManagementController extends Controller
{
    public function index()
    {
        $changes = DataChangeRequest::with(['submitter', 'reviewer'])->latest()->paginate(25);

        return view('dashboard.admin.data.index', compact('changes'));
    }

    public function import()
    {
        return view('dashboard.admin.data.import');
    }

    public function storeImport(Request $request, AirportDataManager $manager)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:json,txt', 'max:10240']]);
        try {
            $document = json_decode($request->file('file')->get(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ValidationException::withMessages(['file' => 'Invalid JSON: '.$exception->getMessage()]);
        }

        if (! is_array($document)) {
            throw ValidationException::withMessages(['file' => 'The JSON root must be an object.']);
        }

        $imports = $manager->normaliseImport($document);
        DB::transaction(function () use ($imports, $request) {
            foreach ($imports as $payload) {
                DataChangeRequest::create([
                    'type' => 'airport_import', 'target' => $payload['airport']['icao'],
                    'payload' => $payload, 'submitted_by' => $request->user()->id,
                ]);
            }
        });

        return redirect()->route('dashboard.admin.data.index')
            ->with('success', count($imports).' airport change request(s) submitted for approval.');
    }

    public function show(DataChangeRequest $change)
    {
        $change->load(['submitter', 'reviewer']);

        return view('dashboard.admin.data.show', compact('change'));
    }

    public function approve(Request $request, DataChangeRequest $change, AirportDataManager $manager)
    {
        $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($request, $change, $manager) {
            $lockedChange = DataChangeRequest::query()->lockForUpdate()->findOrFail($change->id);
            abort_unless($lockedChange->status === 'pending', 409, 'This change has already been reviewed.');
            $manager->approve($lockedChange);
            $lockedChange->update([
                'status' => 'approved', 'reviewed_by' => $request->user()->id,
                'review_note' => $request->review_note, 'reviewed_at' => now(),
            ]);
        });

        return redirect()->route('dashboard.admin.data.index')->with('success', "{$change->target} is now live.");
    }

    public function reject(Request $request, DataChangeRequest $change)
    {
        $validated = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $change, $validated) {
            $lockedChange = DataChangeRequest::query()->lockForUpdate()->findOrFail($change->id);
            abort_unless($lockedChange->status === 'pending', 409, 'This change has already been reviewed.');
            $lockedChange->update([
                'status' => 'rejected', 'reviewed_by' => $request->user()->id,
                'review_note' => $validated['review_note'], 'reviewed_at' => now(),
            ]);
        });

        return redirect()->route('dashboard.admin.data.index')->with('success', "{$change->target} was rejected.");
    }
}
