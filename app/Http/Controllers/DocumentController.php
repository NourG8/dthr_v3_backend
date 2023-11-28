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
        $body = $request->input('body');
    
        $structureHTML = "
            <html lang='en-US'>
                <head>
                    <meta content='text/html; charset=utf-8' http-equiv='Content-Type' />
                    <title>Test Template</title>
                </head>
                <body>
                    $body
                </body>
            </html>
        ";
    
        $validatedData = $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
        ]);
    
        $validatedData['body'] = $structureHTML;
        $document = Document::create($validatedData);
    
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
