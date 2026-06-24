<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanyLogoService
{
    /**
     * Store a new logo for the company, replacing any existing one.
     */
    public function update(Company $company, UploadedFile $file): Company
    {
        $this->deleteExisting($company);

        $path = $file->storeAs(
            "companies/{$company->id}",
            'logo.'.$file->extension(),
            'public',
        );

        $company->logo_path = $path;
        $company->save();

        return $company;
    }

    /**
     * Remove the company's logo file and clear the stored path.
     */
    public function remove(Company $company): Company
    {
        $this->deleteExisting($company);

        $company->logo_path = null;
        $company->save();

        return $company;
    }

    private function deleteExisting(Company $company): void
    {
        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }
    }
}
