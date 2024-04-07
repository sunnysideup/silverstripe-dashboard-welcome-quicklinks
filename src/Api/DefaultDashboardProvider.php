<?php

namespace Sunnysideup\DashboardWelcomeQuicklinks\Api;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\VersionedAdmin\ArchiveAdmin;
use Sunnysideup\DashboardWelcomeQuicklinks\Interfaces\DashboardWelcomeQuickLinksProvider;

class DefaultDashboardProvider implements DashboardWelcomeQuickLinksProvider
{
    protected $links = [];

    public function provideDashboardWelcomeQuickLinks(): array
    {
        $this->addPagesLinks();
        $this->addSecurityLinks();
        $this->addModelAdminLinks();
        return $this->links;
    }


    protected function addPagesLinks()
    {
        $this->addGroup('PAGES', 'Pages', 10);
        $this->addLink('PAGES', 'Create new page', '/admin/pages/add');
        $this->addLink('PAGES', 'Open Sitetree', '/admin/pages');
        $pageLastEdited = DataObject::get_one('Page', '', true, 'LastEdited DESC');
        if ($pageLastEdited) {
            $this->addLink('PAGES', 'Last Edited Page: '.$pageLastEdited->Title, $pageLastEdited->CMSEditLink());
        }
    }

    protected function addSecurityLinks()
    {
        $this->addGroup('SECURITY', 'Security', 20);
        $this->addLink('SECURITY', 'Create new user', '/admin/security/addmember');
        $this->addLink('SECURITY', 'Review Users', '/admin/security');
        $this->addLink('SECURITY', 'Review Users Groups', '/admin/security/groups');
    }

    protected function addGroup(string $groupCode, string $title, $sort)
    {
        $this->links[$groupCode] = [
            'Title' => $title,
            'SortOrdre' => $sort,
        ];
    }

    protected function addLink($groupCode, $title, $link)
    {
        $this->links[$groupCode]['Items'][] = [
            'Title' => $title,
            'Link' => $link,
        ];
    }


    protected function addModelAdminLinks()
    {
        $modelAdmins = [];
        foreach (ClassInfo::subclassesFor(ModelAdmin::class, false) as $className) {
            if($className === ArchiveAdmin::class) {
                continue;
            }
            $modelAdmins[$className] = $className;

        }
        foreach($modelAdmins as $modelAdminClassName) {
            $groupAdded = false;
            $ma = Injector::inst()->get($modelAdminClassName);
            $mas = $ma->getManagedModels();
            if(count($mas)) {
                $numberOfModels = count($mas);
                $groupCode = strtoupper($modelAdminClassName);
                $count = 0;
                foreach($mas as $model => $title) {
                    $count++;
                    if($count > 7) {
                        break;
                    }
                    if(is_array($title)) {
                        $title = $title['title'];
                        $model = $title['dataClass'] ?? $model;
                    }
                    if(! class_exists($model)) {
                        continue;
                    }
                    if(! $groupAdded) {
                        $this->addGroup($groupCode, $ma->menu_title(), 100);
                        $groupAdded = true;
                    }
                    $obj = DataObject::singleton($model);
                    $link = '';
                    if($obj->hasMethod('CMSListLink')) {
                        $link = $obj->CMSListLink();
                    } else {
                        $link = $ma->getLinkForModelTab($model);
                    }
                    $this->addLink($groupCode, $title, $link);
                    if($numberOfModels < 4) {
                        $obj = Injector::inst()->get($model);
                        if($obj->canCreate()) {
                            $classNameEscaped = str_replace('\\', '-', $model);
                            $linkNew = $link .= '/EditForm/field/'.$classNameEscaped.'/item/new';
                            $this->addLink($groupCode, 'New '.$obj->i18n_singular_name(), $linkNew);
                        }
                    }
                }
            } else {

            }
        }
    }
}
