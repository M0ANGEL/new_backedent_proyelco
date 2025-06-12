<?php

namespace App\Http\Controllers;

use App\Models\Submenu;
use Illuminate\Http\Request;

class SubmenuController extends Controller
{
    public function index()
    {
        $submenus = Submenu::with('menu', 'menu.modulo')->get();

        return response()->json($submenus);
    }

    public function show($id)
    {
        $submenu = Submenu::with('menu')->find($id);

        if (!$submenu) {
            return response()->json([
                'message' => 'Submenu not found'
            ], 404);
        }

        return response()->json($submenu);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom_smenu' => 'required',
            'link_smenu' => 'required',
            'desc_smenu' => 'required',
            'id_menu' => 'required'
        ]);

        $submenu = Submenu::create($request->all());

        return response()->json($submenu, 201);
    }

    public function update(Request $request, $id)
    {
        $submenu = Submenu::find($id);

        if (!$submenu) {
            return response()->json([
                'message' => 'Submenu not found'
            ], 404);
        }

        $request->validate([
            'nom_smenu' => 'required',
            'link_smenu' => 'required',
            'desc_smenu' => 'required',
            'id_menu' => 'required'
        ]);

        $submenu->nom_smenu = $request->nom_smenu;
        $submenu->link_smenu = $request->link_smenu;
        $submenu->desc_smenu = $request->desc_smenu;
        $submenu->id_menu = $request->id_menu;
        $submenu->save();

        return response()->json($submenu);
    }

    public function destroy($id)
    {
        Submenu::destroy($id);
        return response()->json(['message' => 'Deleted successfully'], 204);
    }
}
