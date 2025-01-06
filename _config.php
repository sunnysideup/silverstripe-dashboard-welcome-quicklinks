<?php

use SilverStripe\Admin\CMSMenu;
use SilverStripe\Core\Config\Config;
use Sunnysideup\DashboardWelcomeQuicklinks\Admin\DashboardWelcomeQuicklinks;

if (0 === strpos($_SERVER['REQUEST_URI'], '/admin/')) {
    if (Config::inst()->get(DashboardWelcomeQuicklinks::class, 'hide_from_menu')) {
        CMSMenu::remove_menu_class(DashboardWelcomeQuicklinks::class);
    }
}
