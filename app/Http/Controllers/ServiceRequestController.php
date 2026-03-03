<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceRequestController extends Controller
{
    private function authorizeServiceAccess(): void
    {
        $user = auth()->user();

        if (!$user) {
            abort(403);
        }

        $isOwner = $user->role === 'owner';
        $isForeman = $user->role === 'worker' && $user->position === 'foreman';

        if (!$isOwner && !$isForeman) {
            abort(403);
        }
    }

    public function index(): JsonResponse
    {
        $this->authorizeServiceAccess();

        $items = ServiceRequest::query()
            ->orderByDesc('is_urgent')
            ->orderByDesc('id')
            ->get()
            ->map(function (ServiceRequest $item) {
                return [
                    'id' => $item->id,
                    'client_name' => (string) $item->client_name,
                    'settlement' => (string) $item->settlement,
                    'phone_number' => (string) ($item->phone_number ?? ''),
                    'telegram_group_link' => (string) ($item->telegram_group_link ?? ''),
                    'geo_location_link' => (string) ($item->geo_location_link ?? ''),
                    'electrician' => (string) ($item->electrician ?? ''),
                    'installation_team' => (string) ($item->installation_team ?? ''),
                    'is_urgent' => (bool) $item->is_urgent,
                    'description' => (string) ($item->description ?? ''),
                    'status' => (string) ($item->status ?? 'open'),
                    'created_at' => optional($item->created_at)->format('d.m.Y H:i'),
                ];
            })
            ->values();

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeServiceAccess();

        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'settlement' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:64'],
            'telegram_group_link' => ['nullable', 'string', 'max:1000'],
            'geo_location_link' => ['nullable', 'string', 'max:1000'],
            'electrician' => ['nullable', 'string', 'max:255'],
            'installation_team' => ['nullable', 'string', 'max:255'],
            'is_urgent' => ['nullable', 'boolean'],
            'description' => ['required', 'string'],
        ]);

        $item = ServiceRequest::create([
            'client_name' => $data['client_name'],
            'settlement' => $data['settlement'],
            'phone_number' => $data['phone_number'] ?? null,
            'telegram_group_link' => $data['telegram_group_link'] ?? null,
            'geo_location_link' => $data['geo_location_link'] ?? null,
            'electrician' => $data['electrician'] ?? null,
            'installation_team' => $data['installation_team'] ?? null,
            'is_urgent' => (bool) ($data['is_urgent'] ?? false),
            'description' => $data['description'],
            'created_by' => auth()->id(),
            'status' => 'open',
        ]);

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $item->id,
                'client_name' => (string) $item->client_name,
            ],
        ]);
    }
}
