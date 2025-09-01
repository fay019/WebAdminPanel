<?php
namespace App\Controllers;
use App\Helpers\I18n;
use App\Helpers\Response;

class I18nController {
    // GET /lang?set=fr|en – minimal ajax endpoint; returns JSON {ok:true, locale:"fr"}
    public function set(): void {
        $loc = $_GET['set'] ?? '';
        if (!preg_match('/^[a-z]{2}$/', $loc)) { $loc = I18n::getLocale(); }
        I18n::setLocale($loc);
        Response::json(['ok' => true, 'locale' => $loc]);
    }
}
