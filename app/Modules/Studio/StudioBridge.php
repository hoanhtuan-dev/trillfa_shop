<?php
namespace AppModulesStudio;

/**
 * Small facade/bridge so other parts of the app can ask the Studio module for shared
 * information without reaching into the controller (reusable + shareable).
 */
class StudioBridge
{
    public function garmentTypes(): array { return app(AppServicesStylistCatalog::class)->garmentTypes(); }
}
