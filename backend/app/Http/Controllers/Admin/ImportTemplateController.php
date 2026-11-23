<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\ImportType;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportTemplateController extends Controller
{
    public function __invoke(Request $request, ImportType $type): BinaryFileResponse
    {
        abort_unless($type->allows($request->user()), 403);

        return Excel::download(new ImportTemplateExport($type->importer()), "{$type->value}-template.xlsx");
    }
}
