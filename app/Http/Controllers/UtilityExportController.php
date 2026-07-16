<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ExportUtilityUsagesJob;
use App\Models\Export;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UtilityExportController extends Controller
{
    public function export(Request $request, int $propertyId): BinaryFileResponse
    {
        $property = Property::findOrFail($propertyId);
        $user = auth()->user();
        
        // Simple authorization check: user must own the property or be platform staff
        if (! $user->isPlatformStaff() && $property->landlord_id !== $user->effectiveLandlordId()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'time_period' => 'required|string|in:all,this_year,last_year,custom',
            'from_date' => 'nullable|date',
            'until_date' => 'nullable|date',
            'utility_types' => 'nullable|array',
            'format' => 'required|string|in:csv,xlsx,pdf',
        ]);

        $export = Export::create([
            'user_id' => auth()->id(),
            'file_name' => 'utility_export_' . $propertyId . '_' . time() . '.' . $validated['format'],
            'status' => 'pending',
        ]);

        // Run synchronously
        $job = new ExportUtilityUsagesJob($export, $propertyId, $validated);
        $job->handle();

        $export->refresh();
        $fullPath = storage_path('app/' . $export->file_path);

        return response()->download($fullPath, $export->file_name);
    }

    public function download(string $fileId): BinaryFileResponse
    {
        $export = Export::findOrFail($fileId);

        // Security check: only the user who requested the export can download it
        if ($export->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to export file.');
        }

        if ($export->status !== 'completed' || !$export->file_path) {
            abort(404, 'Export file is not ready or has failed.');
        }

        $fullPath = storage_path('app/' . $export->file_path);

        if (!file_exists($fullPath)) {
            abort(404, 'File not found on storage.');
        }

        return response()->download($fullPath, $export->file_name);
    }
}
