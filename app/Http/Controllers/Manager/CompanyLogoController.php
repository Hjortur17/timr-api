<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\UpdateCompanyLogoRequest;
use App\Http\Resources\CompanyResource;
use App\Services\CompanyLogoService;
use Illuminate\Http\Request;

class CompanyLogoController extends Controller
{
    public function __construct(private CompanyLogoService $service) {}

    public function store(UpdateCompanyLogoRequest $request): CompanyResource
    {
        $company = $this->service->update($request->user()->company, $request->file('logo'));

        return new CompanyResource($company);
    }

    public function destroy(Request $request): CompanyResource
    {
        $company = $this->service->remove($request->user()->company);

        return new CompanyResource($company);
    }
}
