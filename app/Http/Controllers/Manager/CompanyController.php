<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;

class CompanyController extends Controller
{
    public function update(UpdateCompanyRequest $request): CompanyResource
    {
        $company = $request->user()->company;
        $company->update($request->validated());

        return new CompanyResource($company);
    }
}
