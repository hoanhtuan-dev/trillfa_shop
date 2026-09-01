<?php
namespace App\Modules\Studio;

class StudioBridge
{
    public function garmentTypes(): array { return app(\App\Services\StylistCatalog::class)->garmentTypes(); }
}
