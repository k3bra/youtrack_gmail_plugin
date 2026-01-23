<?php

namespace App\Actions\PmsDocuments;

use App\Models\PmsDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UpdatePmsDocumentAction
{
    public function handle(Request $request, PmsDocument $pmsDocument): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $title = trim((string) $request->input('title', ''));
        $pmsDocument->title = $title === '' ? null : $title;
        $pmsDocument->save();

        return response()->json([
            'id' => $pmsDocument->id,
            'title' => $pmsDocument->title,
        ]);
    }
}
