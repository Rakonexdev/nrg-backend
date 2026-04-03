<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function __construct(private MediaStorageService $mediaStorage)
    {
    }

    public function idPhoto(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2560',
        ]);

        return response()->json($this->mediaStorage->store($request->file('file'), 'qid'));
    }

    public function lpo(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2560',
        ]);

        return response()->json($this->mediaStorage->store($request->file('file'), 'lpo'));
    }

    public function invoiceCopy(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2560',
        ]);

        return response()->json($this->mediaStorage->store($request->file('file'), 'invoice-copies'));
    }

    public function quotationCopy(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2560',
        ]);

        return response()->json($this->mediaStorage->store($request->file('file'), 'quotation-copies'));
    }

    public function expenseDocument(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2560',
        ]);

        return response()->json($this->mediaStorage->store($request->file('file'), 'expense-documents'));
    }
}
