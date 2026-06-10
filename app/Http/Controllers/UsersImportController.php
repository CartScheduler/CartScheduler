<?php

namespace App\Http\Controllers;

use App\Imports\UsersImport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class UsersImportController extends Controller
{
    /**
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(): Response
    {
        $this->authorize('import', User::class);

        return Inertia::render('Admin/Users/Import');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls'],
        ]);

        $import = UsersImport::importUploadedFiles($request->file('file'));

        $createCount   = $import->getCreateCount();
        $updateCount   = $import->getUpdateCount();
        $createMessage = '';
        $updateMessage = '';
        if ($createCount) {
            $createMessage = "$createCount users were imported";
            $createMessage .= $updateCount ? ' and ' : '!';
        }
        if ($updateCount) {
            $updateMessage = "$updateCount users were updated";
            $updateMessage .= $createCount ? '!' : '';
        }
        Session::flash('flash.title', "Success!");
        Session::flash('flash.message', $createMessage . $updateMessage);

        return Redirect::route('admin.users.import.show', status: \Illuminate\Http\Response::HTTP_SEE_OTHER);
    }
}
