<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ContentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:create content', only: ['store']),
            new Middleware('permission:edit content', only: ['update']),
            new Middleware('permission:delete content', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $contents = Content::withCount('events')->get();

        return inertia('backOffice/Contents', [
            'contents' => $contents,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255|unique:contents,title',
            'body' => 'required|string|max:65535',
        ]);

        Content::create($request->only(['title', 'body']));

        return redirect()->back()->with('success', 'Contenu créé avec succès.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'title' => 'required|string|max:255|unique:contents,title,'.$id,
            'body' => 'required|string|max:65535',
        ]);

        $content = Content::findOrFail($id);
        if (Content::where('title', $request->title)->where('id', '!=', $id)->exists()) {
            return back()->with('error', 'Un contenu avec le même titre existe déjà.');
        }

        try {
            $content->update($request->only(['title', 'body']));
        } catch (Exception $e) {
            return back()->with('error', 'Une erreur est survenue lors de la mise à jour du contenu.');
        }

        return redirect()->back()->with('success', 'Contenu mis à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $content = Content::findOrFail($id);
        if ($content->events()->exists()) {
            return back()->with('error', 'Impossible de supprimer le contenu car il est associé à des événements.');
        }

        $content->delete();

        return redirect()->back()->with('success', 'Contenu supprimé avec succès.');
    }
}
