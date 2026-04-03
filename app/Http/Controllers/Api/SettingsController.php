<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private MediaStorageService $mediaStorage)
    {
    }

    public function index()
    {
        return response()->json([
            'bunny' => [
                'storage_zone' => Setting::getValue('bunny.storage_zone'),
                'access_key' => Setting::getValue('bunny.access_key'),
                'pull_zone' => Setting::getValue('bunny.pull_zone'),
                'enabled' => filter_var(Setting::getValue('bunny.enabled', false), FILTER_VALIDATE_BOOL),
            ],
            'masters' => [
                'default_credit_days' => (int) Setting::getValue('finance.default_credit_days', 0),
                'qid_threshold_days' => (int) Setting::getValue('qid.threshold_days', 30),
                'settlement_interval_days' => (int) Setting::getValue('settlement.interval_days', 7),
                'invoice_prefix' => Setting::getValue('prefixes.invoice', 'INV'),
                'quotation_prefix' => Setting::getValue('prefixes.quotation', 'QTN'),
                'settlement_prefix' => Setting::getValue('prefixes.settlement', 'SET'),
                'quotation_template_text' => Setting::getValue('quotation.template_text', ''),
                'signature_name' => Setting::getValue('documents.signature_name', ''),
                'seal_label' => Setting::getValue('documents.seal_label', ''),
                'company_document_note' => Setting::getValue('documents.company_note', ''),
                'letterhead_url' => Setting::getValue('documents.letterhead_url', ''),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'bunny.storage_zone' => 'nullable|string|max:255',
            'bunny.access_key' => 'nullable|string|max:255',
            'bunny.pull_zone' => 'nullable|string|max:255',
            'bunny.enabled' => 'nullable|boolean',
            'masters.default_credit_days' => 'nullable|integer|min:0|max:365',
            'masters.qid_threshold_days' => 'nullable|integer|min:1|max:365',
            'masters.settlement_interval_days' => 'nullable|integer|min:1|max:60',
            'masters.invoice_prefix' => 'nullable|string|max:10',
            'masters.quotation_prefix' => 'nullable|string|max:10',
            'masters.settlement_prefix' => 'nullable|string|max:10',
            'masters.quotation_template_text' => 'nullable|string',
            'masters.signature_name' => 'nullable|string|max:255',
            'masters.seal_label' => 'nullable|string|max:255',
            'masters.company_document_note' => 'nullable|string',
            'masters.letterhead_url' => 'nullable|string',
        ]);

        foreach (($validated['bunny'] ?? []) as $key => $value) {
            Setting::setValue("bunny.$key", $value, 'storage');
        }

        $map = [
            'default_credit_days' => 'finance.default_credit_days',
            'qid_threshold_days' => 'qid.threshold_days',
            'settlement_interval_days' => 'settlement.interval_days',
            'invoice_prefix' => 'prefixes.invoice',
            'quotation_prefix' => 'prefixes.quotation',
            'settlement_prefix' => 'prefixes.settlement',
            'quotation_template_text' => 'quotation.template_text',
            'signature_name' => 'documents.signature_name',
            'seal_label' => 'documents.seal_label',
            'company_document_note' => 'documents.company_note',
            'letterhead_url' => 'documents.letterhead_url',
        ];

        foreach (($validated['masters'] ?? []) as $key => $value) {
            Setting::setValue($map[$key], $value, 'masters');
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function testStorage()
    {
        return response()->json($this->mediaStorage->testConnection());
    }
}
