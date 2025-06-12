<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Menu;

class MenuController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $menus = Menu::with('modulo')->get();
        return response()->json($menus);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $menu = new Menu();
        $menu->nom_menu = $request->input('nom_menu');
        $menu->link_menu = $request->input('link_menu');
        $menu->desc_menu = $request->input('desc_menu');
        $menu->id_modulo = $request->input('id_modulo');
        $menu->save();

        return response()->json(['message' => 'Menú creado correctamente', 'data' => $menu], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $menu = Menu::find($id);
        if (!$menu) {
            return response()->json(['message' => 'Menú no encontrado'], 404);
        }

        return response()->json($menu);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $menu = Menu::find($id);
        if (!$menu) {
            return response()->json(['message' => 'Menú no encontrado'], 404);
        }

        $menu->nom_menu = $request->input('nom_menu');
        $menu->link_menu = $request->input('link_menu');
        $menu->desc_menu = $request->input('desc_menu');
        $menu->id_modulo = $request->input('id_modulo');
        $menu->save();

        return response()->json(['message' => 'Menú actualizado correctamente', 'data' => $menu]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $menu = Menu::find($id);
        if (!$menu) {
            return response()->json(['message' => 'Menú no encontrado'], 404);
        }
        $menu->delete();
        return response()->json(['message' => 'Menú eliminado correctamente']);
    }
}
