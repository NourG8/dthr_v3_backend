<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Http\Requests\Document\DocumentTypeRequest;

class DocumentTypeController extends Controller
{
    public function index()
    {
        $documents = DocumentType::where('status','=','active')->get();
        return $this->successResponse( $documents );
    }

    public function show($id)
    {
        $document = DocumentType::where("id",$id)->first();
        return $this->successResponse( $document );
    }

    public function getAllArchiveDocumentTypes()
    {
        $documents = DocumentType::where('status','=','inactive')->get();

        return $this->successResponse($documents);
    }

    public function store(DocumentTypeRequest $request)
    {
        $document = DocumentType::create($request->validated());

        return $this->successResponse($document);
    }

    public function update(DocumentTypeRequest $request, $id)
    {
        $validatedData = $request->validated();

        $document = DocumentType::findOrFail($id);
        $document->update($validatedData);

        return $this->successResponse($document);
    }

    public function destroy($id_document)
    {
        $document = DocumentType::findOrFail($id_document);
        $document->delete();

        return $this->successResponse( $document );
    }

    public function deactivateDocumentType($id_document)
    {
        $document = DocumentType::findOrFail($id_document);
        $document->update(['status' => 'inactive']); 

        return $this->successResponse( $document );
    }

    public function activateDocumentType($id_document)
    {
        $document = DocumentType::findOrFail($id_document);
        $document->update(['status' => 'active']);  
        
        return $this->successResponse( $document );
    }

}
