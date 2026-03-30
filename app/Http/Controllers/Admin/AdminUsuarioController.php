<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()->with('vinculos.tenant');

        if ($busca = $request->input('busca')) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$busca}%")
                ->orWhere('email', 'like', "%{$busca}%")
            );
        }

        if ($tenantId = $request->input('tenant_id')) {
            $query->whereHas('vinculos', fn ($q) => $q->where('tenant_id', $tenantId));
        }

        $usuarios = $query->orderBy('name')->paginate(20)->withQueryString();

        return Inertia::render('admin/usuarios/index', [
            'usuarios' => $usuarios,
            'filtros' => [
                'busca' => $request->input('busca', ''),
                'tenant_id' => $request->input('tenant_id', ''),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('vinculos.tenant');

        return response()->json($user);
    }
}
