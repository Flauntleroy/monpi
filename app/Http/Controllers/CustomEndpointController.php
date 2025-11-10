<?php

namespace App\Http\Controllers;

use App\Models\BpjsEndpointConfig;
use Illuminate\Http\Request;

class CustomEndpointController extends Controller
{
    private function normalize(BpjsEndpointConfig $ep): array
    {
        $url = $ep->url ?? '';
        $isBpjs = str_contains($url, 'bpjs-kesehatan.go.') || str_contains($url, 'apijkn.bpjs-kesehatan.go.');
        return [
            'id' => $ep->id,
            'name' => $ep->name,
            'url' => $ep->url,
            'description' => $ep->description ?? '',
            'method' => $ep->method ?? 'GET',
            'headers' => $ep->custom_headers ?? [],
            'timeout' => $ep->timeout_seconds ?? 10,
            'isActive' => (bool) $ep->is_active,
            'isBpjsEndpoint' => $isBpjs,
            'useProxy' => (bool) $ep->use_proxy,
        ];
    }

    public function index(Request $request)
    {
        $items = BpjsEndpointConfig::query()->orderBy('name')->get();
        return response()->json([
            'data' => $items->map(fn ($ep) => $this->normalize($ep)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'description' => ['nullable', 'string'],
            'method' => ['nullable', 'string', 'in:GET,POST,PUT,DELETE,PATCH,PING'],
            'custom_headers' => ['nullable', 'array'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            'is_active' => ['nullable', 'boolean'],
            'use_proxy' => ['nullable', 'boolean'],
        ]);

        $ep = BpjsEndpointConfig::create([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'description' => $validated['description'] ?? null,
            'method' => $validated['method'] ?? 'GET',
            'custom_headers' => $validated['custom_headers'] ?? [],
            'timeout_seconds' => $validated['timeout_seconds'] ?? 10,
            'is_active' => $validated['is_active'] ?? true,
            'use_proxy' => $validated['use_proxy'] ?? false,
        ]);

        return response()->json(['data' => $this->normalize($ep)], 201);
    }

    public function update(Request $request, BpjsEndpointConfig $endpoint)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url', 'max:2048'],
            'description' => ['nullable', 'string'],
            'method' => ['nullable', 'string', 'in:GET,POST,PUT,DELETE,PATCH,PING'],
            'custom_headers' => ['nullable', 'array'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            'is_active' => ['nullable', 'boolean'],
            'use_proxy' => ['nullable', 'boolean'],
        ]);

        $endpoint->fill($validated);
        $endpoint->save();

        return response()->json(['data' => $this->normalize($endpoint)]);
    }

    public function destroy(BpjsEndpointConfig $endpoint)
    {
        $endpoint->delete();
        return response()->json(['success' => true]);
    }
}