<?php

namespace App\Http\Controllers;

use App\Http\Requests\Document\DocumentRequest;
use App\Models\Document;


class DocumentController extends Controller
{
    public function index()
    {
        $documents = Document::where('status','=','active')->get();
        return $this->successResponse( $documents );
    }

    public function show($id)
    {
        $document = Document::where("id",$id)->first();
        return $this->successResponse( $document );
    }

    public function getAllArchiveDocuments()
    {
        $documents = Document::where('status','=','inactive')->get();

        return $this->successResponse($documents);
    }

    public function store(DocumentRequest $request)
    {
        $document = Document::create($request->validated());

        return $this->successResponse($document);
    }

    public function update(DocumentRequest $request, $id)
    {
        $validatedData = $request->validated();

        $document = Document::findOrFail($id);
        $document->update($validatedData);

        return $this->successResponse($document);
    }

    public function destroy($id_document)
    {
        $document = Document::findOrFail($id_document);
        $document->delete();

        return $this->successResponse( $document );
    }

    public function deactivateDocument($id_document)
    {
        $document = Document::findOrFail($id_document);
        $document->update(['status' => 'inactive']); 

        return $this->successResponse( $document );
    }

    public function activateDocument($id_document)
    {
        $document = Document::findOrFail($id_document);
        $document->update(['status' => 'active']);  
        
        return $this->successResponse( $document );
    }

}
